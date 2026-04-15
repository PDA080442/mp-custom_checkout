<?php
/**
 * Менеджер реестра шагов checkout (метаданные, видимость, навигация).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Contracts\CheckoutStepManagerInterface;
use MP\CustomCheckout\Settings\DefaultFeatureFlagsRegistry;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutStepManager
 */
final class CheckoutStepManager implements CheckoutStepManagerInterface {

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private $steps;

	/**
	 * @var array<string, mixed>
	 */
	private $flow;

	/**
	 * @var array<int, string>|null
	 */
	private $visible_step_ids;

	/**
	 * @param array<string, mixed>|null $flow
	 */
	public function __construct( ?array $flow = null ) {
		$this->flow  = is_array( $flow ) ? $flow : CheckoutSessionService::get_flow();
		$this->steps = $this->build_registered_steps();
		$this->visible_step_ids = null;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function get_registered_steps(): array {
		return $this->steps;
	}

	/**
	 * @return array<int, string>
	 */
	public function get_visible_step_ids(): array {
		if ( null !== $this->visible_step_ids ) {
			return $this->visible_step_ids;
		}

		$scenario = $this->get_current_scenario();
		$visible  = array();

		foreach ( $this->steps as $step_id => $meta ) {
			if ( empty( $meta['enabled'] ) ) {
				continue;
			}
			if ( ! $this->is_visible_for_scenario( $scenario, $meta ) ) {
				continue;
			}
			$visible[] = $step_id;
		}

		$this->visible_step_ids = array_values( $visible );

		return $this->visible_step_ids;
	}

	public function get_current_step_id(): ?string {
		$flow_current = isset( $this->flow['current_step'] ) ? sanitize_key( (string) $this->flow['current_step'] ) : '';
		$visible      = $this->get_visible_step_ids();

		if ( '' !== $flow_current && in_array( $flow_current, $visible, true ) ) {
			return $flow_current;
		}

		return isset( $visible[0] ) ? $visible[0] : null;
	}

	public function set_current_step_id( string $step_id ): void {
		$step_id = sanitize_key( $step_id );
		if ( '' === $step_id ) {
			return;
		}

		if ( ! $this->can_navigate_to( $step_id ) ) {
			return;
		}

		CheckoutSessionService::set_current_step( $step_id );
		$this->flow['current_step'] = $step_id;
	}

	public function can_navigate_to( string $step_id ): bool {
		$step_id  = sanitize_key( $step_id );
		$visible  = $this->get_visible_step_ids();
		if ( ! in_array( $step_id, $visible, true ) ) {
			return false;
		}

		$current = $this->get_current_step_id();
		if ( null === $current ) {
			return isset( $visible[0] ) && $visible[0] === $step_id;
		}

		$current_index = array_search( $current, $visible, true );
		$target_index  = array_search( $step_id, $visible, true );
		if ( false === $current_index || false === $target_index ) {
			return false;
		}

		// Разрешены: текущий, назад и на один шаг вперед.
		return (int) $target_index <= ( (int) $current_index + 1 );
	}

	public function get_visible_step_index( string $step_id ): int {
		$visible = $this->get_visible_step_ids();
		$index   = array_search( sanitize_key( $step_id ), $visible, true );
		return false === $index ? 0 : ( (int) $index + 1 );
	}

	public function get_visible_step_count(): int {
		return count( $this->get_visible_step_ids() );
	}

	/**
	 * Валидация шага из запроса (`?step=`) и возврат допустимого значения.
	 */
	public function resolve_requested_step( string $requested ): ?string {
		$requested = sanitize_key( $requested );
		if ( '' === $requested ) {
			return null;
		}

		if ( ! isset( $this->steps[ $requested ] ) ) {
			return null;
		}

		if ( ! $this->can_navigate_to( $requested ) ) {
			return null;
		}

		return $requested;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function build_registered_steps(): array {
		$definitions_raw = SafeSettingsResolver::get( 'registry.step_definitions', ScenarioStepRegistry::step_definitions() );
		$definitions     = is_array( $definitions_raw ) ? $definitions_raw : array();

		$order_raw       = SafeSettingsResolver::get( 'registry.step_order', ScenarioStepRegistry::default_step_order() );
		$order_list      = $this->sanitize_step_order( is_array( $order_raw ) ? $order_raw : array() );

		$steps = array();
		foreach ( $definitions as $id => $meta ) {
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$step_id = sanitize_key( is_string( $id ) ? $id : (string) ( $meta['id'] ?? '' ) );
			if ( '' === $step_id ) {
				continue;
			}

			$default_order = array_search( $step_id, ScenarioStepRegistry::default_step_order(), true );
			$assigned      = array_search( $step_id, $order_list, true );
			$order         = false === $assigned ? ( false === $default_order ? 999 : ( (int) $default_order + 1 ) ) : ( (int) $assigned + 1 );

			$label = isset( $meta['label'] ) ? sanitize_text_field( (string) $meta['label'] ) : $step_id;
			if ( '' === $label ) {
				$label = $step_id;
			}

			$steps[ $step_id ] = array(
				'id'                  => $step_id,
				'label'               => $label,
				'order'               => $order,
				'enabled'             => ! isset( $meta['enabled'] ) || (bool) $meta['enabled'],
				'visibility_mode'     => isset( $meta['visibility_mode'] ) ? sanitize_key( (string) $meta['visibility_mode'] ) : 'always',
				'visible_in'          => $this->sanitize_visible_in( $meta ),
				'validation_mode'     => isset( $meta['validation_mode'] ) ? sanitize_key( (string) $meta['validation_mode'] ) : 'server',
				'discount_step_ready' => ! empty( $meta['discount_step_ready'] ),
			);
		}

		uasort(
			$steps,
			static function ( array $a, array $b ): int {
				return (int) $a['order'] <=> (int) $b['order'];
			}
		);

		return (array) apply_filters( 'mp_custom_checkout_registered_steps', $steps, $order_list );
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<int, string>
	 */
	private function sanitize_visible_in( array $meta ): array {
		$scenarios = array_keys( ScenarioStepRegistry::scenarios() );
		if ( ! isset( $meta['visible_in'] ) || ! is_array( $meta['visible_in'] ) ) {
			return $scenarios;
		}

		$list = array();
		foreach ( $meta['visible_in'] as $scenario ) {
			if ( ! is_string( $scenario ) ) {
				continue;
			}
			$key = sanitize_key( $scenario );
			if ( '' !== $key && in_array( $key, $scenarios, true ) ) {
				$list[] = $key;
			}
		}

		return array_values( array_unique( $list ) );
	}

	private function get_current_scenario(): string {
		$current = isset( $this->flow['scenario'] ) ? sanitize_key( (string) $this->flow['scenario'] ) : '';
		$known   = array_keys( ScenarioStepRegistry::scenarios() );
		if ( '' !== $current && in_array( $current, $known, true ) ) {
			return $current;
		}

		$fallback = SafeSettingsResolver::get( 'registry.default_scenario', ScenarioStepRegistry::SCENARIO_PICKUP );
		$fallback = sanitize_key( is_string( $fallback ) ? $fallback : '' );

		return in_array( $fallback, $known, true ) ? $fallback : ScenarioStepRegistry::SCENARIO_PICKUP;
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function is_visible_for_scenario( string $scenario, array $meta ): bool {
		$step_id = isset( $meta['id'] ) ? sanitize_key( (string) $meta['id'] ) : '';
		if ( ScenarioStepRegistry::STEP_CONDITIONS === $step_id ) {
			$rules = CheckoutScenarioRules::build( $scenario );
			$show_conditions = isset( $rules['step_rules']['show_conditions_step'] ) ? (bool) $rules['step_rules']['show_conditions_step'] : true;
			$feature_enabled = (bool) SafeSettingsResolver::get( 'feature_flags.' . DefaultFeatureFlagsRegistry::FLAG_CONDITIONS_STEP, true );
			if ( ! $show_conditions || ! $feature_enabled ) {
				return false;
			}
		}

		$mode = isset( $meta['visibility_mode'] ) ? (string) $meta['visibility_mode'] : 'always';
		if ( 'hidden' === $mode ) {
			return false;
		}
		if ( 'scenario' !== $mode ) {
			return true;
		}

		$list = isset( $meta['visible_in'] ) && is_array( $meta['visible_in'] ) ? $meta['visible_in'] : array();
		return in_array( $scenario, $list, true );
	}

	/**
	 * @param array<int|string, mixed> $items
	 * @return array<int, string>
	 */
	private function sanitize_step_order( array $items ): array {
		$list = array();
		foreach ( $items as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$key = sanitize_key( $item );
			if ( '' !== $key ) {
				$list[] = $key;
			}
		}
		return array_values( array_unique( $list ) );
	}
}
