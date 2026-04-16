/**
 * MP Custom Checkout — базовая навигация шагов.
 */
(function ($) {
	'use strict';

	var selectors = {
		root: '#mp-cc-checkout',
		app: '#mp-cc-checkout-app',
		progress: '#mp-cc-progress-container',
		actions: '#mp-cc-navigation-actions',
		summary: '#mp-cc-summary-sidebar',
		notifications: '#mp-cc-notifications'
	};
	var flagNames = {
		multiStepFlow: 'multi_step_flow',
		multiPickupPoints: 'multi_pickup_points',
		conditionsStep: 'conditions_step',
		discountPlacement: 'discount_block_placement',
		checkoutTestingMode: 'checkout_testing_mode',
		adminLivePreview: 'admin_live_preview'
	};
	var animationDurationMs = 180;
	var draftSaveTimer = null;
	var pendingCheckoutRequests = 0;

	function getUiText(path, fallback) {
		var source = (window.mpCcCheckout && window.mpCcCheckout.uiText) ? window.mpCcCheckout.uiText : {};
		var parts = String(path || '').split('.');
		var node = source;
		var i;
		for (i = 0; i < parts.length; i += 1) {
			if (!node || typeof node !== 'object' || !Object.prototype.hasOwnProperty.call(node, parts[i])) {
				return fallback;
			}
			node = node[parts[i]];
		}
		return (typeof node === 'string' && node !== '') ? node : fallback;
	}

	function getStepOneConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.stepOneConfig && typeof window.mpCcCheckout.stepOneConfig === 'object')
			? window.mpCcCheckout.stepOneConfig
			: {};
		return $.extend(true, {
			labels: {
				title: '',
				summary_title: '',
				subtotal_label: '',
				items_label: '',
				continue_label: '',
				return_label: '',
				empty_title: ''
			},
			product_meta_visibility: {
				show_image: true,
				show_sku: true,
				show_variation: true,
				show_price: true,
				show_subtotal: true
			},
			quantity_controls: {
				enabled: true,
				allow_manual_input: true,
				show_increment: true,
				show_decrement: true
			},
			empty_state: {
				message: '',
				cta_label: '',
				cta_enabled: true
			},
			style_controls: {
				card_compact: false,
				card_emphasis: 'default',
				summary_emphasis: 'default'
			},
			layout_order: {
				secondary_order: ['price', 'sku', 'variation', 'quantity', 'subtotal', 'remove']
			},
			responsive: {
				desktop_mode: 'comfortable',
				tablet_mode: 'comfortable',
				mobile_mode: 'compact',
				hide_media_mobile: false
			},
			admin_preview: {
				enabled: true
			}
		}, source);
	}

	function getStepOneLabel(state, key, fallbackPath, fallbackText) {
		var configLabels = state && state.stepOneConfig && state.stepOneConfig.labels ? state.stepOneConfig.labels : {};
		var value = configLabels && configLabels[key] ? String(configLabels[key]) : '';
		if (value) {
			return value;
		}
		return getUiText(fallbackPath, fallbackText);
	}

	function getScenarioMap() {
		var map = (window.mpCcCheckout && window.mpCcCheckout.scenarioStepMap && typeof window.mpCcCheckout.scenarioStepMap === 'object')
			? window.mpCcCheckout.scenarioStepMap
			: {};
		return {
			scenarios: (map.scenarios && typeof map.scenarios === 'object') ? map.scenarios : {},
			rules: (map.scenarioRules && typeof map.scenarioRules === 'object') ? map.scenarioRules : {}
		};
	}

	function getPickupConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.pickupConfig && typeof window.mpCcCheckout.pickupConfig === 'object')
			? window.mpCcCheckout.pickupConfig
			: {};
		return {
			enablePointSelection: Boolean(source.enable_point_selection),
			mapSlotEnabled: source.map_slot_enabled !== false,
			points: Array.isArray(source.points) ? source.points : []
		};
	}

	function getScenarioUiConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.scenarioUiConfig && typeof window.mpCcCheckout.scenarioUiConfig === 'object')
			? window.mpCcCheckout.scenarioUiConfig
			: {};
		return $.extend(true, {
			default_scenario: 'pickup',
			card_order: ['pickup', 'delivery'],
			cards: {
				pickup: {
					title: 'Самовывоз',
					description: '',
					helper: '',
					icon_variant: 'pickup',
					icon_style: 'soft'
				},
				delivery: {
					title: 'Доставка',
					description: '',
					helper: '',
					icon_variant: 'delivery',
					icon_style: 'soft'
				}
			},
			responsive: {
				desktop_columns: 2,
				tablet_columns: 1,
				mobile_columns: 1,
				card_density: 'comfortable'
			}
		}, source);
	}

	function getStepThreeConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.stepThreeConfig && typeof window.mpCcCheckout.stepThreeConfig === 'object')
			? window.mpCcCheckout.stepThreeConfig
			: {};
		return $.extend(true, {
			copy: {
				title: 'Выберите дату получения',
				helper_by_scenario: {
					pickup: '',
					krasnoyarsk_delivery: '',
					other_city_delivery: ''
				},
				errors: {
					invalid_date: 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.',
					empty_date: 'Выберите дату, чтобы продолжить.',
					conditions_unconfirmed: 'Подтвердите ознакомление с условиями, чтобы продолжить.'
				},
				admin_preview: {
					enabled: true
				}
			},
			conditions_copy: {
				intro_by_scenario: {
					pickup: '',
					krasnoyarsk_delivery: '',
					other_city_delivery: ''
				},
				secondary_notes: ['', '', ''],
				krasnoyarsk_delivery: {
					title: '',
					body: '',
					delivery_within_day: ''
				},
				other_city_delivery: {
					title: '',
					body: '',
					logistics_note: ''
				},
				pickup: {
					title: '',
					body: '',
					office_block_title: '',
					office_address: '',
					office_description: '',
					office_hours_plain: '',
					office_hours: [],
					convenience_helper: '',
					critical_notice: '',
					show_multi_office_slot: true
				}
			}
		}, source);
	}

	function getStepFourConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.stepFourConfig && typeof window.mpCcCheckout.stepFourConfig === 'object')
			? window.mpCcCheckout.stepFourConfig
			: {};
		return $.extend(true, {
			contact_block: {
				title: '',
				intro: '',
				patronymic_required: false,
				field_order: ['last_name', 'first_name', 'patronymic', 'gender', 'birthdate', 'email', 'phone', 'order_notes'],
				field_visibility: {
					last_name: true,
					first_name: true,
					patronymic: true,
					gender: true,
					birthdate: true,
					email: true,
					phone: true,
					order_notes: true
				},
				field_required: {
					last_name: true,
					first_name: true,
					patronymic: false,
					gender: false,
					birthdate: true,
					email: true,
					phone: true,
					order_notes: false
				},
				placeholders: {
					last_name: '',
					first_name: '',
					patronymic: '',
					gender: '',
					birthdate: '',
					email: '',
					phone: '',
					order_notes: ''
				},
				labels: {
					last_name: '',
					first_name: '',
					patronymic: '',
					gender: '',
					birthdate: '',
					email: '',
					phone: '',
					order_notes: '',
					country_code: ''
				},
				hints: {
					email: '',
					phone: '',
					patronymic: '',
					gender: '',
					birthdate: '',
					order_notes: ''
				},
				gender_options: {
					placeholder: '',
					male: '',
					female: ''
				},
				validation_messages: {
					required: '',
					email_invalid: '',
					phone_required: '',
					phone_format: '',
					birthdate_required: '',
					birthdate_invalid: '',
					birthdate_range: '',
					order_notes_length: '',
					address_required: '',
					address_region: '',
					address_city: '',
					address_postcode: '',
					step_blocked: '',
					conditions_required: ''
				},
				validation_constraints: {
					birthdate_min_age: 0,
					birthdate_max_age: 120,
					phone_digits_override: 0
				},
				ajax_messages: {
					draft_save_failed: '',
					step_sync_failed: '',
					scenario_sync_failed: ''
				},
				order_notes_max_length: 500,
				order_notes_counter: { enabled: true },
				phone_country_codes: [
					{ dial: '+7', iso: 'RU', national_digits: 10 },
					{ dial: '+7', iso: 'KZ', national_digits: 10 },
					{ dial: '+375', iso: 'BY', national_digits: 9 }
				],
				default_phone_country_iso: 'RU',
				layout: {
					desktop_columns: 3,
					tablet_columns: 2,
					mobile_columns: 1,
					grid_gap: '0.75rem 1rem'
				},
				field_state_styles: {
					invalid_style: 'default',
					hint_style: 'default',
					focus_style: 'default',
					disabled_style: 'default'
				}
			},
			payment_block: {
				title: '',
				intro: '',
				gateway_order: [],
				layout: { desktop_columns: 2, tablet_columns: 2, mobile_columns: 1, grid_gap: '0.6rem 0.75rem' },
				card_style: 'default',
				card_active_style: 'accent',
				radio_style: 'default',
				description_style: 'muted',
				show_description: true,
				required: true,
				error_message: '',
				messages: { loading: '', success: '', error: '' },
				diagnostics: { enabled: true }
			},
			available_gateways: [],
			address_block: {
				title: '',
				intro: '',
				default_country: 'RU',
				subfields_order: ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'],
				subfields_visible: {
					country: true,
					state: true,
					city: true,
					address_1: true,
					address_2: true,
					postcode: true
				},
				postcode_max_length: 16,
				labels: {
					country: '',
					state: '',
					city: '',
					address_1: '',
					address_2: '',
					postcode: ''
				}
			},
			address_geo: {},
			discount_layout: {
				placement: 'step_4',
				separate_step_enabled: false,
				order: ['coupon', 'gift_card']
			},
			discount_block_styles: {
				state_empty: 'default',
				state_success: 'success',
				state_error: 'error',
				focus_style: 'default'
			},
			coupon_block: {
				title: '',
				intro: '',
				input_label: '',
				placeholder: '',
				apply_label: '',
				empty_message: '',
				success_message: '',
				error_message: ''
			},
			gift_card_block: {
				title: '',
				intro: '',
				input_label: '',
				placeholder: '',
				apply_label: '',
				empty_message: '',
				success_message: '',
				error_message: ''
			},
			geo_preview: { enabled: false }
		}, source);
	}

	function isContactFieldVisible(key) {
		var cfg = getStepFourConfig();
		var vis = cfg.contact_block && cfg.contact_block.field_visibility ? cfg.contact_block.field_visibility : {};
		return vis[key] !== false;
	}

	function isContactFieldRequired(key) {
		var cfg = getStepFourConfig();
		var req = cfg.contact_block && cfg.contact_block.field_required ? cfg.contact_block.field_required : {};
		if (Object.prototype.hasOwnProperty.call(req, key)) {
			return Boolean(req[key]);
		}
		return key !== 'patronymic';
	}

	function getContactPlaceholder(key) {
		var cfg = getStepFourConfig();
		var placeholders = cfg.contact_block && cfg.contact_block.placeholders ? cfg.contact_block.placeholders : {};
		return trimNonEmpty(placeholders[key] ? String(placeholders[key]) : '');
	}

	function getContactLabel(key) {
		var cfg = getStepFourConfig();
		var labels = cfg.contact_block && cfg.contact_block.labels ? cfg.contact_block.labels : {};
		var fromCfg = labels[key] ? String(labels[key]) : '';
		if (trimNonEmpty(fromCfg)) {
			return fromCfg;
		}
		var fb = {
			last_name: 'Фамилия',
			first_name: 'Имя',
			patronymic: 'Отчество',
			gender: 'Пол',
			birthdate: 'Дата рождения',
			order_notes: 'Примечания к заказу',
			email: 'Email',
			phone: 'Телефон',
			country_code: 'Код страны'
		};
		var path = {
			last_name: 'step_4.contact_last_name',
			first_name: 'step_4.contact_first_name',
			patronymic: 'step_4.contact_patronymic',
			gender: 'step_4.contact_gender',
			birthdate: 'step_4.contact_birthdate',
			order_notes: 'step_4.contact_order_notes',
			email: 'step_4.contact_email',
			phone: 'step_4.contact_phone',
			country_code: 'step_4.contact_country_code'
		};
		return getUiText(path[key] || 'step_4.title', fb[key] || key);
	}

	function getContactHint(key) {
		var cfg = getStepFourConfig();
		var hints = cfg.contact_block && cfg.contact_block.hints ? cfg.contact_block.hints : {};
		var fromCfg = hints[key] ? String(hints[key]) : '';
		if (trimNonEmpty(fromCfg)) {
			return fromCfg;
		}
		var fb = {
			email: 'На этот адрес отправим подтверждение заказа.',
			phone: 'Введите номер без кода страны — он выбран слева.',
			patronymic: 'Укажите при наличии.',
			gender: 'Необязательное поле.',
			birthdate: 'Используем для корректной обработки заказа и персонализации сервиса.',
			order_notes: 'Оставьте детали по доставке, упаковке или пожелания к заказу.'
		};
		var path = {
			email: 'step_4.contact_hint_email',
			phone: 'step_4.contact_hint_phone',
			patronymic: 'step_4.contact_hint_patronymic',
			gender: 'step_4.contact_hint_gender',
			birthdate: 'step_4.contact_hint_birthdate',
			order_notes: 'step_4.contact_hint_order_notes'
		};
		return getUiText(path[key] || 'step_4.title', fb[key] || '');
	}

	function getGenderOptions() {
		var cfg = getStepFourConfig();
		var go = cfg.contact_block && cfg.contact_block.gender_options ? cfg.contact_block.gender_options : {};
		return {
			placeholder: trimNonEmpty(go.placeholder) || getUiText('step_4.contact_gender_placeholder', 'Не указывать'),
			male: trimNonEmpty(go.male) || getUiText('step_4.contact_gender_male', 'Мужчина'),
			female: trimNonEmpty(go.female) || getUiText('step_4.contact_gender_female', 'Женщина')
		};
	}

	function getBirthdateErrorText(code) {
		var cfg = getStepFourConfig();
		var vm = cfg.contact_block && cfg.contact_block.validation_messages ? cfg.contact_block.validation_messages : {};
		if (code === 'required') {
			return trimNonEmpty(vm.birthdate_required) || getUiText('step_4.contact_error_birthdate_required', 'Укажите дату рождения.');
		}
		if (code === 'invalid') {
			return trimNonEmpty(vm.birthdate_invalid) || getUiText('step_4.contact_error_birthdate_invalid', 'Введите корректную дату рождения.');
		}
		return trimNonEmpty(vm.birthdate_range) || getUiText('step_4.contact_error_birthdate_range', 'Допустимый возраст: от 0 до 120 лет.');
	}

	function getStepFourValidationMessages() {
		var cfg = getStepFourConfig();
		return cfg.contact_block && cfg.contact_block.validation_messages && typeof cfg.contact_block.validation_messages === 'object'
			? cfg.contact_block.validation_messages
			: {};
	}

	function getStepFourAjaxMessage(code, fallbackKey, fallbackText) {
		var cfg = getStepFourConfig();
		var ajax = cfg.contact_block && cfg.contact_block.ajax_messages && typeof cfg.contact_block.ajax_messages === 'object'
			? cfg.contact_block.ajax_messages
			: {};
		return trimNonEmpty(ajax[code]) || getUiText(fallbackKey, fallbackText);
	}

	function getFieldConstraintConfig() {
		var cfg = getStepFourConfig();
		var raw = cfg.contact_block && cfg.contact_block.validation_constraints && typeof cfg.contact_block.validation_constraints === 'object'
			? cfg.contact_block.validation_constraints
			: {};
		var minAge = Number(raw.birthdate_min_age);
		var maxAge = Number(raw.birthdate_max_age);
		var phoneOverride = Number(raw.phone_digits_override);
		if (!Number.isFinite(minAge) || minAge < 0) {
			minAge = 0;
		}
		if (!Number.isFinite(maxAge) || maxAge < minAge) {
			maxAge = 120;
		}
		if (!Number.isFinite(phoneOverride) || phoneOverride < 0) {
			phoneOverride = 0;
		}
		return { minAge: minAge, maxAge: maxAge, phoneDigitsOverride: phoneOverride };
	}

	function getOrderNotesSettings() {
		var cfg = getStepFourConfig();
		var block = cfg.contact_block || {};
		var maxLen = Number(block.order_notes_max_length || 500);
		if (!Number.isFinite(maxLen) || maxLen <= 0) {
			maxLen = 500;
		}
		var vm = block.validation_messages && typeof block.validation_messages === 'object' ? block.validation_messages : {};
		var counter = block.order_notes_counter && typeof block.order_notes_counter === 'object' ? block.order_notes_counter : {};
		return {
			maxLength: maxLen,
			showCounter: counter.enabled !== false,
			lengthErrorText: trimNonEmpty(vm.order_notes_length) || getUiText('step_4.contact_error_order_notes_length', 'Превышена максимальная длина примечания.')
		};
	}

	function getAddressGeoMerged() {
		var cfg = getStepFourConfig();
		return cfg.address_geo && typeof cfg.address_geo === 'object' ? cfg.address_geo : {};
	}

	function isAddressSubfieldVisible(key) {
		var cfg = getStepFourConfig();
		var ab = cfg.address_block || {};
		var vm = getStepFourValidationMessages();
		var vis = ab.subfields_visible && typeof ab.subfields_visible === 'object' ? ab.subfields_visible : {};
		return vis[key] !== false;
	}

	function shouldRenderAddressSubfield(key, contact) {
		if (!isAddressSubfieldVisible(key)) {
			return false;
		}
		var vis = contact && contact.__address_visibility ? contact.__address_visibility : {};
		if (vis.hide_address_fields) {
			return false;
		}
		if (key === 'country' && vis.hide_country) {
			return false;
		}
		if (key === 'state' && vis.hide_region) {
			return false;
		}
		if (key === 'city' && vis.hide_city) {
			return false;
		}
		if ((key === 'address_1' || key === 'address_2') && vis.hide_address_lines) {
			return false;
		}
		if (key === 'postcode' && vis.hide_postcode) {
			return false;
		}
		return true;
	}

	function getAddressLabel(key) {
		var cfg = getStepFourConfig();
		var labels = cfg.address_block && cfg.address_block.labels ? cfg.address_block.labels : {};
		var fromCfg = labels[key] ? String(labels[key]) : '';
		if (trimNonEmpty(fromCfg)) {
			return fromCfg;
		}
		var path = {
			country: 'step_4.address_country',
			state: 'step_4.address_region',
			city: 'step_4.address_city',
			address_1: 'step_4.address_line1',
			address_2: 'step_4.address_line2',
			postcode: 'step_4.address_postcode'
		};
		var fb = {
			country: 'Страна',
			state: 'Регион',
			city: 'Населённый пункт',
			address_1: 'Улица, дом',
			address_2: 'Квартира, офис',
			postcode: 'Почтовый индекс'
		};
		return getUiText(path[key] || 'step_4.address_block_title', fb[key] || key);
	}

	function getSortedCountryCodes(geo) {
		var out = [];
		var id;
		for (id in geo) {
			if (!Object.prototype.hasOwnProperty.call(geo, id)) {
				continue;
			}
			var row = geo[id];
			out.push({
				iso: id,
				label: row && row.label ? String(row.label) : id
			});
		}
		out.sort(function (a, b) {
			return a.label.localeCompare(b.label, 'ru');
		});
		return out;
	}

	function getRegionsForCountry(geo, countryIso) {
		var c = geo[countryIso];
		if (!c || typeof c !== 'object') {
			return [];
		}
		var regs = c.regions && typeof c.regions === 'object' ? c.regions : {};
		var out = [];
		var rid;
		for (rid in regs) {
			if (!Object.prototype.hasOwnProperty.call(regs, rid)) {
				continue;
			}
			var row = regs[rid];
			if (!row || typeof row !== 'object') {
				continue;
			}
			out.push({
				id: rid,
				label: String(row.label || rid),
				settlements: Array.isArray(row.settlements) ? row.settlements : []
			});
		}
		out.sort(function (a, b) {
			return a.label.localeCompare(b.label, 'ru');
		});
		return out;
	}

	function getSettlementsForRegion(geo, countryIso, regionId) {
		var c = geo[countryIso];
		if (!c || !c.regions || !c.regions[regionId]) {
			return [];
		}
		var row = c.regions[regionId];
		return Array.isArray(row.settlements) ? row.settlements.slice() : [];
	}

	function isCountryInGeo(geo, countryIso) {
		return Boolean(geo[countryIso]);
	}

	function ensureAddressDefaults(state) {
		if (!state || !state.frontendStore || !state.frontendStore.form) {
			return;
		}
		var contact = state.frontendStore.form.contact || {};
		var vis = contact.__address_visibility;
		if (vis && vis.hide_address_fields) {
			return;
		}
		var cfg = getStepFourConfig();
		var ab = cfg.address_block || {};
		var defCountry = trimNonEmpty(ab.default_country) ? String(ab.default_country) : 'RU';
		var geo = getAddressGeoMerged();
		var needGeoKey = shouldRenderAddressSubfield('country', contact)
			|| shouldRenderAddressSubfield('state', contact)
			|| shouldRenderAddressSubfield('city', contact);
		if (needGeoKey && !trimNonEmpty(contact.country)) {
			contact.country = defCountry;
		}
		if (needGeoKey && !isCountryInGeo(geo, contact.country)) {
			contact.country = defCountry;
		}
		var regions = getRegionsForCountry(geo, contact.country);
		if (regions.length && contact.state) {
			var found = false;
			var ri;
			for (ri = 0; ri < regions.length; ri++) {
				if (regions[ri].id === contact.state) {
					found = true;
					break;
				}
			}
			if (!found) {
				delete contact.state;
				delete contact.city;
			}
		}
		if (contact.state && trimNonEmpty(contact.city)) {
			var settlements = getSettlementsForRegion(geo, contact.country, contact.state);
			if (settlements.length) {
				var ok = false;
				var si;
				for (si = 0; si < settlements.length; si++) {
					if (settlements[si] === contact.city) {
						ok = true;
						break;
					}
				}
				if (!ok) {
					delete contact.city;
				}
			}
		}
		state.frontendStore.form.contact = contact;
	}

	function findPhoneCountryMeta(codes, iso) {
		var list = Array.isArray(codes) ? codes : [];
		var i;
		for (i = 0; i < list.length; i++) {
			var row = list[i];
			if (row && String(row.iso || '') === String(iso || '')) {
				return {
					dial: String(row.dial || '+7'),
					national_digits: Number(row.national_digits || 10),
					iso: String(row.iso || '')
				};
			}
		}
		if (list.length && list[0]) {
			return {
				dial: String(list[0].dial || '+7'),
				national_digits: Number(list[0].national_digits || 10),
				iso: String(list[0].iso || 'RU')
			};
		}
		return { dial: '+7', national_digits: 10, iso: 'RU' };
	}

	function formatNationalPhoneDisplay(dial, digits) {
		var d = String(digits || '').replace(/\D/g, '');
		if (dial === '+375') {
			d = d.slice(0, 9);
			var p1 = d.slice(0, 2);
			var p2 = d.slice(2, 5);
			var p3 = d.slice(5, 7);
			var p4 = d.slice(7, 9);
			var out = '';
			if (p1) {
				out += '(' + p1 + ')';
			}
			if (p2) {
				out += (out ? ' ' : '') + p2;
			}
			if (p3) {
				out += '-' + p3;
			}
			if (p4) {
				out += '-' + p4;
			}
			return out;
		}
		d = d.slice(0, 10);
		var a = d.slice(0, 3);
		var b = d.slice(3, 6);
		var c = d.slice(6, 8);
		var e = d.slice(8, 10);
		if (!a) {
			return '';
		}
		var s = '(' + a + ')';
		if (b) {
			s += ' ' + b;
		}
		if (c) {
			s += '-' + c;
		}
		if (e) {
			s += '-' + e;
		}
		return s;
	}

	function buildFullPhoneE164(contact) {
		var dial = String(contact.phone_dial_code || '+7');
		var nat = String(contact.billing_phone_national || '').replace(/\D/g, '');
		return dial + nat;
	}

	function isValidEmailValue(value) {
		var s = String(value || '').trim();
		if (!s) {
			return false;
		}
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);
	}

	function ensureContactDefaults(state) {
		if (!state || !state.frontendStore || !state.frontendStore.form) {
			return;
		}
		var c = state.frontendStore.form.contact || {};
		if (!c.__address_visibility) {
			applyScenarioFieldAvailability(state);
		}
		c = state.frontendStore.form.contact || {};
		var cfg = getStepFourConfig();
		var block = cfg.contact_block || {};
		var codes = Array.isArray(block.phone_country_codes) ? block.phone_country_codes : [];
		var constraints = getFieldConstraintConfig();
		var defIso = trimNonEmpty(block.default_phone_country_iso) ? String(block.default_phone_country_iso) : 'RU';
		if (!trimNonEmpty(c.phone_country_iso)) {
			c.phone_country_iso = defIso;
		}
		var meta = findPhoneCountryMeta(codes, c.phone_country_iso);
		c.phone_dial_code = meta.dial;
		if (!trimNonEmpty(c.billing_phone_national) && trimNonEmpty(c.billing_phone)) {
			var raw = String(c.billing_phone).replace(/\D/g, '');
			var dialDigits = String(meta.dial || '').replace(/\D/g, '');
			if (dialDigits && raw.indexOf(dialDigits) === 0) {
				c.billing_phone_national = raw.slice(dialDigits.length);
			} else {
				c.billing_phone_national = raw;
			}
		}
		c.billing_phone = buildFullPhoneE164(c);
		if (state.frontendStore && state.frontendStore.payment && !trimNonEmpty(state.frontendStore.payment.gateway)) {
			var gateways = getAvailablePaymentGateways();
			if (gateways.length) {
				state.frontendStore.payment.gateway = String(gateways[0].id || '');
				c.payment_gateway = state.frontendStore.payment.gateway;
				c.gateway = state.frontendStore.payment.gateway;
			}
		}
		state.frontendStore.form.contact = c;
		ensureAddressDefaults(state);
	}

	function getAvailablePaymentGateways() {
		var cfg = getStepFourConfig();
		var list = Array.isArray(cfg.available_gateways) ? cfg.available_gateways : [];
		var pb = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var order = Array.isArray(pb.gateway_order) ? pb.gateway_order : [];
		var normalized = [];
		for (var i = 0; i < list.length; i += 1) {
			var row = list[i] || {};
			var id = trimNonEmpty(row.id);
			if (!id) {
				continue;
			}
			normalized.push({
				id: id,
				title: trimNonEmpty(row.title) || id,
				description: trimNonEmpty(row.description) || ''
			});
		}
		if (!order.length) {
			return normalized;
		}
		var rank = {};
		for (i = 0; i < order.length; i += 1) {
			var id = trimNonEmpty(order[i]);
			if (!id || Object.prototype.hasOwnProperty.call(rank, id)) {
				continue;
			}
			rank[id] = i;
		}
		normalized.sort(function (a, b) {
			var ar = Object.prototype.hasOwnProperty.call(rank, a.id) ? rank[a.id] : 9999;
			var br = Object.prototype.hasOwnProperty.call(rank, b.id) ? rank[b.id] : 9999;
			if (ar !== br) {
				return ar - br;
			}
			return String(a.title || '').localeCompare(String(b.title || ''));
		});
		return normalized;
	}

	function getSelectedGatewayTitle(state) {
		var selected = trimNonEmpty(state && state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : '');
		if (!selected) {
			return '';
		}
		var gateways = getAvailablePaymentGateways();
		for (var i = 0; i < gateways.length; i += 1) {
			if (String(gateways[i].id) === selected) {
				return String(gateways[i].title || selected);
			}
		}
		return selected;
	}

	function resetPrePaymentConfirm(state) {
		if (!state || !state.frontendStore || !state.frontendStore.runtime) {
			return;
		}
		state.frontendStore.runtime.prePaymentConfirm = false;
	}

	function isPaymentSubmissionLocked(state) {
		return !!(state && state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.paymentSubmitting);
	}

	function submitFinalPayment(state, $app) {
		if (!state || !state.frontendStore) {
			return;
		}
		state.frontendStore.runtime = state.frontendStore.runtime || {};
		if (isPaymentSubmissionLocked(state)) {
			notify(getUiText('order_review.payment_in_progress', 'Оплата уже отправляется. Подождите.'), 'info');
			return;
		}
		if (pendingCheckoutRequests > 0) {
			notify(getUiText('order_review.payment_wait_requests', 'Дождитесь завершения фоновых операций и повторите оплату.'), 'error');
			return;
		}
		state.frontendStore.runtime.paymentSubmitting = true;
		state.frontendStore.payment = state.frontendStore.payment || { gateway: '', state: 'idle' };
		state.frontendStore.payment.state = 'syncing';
		render(state, $app);
		postCheckout('submit_payment', {
			context_id: state.flowContextId
		}).then(function (response) {
			var data = response && response.data ? response.data : {};
			state.frontendStore.runtime.paymentSubmitting = false;
			state.frontendStore.payment.state = 'success';
			if (data.flow || data.cart) {
				syncFromFlow(state, data.flow || {}, data.cart || {});
			}
			render(state, $app);
			if (data && data.confirmed && trimNonEmpty(data.success_url)) {
				window.location.href = String(data.success_url);
				return;
			}
			notify(getUiText('order_review.payment_unconfirmed', 'Платёж не подтверждён. Проверьте состояние заказа.'), 'error');
		}).fail(function (xhr) {
			var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			state.frontendStore.runtime.paymentSubmitting = false;
			state.frontendStore.payment.state = 'error';
			if (payload.flow || payload.cart) {
				syncFromFlow(state, payload.flow || {}, payload.cart || {});
			}
			render(state, $app);
			saveCurrentStepDraft(state);
			notify(trimNonEmpty(payload.message) || getUiText('order_review.payment_submit_failed', 'Не удалось отправить оплату. Попробуйте ещё раз.'), 'error');
		});
	}

	function maybeSendGatewayRenderDiagnostics(state, issues) {
		var cfg = getStepFourConfig();
		var pb = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var diagnostics = pb.diagnostics && typeof pb.diagnostics === 'object' ? pb.diagnostics : {};
		if (diagnostics.enabled === false) {
			return;
		}
		if (!Array.isArray(issues) || !issues.length) {
			return;
		}
		postCheckout('gateway_render_diagnostics', {
			context_id: state.flowContextId,
			issues: issues
		});
	}

	function ensureDiscountDefaults(state) {
		if (!state || !state.frontendStore) {
			return;
		}
		var discounts = state.frontendStore.discounts && typeof state.frontendStore.discounts === 'object'
			? state.frontendStore.discounts
			: {};
		discounts.coupons = Array.isArray(discounts.coupons) ? discounts.coupons : [];
		discounts.gift_card = Array.isArray(discounts.gift_card) ? discounts.gift_card : [];
		discounts.coupon_runtime = discounts.coupon_runtime && typeof discounts.coupon_runtime === 'object'
			? discounts.coupon_runtime
			: { code: '', state: 'empty', message: '' };
		discounts.gift_card_runtime = discounts.gift_card_runtime && typeof discounts.gift_card_runtime === 'object'
			? discounts.gift_card_runtime
			: { code: '', state: 'empty', message: '' };
		if (!trimNonEmpty(discounts.coupon_runtime.state)) {
			discounts.coupon_runtime.state = 'empty';
		}
		if (!trimNonEmpty(discounts.gift_card_runtime.state)) {
			discounts.gift_card_runtime.state = 'empty';
		}
		state.frontendStore.discounts = discounts;
	}

	function getCouponCopy() {
		var cfg = getStepFourConfig();
		var c = cfg.coupon_block || {};
		return {
			title: trimNonEmpty(c.title) || getUiText('step_4.coupon_title', 'Промокод'),
			intro: trimNonEmpty(c.intro) || getUiText('step_4.coupon_intro', 'Введите код купона, если он у вас есть.'),
			inputLabel: trimNonEmpty(c.input_label) || getUiText('step_4.coupon_input_label', 'Код купона'),
			placeholder: trimNonEmpty(c.placeholder) || getUiText('step_4.coupon_placeholder', 'Например, SPRING10'),
			applyLabel: trimNonEmpty(c.apply_label) || getUiText('step_4.coupon_apply', 'Применить'),
			emptyMessage: trimNonEmpty(c.empty_message) || getUiText('step_4.coupon_empty', 'Введите код купона.'),
			successMessage: trimNonEmpty(c.success_message) || getUiText('step_4.coupon_success', 'Промокод применён.'),
			errorMessage: trimNonEmpty(c.error_message) || getUiText('step_4.coupon_error', 'Не удалось применить промокод.')
		};
	}

	function getGiftCardCopy() {
		var cfg = getStepFourConfig();
		var g = cfg.gift_card_block || {};
		return {
			title: trimNonEmpty(g.title) || getUiText('step_4.gift_card_title', 'Подарочная карта'),
			intro: trimNonEmpty(g.intro) || getUiText('step_4.gift_card_intro', 'Введите код подарочной карты.'),
			inputLabel: trimNonEmpty(g.input_label) || getUiText('step_4.gift_card_input_label', 'Код подарочной карты'),
			placeholder: trimNonEmpty(g.placeholder) || getUiText('step_4.gift_card_placeholder', 'Например, GIFT-123'),
			applyLabel: trimNonEmpty(g.apply_label) || getUiText('step_4.gift_card_apply', 'Применить'),
			emptyMessage: trimNonEmpty(g.empty_message) || getUiText('step_4.gift_card_empty', 'Введите код подарочной карты.'),
			successMessage: trimNonEmpty(g.success_message) || getUiText('step_4.gift_card_success', 'Подарочная карта применена.'),
			errorMessage: trimNonEmpty(g.error_message) || getUiText('step_4.gift_card_error', 'Не удалось применить подарочную карту.')
		};
	}

	function validateContactPaymentStep(state) {
		ensureContactDefaults(state);
		var contact = state.frontendStore.form.contact || {};
		var cfg = getStepFourConfig();
		var block = cfg.contact_block || {};
		var codes = Array.isArray(block.phone_country_codes) ? block.phone_country_codes : [];
		var errors = {};
		var ok = true;
		if (isContactFieldVisible('last_name') && isContactFieldRequired('last_name') && !trimNonEmpty(contact.billing_last_name)) {
			errors.billing_last_name = 'required';
			ok = false;
		}
		if (isContactFieldVisible('first_name') && isContactFieldRequired('first_name') && !trimNonEmpty(contact.billing_first_name)) {
			errors.billing_first_name = 'required';
			ok = false;
		}
		if (isContactFieldVisible('patronymic') && (block.patronymic_required || isContactFieldRequired('patronymic')) && !trimNonEmpty(contact.billing_patronymic)) {
			errors.billing_patronymic = 'required';
			ok = false;
		}
		if (isContactFieldVisible('gender') && isContactFieldRequired('gender') && !trimNonEmpty(contact.billing_gender)) {
			errors.billing_gender = 'required';
			ok = false;
		}
		if (isContactFieldVisible('birthdate')) {
			var birthRaw = trimNonEmpty(contact.billing_birthdate);
			if (isContactFieldRequired('birthdate') && !birthRaw) {
				errors.billing_birthdate = 'required';
				ok = false;
			} else if (birthRaw) {
				var m = birthRaw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
				if (!m) {
					errors.billing_birthdate = 'invalid';
					ok = false;
				} else {
					var yyyy = Number(m[1]);
					var mm = Number(m[2]) - 1;
					var dd = Number(m[3]);
					var date = new Date(yyyy, mm, dd);
					if (date.getFullYear() !== yyyy || date.getMonth() !== mm || date.getDate() !== dd) {
						errors.billing_birthdate = 'invalid';
						ok = false;
					} else {
						var today = new Date();
						var age = today.getFullYear() - yyyy;
						var beforeBirthday = (today.getMonth() < mm) || (today.getMonth() === mm && today.getDate() < dd);
						if (beforeBirthday) {
							age -= 1;
						}
						if (age < constraints.minAge || age > constraints.maxAge) {
							errors.billing_birthdate = 'range';
							ok = false;
						}
					}
				}
			}
		}
		if (isContactFieldVisible('order_notes') && trimNonEmpty(contact.order_notes)) {
			var notesCfg = getOrderNotesSettings();
			if (String(contact.order_notes).length > notesCfg.maxLength) {
				errors.order_notes = 'length';
				ok = false;
			}
		}
		if (isContactFieldVisible('email') && isContactFieldRequired('email') && !trimNonEmpty(contact.billing_email)) {
			errors.billing_email = 'required';
			ok = false;
		} else if (isContactFieldVisible('email') && trimNonEmpty(contact.billing_email) && !isValidEmailValue(contact.billing_email)) {
			errors.billing_email = 'format';
			ok = false;
		}
		var meta = findPhoneCountryMeta(codes, contact.phone_country_iso);
		var digits = String(contact.billing_phone_national || '').replace(/\D/g, '');
		var need = constraints.phoneDigitsOverride > 0 ? constraints.phoneDigitsOverride : (meta.national_digits || 10);
		if (isContactFieldVisible('phone') && isContactFieldRequired('phone') && !digits.length) {
			errors.billing_phone_national = 'required';
			ok = false;
		} else if (isContactFieldVisible('phone') && isContactFieldRequired('phone') && digits.length !== need) {
			errors.billing_phone_national = 'format';
			ok = false;
		}
		var addrVis = contact.__address_visibility;
		if (addrVis && !addrVis.hide_address_fields && addrVis.required_address_fields) {
			var geo = getAddressGeoMerged();
			var ab = cfg.address_block || {};
			var order = Array.isArray(ab.subfields_order)
				? ab.subfields_order
				: ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'];
			var maxZip = Number(ab.postcode_max_length);
			if (!Number.isFinite(maxZip) || maxZip <= 0) {
				maxZip = 16;
			}
			var ki;
			for (ki = 0; ki < order.length; ki++) {
				var ak = order[ki];
				if (!shouldRenderAddressSubfield(ak, contact)) {
					continue;
				}
				if (ak === 'address_2') {
					continue;
				}
				if (ak === 'country') {
					if (!trimNonEmpty(contact.country)) {
						errors.country = 'required';
						ok = false;
					}
					continue;
				}
				if (ak === 'state') {
					var stateVal = trimNonEmpty(contact.state);
					var regions = getRegionsForCountry(geo, contact.country);
					var requiresKnownRegion = regions.length > 0;
					if (!stateVal) {
						errors.state = 'region';
						ok = false;
					} else if (requiresKnownRegion && regions.indexOf(stateVal) < 0) {
						errors.state = 'region';
						ok = false;
					}
					continue;
				}
				if (ak === 'city') {
					var settlements = getSettlementsForRegion(geo, contact.country, contact.state);
					if (!trimNonEmpty(contact.city)) {
						errors.city = 'required';
						ok = false;
					} else if (settlements.length) {
						var cityOk = false;
						var ci;
						for (ci = 0; ci < settlements.length; ci++) {
							if (settlements[ci] === contact.city) {
								cityOk = true;
								break;
							}
						}
						if (!cityOk) {
						errors.city = 'city';
							ok = false;
						}
					}
					continue;
				}
				if (ak === 'postcode') {
					var pc = String(contact.postcode || '').trim();
					if (!pc) {
						errors.postcode = 'required';
						ok = false;
					} else if (pc.length > maxZip) {
						errors.postcode = 'postcode';
						ok = false;
					}
					continue;
				}
				if (ak === 'address_1' && !trimNonEmpty(contact.address_1)) {
					errors.address_1 = 'required';
					ok = false;
				}
			}
		}
		var gateways = getAvailablePaymentGateways();
		var paymentCfg = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var paymentRequired = paymentCfg.required !== false;
		var selectedGateway = trimNonEmpty(state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : '');
		if (paymentRequired && gateways.length && !selectedGateway) {
			errors.payment_gateway = 'required';
			ok = false;
		}
		state.frontendStore.form.errors = state.frontendStore.form.errors || {};
		state.frontendStore.form.errors.contact = ok ? {} : errors;
		return ok;
	}

	function setStepInvalidState(state, stepId, isInvalid) {
		if (!state || !state.frontendStore || !state.frontendStore.runtime || !stepId) {
			return;
		}
		var map = state.frontendStore.runtime.invalid_steps && typeof state.frontendStore.runtime.invalid_steps === 'object'
			? state.frontendStore.runtime.invalid_steps
			: {};
		if (isInvalid) {
			map[stepId] = true;
		} else if (Object.prototype.hasOwnProperty.call(map, stepId)) {
			delete map[stepId];
		}
		state.frontendStore.runtime.invalid_steps = map;
	}

	function findFirstInvalidFieldElement($app) {
		var selectorsList = [
			'.mp-cc-input.is-invalid',
			'.mp-cc-select.is-invalid',
			'.mp-cc-payment-card__radio.is-invalid',
			'.mp-cc-conditions-step__confirm.is-error input[type="checkbox"]',
			'.mp-cc-date-step__helper.is-error'
		];
		var i;
		for (i = 0; i < selectorsList.length; i += 1) {
			var $el = $app.find(selectorsList[i]).first();
			if ($el.length) {
				return $el;
			}
		}
		return $();
	}

	function scrollToFirstInvalidField($app) {
		var $el = findFirstInvalidFieldElement($app);
		if (!$el.length) {
			return;
		}
		var node = $el.get(0);
		if (node && typeof node.scrollIntoView === 'function') {
			node.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
		if (node && typeof node.focus === 'function' && !$el.is('.mp-cc-date-step__helper')) {
			try {
				node.focus({ preventScroll: true });
			} catch (e) {
				node.focus();
			}
		}
	}

	function logValidationFailure(state, stepId, errorsMap) {
		var cleanStep = String(stepId || '');
		var errs = errorsMap && typeof errorsMap === 'object' ? errorsMap : {};
		var keys = Object.keys(errs);
		if (!cleanStep || !keys.length) {
			return;
		}
		postCheckout('validation_log', {
			context_id: state.flowContextId,
			step_id: cleanStep,
			errors: errs
		});
	}

	function getContactFieldError(state, fieldKey) {
		var e = state.frontendStore && state.frontendStore.form && state.frontendStore.form.errors && state.frontendStore.form.errors.contact
			? state.frontendStore.form.errors.contact
			: {};
		return e[fieldKey] ? String(e[fieldKey]) : '';
	}

	function buildContactPaymentHtml(state) {
		ensureContactDefaults(state);
		var contact = state.frontendStore.form.contact || {};
		var cfg = getStepFourConfig();
		var block = cfg.contact_block || {};
		var codes = Array.isArray(block.phone_country_codes) ? block.phone_country_codes : [];
		var meta = findPhoneCountryMeta(codes, contact.phone_country_iso);
		var nationalDigits = String(contact.billing_phone_national || '').replace(/\D/g, '');
		var displayPhone = formatNationalPhoneDisplay(meta.dial, nationalDigits);
		var title = trimNonEmpty(block.title) || getUiText('step_4.title', 'Контакты и оплата');
		var intro = trimNonEmpty(block.intro) || getUiText('step_4.contact_block_intro', 'Укажите данные для связи и оформления заказа.');
		var errLast = getContactFieldError(state, 'billing_last_name');
		var errFirst = getContactFieldError(state, 'billing_first_name');
		var errPat = getContactFieldError(state, 'billing_patronymic');
		var errGender = getContactFieldError(state, 'billing_gender');
		var errBirth = getContactFieldError(state, 'billing_birthdate');
		var errNotes = getContactFieldError(state, 'order_notes');
		var errEmail = getContactFieldError(state, 'billing_email');
		var errPhone = getContactFieldError(state, 'billing_phone_national');
		var vm = getStepFourValidationMessages();
		var order = Array.isArray(block.field_order) ? block.field_order : ['last_name', 'first_name', 'patronymic', 'gender', 'birthdate', 'email', 'phone', 'order_notes'];
		var seen = {};
		var ordered = [];
		var oi;
		for (oi = 0; oi < order.length; oi++) {
			var k = String(order[oi] || '');
			if (!k || seen[k]) {
				continue;
			}
			seen[k] = true;
			ordered.push(k);
		}
		var fallbackOrder = ['last_name', 'first_name', 'patronymic', 'gender', 'birthdate', 'email', 'phone', 'order_notes'];
		var genderOptions = getGenderOptions();
		var notesCfg = getOrderNotesSettings();
		for (oi = 0; oi < fallbackOrder.length; oi++) {
			if (!seen[fallbackOrder[oi]]) {
				ordered.push(fallbackOrder[oi]);
			}
		}
		var layout = block.layout && typeof block.layout === 'object' ? block.layout : {};
		var desktopCols = Math.max(1, Number(layout.desktop_columns || 3));
		var tabletCols = Math.max(1, Number(layout.tablet_columns || 2));
		var mobileCols = Math.max(1, Number(layout.mobile_columns || 1));
		var gridGap = trimNonEmpty(layout.grid_gap) || '0.75rem 1rem';
		var stateStyles = block.field_state_styles && typeof block.field_state_styles === 'object' ? block.field_state_styles : {};
		var invalidStyle = trimNonEmpty(stateStyles.invalid_style) || 'default';
		var hintStyle = trimNonEmpty(stateStyles.hint_style) || 'default';
		var focusStyle = trimNonEmpty(stateStyles.focus_style) || 'default';
		var disabledStyle = trimNonEmpty(stateStyles.disabled_style) || 'default';
		var html = '';
		html += '<section class="mp-cc-contact mp-cc-contact--invalid-' + escapeHtml(invalidStyle) + ' mp-cc-contact--hint-' + escapeHtml(hintStyle) + ' mp-cc-contact--focus-' + escapeHtml(focusStyle) + ' mp-cc-contact--disabled-' + escapeHtml(disabledStyle) + '" aria-labelledby="mp-cc-contact-title">';
		html += '<header class="mp-cc-contact__header">';
		html += '<h3 class="mp-cc-contact__title" id="mp-cc-contact-title">' + escapeHtml(title) + '</h3>';
		if (intro) {
			html += '<p class="mp-cc-contact__intro" id="mp-cc-contact-intro">' + escapeHtml(intro) + '</p>';
		}
		html += '</header>';
		html += '<div class="mp-cc-contact__grid" style="--mp-cc-contact-cols:' + escapeHtml(String(desktopCols)) + ';--mp-cc-contact-cols-tablet:' + escapeHtml(String(tabletCols)) + ';--mp-cc-contact-cols-mobile:' + escapeHtml(String(mobileCols)) + ';--mp-cc-contact-gap:' + escapeHtml(gridGap) + ';">';
		for (oi = 0; oi < ordered.length; oi++) {
			var field = ordered[oi];
			if (!isContactFieldVisible(field)) {
				continue;
			}
			if (field === 'last_name') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
		html += '<label class="mp-cc-field-label" for="mp-cc-contact-last-name">' + escapeHtml(getContactLabel('last_name')) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (errLast ? ' is-invalid' : '') + '" id="mp-cc-contact-last-name" name="billing_last_name" autocomplete="family-name" ';
		html += 'value="' + escapeHtml(String(contact.billing_last_name || '')) + '" ';
		html += 'data-contact-field="billing_last_name"' + (isContactFieldRequired('last_name') ? ' aria-required="true"' : '');
		var pLast = getContactPlaceholder('last_name');
		if (pLast) { html += ' placeholder="' + escapeHtml(pLast) + '"'; }
		html += errLast ? ' aria-invalid="true"' : '';
		html += '/>';
		if (errLast) {
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-last-name-err" role="alert">' + escapeHtml(trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.')) + '</p>';
		}
		html += '</div>';
				continue;
			}
			if (field === 'first_name') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
		html += '<label class="mp-cc-field-label" for="mp-cc-contact-first-name">' + escapeHtml(getContactLabel('first_name')) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (errFirst ? ' is-invalid' : '') + '" id="mp-cc-contact-first-name" name="billing_first_name" autocomplete="given-name" ';
		html += 'value="' + escapeHtml(String(contact.billing_first_name || '')) + '" ';
		html += 'data-contact-field="billing_first_name"' + (isContactFieldRequired('first_name') ? ' aria-required="true"' : '');
		var pFirst = getContactPlaceholder('first_name');
		if (pFirst) { html += ' placeholder="' + escapeHtml(pFirst) + '"'; }
		html += errFirst ? ' aria-invalid="true"' : '';
		html += '/>';
		if (errFirst) {
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-first-name-err" role="alert">' + escapeHtml(trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.')) + '</p>';
		}
		html += '</div>';
				continue;
			}
			if (field === 'patronymic') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
		html += '<label class="mp-cc-field-label" for="mp-cc-contact-patronymic">' + escapeHtml(getContactLabel('patronymic')) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (errPat ? ' is-invalid' : '') + '" id="mp-cc-contact-patronymic" name="billing_patronymic" autocomplete="additional-name" ';
		html += 'value="' + escapeHtml(String(contact.billing_patronymic || '')) + '" ';
		html += 'data-contact-field="billing_patronymic"';
		html += (block.patronymic_required || isContactFieldRequired('patronymic')) ? ' aria-required="true"' : '';
		var pPatr = getContactPlaceholder('patronymic');
		if (pPatr) { html += ' placeholder="' + escapeHtml(pPatr) + '"'; }
		html += ' aria-describedby="mp-cc-contact-patronymic-hint"';
		html += errPat ? ' aria-invalid="true"' : '';
		html += '/>';
		html += '<p class="mp-cc-field-hint" id="mp-cc-contact-patronymic-hint">' + escapeHtml(getContactHint('patronymic')) + '</p>';
		if (errPat) {
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-patronymic-err" role="alert">' + escapeHtml(trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.')) + '</p>';
		}
		html += '</div>';
				continue;
			}
			if (field === 'gender') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
				html += '<label class="mp-cc-field-label" for="mp-cc-contact-gender">' + escapeHtml(getContactLabel('gender')) + '</label>';
				html += '<select id="mp-cc-contact-gender" class="mp-cc-select' + (errGender ? ' is-invalid' : '') + '" data-contact-field="billing_gender"';
				html += isContactFieldRequired('gender') ? ' aria-required="true"' : '';
				html += errGender ? ' aria-invalid="true"' : '';
				html += '>';
				html += '<option value="">' + escapeHtml(genderOptions.placeholder) + '</option>';
				html += '<option value="male"' + (String(contact.billing_gender || '') === 'male' ? ' selected' : '') + '>' + escapeHtml(genderOptions.male) + '</option>';
				html += '<option value="female"' + (String(contact.billing_gender || '') === 'female' ? ' selected' : '') + '>' + escapeHtml(genderOptions.female) + '</option>';
				html += '</select>';
				if (trimNonEmpty(getContactHint('gender'))) {
					html += '<p class="mp-cc-field-hint">' + escapeHtml(getContactHint('gender')) + '</p>';
				}
				if (errGender) {
					html += '<p class="mp-cc-field-error" id="mp-cc-contact-gender-err" role="alert">' + escapeHtml(trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.')) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (field === 'birthdate') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
				html += '<label class="mp-cc-field-label" for="mp-cc-contact-birthdate">' + escapeHtml(getContactLabel('birthdate')) + '</label>';
				html += '<input type="date" class="mp-cc-input' + (errBirth ? ' is-invalid' : '') + '" id="mp-cc-contact-birthdate" name="billing_birthdate" autocomplete="bday" ';
				html += 'value="' + escapeHtml(String(contact.billing_birthdate || '')) + '" ';
				html += 'data-contact-field="billing_birthdate"' + (isContactFieldRequired('birthdate') ? ' aria-required="true"' : '');
				var pBirth = getContactPlaceholder('birthdate');
				if (pBirth) { html += ' placeholder="' + escapeHtml(pBirth) + '"'; }
				html += ' max="' + escapeHtml((new Date()).toISOString().slice(0, 10)) + '"';
				html += errBirth ? ' aria-invalid="true"' : '';
				html += '/>';
				html += '<p class="mp-cc-field-hint" id="mp-cc-contact-birthdate-hint">' + escapeHtml(getContactHint('birthdate')) + '</p>';
				if (errBirth) {
					html += '<p class="mp-cc-field-error" id="mp-cc-contact-birthdate-err" role="alert">' + escapeHtml(getBirthdateErrorText(errBirth)) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (field === 'email') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-2">';
		html += '<label class="mp-cc-field-label" for="mp-cc-contact-email">' + escapeHtml(getContactLabel('email')) + '</label>';
		html += '<input type="email" class="mp-cc-input' + (errEmail ? ' is-invalid' : '') + '" id="mp-cc-contact-email" name="billing_email" autocomplete="email" inputmode="email" ';
		html += 'value="' + escapeHtml(String(contact.billing_email || '')) + '" ';
		html += 'data-contact-field="billing_email"' + (isContactFieldRequired('email') ? ' aria-required="true"' : '');
		var pEmail = getContactPlaceholder('email');
		if (pEmail) { html += ' placeholder="' + escapeHtml(pEmail) + '"'; }
		html += ' aria-describedby="mp-cc-contact-email-hint"';
		html += errEmail ? ' aria-invalid="true"' : '';
		html += '/>';
		html += '<p class="mp-cc-field-hint" id="mp-cc-contact-email-hint">' + escapeHtml(getContactHint('email')) + '</p>';
		if (errEmail) {
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-email-err" role="alert">' + escapeHtml(errEmail === 'format' ? (trimNonEmpty(vm.email_invalid) || getUiText('step_4.contact_error_email', 'Введите корректный email.')) : (trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.'))) + '</p>';
		}
		html += '</div>';
				continue;
			}
			if (field === 'phone') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-2 mp-cc-contact__field--phone">';
		html += '<span class="mp-cc-field-label" id="mp-cc-contact-phone-label">' + escapeHtml(getContactLabel('phone')) + '</span>';
		html += '<div class="mp-cc-contact__phone-row" role="group" aria-labelledby="mp-cc-contact-phone-label">';
		html += '<div class="mp-cc-contact__country">';
		html += '<label class="mp-cc-visually-hidden" for="mp-cc-contact-phone-country">' + escapeHtml(getContactLabel('country_code')) + '</label>';
		html += '<select id="mp-cc-contact-phone-country" class="mp-cc-select" data-contact-phone-country="1" aria-describedby="mp-cc-contact-phone-hint"';
		html += errPhone ? ' aria-invalid="true"' : '';
		html += '>';
		var ci;
		for (ci = 0; ci < codes.length; ci++) {
			var opt = codes[ci];
			if (!opt) {
				continue;
			}
			var iso = String(opt.iso || '');
			var dial = String(opt.dial || '');
			var sel = iso === String(contact.phone_country_iso || '');
			html += '<option value="' + escapeHtml(iso) + '"' + (sel ? ' selected' : '') + '>' + escapeHtml(dial + ' · ' + iso) + '</option>';
		}
		html += '</select>';
		html += '</div>';
		html += '<div class="mp-cc-contact__national">';
		html += '<label class="mp-cc-visually-hidden" for="mp-cc-contact-phone-national">' + escapeHtml(getContactLabel('phone')) + '</label>';
		html += '<input type="tel" class="mp-cc-input' + (errPhone ? ' is-invalid' : '') + '" id="mp-cc-contact-phone-national" name="billing_phone_national" autocomplete="tel-national" inputmode="numeric" ';
		html += 'value="' + escapeHtml(displayPhone) + '" ';
		html += 'data-contact-phone-national="1"' + (isContactFieldRequired('phone') ? ' aria-required="true"' : '');
		var pPhone = getContactPlaceholder('phone');
		if (pPhone) { html += ' placeholder="' + escapeHtml(pPhone) + '"'; }
		html += ' aria-describedby="mp-cc-contact-phone-hint"';
		html += errPhone ? ' aria-invalid="true"' : '';
		html += '/>';
		html += '</div>';
		html += '</div>';
		html += '<p class="mp-cc-field-hint" id="mp-cc-contact-phone-hint">' + escapeHtml(getContactHint('phone')) + '</p>';
		if (errPhone) {
			var phoneMsg = errPhone === 'required'
				? (trimNonEmpty(vm.phone_required) || getUiText('step_4.contact_error_phone_required', 'Укажите номер телефона.'))
				: (trimNonEmpty(vm.phone_format) || getUiText('step_4.contact_error_phone', 'Введите номер полностью.'));
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-phone-err" role="alert">' + escapeHtml(phoneMsg) + '</p>';
		}
		html += '</div>';
			}
		}
		html += '</div>';
		html += buildPaymentGatewaysHtml(state);
		html += '</section>';
		return html;
	}

	function buildPaymentGatewaysHtml(state) {
		var cfg = getStepFourConfig();
		var pb = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var gateways = getAvailablePaymentGateways();
		if (!gateways.length) {
			return '';
		}
		var selected = trimNonEmpty(state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : '') || String(gateways[0].id || '');
		var errPayment = getContactFieldError(state, 'payment_gateway');
		var title = trimNonEmpty(pb.title) || getUiText('step_4.payment_title', 'Способ оплаты');
		var intro = trimNonEmpty(pb.intro) || getUiText('step_4.payment_intro', 'Выберите удобный способ оплаты.');
		var showDescription = pb.show_description !== false;
		var layout = pb.layout && typeof pb.layout === 'object' ? pb.layout : {};
		var messages = pb.messages && typeof pb.messages === 'object' ? pb.messages : {};
		var paymentState = state.frontendStore && state.frontendStore.payment ? String(state.frontendStore.payment.state || 'idle') : 'idle';
		var diagnosticsIssues = [];
		if (trimNonEmpty(selected)) {
			var selectedPresent = false;
			for (var si = 0; si < gateways.length; si += 1) {
				if (String(gateways[si].id) === String(selected)) {
					selectedPresent = true;
					break;
				}
			}
			if (!selectedPresent) {
				diagnosticsIssues.push('Selected gateway is missing from available list: ' + selected);
			}
		}
		maybeSendGatewayRenderDiagnostics(state, diagnosticsIssues);
		var html = '';
		html += '<section class="mp-cc-payment mp-cc-payment--' + escapeHtml(trimNonEmpty(pb.card_style) || 'default') + ' mp-cc-payment--radio-' + escapeHtml(trimNonEmpty(pb.radio_style) || 'default') + ' mp-cc-payment--desc-' + escapeHtml(trimNonEmpty(pb.description_style) || 'muted') + (paymentState === 'syncing' ? ' is-loading' : '') + '" aria-labelledby="mp-cc-payment-title">';
		html += '<header class="mp-cc-payment__header">';
		html += '<h4 class="mp-cc-payment__title" id="mp-cc-payment-title">' + escapeHtml(title) + '</h4>';
		if (intro) {
			html += '<p class="mp-cc-payment__intro">' + escapeHtml(intro) + '</p>';
		}
		html += '</header>';
		html += '<div class="mp-cc-payment__grid" style="--mp-cc-payment-cols:' + escapeHtml(String(Number(layout.desktop_columns || 2))) + ';--mp-cc-payment-cols-tablet:' + escapeHtml(String(Number(layout.tablet_columns || 2))) + ';--mp-cc-payment-cols-mobile:' + escapeHtml(String(Number(layout.mobile_columns || 1))) + ';--mp-cc-payment-gap:' + escapeHtml(trimNonEmpty(layout.grid_gap) || '0.6rem 0.75rem') + ';">';
		for (var gi = 0; gi < gateways.length; gi += 1) {
			var g = gateways[gi];
			var isSelected = String(g.id) === String(selected);
			html += '<label class="mp-cc-payment-card mp-cc-payment-card--active-' + escapeHtml(trimNonEmpty(pb.card_active_style) || 'accent') + (isSelected ? ' is-active' : '') + '">';
			html += '<input type="radio" class="mp-cc-payment-card__radio' + (errPayment ? ' is-invalid' : '') + '" name="mp_cc_payment_gateway" value="' + escapeHtml(g.id) + '" data-payment-gateway="1"' + (isSelected ? ' checked' : '') + ' />';
			html += '<span class="mp-cc-payment-card__title">' + escapeHtml(g.title) + '</span>';
			if (showDescription && g.description) {
				html += '<span class="mp-cc-payment-card__desc">' + escapeHtml(g.description) + '</span>';
			}
			html += '</label>';
		}
		html += '</div>';
		if (paymentState === 'syncing') {
			html += '<p class="mp-cc-payment__state mp-cc-payment__state--loading">' + escapeHtml(trimNonEmpty(messages.loading) || getUiText('step_4.payment_loading', 'Сохраняем выбранный способ оплаты...')) + '</p>';
		} else if (paymentState === 'success') {
			html += '<p class="mp-cc-payment__state mp-cc-payment__state--success">' + escapeHtml(trimNonEmpty(messages.success) || getUiText('step_4.payment_success', 'Способ оплаты обновлён.')) + '</p>';
		} else if (paymentState === 'error') {
			html += '<p class="mp-cc-payment__state mp-cc-payment__state--error">' + escapeHtml(trimNonEmpty(messages.error) || getUiText('step_4.payment_error_switch', 'Не удалось переключить способ оплаты.')) + '</p>';
		}
		if (errPayment) {
			html += '<p class="mp-cc-field-error" role="alert">' + escapeHtml(trimNonEmpty(pb.error_message) || getUiText('step_4.payment_error_required', 'Выберите способ оплаты.')) + '</p>';
		}
		html += '</section>';
		return html;
	}

	function buildAddressBlockHtml(state) {
		ensureContactDefaults(state);
		var contact = state.frontendStore.form.contact || {};
		var vis = contact.__address_visibility;
		if (vis && vis.hide_address_fields) {
			return '';
		}
		var cfg = getStepFourConfig();
		var ab = cfg.address_block || {};
		var geo = getAddressGeoMerged();
		var countries = getSortedCountryCodes(geo);
		if (!countries.length) {
			return '';
		}
		var title = trimNonEmpty(ab.title) || getUiText('step_4.address_block_title', 'Адрес доставки');
		var intro = trimNonEmpty(ab.intro) || getUiText('step_4.address_block_intro', '');
		var order = Array.isArray(ab.subfields_order)
			? ab.subfields_order
			: ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'];
		var pi;
		var hasAnyAddressField = false;
		for (pi = 0; pi < order.length; pi++) {
			if (shouldRenderAddressSubfield(order[pi], contact)) {
				hasAnyAddressField = true;
				break;
			}
		}
		if (!hasAnyAddressField) {
			return '';
		}
		var html = '';
		html += '<section class="mp-cc-address" aria-labelledby="mp-cc-address-title">';
		html += '<header class="mp-cc-address__header">';
		html += '<h3 class="mp-cc-address__title" id="mp-cc-address-title">' + escapeHtml(title) + '</h3>';
		if (intro) {
			html += '<p class="mp-cc-address__intro" id="mp-cc-address-intro">' + escapeHtml(intro) + '</p>';
		}
		if (cfg.geo_preview && cfg.geo_preview.enabled) {
			var regionsCount = getRegionsForCountry(geo, String(contact.country || '')).length;
			var settlementsCount = getSettlementsForRegion(geo, String(contact.country || ''), String(contact.state || '')).length;
			html += '<p class="mp-cc-address__intro"><strong>Geo debug:</strong> country=' + escapeHtml(String(contact.country || '')) + ', region=' + escapeHtml(String(contact.state || '')) + ', regions=' + escapeHtml(String(regionsCount)) + ', settlements=' + escapeHtml(String(settlementsCount)) + '</p>';
		}
		html += '</header>';
		html += '<div class="mp-cc-address__grid">';
		var idx;
		for (idx = 0; idx < order.length; idx++) {
			var key = order[idx];
			if (!shouldRenderAddressSubfield(key, contact)) {
				continue;
			}
			var err = getContactFieldError(state, key);
			if (key === 'country') {
				html += '<div class="mp-cc-address__field mp-cc-address__field--country">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-country">' + escapeHtml(getAddressLabel('country')) + '</label>';
				html += '<select id="mp-cc-address-country" class="mp-cc-select' + (err ? ' is-invalid' : '') + '" data-address-country="1" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '>';
				var ci;
				for (ci = 0; ci < countries.length; ci++) {
					var co = countries[ci];
					var sel = co.iso === String(contact.country || '');
					html += '<option value="' + escapeHtml(co.iso) + '"' + (sel ? ' selected' : '') + '>' + escapeHtml(co.label) + '</option>';
				}
				html += '</select>';
				if (err) {
					html += '<p class="mp-cc-field-error" id="mp-cc-address-country-err" role="alert">' + escapeHtml(trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.')) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (key === 'state') {
				var regions = getRegionsForCountry(geo, contact.country);
				html += '<div class="mp-cc-address__field mp-cc-address__field--region">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-region">' + escapeHtml(getAddressLabel('state')) + '</label>';
				html += '<select id="mp-cc-address-region" class="mp-cc-select' + (err ? ' is-invalid' : '') + '" data-address-region="1" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '>';
				html += '<option value="">' + escapeHtml(getUiText('step_4.address_region_placeholder', 'Выберите регион')) + '</option>';
				var ri;
				for (ri = 0; ri < regions.length; ri++) {
					var reg = regions[ri];
					var sr = reg.id === String(contact.state || '');
					html += '<option value="' + escapeHtml(reg.id) + '"' + (sr ? ' selected' : '') + '>' + escapeHtml(reg.label) + '</option>';
				}
				html += '</select>';
				if (err) {
					var regionMsg = err === 'region'
						? (trimNonEmpty(vm.address_region) || getUiText('step_4.address_error_region', 'Выберите корректный регион.'))
						: (trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.'));
					html += '<p class="mp-cc-field-error" id="mp-cc-address-region-err" role="alert">' + escapeHtml(regionMsg) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (key === 'city') {
				var settlements = getSettlementsForRegion(geo, contact.country, contact.state);
				html += '<div class="mp-cc-address__field mp-cc-address__field--city">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-city">' + escapeHtml(getAddressLabel('city')) + '</label>';
				if (settlements.length) {
					html += '<select id="mp-cc-address-city" class="mp-cc-select' + (err ? ' is-invalid' : '') + '" data-address-city="1" aria-required="true"';
					html += err ? ' aria-invalid="true"' : '';
					html += '>';
					html += '<option value="">' + escapeHtml(getUiText('step_4.address_city_placeholder', 'Выберите населённый пункт')) + '</option>';
					var si;
					for (si = 0; si < settlements.length; si++) {
						var st = settlements[si];
						var cs = st === String(contact.city || '');
						html += '<option value="' + escapeHtml(st) + '"' + (cs ? ' selected' : '') + '>' + escapeHtml(st) + '</option>';
					}
					html += '</select>';
				} else {
					html += '<input type="text" class="mp-cc-input' + (err ? ' is-invalid' : '') + '" id="mp-cc-address-city" name="city" autocomplete="address-level2" ';
					html += 'value="' + escapeHtml(String(contact.city || '')) + '" ';
					html += 'data-contact-field="city" aria-required="true"';
					html += err ? ' aria-invalid="true"' : '';
					html += '/>';
				}
				if (err) {
					var cityMsg = err === 'city'
						? (trimNonEmpty(vm.address_city) || getUiText('step_4.address_error_city', 'Выберите населённый пункт из списка.'))
						: (trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.'));
					html += '<p class="mp-cc-field-error" id="mp-cc-address-city-err" role="alert">' + escapeHtml(cityMsg) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (key === 'address_1') {
				html += '<div class="mp-cc-address__field mp-cc-address__field--line1">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-line1">' + escapeHtml(getAddressLabel('address_1')) + '</label>';
				html += '<input type="text" class="mp-cc-input' + (err ? ' is-invalid' : '') + '" id="mp-cc-address-line1" name="address_1" autocomplete="address-line1" ';
				html += 'value="' + escapeHtml(String(contact.address_1 || '')) + '" ';
				html += 'data-contact-field="address_1" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '/>';
				if (err) {
					html += '<p class="mp-cc-field-error" id="mp-cc-address-line1-err" role="alert">' + escapeHtml(trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.')) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (key === 'address_2') {
				html += '<div class="mp-cc-address__field mp-cc-address__field--line2">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-line2">' + escapeHtml(getAddressLabel('address_2')) + '</label>';
				html += '<input type="text" class="mp-cc-input" id="mp-cc-address-line2" name="address_2" autocomplete="address-line2" ';
				html += 'value="' + escapeHtml(String(contact.address_2 || '')) + '" ';
				html += 'data-contact-field="address_2"';
				html += '/>';
				html += '</div>';
				continue;
			}
			if (key === 'postcode') {
				var pcMsg = err === 'postcode'
					? (trimNonEmpty(vm.address_postcode) || getUiText('step_4.address_error_postcode', 'Слишком длинный индекс.'))
					: (trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.'));
				html += '<div class="mp-cc-address__field mp-cc-address__field--postcode">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-postcode">' + escapeHtml(getAddressLabel('postcode')) + '</label>';
				html += '<input type="text" class="mp-cc-input' + (err ? ' is-invalid' : '') + '" id="mp-cc-address-postcode" name="postcode" autocomplete="postal-code" inputmode="text" ';
				html += 'value="' + escapeHtml(String(contact.postcode || '')) + '" ';
				html += 'data-contact-field="postcode" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '/>';
				if (err) {
					html += '<p class="mp-cc-field-error" id="mp-cc-address-postcode-err" role="alert">' + escapeHtml(pcMsg) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (field === 'order_notes') {
				var notesValue = String(contact.order_notes || '');
				if (notesValue.length > notesCfg.maxLength) {
					notesValue = notesValue.slice(0, notesCfg.maxLength);
				}
				var remain = notesCfg.maxLength - notesValue.length;
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-2">';
				html += '<label class="mp-cc-field-label" for="mp-cc-contact-order-notes">' + escapeHtml(getContactLabel('order_notes')) + '</label>';
				html += '<textarea class="mp-cc-input' + (errNotes ? ' is-invalid' : '') + '" id="mp-cc-contact-order-notes" name="order_notes" rows="4"';
				html += ' data-contact-field="order_notes" maxlength="' + escapeHtml(String(notesCfg.maxLength)) + '"';
				var pNotes = getContactPlaceholder('order_notes');
				if (pNotes) { html += ' placeholder="' + escapeHtml(pNotes) + '"'; }
				html += errNotes ? ' aria-invalid="true"' : '';
				html += '>';
				html += escapeHtml(notesValue);
				html += '</textarea>';
				if (trimNonEmpty(getContactHint('order_notes'))) {
					html += '<p class="mp-cc-field-hint">' + escapeHtml(getContactHint('order_notes')) + '</p>';
				}
				if (notesCfg.showCounter) {
					html += '<p class="mp-cc-field-hint" data-order-notes-counter="1">' + escapeHtml('Осталось символов: ' + String(remain)) + '</p>';
				}
				if (errNotes) {
					html += '<p class="mp-cc-field-error" id="mp-cc-contact-order-notes-err" role="alert">' + escapeHtml(notesCfg.lengthErrorText) + '</p>';
				}
				html += '</div>';
			}
		}
		html += '</div>';
		html += '</section>';
		return html;
	}

	function buildDiscountToolsHtml(state) {
		ensureDiscountDefaults(state);
		var cfg = getStepFourConfig();
		var layout = cfg.discount_layout && typeof cfg.discount_layout === 'object' ? cfg.discount_layout : {};
		var placement = trimNonEmpty(layout.placement) || 'step_4';
		var separateStepEnabled = layout.separate_step_enabled === true;
		var order = Array.isArray(layout.order) ? layout.order : ['coupon', 'gift_card'];
		if (placement !== 'step_4' && separateStepEnabled) {
			return '';
		}
		var html = '<section class="mp-cc-discount-tools" data-coupon-step-ready="' + (separateStepEnabled ? '1' : '0') + '" data-coupon-placement="' + escapeHtml(placement) + '">';
		for (var i = 0; i < order.length; i += 1) {
			var key = String(order[i] || '');
			if (key === 'coupon') {
				html += buildCouponBlockHtml(state);
			} else if (key === 'gift_card') {
				html += buildGiftCardBlockHtml(state);
			}
		}
		html += '</section>';
		return html;
	}

	function buildCouponBlockHtml(state) {
		var copy = getCouponCopy();
		var cfg = getStepFourConfig();
		var styles = cfg.discount_block_styles || {};
		var rt = state.frontendStore && state.frontendStore.discounts && state.frontendStore.discounts.coupon_runtime
			? state.frontendStore.discounts.coupon_runtime
			: { code: '', state: 'empty', message: '' };
		var appliedCoupons = state.frontendStore && state.frontendStore.discounts && Array.isArray(state.frontendStore.discounts.coupons)
			? state.frontendStore.discounts.coupons
			: [];
		var code = String(rt.code || '');
		var runtimeState = String(rt.state || 'empty');
		var msg = trimNonEmpty(rt.message);
		var html = '';
		var stateClass = runtimeState === 'success' ? String(styles.state_success || 'success') : (runtimeState === 'error' ? String(styles.state_error || 'error') : String(styles.state_empty || 'default'));
		html += '<article class="mp-cc-coupon mp-cc-coupon--' + escapeHtml(stateClass) + '" data-coupon-block="1">';
		html += '<h4 class="mp-cc-coupon__title">' + escapeHtml(copy.title) + '</h4>';
		if (copy.intro) {
			html += '<p class="mp-cc-coupon__intro">' + escapeHtml(copy.intro) + '</p>';
		}
		html += '<div class="mp-cc-coupon__row">';
		html += '<label class="mp-cc-field-label" for="mp-cc-coupon-code">' + escapeHtml(copy.inputLabel) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (runtimeState === 'error' ? ' is-invalid' : '') + '" id="mp-cc-coupon-code" data-coupon-code="1" value="' + escapeHtml(code) + '" placeholder="' + escapeHtml(copy.placeholder) + '" />';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next mp-cc-coupon__apply" data-coupon-apply="1">' + escapeHtml(copy.applyLabel) + '</button>';
		html += '</div>';
		if (msg) {
			html += '<p class="mp-cc-field-hint' + (runtimeState === 'error' ? ' mp-cc-field-error' : '') + '" data-coupon-message="1">' + escapeHtml(msg) + '</p>';
		}
		if (appliedCoupons.length) {
			html += '<div class="mp-cc-coupon__applied" data-coupon-list="1">';
			for (var i = 0; i < appliedCoupons.length; i += 1) {
				var cp = String(appliedCoupons[i] || '');
				if (!cp) {
					continue;
				}
				html += '<span class="mp-cc-coupon__chip">';
				html += '<span>' + escapeHtml(cp) + '</span>';
				html += '</span>';
			}
			html += '</div>';
		}
		html += '</article>';
		return html;
	}

function buildGiftCardBlockHtml(state) {
		var copy = getGiftCardCopy();
		var cfg = getStepFourConfig();
		var styles = cfg.discount_block_styles || {};
		var rt = state.frontendStore && state.frontendStore.discounts && state.frontendStore.discounts.gift_card_runtime
			? state.frontendStore.discounts.gift_card_runtime
			: { code: '', state: 'empty', message: '' };
		var codes = state.frontendStore && state.frontendStore.discounts && Array.isArray(state.frontendStore.discounts.gift_card)
			? state.frontendStore.discounts.gift_card
			: [];
		var html = '';
		var giftStateClass = rt.state === 'success' ? String(styles.state_success || 'success') : (rt.state === 'error' ? String(styles.state_error || 'error') : String(styles.state_empty || 'default'));
		html += '<article class="mp-cc-coupon mp-cc-coupon--gift mp-cc-coupon--' + escapeHtml(giftStateClass) + '" data-gift-card-block="1">';
		html += '<h4 class="mp-cc-coupon__title">' + escapeHtml(copy.title) + '</h4>';
		if (copy.intro) {
			html += '<p class="mp-cc-coupon__intro">' + escapeHtml(copy.intro) + '</p>';
		}
		html += '<div class="mp-cc-coupon__row">';
		html += '<label class="mp-cc-field-label" for="mp-cc-gift-card-code">' + escapeHtml(copy.inputLabel) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (rt.state === 'error' ? ' is-invalid' : '') + '" id="mp-cc-gift-card-code" data-gift-card-code="1" value="' + escapeHtml(String(rt.code || '')) + '" placeholder="' + escapeHtml(copy.placeholder) + '" />';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next mp-cc-coupon__apply" data-gift-card-apply="1">' + escapeHtml(copy.applyLabel) + '</button>';
		html += '</div>';
		if (trimNonEmpty(rt.message)) {
			html += '<p class="mp-cc-field-hint' + (rt.state === 'error' ? ' mp-cc-field-error' : '') + '">' + escapeHtml(String(rt.message)) + '</p>';
		}
		if (codes.length) {
			html += '<div class="mp-cc-coupon__applied">';
			for (var i = 0; i < codes.length; i += 1) {
				var gc = String(codes[i] || '');
				if (!gc) {
					continue;
				}
				html += '<span class="mp-cc-coupon__chip"><span>' + escapeHtml(gc) + '</span></span>';
			}
			html += '</div>';
		}
		html += '</article>';
		return html;
	}

	function getStepThreeTitle() {
		var config = getStepThreeConfig();
		var title = config && config.copy && config.copy.title ? String(config.copy.title) : '';
		return title || getUiText('step_3.title', 'Выберите дату получения');
	}

	function getStepThreeHelperByScenario(scenario) {
		var config = getStepThreeConfig();
		var map = config && config.copy && config.copy.helper_by_scenario && typeof config.copy.helper_by_scenario === 'object'
			? config.copy.helper_by_scenario
			: {};
		var value = map[scenario] ? String(map[scenario]) : '';
		return value || getUiText('step_3.date_helper', 'Выберите дату из доступных слотов.');
	}

	function getStepThreeErrorCopy(key, fallback) {
		var config = getStepThreeConfig();
		var errors = config && config.copy && config.copy.errors && typeof config.copy.errors === 'object'
			? config.copy.errors
			: {};
		var value = errors[key] ? String(errors[key]) : '';
		return value || fallback;
	}

	function getStepThreeCalendarStyle() {
		var config = getStepThreeConfig();
		var style = config && config.calendar_style && typeof config.calendar_style === 'object' ? config.calendar_style : {};
		return {
			density: String(style.density || 'comfortable'),
			dayShape: String(style.day_shape || 'rounded'),
			highlightStyle: String(style.highlight_style || 'accent'),
			showWeekendTint: style.show_weekend_tint !== false
		};
	}

	function trimNonEmpty(value) {
		var s = String(value || '').trim();
		return s ? s : '';
	}

	function getConditionsCopyRoot() {
		var cfg = getStepThreeConfig();
		return cfg && cfg.conditions_copy && typeof cfg.conditions_copy === 'object' ? cfg.conditions_copy : {};
	}

	function getConditionsBlockForScenario(scenario) {
		var root = getConditionsCopyRoot();
		var key = String(scenario || '');
		if (!key || !root[key] || typeof root[key] !== 'object') {
			return {};
		}
		return root[key];
	}

	function getPickupPointForConditions(state) {
		var scenarioData = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenarioData || {}) : {};
		var point = scenarioData.pickup_point && typeof scenarioData.pickup_point === 'object' ? scenarioData.pickup_point : null;
		if (!point) {
			point = getPickupPointById('');
		}
		return point;
	}

	function buildConditionsReceiptPlainText(state) {
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || '');
		var root = getConditionsCopyRoot();
		var introMap = root.intro_by_scenario && typeof root.intro_by_scenario === 'object' ? root.intro_by_scenario : {};
		var lines = [];
		var intro = trimNonEmpty(introMap[scenario]) || getUiText('step_3.conditions_intro', 'Перед продолжением проверьте правила для выбранного способа получения.');
		if (intro) {
			lines.push(intro);
		}
		var block = getConditionsBlockForScenario(scenario);
		var i;
		if (scenario === 'krasnoyarsk_delivery') {
			var krBody = trimNonEmpty(block.body) || getUiText('step_3.krasnoyarsk_conditions', 'Доставка выполняется в пределах города в выбранную дату. Курьер связывается заранее для подтверждения интервала.');
			var krDay = trimNonEmpty(block.delivery_within_day) || getUiText('step_3.krasnoyarsk_delivery_day', 'Доставка в течение дня в выбранную дату. Интервал уточняется у курьера.');
			lines.push(krBody, krDay);
		} else if (scenario === 'other_city_delivery') {
			var ocBody = trimNonEmpty(block.body) || getUiText('step_3.other_city_conditions', 'Срок и стоимость уточняются после подтверждения заказа. Отправка выполняется через транспортного партнера по согласованным данным.');
			var ocLog = trimNonEmpty(block.logistics_note) || getUiText('step_3.other_city_logistics', 'Отправка выполняется через логистическую компанию после комплектации и согласования реквизитов.');
			lines.push(ocBody, ocLog);
		} else {
			var puBody = trimNonEmpty(block.body) || getUiText('step_3.pickup_conditions', 'Заказ выдается в точке самовывоза после подтверждения готовности. Пожалуйста, дождитесь уведомления перед визитом.');
			lines.push(puBody);
			var officeTitle = trimNonEmpty(block.office_block_title) || getUiText('step_3.pickup_office_block_title', 'Офис и график работы');
			lines.push(officeTitle);
			var point = getPickupPointForConditions(state);
			if (point && point.title) {
				lines.push(String(point.title));
			}
			var address = trimNonEmpty(block.office_address);
			if (!address && point && point.address) {
				address = String(point.address);
			}
			if (address) {
				lines.push(address);
			}
			var desc = trimNonEmpty(block.office_description);
			if (!desc && point && point.description) {
				desc = String(point.description);
			}
			if (!desc) {
				desc = getUiText('step_3.pickup_office_default', 'Выдача заказа в офисе самовывоза после уведомления о готовности.');
			}
			lines.push(desc);
			var plain = trimNonEmpty(block.office_hours_plain);
			var hoursLabel = getUiText('step_3.pickup_hours_label', 'Часы выдачи');
			if (plain) {
				var scheduleLines = plain.split(/\r?\n/).map(function (ln) {
					return trimNonEmpty(ln);
				}).filter(Boolean);
				lines.push(hoursLabel + ':\n' + scheduleLines.join('\n'));
			} else {
				var hours = Array.isArray(block.office_hours) ? block.office_hours : [];
				var hoursClean = [];
				for (i = 0; i < hours.length; i += 1) {
					var slot = trimNonEmpty(hours[i]);
					if (slot) {
						hoursClean.push(slot);
					}
				}
				if (!hoursClean.length) {
					hoursClean = ['10:00–13:00', '13:00–17:00', '17:00–20:00'];
				}
				lines.push(hoursLabel + ': ' + hoursClean.join(', '));
			}
			var critical = trimNonEmpty(block.critical_notice);
			if (critical) {
				lines.push(getUiText('step_3.critical_notice_prefix', 'Важно:') + ' ' + critical);
			}
			var helper = trimNonEmpty(block.convenience_helper) || getUiText('step_3.pickup_convenience_helper', 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.');
			lines.push(helper);
		}
		var filtered = [];
		for (i = 0; i < lines.length; i += 1) {
			var line = trimNonEmpty(lines[i]);
			if (line) {
				filtered.push(lines[i]);
			}
		}
		return filtered.join('\n\n');
	}

	function formatConditionsReceiptHtmlFromPlain(text) {
		if (!trimNonEmpty(text)) {
			return '';
		}
		var paras = String(text).split(/\n\n+/);
		var out = '';
		var pi;
		for (pi = 0; pi < paras.length; pi += 1) {
			var p = trimNonEmpty(paras[pi]);
			if (!p) {
				continue;
			}
			var inner = escapeHtml(p).replace(/\n/g, '<br />');
			out += '<p class="mp-cc-summary-card__conditions-para">' + inner + '</p>';
		}
		return out;
	}

	function getConditionsIntroForScenario(scenario) {
		var root = getConditionsCopyRoot();
		var map = root.intro_by_scenario && typeof root.intro_by_scenario === 'object' ? root.intro_by_scenario : {};
		var custom = trimNonEmpty(map[scenario]);
		if (custom) {
			return custom;
		}
		return getUiText('step_3.conditions_intro', 'Перед продолжением проверьте правила для выбранного способа получения.');
	}

	function getConditionsSecondaryNotesList() {
		var root = getConditionsCopyRoot();
		var fromConfig = Array.isArray(root.secondary_notes) ? root.secondary_notes : [];
		var filtered = [];
		var i;
		for (i = 0; i < fromConfig.length; i += 1) {
			var line = trimNonEmpty(fromConfig[i]);
			if (line) {
				filtered.push(line);
			}
		}
		if (filtered.length) {
			return filtered;
		}
		return [
			getUiText('step_3.secondary_note_1', 'Проверяйте корректность телефона: статус заказа приходит в уведомления.'),
			getUiText('step_3.secondary_note_2', 'При изменении сценария условия и доступность дат обновляются автоматически.'),
			getUiText('step_3.secondary_note_3', 'Для вопросов по срокам и логистике используйте контакты поддержки магазина.')
		];
	}

	function getConditionsCardTitle(scenario, block, rules) {
		var custom = trimNonEmpty(block.title);
		if (custom) {
			return custom;
		}
		var cr = rules.copy_rules && typeof rules.copy_rules === 'object' ? rules.copy_rules : {};
		if (trimNonEmpty(cr.conditions_title)) {
			return trimNonEmpty(cr.conditions_title);
		}
		if (trimNonEmpty(rules.label)) {
			return trimNonEmpty(rules.label);
		}
		if (scenario === 'krasnoyarsk_delivery') {
			return getUiText('step_3.krasnoyarsk_title', 'Доставка по Красноярску');
		}
		if (scenario === 'other_city_delivery') {
			return getUiText('step_3.other_city_title', 'Доставка в другой город');
		}
		return getUiText('step_3.pickup_title', 'Самовывоз');
	}

	function getConditionsStepPanelTitle(state) {
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || '');
		var block = getConditionsBlockForScenario(scenario);
		var rules = getScenarioRulesById(scenario);
		return getConditionsCardTitle(scenario, block, rules);
	}

	function getPickupPointForConditions(state) {
		var scenarioData = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenarioData || {}) : {};
		var point = scenarioData.pickup_point && typeof scenarioData.pickup_point === 'object' ? scenarioData.pickup_point : null;
		if (!point) {
			point = getPickupPointById('');
		}
		return point;
	}

	function buildPickupOfficeBlockHtml(state, block) {
		var point = getPickupPointForConditions(state);
		var officeTitle = trimNonEmpty(block.office_block_title) || getUiText('step_3.pickup_office_block_title', 'Офис и график работы');
		var address = trimNonEmpty(block.office_address);
		if (!address && point && point.address) {
			address = String(point.address);
		}
		var pointName = point && point.title ? String(point.title) : '';
		var desc = trimNonEmpty(block.office_description);
		if (!desc && point && point.description) {
			desc = String(point.description);
		}
		if (!desc) {
			desc = getUiText('step_3.pickup_office_default', 'Выдача заказа в офисе самовывоза после уведомления о готовности.');
		}
		var plain = trimNonEmpty(block.office_hours_plain);
		var hours = Array.isArray(block.office_hours) ? block.office_hours : [];
		var hoursClean = [];
		var i;
		for (i = 0; i < hours.length; i += 1) {
			var slot = trimNonEmpty(hours[i]);
			if (slot) {
				hoursClean.push(slot);
			}
		}
		if (!plain && !hoursClean.length) {
			hoursClean = ['10:00–13:00', '13:00–17:00', '17:00–20:00'];
		}
		var helper = trimNonEmpty(block.convenience_helper) || getUiText('step_3.pickup_convenience_helper', 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.');
		var critical = trimNonEmpty(block.critical_notice);
		var showMulti = block.show_multi_office_slot !== false;

		var html = '';
		html += '<div class="mp-cc-pickup-office">';
		html += '<div class="mp-cc-pickup-office__head">';
		html += '<h3 class="mp-cc-pickup-office__heading">' + escapeHtml(officeTitle) + '</h3>';
		html += '</div>';
		if (pointName) {
			html += '<p class="mp-cc-pickup-office__point-name">' + escapeHtml(pointName) + '</p>';
		}
		if (address) {
			html += '<p class="mp-cc-pickup-office__address">' + escapeHtml(address) + '</p>';
		}
		html += '<p class="mp-cc-pickup-office__description">' + escapeHtml(desc) + '</p>';
		html += '<div class="mp-cc-pickup-office__hours" aria-label="' + escapeHtml(getUiText('step_3.pickup_hours_label', 'Часы выдачи')) + '">';
		if (plain) {
			html += '<div class="mp-cc-pickup-office__schedule mp-cc-pickup-office__schedule--plain">';
			var lines = plain.split(/\r?\n/);
			var firstLine = true;
			for (i = 0; i < lines.length; i += 1) {
				var line = trimNonEmpty(lines[i]);
				if (!line) {
					continue;
				}
				html += '<p class="mp-cc-pickup-office__line' + (firstLine ? ' mp-cc-pickup-office__line--key' : '') + '">' + escapeHtml(line) + '</p>';
				firstLine = false;
			}
			html += '</div>';
		} else {
			html += '<div class="mp-cc-pickup-office__schedule mp-cc-pickup-office__schedule--chips">';
			html += '<div class="mp-cc-pickup-office__chips" role="list">';
			for (i = 0; i < hoursClean.length; i += 1) {
				html += '<span class="mp-cc-pickup-office__chip' + (i === 0 ? ' mp-cc-pickup-office__chip--key' : '') + '" role="listitem">' + escapeHtml(hoursClean[i]) + '</span>';
			}
			html += '</div>';
			html += '</div>';
		}
		html += '</div>';
		if (critical) {
			html += '<div class="mp-cc-pickup-office__critical" role="note">';
			html += '<span class="mp-cc-pickup-office__critical-icon" aria-hidden="true">!</span>';
			html += '<p class="mp-cc-pickup-office__critical-text">' + escapeHtml(critical) + '</p>';
			html += '</div>';
		}
		html += '<p class="mp-cc-pickup-office__helper">' + escapeHtml(helper) + '</p>';
		if (showMulti) {
			html += '<div class="mp-cc-pickup-office__multi-slot" data-mp-cc-multi-office="1">';
			html += '<span class="mp-cc-pickup-office__multi-slot-label">' + escapeHtml(getUiText('step_3.pickup_multi_office_hint', 'Дополнительные точки самовывоза будут отображаться здесь при подключении.')) + '</span>';
			html += '</div>';
		}
		html += '</div>';
		return html;
	}

	function buildConditionsStepHtml(state) {
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || '');
		var dateState = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
			? state.frontendStore.fulfillment.date
			: {};
		var isConfirmed = Boolean(dateState.conditions_confirmed);
		var hasError = Boolean(state.frontendStore.form && state.frontendStore.form.errors && state.frontendStore.form.errors.conditions_unconfirmed);
		var block = getConditionsBlockForScenario(scenario);
		var scenarioRules = getScenarioRulesById(scenario);
		var cardTitle = getConditionsCardTitle(scenario, block, scenarioRules);
		var intro = getConditionsIntroForScenario(scenario);
		var notes = getConditionsSecondaryNotesList();
		var html = '';
		var i;

		html += '<section class="mp-cc-conditions-step" data-mp-cc-conditions-scenario="' + escapeHtml(scenario) + '" aria-label="' + escapeHtml(cardTitle) + '">';
		html += '<p class="mp-cc-conditions-step__intro">' + escapeHtml(intro) + '</p>';
		html += '<div class="mp-cc-conditions-step__grid mp-cc-conditions-step__grid--single">';
		html += '<article class="mp-cc-conditions-card is-active" data-conditions-scenario="' + escapeHtml(scenario) + '">';
		if (scenario === 'krasnoyarsk_delivery') {
			var krBody = trimNonEmpty(block.body) || getUiText('step_3.krasnoyarsk_conditions', 'Доставка выполняется в пределах города в выбранную дату. Курьер связывается заранее для подтверждения интервала.');
			var krDay = trimNonEmpty(block.delivery_within_day) || getUiText('step_3.krasnoyarsk_delivery_day', 'Доставка в течение дня в выбранную дату. Интервал уточняется у курьера.');
			html += '<p class="mp-cc-conditions-card__text">' + escapeHtml(krBody) + '</p>';
			html += '<p class="mp-cc-conditions-card__text mp-cc-conditions-card__accent">' + escapeHtml(krDay) + '</p>';
		} else if (scenario === 'other_city_delivery') {
			var ocBody = trimNonEmpty(block.body) || getUiText('step_3.other_city_conditions', 'Срок и стоимость уточняются после подтверждения заказа. Отправка выполняется через транспортного партнера по согласованным данным.');
			var ocLog = trimNonEmpty(block.logistics_note) || getUiText('step_3.other_city_logistics', 'Отправка выполняется через логистическую компанию после комплектации и согласования реквизитов.');
			html += '<p class="mp-cc-conditions-card__text">' + escapeHtml(ocBody) + '</p>';
			html += '<p class="mp-cc-conditions-card__text mp-cc-conditions-card__accent">' + escapeHtml(ocLog) + '</p>';
		} else {
			var puBody = trimNonEmpty(block.body) || getUiText('step_3.pickup_conditions', 'Заказ выдается в точке самовывоза после подтверждения готовности. Пожалуйста, дождитесь уведомления перед визитом.');
			html += '<p class="mp-cc-conditions-card__text">' + escapeHtml(puBody) + '</p>';
			html += buildPickupOfficeBlockHtml(state, block);
		}
		html += '</article>';
		html += '</div>';
		html += '<div class="mp-cc-conditions-step__confirm' + (hasError ? ' is-error' : '') + '">';
		html += '<label class="mp-cc-conditions-step__confirm-label">';
		html += '<input type="checkbox" data-conditions-confirm="1" ' + (isConfirmed ? 'checked' : '') + ' aria-invalid="' + (hasError ? 'true' : 'false') + '" aria-required="true" />';
		html += '<span>' + escapeHtml(getUiText('step_3.confirm_checkbox', 'Я ознакомился с условиями')) + '</span>';
		html += '</label>';
		if (hasError) {
			html += '<p class="mp-cc-conditions-step__error" role="alert">' + escapeHtml(getUiText('step_3.unconfirmed_error', 'Подтвердите ознакомление с условиями, чтобы продолжить.')) + '</p>';
		}
		html += '</div>';
		html += '<aside class="mp-cc-conditions-step__notes" aria-label="' + escapeHtml(getUiText('step_3.notes_title', 'Важные замечания')) + '">';
		html += '<h5 class="mp-cc-conditions-step__notes-title">' + escapeHtml(getUiText('step_3.notes_title', 'Важные замечания')) + '</h5>';
		html += '<ul class="mp-cc-conditions-step__notes-list">';
		for (i = 0; i < notes.length; i += 1) {
			if (!notes[i]) {
				continue;
			}
			html += '<li>' + escapeHtml(notes[i]) + '</li>';
		}
		html += '</ul>';
		html += '</aside>';
		html += '</section>';

		return html;
	}

	function getPickupPointById(pointId) {
		var pickup = getPickupConfig();
		var points = pickup.points || [];
		var safeId = String(pointId || '');
		var i;
		for (i = 0; i < points.length; i += 1) {
			if (String(points[i].id || '') === safeId) {
				return points[i];
			}
		}
		return points.length ? points[0] : null;
	}

	function normalizeScenarioId(scenarioId) {
		var map = getScenarioMap();
		var scenarios = map.scenarios || {};
		var key = String(scenarioId || '');
		if (key && Object.prototype.hasOwnProperty.call(scenarios, key)) {
			return key;
		}
		var config = getScenarioUiConfig();
		var fallback = String(config.default_scenario || 'pickup');
		return (fallback && Object.prototype.hasOwnProperty.call(scenarios, fallback)) ? fallback : 'pickup';
	}

	function getScenarioRulesById(scenarioId) {
		var map = getScenarioMap();
		var rulesMap = map.rules || {};
		var normalized = normalizeScenarioId(scenarioId);
		return rulesMap[normalized] && typeof rulesMap[normalized] === 'object' ? rulesMap[normalized] : {};
	}

	function startOfDay(date) {
		var value = new Date(date.getTime());
		value.setHours(0, 0, 0, 0);
		return value;
	}

	function addDays(date, days) {
		var value = new Date(date.getTime());
		value.setDate(value.getDate() + Number(days || 0));
		return value;
	}

	function isoDate(date) {
		return [
			date.getFullYear(),
			String(date.getMonth() + 1).padStart(2, '0'),
			String(date.getDate()).padStart(2, '0')
		].join('-');
	}

	function parseIsoDate(value) {
		var raw = String(value || '');
		var matched = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
		if (!matched) {
			return null;
		}
		var year = Number(matched[1]);
		var month = Number(matched[2]) - 1;
		var day = Number(matched[3]);
		var date = new Date(year, month, day);
		if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
			return null;
		}
		return startOfDay(date);
	}

	function formatIsoDateForUi(value) {
		var date = parseIsoDate(value);
		if (!date) {
			return String(value || '');
		}
		return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
	}

	function monthKeyFromDate(date) {
		return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0')].join('-');
	}

	function parseMonthKey(value) {
		var raw = String(value || '');
		var matched = raw.match(/^(\d{4})-(\d{2})$/);
		if (!matched) {
			return null;
		}
		var year = Number(matched[1]);
		var month = Number(matched[2]) - 1;
		if (!Number.isFinite(year) || !Number.isFinite(month) || month < 0 || month > 11) {
			return null;
		}
		return new Date(year, month, 1);
	}

	function buildDateCalendarModel(state) {
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || '');
		var rules = getScenarioRulesById(scenario);
		var dateRules = rules.date_rules && typeof rules.date_rules === 'object' ? rules.date_rules : {};
		var leadTime = Math.max(1, Number(dateRules.lead_time_days || 1));
		var maxDays = Math.max(1, Number(dateRules.max_days_ahead || 14));
		var allowWeekends = dateRules.allow_weekends !== false;
		var today = startOfDay(new Date());
		var minByRules = parseIsoDate(dateRules.min_date || '');
		var maxByRules = parseIsoDate(dateRules.max_date || '');
		var earliest = minByRules || addDays(today, leadTime);
		var latest = maxByRules || addDays(today, maxDays);
		if (earliest < addDays(today, 1)) {
			earliest = addDays(today, 1);
		}
		if (latest < earliest) {
			latest = earliest;
		}
		var allowedWeekdays = Array.isArray(dateRules.allowed_weekdays) ? dateRules.allowed_weekdays.map(function (value) {
			return Number(value);
		}).filter(function (value) {
			return Number.isFinite(value) && value >= 0 && value <= 6;
		}) : [];
		if (!allowedWeekdays.length) {
			allowedWeekdays = allowWeekends ? [0, 1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5];
		}
		var blockedDates = Array.isArray(dateRules.blocked_dates) ? dateRules.blocked_dates : [];
		var blockedMap = {};
		for (var b = 0; b < blockedDates.length; b += 1) {
			blockedMap[String(blockedDates[b] || '')] = true;
		}
		var availableDates = Array.isArray(dateRules.available_dates) ? dateRules.available_dates : [];
		var availableMap = {};
		for (var a = 0; a < availableDates.length; a += 1) {
			availableMap[String(availableDates[a] || '')] = true;
		}
		var hasServerAvailability = availableDates.length > 0;
		var firstAvailable = null;
		var pointer = new Date(earliest.getTime());
		while (pointer <= latest) {
			var pointerIso = isoDate(pointer);
			var pointerWeekday = pointer.getDay();
			var allowedByWeekday = allowedWeekdays.indexOf(pointerWeekday) > -1;
			var allowedByServer = !hasServerAvailability || Boolean(availableMap[pointerIso]);
			var allowedByBlocked = !blockedMap[pointerIso];
			if (allowedByWeekday && allowedByServer && allowedByBlocked) {
				firstAvailable = new Date(pointer.getTime());
				break;
			}
			pointer = addDays(pointer, 1);
		}
		var hasAnyAvailable = Boolean(firstAvailable);
		if (!firstAvailable) {
			firstAvailable = new Date(earliest.getTime());
		}
		var earliestMonth = new Date(earliest.getFullYear(), earliest.getMonth(), 1);
		var latestMonth = new Date(latest.getFullYear(), latest.getMonth(), 1);
		var dateStore = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.date
			? state.frontendStore.fulfillment.date
			: {};
		var preferredMonth = parseMonthKey(dateStore.calendar_month || '');
		var monthStart = preferredMonth ? preferredMonth : new Date(earliestMonth.getTime());
		if (monthStart < earliestMonth) {
			monthStart = new Date(earliestMonth.getTime());
		}
		if (monthStart > latestMonth) {
			monthStart = new Date(latestMonth.getTime());
		}
		var monthLabel = monthStart.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' });
		var selectedDate = String(dateStore.selected_date || '');
		var selected = parseIsoDate(selectedDate);
		if (!selected || selected < earliest || selected > latest || allowedWeekdays.indexOf(selected.getDay()) === -1 || blockedMap[selectedDate] || (hasServerAvailability && !availableMap[selectedDate])) {
			selected = firstAvailable;
			selectedDate = isoDate(firstAvailable);
		}
		if (!hasAnyAvailable) {
			selectedDate = '';
		}

		var gridStart = addDays(monthStart, -((monthStart.getDay() + 6) % 7));
		var days = [];
		var i;
		for (i = 0; i < 42; i += 1) {
			var date = addDays(gridStart, i);
			var inMonth = date.getMonth() === monthStart.getMonth();
			var weekend = date.getDay() === 0 || date.getDay() === 6;
			var value = isoDate(date);
			var outOfRange = date < earliest || date > latest;
			var blockedByManualDate = Boolean(blockedMap[value]);
			var allowedByWeekdayDate = allowedWeekdays.indexOf(date.getDay()) > -1;
			var allowedByServerDate = !hasServerAvailability || Boolean(availableMap[value]);
			var disabled = outOfRange || blockedByManualDate || !allowedByWeekdayDate || !allowedByServerDate;
			days.push({
				value: value,
				label: date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }),
				dayOfMonth: date.getDate(),
				inMonth: inMonth,
				disabled: disabled,
				selected: value === selectedDate,
				weekend: weekend
			});
		}

		var helper = getStepThreeHelperByScenario(scenario);
		return {
			scenario: scenario,
			selectedDate: selectedDate,
			hasAnyAvailable: hasAnyAvailable,
			monthLabel: monthLabel,
			monthKey: monthKeyFromDate(monthStart),
			minMonthKey: monthKeyFromDate(earliestMonth),
			maxMonthKey: monthKeyFromDate(latestMonth),
			canGoPrevMonth: monthStart > earliestMonth,
			canGoNextMonth: monthStart < latestMonth,
			days: days,
			helper: helper
		};
	}

	function buildDateCalendarHtml(state) {
		var model = buildDateCalendarModel(state);
		var style = getStepThreeCalendarStyle();
		var weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
		var formErrors = state.frontendStore && state.frontendStore.form && state.frontendStore.form.errors
			? state.frontendStore.form.errors
			: {};
		var dateError = formErrors.date || '';
		var html = '';
		var i;

		html += '<section class="mp-cc-date-step mp-cc-date-step--' + escapeHtml(style.density) + ' mp-cc-date-step--shape-' + escapeHtml(style.dayShape) + ' mp-cc-date-step--highlight-' + escapeHtml(style.highlightStyle) + (style.showWeekendTint ? ' mp-cc-date-step--weekend-tint' : '') + '" aria-labelledby="mp-cc-date-title">';
		html += '<header class="mp-cc-date-step__header">';
		html += '<h4 class="mp-cc-date-step__title" id="mp-cc-date-title">' + escapeHtml(getStepThreeTitle()) + '</h4>';
		html += '<div class="mp-cc-date-step__month-nav">';
		html += '<button type="button" class="mp-cc-date-step__month-btn" data-calendar-nav="-1" aria-label="' + escapeHtml(getUiText('step_3.prev_month', 'Предыдущий месяц')) + '"' + (model.canGoPrevMonth ? '' : ' disabled') + '>‹</button>';
		html += '<p class="mp-cc-date-step__month" aria-live="polite" data-calendar-month="' + escapeHtml(model.monthKey) + '">' + escapeHtml(model.monthLabel) + '</p>';
		html += '<button type="button" class="mp-cc-date-step__month-btn" data-calendar-nav="+1" aria-label="' + escapeHtml(getUiText('step_3.next_month', 'Следующий месяц')) + '"' + (model.canGoNextMonth ? '' : ' disabled') + '>›</button>';
		html += '</div>';
		html += '</header>';
		html += '<div class="mp-cc-calendar" role="group" aria-label="' + escapeHtml(getStepThreeTitle()) + '">';
		html += '<div class="mp-cc-calendar__weekdays" aria-hidden="true">';
		for (i = 0; i < weekdays.length; i += 1) {
			html += '<span class="mp-cc-calendar__weekday">' + escapeHtml(weekdays[i]) + '</span>';
		}
		html += '</div>';
		html += '<div class="mp-cc-calendar__grid" role="grid" aria-labelledby="mp-cc-date-title" data-calendar-grid="1">';
		var focusAssigned = false;
		for (i = 0; i < model.days.length; i += 1) {
			var day = model.days[i];
			var classes = ['mp-cc-calendar__day'];
			if (!day.inMonth) {
				classes.push('is-outside');
			}
			if (day.disabled) {
				classes.push('is-disabled');
			}
			if (day.selected) {
				classes.push('is-selected');
			}
			if (day.weekend) {
				classes.push('is-weekend');
			}
			var isFocusable = !day.disabled && (day.selected || !focusAssigned);
			var tabIndex = isFocusable ? '0' : '-1';
			if (isFocusable) {
				focusAssigned = true;
			}
			html += '<button type="button" class="' + classes.join(' ') + '"';
			html += ' role="gridcell"';
			html += ' data-calendar-date="' + escapeHtml(day.value) + '"';
			html += ' aria-label="' + escapeHtml(day.label) + '"';
			html += ' aria-selected="' + (day.selected ? 'true' : 'false') + '"';
			html += ' tabindex="' + tabIndex + '"';
			if (day.disabled) {
				html += ' disabled aria-disabled="true"';
			}
			html += '>';
			html += '<span>' + escapeHtml(day.dayOfMonth) + '</span>';
			html += '</button>';
		}
		html += '</div>';
		html += '</div>';
		if (dateError === 'required') {
			html += '<p class="mp-cc-date-step__helper is-error" id="mp-cc-date-helper" role="alert">' + escapeHtml(getStepThreeErrorCopy('empty_date', 'Выберите дату, чтобы продолжить.')) + '</p>';
		} else if (dateError === 'invalid') {
			html += '<p class="mp-cc-date-step__helper is-error" id="mp-cc-date-helper" role="alert">' + escapeHtml(getStepThreeErrorCopy('invalid_date', 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.')) + '</p>';
		} else if (!model.hasAnyAvailable) {
			html += '<p class="mp-cc-date-step__helper" id="mp-cc-date-helper">' + escapeHtml(getStepThreeErrorCopy('invalid_date', 'Нет доступных дат. Выберите другой сценарий или свяжитесь с поддержкой.')) + '</p>';
		} else {
			html += '<p class="mp-cc-date-step__helper" id="mp-cc-date-helper">' + escapeHtml(model.helper) + '</p>';
		}
		html += '</section>';
		return html;
	}

	function parseContext() {
		var root = document.querySelector(selectors.root);
		if (!root) {
			return {};
		}

		var raw = root.getAttribute('data-mp-cc-context');
		if (!raw) {
			return {};
		}

		try {
			return JSON.parse(raw) || {};
		} catch (e) {
			return {};
		}
	}

	function applyThemeVariant(context) {
		var root = document.querySelector(selectors.root);
		if (!root) {
			return;
		}

		var designTokens = (window.mpCcCheckout && window.mpCcCheckout.designTokens) ? window.mpCcCheckout.designTokens : {};
		var variant = '';
		if (designTokens && typeof designTokens === 'object' && designTokens.theme_variant) {
			variant = String(designTokens.theme_variant);
		} else if (context && context.design_tokens && context.design_tokens.theme_variant) {
			variant = String(context.design_tokens.theme_variant);
		}

		root.classList.remove('mp-cc-theme-luxe');
		if (variant.toLowerCase() === 'luxe' || variant.toLowerCase() === 'luxury') {
			root.classList.add('mp-cc-theme-luxe');
		}
	}

	function buildState(context) {
		var flow = context.checkout_flow || {};
		var contextFlags = context.feature_flags || {};
		var localizedFlags = (window.mpCcCheckout && window.mpCcCheckout.flags) ? window.mpCcCheckout.flags : {};
		var allSteps = Array.isArray(flow.steps) ? flow.steps : [];
		var visibleIds = Array.isArray(flow.visible_steps) ? flow.visible_steps : [];
		var currentStep = flow.current_step || (visibleIds[0] || '');
		var visible = [];
		var i;

		for (i = 0; i < visibleIds.length; i += 1) {
			var stepId = visibleIds[i];
			var found = null;
			var j;

			for (j = 0; j < allSteps.length; j += 1) {
				if (allSteps[j] && allSteps[j].id === stepId) {
					found = allSteps[j];
					break;
				}
			}

			if (!found) {
				found = { id: stepId, label: stepId, validation_mode: 'server' };
			}

			visible.push(found);
		}

		if (!currentStep && visible.length) {
			currentStep = visible[0].id;
		}

		var currentIndex = getStepIndex(visible, currentStep);
		if (currentIndex < 0) {
			currentIndex = 0;
			currentStep = visible.length ? visible[0].id : '';
		}

		return {
			context: context,
			flowContextId: flow.context_id || '',
			visibleSteps: visible,
			currentStepId: currentStep,
			maxReachedIndex: currentIndex,
			isTransitioning: false,
			frontendStore: createFrontendStore(flow, visible, allSteps, currentStep),
			featureFlags: $.extend({}, contextFlags, localizedFlags),
			stepOneConfig: getStepOneConfig()
		};
	}

	function createFrontendStore(flow, visibleSteps, allSteps, currentStepId) {
		var answers = flow.answers || {};
		var contactBilling = answers.contact_billing || {};
		var paymentGateway = '';
		if (contactBilling && typeof contactBilling === 'object') {
			paymentGateway = contactBilling.payment_gateway || contactBilling.gateway || '';
		}

		return {
			steps: {
				current: currentStepId || '',
				visible: visibleSteps || [],
				all: allSteps || []
			},
			cart: {
				snapshot: flow.snapshot || {},
				summary: {},
				items: []
			},
			form: {
				contact: contactBilling,
				errors: {}
			},
			fulfillment: {
				scenario: flow.scenario || '',
				date: answers.date_conditions || {},
				scenarioData: $.extend({}, answers.scenario || {}, { rules: flow.scenario_rules || {} })
			},
			discounts: answers.discounts || { coupons: [], gift_card: [] },
			payment: {
				gateway: paymentGateway || '',
				state: 'idle'
			},
			runtime: {
				loading: false,
				success: false,
				blocked: false,
				dirty: false,
				lastSyncAt: Date.now(),
				summaryHydrated: false,
				prePaymentConfirm: false,
				paymentSubmitting: false
			},
			meta: {
				contextId: flow.context_id || '',
				expiresAt: flow.expires_at || 0,
				lastSummarySignature: ''
			}
		};
	}

	function ensurePickupScenarioData(state) {
		if (!state || !state.frontendStore || !state.frontendStore.fulfillment) {
			return;
		}
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || 'pickup');
		if (scenario !== 'pickup') {
			return;
		}
		var existing = state.frontendStore.fulfillment.scenarioData || {};
		var pickupPoint = existing.pickup_point && typeof existing.pickup_point === 'object' ? existing.pickup_point : null;
		if (!pickupPoint) {
			var fallback = getPickupPointById('');
			if (fallback) {
				existing.pickup_point = fallback;
				state.frontendStore.fulfillment.scenarioData = existing;
			}
		}
	}

	function ensureDateSelection(state) {
		if (!state || !state.frontendStore || !state.frontendStore.fulfillment) {
			return;
		}
		var dateBox = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
			? state.frontendStore.fulfillment.date
			: {};
		if (dateBox.selected_date) {
			if (!dateBox.calendar_month) {
				var parsedDate = parseIsoDate(dateBox.selected_date);
				if (parsedDate) {
					dateBox.calendar_month = monthKeyFromDate(parsedDate);
					state.frontendStore.fulfillment.date = dateBox;
				}
			}
			return;
		}
		var model = buildDateCalendarModel(state);
		if (!model || !model.selectedDate || !model.hasAnyAvailable) {
			return;
		}
		dateBox.selected_date = model.selectedDate;
		if (!dateBox.calendar_month) {
			dateBox.calendar_month = model.monthKey;
		}
		state.frontendStore.fulfillment.date = dateBox;
	}

	function normalizeCartPayload(cartPayload) {
		var safePayload = cartPayload && typeof cartPayload === 'object' ? cartPayload : {};
		var safeSummary = (safePayload.summary && typeof safePayload.summary === 'object') ? safePayload.summary : {};
		var fallbackCatalogUrl = (window.mpCcCheckout && window.mpCcCheckout.checkoutUrl) ? String(window.mpCcCheckout.checkoutUrl) : '/';
		if (safePayload && safePayload.home_url) {
			fallbackCatalogUrl = String(safePayload.home_url);
		}
		return {
			items: Array.isArray(safePayload.items) ? safePayload.items : [],
			summary: $.extend(
				{
					items_count: 0,
					subtotal: '',
					total: '',
					discount: '',
					applied_coupons: [],
					catalog_url: fallbackCatalogUrl
				},
				safeSummary
			)
		};
	}

	function getStepIndex(steps, stepId) {
		var i;
		for (i = 0; i < steps.length; i += 1) {
			if (steps[i] && steps[i].id === stepId) {
				return i;
			}
		}
		return -1;
	}

	function postCheckout(subAction, payload) {
		var localized = window.mpCcCheckout || {};
		if (!localized.ajaxUrl || !localized.nonce) {
			return $.Deferred().resolve({ success: true }).promise();
		}

		var request = $.ajax({
			url: localized.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: $.extend(
				{
					action: 'mp_cc_checkout',
					nonce: localized.nonce,
					sub_action: subAction,
					context_id: (payload && payload.context_id) ? payload.context_id : ''
				},
				payload || {}
			)
		});
		pendingCheckoutRequests += 1;
		request.always(function () {
			pendingCheckoutRequests = Math.max(0, pendingCheckoutRequests - 1);
		});
		return request;
	}

	function stepKeyById(stepId) {
		if (stepId === 'cart') {
			return 'step_one';
		}
		if (stepId === 'date' || stepId === 'conditions') {
			return 'date_conditions';
		}
		if (stepId === 'contact_payment') {
			return 'contact_billing';
		}
		return stepId;
	}

	function syncFromFlow(state, flow, cartPayload) {
		var nextFlow = flow || {};
		var prevRuntime = state.frontendStore && state.frontendStore.discounts ? state.frontendStore.discounts.coupon_runtime : null;
		state.context.checkout_flow = nextFlow;
		if (cartPayload && typeof cartPayload === 'object') {
			state.context.cart = normalizeCartPayload(cartPayload);
		}
		state.flowContextId = nextFlow.context_id || state.flowContextId || '';
		state.frontendStore = createFrontendStore(
			nextFlow,
			state.visibleSteps,
			(state.frontendStore.steps && state.frontendStore.steps.all) ? state.frontendStore.steps.all : [],
			state.currentStepId
		);
		state.frontendStore.runtime.loading = false;
		state.frontendStore.runtime.blocked = false;
		state.frontendStore.runtime.dirty = false;
		state.frontendStore.runtime.lastSyncAt = Date.now();
		state.frontendStore.runtime.summaryHydrated = true;
		var contextCart = normalizeCartPayload(state.context && state.context.cart ? state.context.cart : {});
		state.frontendStore.cart.items = contextCart.items;
		state.frontendStore.cart.summary = contextCart.summary;
		ensureDiscountDefaults(state);
		if (prevRuntime && typeof prevRuntime === 'object') {
			state.frontendStore.discounts.coupon_runtime = $.extend({}, prevRuntime);
		}
		applyScenarioFieldAvailability(state);
	}

	function stripAddressFieldErrors(state) {
		if (!state || !state.frontendStore || !state.frontendStore.form || !state.frontendStore.form.errors) {
			return;
		}
		var errors = state.frontendStore.form.errors.contact;
		if (!errors || typeof errors !== 'object') {
			return;
		}
		var contact = state.frontendStore.form.contact || {};
		var vis = contact.__address_visibility;
		var keys = ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'];
		var i;
		for (i = 0; i < keys.length; i++) {
			var k = keys[i];
			if (!vis || vis.hide_address_fields) {
				delete errors[k];
				continue;
			}
			if (k === 'country' && vis.hide_country) {
				delete errors[k];
			} else if (k === 'state' && vis.hide_region) {
				delete errors[k];
			} else if (k === 'city' && vis.hide_city) {
				delete errors[k];
			} else if ((k === 'address_1' || k === 'address_2') && vis.hide_address_lines) {
				delete errors[k];
			} else if (k === 'postcode' && vis.hide_postcode) {
				delete errors[k];
			}
		}
	}

	function applyScenarioFieldAvailability(state) {
		if (!state || !state.frontendStore || !state.frontendStore.fulfillment) {
			return;
		}
		var scenarioId = normalizeScenarioId(state.frontendStore.fulfillment.scenario || 'pickup');
		var rules = getScenarioRulesById(scenarioId);
		var fieldRules = rules.field_rules && typeof rules.field_rules === 'object' ? rules.field_rules : {};
		var hideAll = Boolean(fieldRules.hide_address_fields);
		var contact = state.frontendStore.form && state.frontendStore.form.contact ? state.frontendStore.form.contact : {};
		contact.__address_visibility = {
			hide_address_fields: hideAll,
			required_address_fields: Boolean(fieldRules.required_address_fields),
			visible_groups: Array.isArray(fieldRules.visible_groups) ? fieldRules.visible_groups : [],
			hide_country: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_country') ? Boolean(fieldRules.hide_country) : hideAll,
			hide_region: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_region') ? Boolean(fieldRules.hide_region) : hideAll,
			hide_city: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_city') ? Boolean(fieldRules.hide_city) : hideAll,
			hide_address_lines: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_address_lines') ? Boolean(fieldRules.hide_address_lines) : hideAll,
			hide_postcode: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_postcode') ? Boolean(fieldRules.hide_postcode) : hideAll
		};
		state.frontendStore.form.contact = contact;
		stripAddressFieldErrors(state);
		document.dispatchEvent(
			new CustomEvent('mp_cc_address_visibility_changed', {
				detail: {
					scenario: scenarioId,
					fieldRules: contact.__address_visibility
				}
			})
		);
	}

	function resetDependentStateForScenario(state, scenarioId) {
		if (!state || !state.frontendStore) {
			return;
		}
		state.frontendStore.fulfillment.date = {};
		state.frontendStore.fulfillment.scenario = scenarioId;
		state.frontendStore.fulfillment.scenarioData = {
			id: scenarioId,
			rules: getScenarioRulesById(scenarioId)
		};
		if (scenarioId === 'pickup') {
			var defaultPoint = getPickupPointById('');
			if (defaultPoint) {
				state.frontendStore.fulfillment.scenarioData.pickup_point = defaultPoint;
			}
		}

		var rules = getScenarioRulesById(scenarioId);
		var fieldRules = rules.field_rules && typeof rules.field_rules === 'object' ? rules.field_rules : {};
		if (fieldRules.hide_address_fields && state.frontendStore.form && state.frontendStore.form.contact) {
			var contact = $.extend({}, state.frontendStore.form.contact);
			delete contact.address_1;
			delete contact.address_2;
			delete contact.city;
			delete contact.state;
			delete contact.postcode;
			delete contact.country;
			delete contact.shipping_address;
			delete contact.shipping_city;
			delete contact.shipping_postcode;
			state.frontendStore.form.contact = contact;
		}
		applyScenarioFieldAvailability(state);
	}

	function setRuntimeFlag(state, key, value) {
		if (!state || !state.frontendStore || !state.frontendStore.runtime) {
			return;
		}
		state.frontendStore.runtime[key] = Boolean(value);
	}

	function syncStoreWithBackend(state, $app) {
		return postCheckout('session_get_state', { context_id: state.flowContextId }).then(function (response) {
			if (!response || !response.success || !response.data || !response.data.flow) {
				return;
			}
			syncFromFlow(state, response.data.flow, response.data.cart || {});
			var rehydrated = buildState(state.context);
			state.visibleSteps = rehydrated.visibleSteps;
			state.currentStepId = rehydrated.currentStepId;
			state.maxReachedIndex = Math.max(state.maxReachedIndex, rehydrated.maxReachedIndex);
			state.frontendStore = rehydrated.frontendStore;
			state.flowContextId = rehydrated.flowContextId;
			render(state, $app);
		});
	}

	function requestForwardValidation(stepId) {
		var deferred = $.Deferred();
		var detail = {
			stepId: stepId,
			valid: true,
			resolve: function (isValid) {
				deferred.resolve(Boolean(isValid));
			}
		};

		var event = new CustomEvent('mp_cc_before_step_forward', {
			detail: detail
		});
		document.dispatchEvent(event);

		if (typeof window.mpCcValidateStep === 'function') {
			try {
				var fnResult = window.mpCcValidateStep(stepId);
				if (fnResult && typeof fnResult.then === 'function') {
					fnResult.then(function (ok) {
						deferred.resolve(Boolean(ok));
					}).catch(function () {
						deferred.resolve(false);
					});
					return deferred.promise();
				}
				deferred.resolve(Boolean(fnResult));
				return deferred.promise();
			} catch (e) {
				deferred.resolve(false);
				return deferred.promise();
			}
		}

		deferred.resolve(Boolean(detail.valid));
		return deferred.promise();
	}

	function withTransitionLock(state, $app, task) {
		if (state.isTransitioning) {
			return $.Deferred().reject().promise();
		}

		state.isTransitioning = true;
		setRuntimeFlag(state, 'loading', true);
		$app.attr('data-nav-lock', '1').addClass('is-nav-lock is-loading');
		$(selectors.summary).addClass('is-loading');
		$app.find('button, a').attr('aria-disabled', 'true');
		$(selectors.actions).find('.mp-cc-nav__btn').prop('disabled', true);

		var done = function () {
			state.isTransitioning = false;
			setRuntimeFlag(state, 'loading', false);
			$app.attr('data-nav-lock', '0').removeClass('is-nav-lock is-loading');
			$(selectors.summary).removeClass('is-loading');
			$app.find('button, a').removeAttr('aria-disabled');
			$(selectors.actions).find('.mp-cc-nav__btn').prop('disabled', false);
		};

		return task().always(done);
	}

	function scrollToStepTop() {
		var root = document.querySelector(selectors.root);
		if (!root) {
			return;
		}
		root.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function setCurrentStep(state, $app, targetStepId) {
		var targetIndex = getStepIndex(state.visibleSteps, targetStepId);
		if (targetIndex < 0) {
			return $.Deferred().reject().promise();
		}

		return withTransitionLock(state, $app, function () {
			runStepTransitionAnimation($app);
			return postCheckout('session_set_step', { step_id: targetStepId, context_id: state.flowContextId }).then(function (response) {
				if (!response || !response.success) {
					return $.Deferred().reject(response).promise();
				}
				state.currentStepId = targetStepId;
				state.frontendStore.steps.current = targetStepId;
				if (targetStepId !== 'contact_payment') {
					resetPrePaymentConfirm(state);
				}
				state.maxReachedIndex = Math.max(state.maxReachedIndex, targetIndex);
				setRuntimeFlag(state, 'blocked', false);
				render(state, $app);
				scrollToStepTop();
				focusStepHeading($app);

				document.dispatchEvent(
					new CustomEvent('mp_cc_step_changed', {
						detail: {
							stepId: targetStepId,
							index: targetIndex + 1,
							total: state.visibleSteps.length
						}
					})
				);
				return syncStoreWithBackend(state, $app);
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				var code = payload.code ? String(payload.code) : '';
				if (code === 'conditions_unconfirmed') {
					state.frontendStore.form.errors = state.frontendStore.form.errors || {};
					state.frontendStore.form.errors.conditions_unconfirmed = true;
					setStepInvalidState(state, 'conditions', true);
					var vm = getStepFourValidationMessages();
					var msg = payload.message || trimNonEmpty(vm.conditions_required) || getStepThreeErrorCopy('conditions_unconfirmed', 'Подтвердите ознакомление с условиями, чтобы продолжить.');
					notify(msg, 'error');
					render(state, $app);
					scrollToFirstInvalidField($app);
					document.dispatchEvent(
						new CustomEvent('mp_cc_conditions_step_blocked', {
							detail: { code: code, payload: payload }
						})
					);
					return;
				}
				if (xhr && xhr.status === 422) {
					notify(payload.message || getUiText('common.error_generic', 'Произошла ошибка. Попробуйте ещё раз.'), 'error');
				} else {
					notify(getStepFourAjaxMessage('step_sync_failed', 'step_4.contact_ajax_step_sync_failed', 'Не удалось синхронизировать шаг. Обновите страницу.'), 'error');
				}
			});
		});
	}

	function moveBackward(state, $app) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		if (currentIndex <= 0) {
			return;
		}
		var target = state.visibleSteps[currentIndex - 1];
		resetPrePaymentConfirm(state);
		setCurrentStep(state, $app, target.id);
	}

	function moveForward(state, $app) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		if (currentIndex < 0) {
			return;
		}
		if (currentIndex >= state.visibleSteps.length - 1) {
			if (state.currentStepId === 'contact_payment') {
				ensureContactDefaults(state);
				if (!validateContactPaymentStep(state)) {
					setStepInvalidState(state, 'contact_payment', true);
					logValidationFailure(state, 'contact_payment', state.frontendStore.form.errors ? state.frontendStore.form.errors.contact : {});
					notify(getUiText('step_4.contact_error_required', 'Проверьте контактные данные.'), 'error');
					render(state, $app);
					scrollToFirstInvalidField($app);
					return;
				}
				setStepInvalidState(state, 'contact_payment', false);
				saveCurrentStepDraft(state).fail(function () {
					notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
				});
				state.frontendStore.runtime = state.frontendStore.runtime || {};
				if (!state.frontendStore.runtime.prePaymentConfirm) {
					state.frontendStore.runtime.prePaymentConfirm = true;
					notify(getUiText('order_review.pre_payment_state', 'Проверьте финальный review перед запуском оплаты.'), 'info');
					render(state, $app);
					return;
				}
				document.dispatchEvent(
					new CustomEvent('mp_cc_pre_payment_confirmed', {
						detail: {
							context_id: state.flowContextId,
							payment_gateway: state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : ''
						}
					})
				);
				submitFinalPayment(state, $app);
			}
			return;
		}
		var target = state.visibleSteps[currentIndex + 1];
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		if (state.currentStepId === 'cart' && Number(cartSummary.items_count || 0) <= 0) {
			notify(getUiText('step_1.empty_cart', 'Cart is empty'), 'error');
			return;
		}
		if (state.currentStepId === 'date') {
			var selectedDate = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.date
				? String(state.frontendStore.fulfillment.date.selected_date || '')
				: '';
			var parsedSelected = parseIsoDate(selectedDate);
			if (!selectedDate) {
				state.frontendStore.form.errors = state.frontendStore.form.errors || {};
				state.frontendStore.form.errors.date = 'required';
				setStepInvalidState(state, 'date', true);
				logValidationFailure(state, 'date', { selected_date: 'required' });
				notify(getStepThreeErrorCopy('empty_date', 'Выберите дату, чтобы продолжить.'), 'error');
				render(state, $app);
				scrollToFirstInvalidField($app);
				return;
			}
			if (!parsedSelected) {
				state.frontendStore.form.errors = state.frontendStore.form.errors || {};
				state.frontendStore.form.errors.date = 'invalid';
				setStepInvalidState(state, 'date', true);
				logValidationFailure(state, 'date', { selected_date: 'invalid' });
				notify(getStepThreeErrorCopy('invalid_date', 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.'), 'error');
				render(state, $app);
				scrollToFirstInvalidField($app);
				return;
			}
			state.frontendStore.form.errors = state.frontendStore.form.errors || {};
			state.frontendStore.form.errors.date = '';
			setStepInvalidState(state, 'date', false);
		}
		if (state.currentStepId === 'conditions') {
			var dateState = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.date
				? state.frontendStore.fulfillment.date
				: {};
			if (!dateState.conditions_confirmed) {
				state.frontendStore.form.errors.conditions_unconfirmed = true;
				setStepInvalidState(state, 'conditions', true);
				logValidationFailure(state, 'conditions', { conditions_confirmed: 'required' });
				render(state, $app);
				var vm2 = getStepFourValidationMessages();
				notify(trimNonEmpty(vm2.conditions_required) || getUiText('step_3.unconfirmed_error', 'Подтвердите ознакомление с условиями, чтобы продолжить.'), 'error');
				scrollToFirstInvalidField($app);
				return;
			}
			state.frontendStore.form.errors.conditions_unconfirmed = false;
			setStepInvalidState(state, 'conditions', false);
		}
		requestForwardValidation(state.currentStepId).then(function (valid) {
			if (!valid) {
				setRuntimeFlag(state, 'blocked', true);
				var vm = getStepFourValidationMessages();
				notify(trimNonEmpty(vm.step_blocked) || getUiText('step_4.contact_error_step_blocked', 'Заполните обязательные поля текущего шага.'), 'error');
				return;
			}
			setRuntimeFlag(state, 'blocked', false);
			setStepInvalidState(state, state.currentStepId, false);
			resetPrePaymentConfirm(state);
			setCurrentStep(state, $app, target.id);
		});
	}

	function saveCurrentStepDraft(state) {
		var stepId = state.currentStepId;
		if (!stepId) {
			return $.Deferred().resolve().promise();
		}

		var storageKey = stepKeyById(stepId);
		var payload = getDraftPayloadByStorageKey(state, storageKey);
		setRuntimeFlag(state, 'dirty', true);

		return postCheckout('session_set_answers', {
			step_id: stepId,
			context_id: state.flowContextId,
			answers: payload
		}).then(function () {
			setRuntimeFlag(state, 'dirty', false);
		});
	}

	function scheduleCurrentStepDraftSave(state, onError) {
		if (draftSaveTimer) {
			window.clearTimeout(draftSaveTimer);
		}
		draftSaveTimer = window.setTimeout(function () {
			draftSaveTimer = null;
			saveCurrentStepDraft(state).fail(function () {
				if (typeof onError === 'function') {
					onError();
				}
			});
		}, 260);
	}

	function saveDiscountDraft(state) {
		ensureDiscountDefaults(state);
		return postCheckout('session_set_answers', {
			step_id: 'discounts',
			context_id: state.flowContextId,
			answers: state.frontendStore.discounts || {}
		});
	}

	function getDraftPayloadByStorageKey(state, storageKey) {
		if (!state || !state.frontendStore) {
			return {};
		}
		if (storageKey === 'step_one') {
			return state.frontendStore.cart.snapshot || {};
		}
		if (storageKey === 'date_conditions') {
			return state.frontendStore.fulfillment.date || {};
		}
		if (storageKey === 'contact_billing') {
			var rawContact = state.frontendStore.form.contact || {};
			var out = {};
			var ck;
			for (ck in rawContact) {
				if (!Object.prototype.hasOwnProperty.call(rawContact, ck)) {
					continue;
				}
				if (ck.indexOf('__') === 0) {
					continue;
				}
				out[ck] = rawContact[ck];
			}
			return out;
		}
		if (storageKey === 'scenario') {
			return state.frontendStore.fulfillment.scenarioData || {};
		}
		if (storageKey === 'discounts') {
			return state.frontendStore.discounts || {};
		}
		return {};
	}

	function buildProgressHtml(state) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var invalidMap = state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.invalid_steps
			? state.frontendStore.runtime.invalid_steps
			: {};
		var html = '';
		var i;

		html += '<ol class="mp-cc-progress" role="list" aria-label="Checkout steps">';
		for (i = 0; i < state.visibleSteps.length; i += 1) {
			var step = state.visibleSteps[i];
			var canGo = i <= state.maxReachedIndex;
			var isCurrent = i === currentIndex;
			var classes = ['mp-cc-progress__item'];

			if (isCurrent) {
				classes.push('is-active');
			}
			if (i < currentIndex) {
				classes.push('is-complete');
			}
			if (invalidMap[step.id]) {
				classes.push('is-invalid');
			}

			html += '<li class="' + classes.join(' ') + '">';
			html += '<button type="button" class="mp-cc-progress__btn" data-step="' + step.id + '"';
			html += canGo ? '' : ' disabled';
			html += isCurrent ? ' aria-current="step"' : '';
			html += '>';
			html += '<span class="mp-cc-progress__index">' + (i + 1) + '</span>';
			html += '<span class="mp-cc-progress__label">' + escapeHtml(step.label || step.id) + '</span>';
			html += '</button>';
			html += '</li>';
		}
		html += '</ol>';

		return html;
	}

	function buildStepPanelHtml(state) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var step = currentIndex >= 0 ? state.visibleSteps[currentIndex] : null;
		var label = step ? (step.label || step.id) : '';
		if (step && step.id === 'cart') {
			label = getStepOneLabel(state, 'title', 'step_1.title', label || 'Cart');
		}
		if (step && step.id === 'conditions') {
			label = getConditionsStepPanelTitle(state);
		}
		if (step && step.id === 'contact_payment') {
			var s4 = getStepFourConfig();
			var s4t = s4.contact_block && trimNonEmpty(s4.contact_block.title) ? String(s4.contact_block.title) : '';
			label = s4t || getUiText('step_4.title', label || 'Контакты и оплата');
		}
		var html = '';

		html += '<section class="mp-cc-step-panel" data-step-panel="' + escapeHtml(step ? step.id : '') + '">';
		html += '<header class="mp-cc-step-panel__header">';
		html += '<p class="mp-cc-step-panel__meta">Step ' + (currentIndex + 1) + ' / ' + state.visibleSteps.length + '</p>';
		html += '<h2 class="mp-cc-step-panel__title" id="mp-cc-step-heading" tabindex="-1">' + escapeHtml(label) + '</h2>';
		html += '</header>';
		html += '<div class="mp-cc-step-panel__content" data-mp-cc-step-slot="' + escapeHtml(step ? step.id : '') + '">';
		if (step && step.id === 'cart') {
			html += buildCartItemsHtml(state);
		}
		if (step && step.id === 'date') {
			html += buildFulfillmentChoiceHtml(state);
		}
		if (step && step.id === 'conditions') {
			html += buildConditionsStepHtml(state);
		}
		if (step && step.id === 'contact_payment') {
			html += buildContactPaymentHtml(state);
			html += buildAddressBlockHtml(state);
			html += buildDiscountToolsHtml(state);
		}
		html += '</div>';
		if (!isFlagEnabled(state, flagNames.discountPlacement, true)) {
			html += '<p class="mp-cc-step-panel__hint">Discount tools are rendered inline in payment step.</p>';
		}
		if (!isFlagEnabled(state, flagNames.multiPickupPoints, false)) {
			html += '<p class="mp-cc-step-panel__hint">Single pickup point mode is active.</p>';
		}
		if (isFlagEnabled(state, flagNames.checkoutTestingMode, false)) {
			html += '<p class="mp-cc-step-panel__hint">Checkout testing mode is enabled.</p>';
		}
		html += '</section>';

		return html;
	}

	function buildFulfillmentChoiceHtml(state) {
		var map = getScenarioMap();
		var ui = getScenarioUiConfig();
		var scenarios = map.scenarios || {};
		var rules = map.rules || {};
		var selectedScenario = String(state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenario || '') : '');
		var scenarioIds = Object.keys(scenarios);
		var pickupId = 'pickup';
		var deliveryIds = [];
		var i;

		for (i = 0; i < scenarioIds.length; i += 1) {
			if (scenarioIds[i] !== pickupId) {
				deliveryIds.push(scenarioIds[i]);
			}
		}
		var defaultDeliveryId = deliveryIds.length ? deliveryIds[0] : 'krasnoyarsk_delivery';
		var selectedGroup = selectedScenario === pickupId ? pickupId : 'delivery';
		var deliveryLabel = scenarios[defaultDeliveryId] ? String(scenarios[defaultDeliveryId]) : 'Доставка';
		var pickupLabel = scenarios[pickupId] ? String(scenarios[pickupId]) : 'Самовывоз';
		var pickupCopy = rules[pickupId] && rules[pickupId].copy_rules ? String(rules[pickupId].copy_rules.hint || '') : '';
		var deliveryCopy = rules[defaultDeliveryId] && rules[defaultDeliveryId].copy_rules ? String(rules[defaultDeliveryId].copy_rules.hint || '') : '';

		var cardOrder = Array.isArray(ui.card_order) ? ui.card_order : ['pickup', 'delivery'];
		var cards = [];
		for (i = 0; i < cardOrder.length; i += 1) {
			var key = String(cardOrder[i] || '');
			if (key !== 'pickup' && key !== 'delivery') {
				continue;
			}
			var cardCfg = ui.cards && ui.cards[key] ? ui.cards[key] : {};
			if (key === 'pickup') {
				cards.push({
					group: 'pickup',
					target: pickupId,
					label: cardCfg.title ? String(cardCfg.title) : pickupLabel,
					copy: cardCfg.description ? String(cardCfg.description) : pickupCopy,
					helper: cardCfg.helper ? String(cardCfg.helper) : '',
					icon: cardCfg.icon_variant ? String(cardCfg.icon_variant) : 'pickup',
					iconStyle: cardCfg.icon_style ? String(cardCfg.icon_style) : 'soft'
				});
			} else {
				cards.push({
					group: 'delivery',
					target: defaultDeliveryId,
					label: cardCfg.title ? String(cardCfg.title) : deliveryLabel,
					copy: cardCfg.description ? String(cardCfg.description) : deliveryCopy,
					helper: cardCfg.helper ? String(cardCfg.helper) : '',
					icon: cardCfg.icon_variant ? String(cardCfg.icon_variant) : 'delivery',
					iconStyle: cardCfg.icon_style ? String(cardCfg.icon_style) : 'soft'
				});
			}
		}
		if (!cards.length) {
			cards.push({ group: 'pickup', target: pickupId, label: pickupLabel, copy: pickupCopy, helper: '', icon: 'pickup', iconStyle: 'soft' });
			cards.push({ group: 'delivery', target: defaultDeliveryId, label: deliveryLabel, copy: deliveryCopy, helper: '', icon: 'delivery', iconStyle: 'soft' });
		}

		var html = '';
		html += '<section class="mp-cc-fulfillment mp-cc-fulfillment--desktop-' + escapeHtml(ui.responsive.desktop_columns) + ' mp-cc-fulfillment--tablet-' + escapeHtml(ui.responsive.tablet_columns) + ' mp-cc-fulfillment--mobile-' + escapeHtml(ui.responsive.mobile_columns) + ' mp-cc-fulfillment--' + escapeHtml(ui.responsive.card_density || 'comfortable') + '" aria-labelledby="mp-cc-fulfillment-title">';
		html += '<header class="mp-cc-fulfillment__header">';
		html += '<h3 class="mp-cc-fulfillment__title" id="mp-cc-fulfillment-title">' + escapeHtml(getUiText('step_2.title', 'Выберите способ получения')) + '</h3>';
		html += '</header>';
		html += '<div class="mp-cc-fulfillment__cards" role="radiogroup" aria-label="' + escapeHtml(getUiText('step_2.title', 'Способ получения')) + '">';
		for (i = 0; i < cards.length; i += 1) {
			var card = cards[i];
			var isActive = selectedGroup === card.group;
			html += '<button type="button" class="mp-cc-fulfillment-card mp-cc-fulfillment-card--' + escapeHtml(card.iconStyle || 'soft') + (isActive ? ' is-active' : '') + '"';
			html += ' role="radio"';
			html += ' aria-checked="' + (isActive ? 'true' : 'false') + '"';
			html += ' tabindex="' + (isActive ? '0' : '-1') + '"';
			html += ' data-scenario-card="' + escapeHtml(card.group) + '"';
			html += ' data-scenario-target="' + escapeHtml(card.target) + '"';
			html += '>';
			html += '<span class="mp-cc-fulfillment-card__media" aria-hidden="true">';
			html += '<span class="mp-cc-fulfillment-card__icon mp-cc-fulfillment-card__icon--' + escapeHtml(card.icon) + '"></span>';
			html += '</span>';
			html += '<span class="mp-cc-fulfillment-card__body">';
			html += '<span class="mp-cc-fulfillment-card__label">' + escapeHtml(card.label) + '</span>';
			if (card.copy) {
				html += '<span class="mp-cc-fulfillment-card__copy">' + escapeHtml(card.copy) + '</span>';
			}
			if (card.helper) {
				html += '<span class="mp-cc-fulfillment-card__helper">' + escapeHtml(card.helper) + '</span>';
			}
			html += '</span>';
			html += '</button>';
		}
		html += '</div>';
		if (selectedGroup === 'pickup') {
			html += buildPickupPointHtml(state);
		}
		html += buildDateCalendarHtml(state);
		html += '</section>';
		return html;
	}

	function buildPickupPointHtml(state) {
		var pickupConfig = getPickupConfig();
		var points = pickupConfig.points || [];
		var scenarioData = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenarioData || {}) : {};
		var selectedPoint = scenarioData.pickup_point && typeof scenarioData.pickup_point === 'object' ? scenarioData.pickup_point : getPickupPointById('');
		var selectedPointId = selectedPoint && selectedPoint.id ? String(selectedPoint.id) : '';
		var html = '';

		html += '<section class="mp-cc-pickup-point" aria-labelledby="mp-cc-pickup-title">';
		html += '<h4 class="mp-cc-pickup-point__title" id="mp-cc-pickup-title">' + escapeHtml('Точка самовывоза') + '</h4>';
		if (pickupConfig.enablePointSelection && points.length > 1) {
			html += '<div class="mp-cc-pickup-point__choices" role="radiogroup" aria-label="Выбор точки самовывоза">';
			for (var i = 0; i < points.length; i += 1) {
				var point = points[i] || {};
				var pointId = String(point.id || '');
				var active = pointId === selectedPointId;
				html += '<button type="button" class="mp-cc-pickup-point__choice' + (active ? ' is-active' : '') + '" role="radio"';
				html += ' aria-checked="' + (active ? 'true' : 'false') + '"';
				html += ' data-pickup-point="' + escapeHtml(pointId) + '">';
				html += '<span class="mp-cc-pickup-point__choice-title">' + escapeHtml(String(point.title || pointId)) + '</span>';
				if (point.address) {
					html += '<span class="mp-cc-pickup-point__choice-address">' + escapeHtml(String(point.address)) + '</span>';
				}
				html += '</button>';
			}
			html += '</div>';
		}

		if (selectedPoint) {
			html += '<div class="mp-cc-pickup-point__info">';
			html += '<p class="mp-cc-pickup-point__name">' + escapeHtml(String(selectedPoint.title || '')) + '</p>';
			if (selectedPoint.address) {
				html += '<p class="mp-cc-pickup-point__address">' + escapeHtml(String(selectedPoint.address)) + '</p>';
			}
			if (selectedPoint.description) {
				html += '<p class="mp-cc-pickup-point__description">' + escapeHtml(String(selectedPoint.description)) + '</p>';
			}
			html += '</div>';
			if (pickupConfig.mapSlotEnabled) {
				html += '<div class="mp-cc-pickup-point__map-slot" data-pickup-map-slot="1">';
				html += '<p>' + escapeHtml(String(selectedPoint.map_hint || 'Слот карты будет подключен позже.')) + '</p>';
				html += '</div>';
			}
		}
		html += '</section>';

		return html;
	}

	function buildCartItemsHtml(state) {
		var items = state.frontendStore && state.frontendStore.cart && Array.isArray(state.frontendStore.cart.items)
			? state.frontendStore.cart.items
			: [];
		var config = state.stepOneConfig || {};
		var visibility = config.product_meta_visibility || {};
		var quantityControls = config.quantity_controls || {};
		var layout = config.layout_order || {};
		var order = Array.isArray(layout.secondary_order) ? layout.secondary_order : ['price', 'sku', 'variation', 'quantity', 'subtotal', 'remove'];
		var html = '';

		html += '<section class="mp-cc-cart-list" aria-label="' + escapeHtml(getStepOneLabel(state, 'title', 'step_1.title', 'Cart items')) + '">';
		html += '<div class="mp-cc-cart-list__items" data-mp-cc-item-list="1">';

		if (!items.length) {
			var emptySummary = state.frontendStore && state.frontendStore.cart ? (state.frontendStore.cart.summary || {}) : {};
			var catalogUrl = emptySummary.catalog_url ? String(emptySummary.catalog_url) : '/';
			var emptyCtaEnabled = (config.empty_state && typeof config.empty_state.cta_enabled !== 'undefined') ? Boolean(config.empty_state.cta_enabled) : true;
			html += '<div class="mp-cc-cart-list__empty">';
			html += '<p class="mp-cc-empty">' + escapeHtml(getStepOneLabel(state, 'empty_title', 'step_1.empty_cart', 'Cart is empty')) + '</p>';
			if (config.empty_state && config.empty_state.message) {
				html += '<p class="mp-cc-cart-list__empty-message">' + escapeHtml(String(config.empty_state.message)) + '</p>';
			}
			if (emptyCtaEnabled) {
				html += '<a class="mp-cc-cart-list__cta" href="' + escapeHtml(catalogUrl) + '">' + escapeHtml(getStepOneLabel(state, 'return_label', 'step_1.return_to_shop', 'Return to catalog')) + '</a>';
			}
			html += '</div>';
			html += '</div></section>';
			return html;
		}

		for (var i = 0; i < items.length; i += 1) {
			var item = items[i] || {};
			var itemKey = String(item.key || 'item-' + i);
			var productId = Number(item.product_id || 0);
			var variationId = Number(item.variation_id || 0);
			var title = item.name ? String(item.name) : getUiText('step_1.title', 'Product');
			var priceHtml = item.price_html ? String(item.price_html) : '';
			var sku = item.sku ? String(item.sku) : '';
			var variationText = item.variation_text ? String(item.variation_text) : '';
			var imageUrl = item.image_url ? String(item.image_url) : '';
			var qty = Number(item.quantity || 0);
			var minQty = Number(item.min_quantity || 1);
			var maxQty = Number(item.max_quantity || 9999);
			var subtotal = item.line_subtotal ? String(item.line_subtotal) : '';

			html += '<article class="mp-cc-cart-item"';
			html += ' data-cart-item-key="' + escapeHtml(itemKey) + '"';
			html += ' data-product-id="' + escapeHtml(productId) + '"';
			html += ' data-variation-id="' + escapeHtml(variationId) + '"';
			html += '>';
			html += '<div class="mp-cc-cart-item__media">';
			if (visibility.show_image !== false && imageUrl) {
				html += '<img src="' + escapeHtml(imageUrl) + '" alt="" loading="lazy" />';
			} else if (visibility.show_image !== false) {
				html += '<div class="mp-cc-cart-item__placeholder" aria-hidden="true"></div>';
			}
			html += '</div>';
			html += '<div class="mp-cc-cart-item__body">';
			html += '<h3 class="mp-cc-cart-item__title" title="' + escapeHtml(title) + '">' + escapeHtml(title) + '</h3>';
			var topBlocks = {};
			topBlocks.price = visibility.show_price !== false && priceHtml ? '<div class="mp-cc-cart-item__price" data-secondary-block="price">' + priceHtml + '</div>' : '';
			topBlocks.sku = visibility.show_sku !== false && sku ? '<div class="mp-cc-cart-item__meta-row" data-secondary-block="sku"><dt>SKU</dt><dd>' + escapeHtml(sku) + '</dd></div>' : '';
			topBlocks.variation = visibility.show_variation !== false && variationText ? '<div class="mp-cc-cart-item__meta-row" data-secondary-block="variation"><dt>' + escapeHtml(getUiText('step_1.positions_count', 'Details')) + '</dt><dd>' + escapeHtml(variationText) + '</dd></div>' : '';
			for (var t = 0; t < order.length; t += 1) {
				var topKey = String(order[t] || '');
				if (topKey === 'price' && topBlocks.price) {
					html += topBlocks.price;
				}
			}
			var metaRows = '';
			for (var m = 0; m < order.length; m += 1) {
				var metaKey = String(order[m] || '');
				if (metaKey === 'sku' || metaKey === 'variation') {
					metaRows += topBlocks[metaKey] || '';
				}
			}
			if (metaRows) {
				html += '<dl class="mp-cc-cart-item__meta">' + metaRows + '</dl>';
			}
			html += '<div class="mp-cc-cart-item__footer">';
			var secondaryBlocks = {};
			secondaryBlocks.quantity = '';
			if (quantityControls.enabled !== false) {
				secondaryBlocks.quantity += '<div class="mp-cc-cart-item__qty-controls" data-secondary-block="quantity" role="group" aria-label="' + escapeHtml(getUiText('step_1.positions_count', 'Quantity')) + '">';
				if (quantityControls.show_decrement !== false) {
					secondaryBlocks.quantity += '<button type="button" class="mp-cc-qty-btn" data-qty-action="decrease" data-cart-qty-btn="-1" aria-label="Decrease quantity"' + (qty <= minQty ? ' disabled' : '') + '>−</button>';
				}
				if (quantityControls.allow_manual_input !== false) {
					secondaryBlocks.quantity += '<input class="mp-cc-qty-input" type="number" inputmode="numeric" min="' + escapeHtml(minQty) + '" max="' + escapeHtml(maxQty) + '" step="1" value="' + escapeHtml(qty) + '" data-cart-qty-input="1" aria-label="Quantity" />';
				} else {
					secondaryBlocks.quantity += '<span class="mp-cc-qty-static">' + escapeHtml(qty) + '</span>';
				}
				if (quantityControls.show_increment !== false) {
					secondaryBlocks.quantity += '<button type="button" class="mp-cc-qty-btn" data-qty-action="increase" data-cart-qty-btn="+1" aria-label="Increase quantity"' + (qty >= maxQty ? ' disabled' : '') + '>+</button>';
				}
				secondaryBlocks.quantity += '</div>';
			}
			secondaryBlocks.subtotal = visibility.show_subtotal !== false ? '<span class="mp-cc-cart-item__subtotal" data-secondary-block="subtotal">' + (subtotal || '—') + '</span>' : '';
			secondaryBlocks.remove = '<button type="button" class="mp-cc-cart-item__remove" data-secondary-block="remove" data-cart-remove="1" aria-label="Remove item">' + escapeHtml(getUiText('common.remove', 'Remove')) + '</button>';

			for (var o = 0; o < order.length; o += 1) {
				var blockKey = String(order[o] || '');
				if (secondaryBlocks[blockKey]) {
					html += secondaryBlocks[blockKey];
				}
			}
			html += '</div>';
			html += '</div>';
			html += '</article>';
		}

		html += '</div></section>';
		return html;
	}

	function buildNavHtml(state) {
		if (!isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			return '';
		}
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var isFirst = currentIndex <= 0;
		var isLast = currentIndex >= state.visibleSteps.length - 1;
		var isLoading = !!(state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.loading);
		var isPaymentSubmitting = isPaymentSubmissionLocked(state);
		var isDirty = !!(state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.dirty);
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		var isCartEmpty = (state.currentStepId === 'cart') && Number(cartSummary.items_count || 0) <= 0;
		var backLabel = getUiText('common.back', 'Back');
		var nextLabel = getUiText('common.next', 'Next');
		var payLabel = getUiText('common.pay', 'Proceed to payment');
		var confirmLabel = getUiText('common.confirm', 'Confirm');
		var reviewLabel = getUiText('order_review.pre_payment_cta', 'Проверить перед оплатой');
		var currentStepId = state.currentStepId || '';
		var nextText = nextLabel;
		var isPreReviewPending = false;
		if (isLast && currentStepId === 'contact_payment') {
			var preConfirm = !!(state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.prePaymentConfirm);
			if (!preConfirm) {
				nextText = reviewLabel;
				isPreReviewPending = true;
			} else {
				nextText = state.frontendStore && state.frontendStore.payment && state.frontendStore.payment.gateway ? confirmLabel : payLabel;
			}
		}
		var html = '';

		html += '<nav class="mp-cc-nav" aria-label="Step navigation">';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--back" data-nav="back"' + (isFirst || isLoading || isPaymentSubmitting ? ' disabled' : '') + '>' + escapeHtml(backLabel) + '</button>';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next' + (isPreReviewPending ? ' mp-cc-nav__btn--review' : '') + '" data-nav="next"' + (isLoading || isCartEmpty || isPaymentSubmitting ? ' disabled' : '') + '>' + escapeHtml(nextText) + '</button>';
		html += '</nav>';
		if (isPaymentSubmitting) {
			html += '<p class="mp-cc-nav__review-mode" role="status" aria-live="polite">' + escapeHtml(getUiText('order_review.payment_loading', 'Отправляем оплату, пожалуйста подождите...')) + '</p>';
		}
		if (isPreReviewPending) {
			html += '<p class="mp-cc-nav__review-mode" role="status" aria-live="polite">' + escapeHtml(getUiText('order_review.pre_payment_mode', 'Режим проверки: перед оплатой подтвердите данные на экране справа.')) + '</p>';
		}
		if (isDirty) {
			html += '<p class="mp-cc-nav__dirty" role="status" aria-live="polite">' + escapeHtml('Unsaved changes') + '</p>';
		}
		return html;
	}

	function buildSummaryHtml(state) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var total = state.visibleSteps.length;
		var snapshot = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.snapshot || {} : {};
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		var runtime = state.frontendStore && state.frontendStore.runtime ? state.frontendStore.runtime : {};
		var showPlaceholders = !runtime.summaryHydrated;
		var itemsCount = cartSummary.items_count || snapshot.items_count || 0;
		var subtotalText = cartSummary.subtotal || '';
		var totalText = cartSummary.total || snapshot.total || subtotalText || '';
		var displayAmount = state.currentStepId === 'cart' ? subtotalText : totalText;
		var amountLabel = state.currentStepId === 'cart'
			? getStepOneLabel(state, 'subtotal_label', 'step_1.subtotal', 'Subtotal')
			: getUiText('order_review.total', 'Total');
		var returnUrl = cartSummary.catalog_url ? String(cartSummary.catalog_url) : '/';
		var scenario = String(state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenario || '') : '');
		var scenarioRules = getScenarioRulesById(scenario);
		var scenarioLabel = scenarioRules && scenarioRules.label ? String(scenarioRules.label) : '';
		var couponLines = Array.isArray(cartSummary.coupon_lines) ? cartSummary.coupon_lines : [];
		var giftCardCodes = Array.isArray(cartSummary.applied_gift_cards) ? cartSummary.applied_gift_cards : [];
		var giftCardTotal = String(cartSummary.gift_card_total || '');
		var shippingText = String(cartSummary.shipping || '');
		var taxText = String(cartSummary.tax || '');
		var gatewayTitle = getSelectedGatewayTitle(state);
		var cartItems = state.frontendStore && state.frontendStore.cart && Array.isArray(state.frontendStore.cart.items) ? state.frontendStore.cart.items : [];
		var prePaymentConfirm = !!(runtime.prePaymentConfirm);
		var pickupPoint = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.scenarioData
			? (state.frontendStore.fulfillment.scenarioData.pickup_point || null)
			: null;
		var html = '';

		html += '<section class="mp-cc-summary-card" aria-label="Order summary panel">';
		html += '<h3 class="mp-cc-summary-card__title">' + escapeHtml(getStepOneLabel(state, 'summary_title', 'order_review.title', 'Order Summary')) + '</h3>';
		html += '<p class="mp-cc-summary-card__meta">Step ' + (currentIndex + 1) + ' of ' + total + '</p>';
		if (showPlaceholders) {
			html += '<div class="mp-cc-summary-card__placeholder" aria-hidden="true"></div>';
			html += '<div class="mp-cc-summary-card__placeholder mp-cc-summary-card__placeholder--sm" aria-hidden="true"></div>';
		} else {
			html += '<p class="mp-cc-summary-card__meta">' + escapeHtml(getStepOneLabel(state, 'items_label', 'step_1.positions_count', 'Items')) + ': <strong>' + escapeHtml(itemsCount) + '</strong></p>';
			if (displayAmount) {
				html += '<p class="mp-cc-summary-card__meta"><span class="mp-cc-summary-card__amount-label">' + escapeHtml(amountLabel) + ':</span> <span class="mp-cc-summary-card__amount" data-summary-amount="1">' + displayAmount + '</span></p>';
			}
		}
		if (state.currentStepId === 'cart') {
			html += '<div class="mp-cc-summary-card__actions">';
			html += '<button type="button" class="mp-cc-summary-card__btn mp-cc-summary-card__btn--primary" data-summary-action="continue">' + escapeHtml(getStepOneLabel(state, 'continue_label', 'step_1.continue', 'Continue')) + '</button>';
			html += '<a href="' + escapeHtml(returnUrl) + '" class="mp-cc-summary-card__btn mp-cc-summary-card__btn--ghost">' + escapeHtml(getStepOneLabel(state, 'return_label', 'step_1.return_to_shop', 'Return to shop')) + '</a>';
			html += '</div>';
		}
		if (state.currentStepId === 'contact_payment') {
			html += '<div class="mp-cc-summary-card__scenario mp-cc-summary-card__scenario--final-review" data-final-review-block="1">';
			html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('order_review.final_review_title', 'Финальный review перед оплатой')) + '</strong></p>';
			if (prePaymentConfirm) {
				html += '<p class="mp-cc-summary-card__scenario-meta mp-cc-summary-card__scenario-meta--ok">' + escapeHtml(getUiText('order_review.pre_payment_ready', 'Проверка завершена, можно запускать оплату.')) + '</p>';
			} else {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(getUiText('order_review.pre_payment_hint', 'Проверьте данные и нажмите кнопку подтверждения ещё раз.')) + '</p>';
			}
			html += '</div>';
			var contact = state.frontendStore && state.frontendStore.form ? (state.frontendStore.form.contact || {}) : {};
			var fullName = [contact.billing_last_name, contact.billing_first_name, contact.billing_patronymic]
				.filter(function (part) { return trimNonEmpty(part); })
				.join(' ');
			var gOpt = getGenderOptions();
			var genderLabel = String(contact.billing_gender || '') === 'male' ? gOpt.male : (String(contact.billing_gender || '') === 'female' ? gOpt.female : '');
			var contactAddress = [contact.country, contact.state, contact.city, contact.address_1, contact.address_2, contact.postcode]
				.filter(function (part) { return trimNonEmpty(part); })
				.join(', ');
			var hasContactReview = trimNonEmpty(fullName) || trimNonEmpty(genderLabel) || trimNonEmpty(contact.billing_birthdate) || trimNonEmpty(contact.billing_email) || trimNonEmpty(contact.billing_phone) || trimNonEmpty(contactAddress);
			if (hasContactReview) {
				html += '<div class="mp-cc-summary-card__scenario" data-final-review-contact="1">';
				html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('step_4.title', 'Контактные данные')) + '</strong></p>';
				if (trimNonEmpty(fullName)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_first_name', 'Получатель')) + ':</strong> ' + escapeHtml(fullName) + '</p>';
				}
				if (trimNonEmpty(contact.billing_email)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_email', 'Email')) + ':</strong> ' + escapeHtml(String(contact.billing_email)) + '</p>';
				}
				if (trimNonEmpty(contact.billing_phone)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_phone', 'Телефон')) + ':</strong> ' + escapeHtml(String(contact.billing_phone)) + '</p>';
				}
				if (trimNonEmpty(genderLabel)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_gender', 'Пол')) + ':</strong> ' + escapeHtml(genderLabel) + '</p>';
				}
				if (trimNonEmpty(contact.billing_birthdate)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_birthdate', 'Дата рождения')) + ':</strong> ' + escapeHtml(formatIsoDateForUi(String(contact.billing_birthdate))) + '</p>';
				}
				if (trimNonEmpty(contactAddress)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.address_block_title', 'Адрес')) + ':</strong> ' + escapeHtml(contactAddress) + '</p>';
				}
				html += '</div>';
			}
			if (couponLines.length || giftCardCodes.length) {
				html += '<div class="mp-cc-summary-card__scenario" data-final-review-discounts="1">';
				html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('order_review.discount', 'Скидка')) + '</strong></p>';
				for (var di = 0; di < couponLines.length; di += 1) {
					var line = couponLines[di] || {};
					html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml('Купон ' + String(line.code || '')) + ': ' + escapeHtml(String(line.amount || '')) + '</p>';
				}
				if (giftCardCodes.length) {
					html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml('Подарочная карта ' + giftCardCodes.join(', ')) + ': ' + escapeHtml(giftCardTotal || '—') + '</p>';
				}
				html += '</div>';
			}
			if (cartItems.length) {
				html += '<div class="mp-cc-summary-card__scenario" data-final-review-cart="1">';
				html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('order_review.items', 'Состав заказа')) + '</strong></p>';
				for (var ci = 0; ci < cartItems.length; ci += 1) {
					var item = cartItems[ci] || {};
					var rowTitle = String(item.name || getUiText('step_1.title', 'Товар'));
					var rowQty = Number(item.quantity || 0);
					var rowSubtotal = String(item.line_subtotal || '');
					html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(rowTitle) + ' × ' + escapeHtml(String(rowQty)) + (rowSubtotal ? ' — ' + escapeHtml(rowSubtotal) : '') + '</p>';
				}
				html += '</div>';
			}
			html += '<div class="mp-cc-summary-card__scenario" data-final-review-financials="1">';
			html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('order_review.financial', 'Итоги')) + '</strong></p>';
			if (trimNonEmpty(subtotalText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(getUiText('step_1.subtotal', 'Подытог')) + ': ' + escapeHtml(subtotalText) + '</p>';
			}
			if (trimNonEmpty(cartSummary.discount)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(getUiText('order_review.discount', 'Скидка')) + ': ' + escapeHtml(String(cartSummary.discount)) + '</p>';
			}
			if (trimNonEmpty(shippingText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(getUiText('order_review.shipping', 'Доставка')) + ': ' + escapeHtml(shippingText) + '</p>';
			}
			if (trimNonEmpty(taxText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(getUiText('order_review.tax', 'Налоги')) + ': ' + escapeHtml(taxText) + '</p>';
			}
			if (trimNonEmpty(totalText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('order_review.total', 'Итого')) + ':</strong> ' + escapeHtml(totalText) + '</p>';
			}
			html += '</div>';
			if (trimNonEmpty(gatewayTitle)) {
				html += '<div class="mp-cc-summary-card__scenario" data-final-review-gateway="1">';
				html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('step_4.payment_title', 'Способ оплаты')) + '</strong></p>';
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(gatewayTitle) + '</p>';
				html += '</div>';
			}
		}
		if (state.currentStepId === 'contact_payment' && scenarioLabel) {
			html += '<div class="mp-cc-summary-card__scenario" data-final-review-scenario="1">';
			html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('step_2.title', 'Способ получения')) + ':</strong> ' + escapeHtml(scenarioLabel) + '</p>';
			var selectedDate = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.date
				? String(state.frontendStore.fulfillment.date.selected_date || '')
				: '';
			if (selectedDate) {
				html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getStepThreeTitle()) + ':</strong> ' + escapeHtml(formatIsoDateForUi(selectedDate)) + '</p>';
			}
			if (scenario === 'pickup' && pickupPoint && pickupPoint.title) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(String(pickupPoint.title)) + '</p>';
				if (pickupPoint.address) {
					html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(String(pickupPoint.address)) + '</p>';
				}
			}
			var receiptPlain = buildConditionsReceiptPlainText(state);
			if (receiptPlain) {
				html += '<div class="mp-cc-summary-card__conditions" data-final-review-conditions="1">';
				html += '<p class="mp-cc-summary-card__conditions-title"><strong>' + escapeHtml(getUiText('step_3.conditions_title', 'Условия получения')) + '</strong></p>';
				html += '<div class="mp-cc-summary-card__conditions-body">' + formatConditionsReceiptHtmlFromPlain(receiptPlain) + '</div>';
				html += '</div>';
			}
			html += '</div>';
		}
		html += '<div class="mp-cc-summary-card__slot" data-mp-cc-summary-slot="1"></div>';
		html += '</section>';

		return html;
	}

	function notify(message, level) {
		var container = document.querySelector(selectors.notifications);
		if (!container) {
			return;
		}
		var safeMessage = escapeHtml(message || '');
		var safeLevel = level === 'error' ? 'error' : 'info';
		container.innerHTML = '<div class="mp-cc-notice mp-cc-notice--' + safeLevel + '" role="alert">' + safeMessage + '</div>';
	}

	function focusStepHeading($app) {
		var heading = $app.find('#mp-cc-step-heading').get(0);
		if (!heading || typeof heading.focus !== 'function') {
			return;
		}
		try {
			heading.focus({ preventScroll: true });
		} catch (e) {
			heading.focus();
		}
	}

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function runStepTransitionAnimation($app) {
		if (prefersReducedMotion()) {
			return;
		}
		$app.addClass('is-step-transition');
		window.setTimeout(function () {
			$app.removeClass('is-step-transition');
		}, animationDurationMs);
	}

	function applyStepOnePresentation(state) {
		var root = document.querySelector(selectors.root);
		if (!root || !state || !state.stepOneConfig) {
			return;
		}
		var cfg = state.stepOneConfig;
		var styles = cfg.style_controls || {};
		var responsive = cfg.responsive || {};
		root.classList.toggle('mp-cc-step1-card-compact', Boolean(styles.card_compact));
		root.classList.toggle('mp-cc-step1-hide-media-mobile', Boolean(responsive.hide_media_mobile));
		root.classList.toggle('mp-cc-step1-card-emphasis-elevated', String(styles.card_emphasis || '') === 'elevated');
		root.classList.toggle('mp-cc-step1-summary-emphasis-elevated', String(styles.summary_emphasis || '') === 'elevated');

		var responsiveClasses = [
			'mp-cc-step1-desktop-comfortable', 'mp-cc-step1-desktop-compact',
			'mp-cc-step1-tablet-comfortable', 'mp-cc-step1-tablet-compact',
			'mp-cc-step1-mobile-comfortable', 'mp-cc-step1-mobile-compact'
		];
		for (var i = 0; i < responsiveClasses.length; i += 1) {
			root.classList.remove(responsiveClasses[i]);
		}
		root.classList.add('mp-cc-step1-desktop-' + (responsive.desktop_mode === 'compact' ? 'compact' : 'comfortable'));
		root.classList.add('mp-cc-step1-tablet-' + (responsive.tablet_mode === 'compact' ? 'compact' : 'comfortable'));
		root.classList.add('mp-cc-step1-mobile-' + (responsive.mobile_mode === 'comfortable' ? 'comfortable' : 'compact'));
	}

	function render(state, $app) {
		var $progress = $(selectors.progress);
		var $actions = $(selectors.actions);
		var $summary = $(selectors.summary);

		if (!state.visibleSteps.length) {
			$app.html('<p class="mp-cc-empty">No steps available.</p>');
			$progress.empty();
			$actions.empty();
			$summary.empty();
			return;
		}
		ensureDateSelection(state);
		ensureContactDefaults(state);
		ensureDiscountDefaults(state);

		$app.html(buildStepPanelHtml(state));
		$summary.html(buildSummaryHtml(state));
		applyStepOnePresentation(state);
		animateSummaryUpdate(state, $summary);
		if (isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			$progress.html(buildProgressHtml(state));
			$actions.html(buildNavHtml(state));
		} else {
			$progress.empty();
			$actions.empty();
		}
		bindHandlers(state, $app, $progress, $actions);
		focusStepHeading($app);

		document.dispatchEvent(
			new CustomEvent('mp_cc_store_synced', {
				detail: {
					contextId: state.flowContextId,
					store: state.frontendStore
				}
			})
		);
	}

	function bindHandlers(state, $app, $progress, $actions) {
		if (!isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			return;
		}

		$actions.find('[data-nav="back"]').off('click').on('click', function () {
			moveBackward(state, $app);
		});

		$actions.find('[data-nav="next"]').off('click').on('click', function () {
			saveCurrentStepDraft(state).always(function () {
				moveForward(state, $app);
			});
		});

		$(selectors.summary).find('[data-summary-action="continue"]').off('click').on('click', function () {
			saveCurrentStepDraft(state).always(function () {
				moveForward(state, $app);
			});
		});

		$progress.find('.mp-cc-progress__btn').off('click').on('click', function () {
			var target = $(this).data('step');
			if (!target) {
				return;
			}
			var targetIndex = getStepIndex(state.visibleSteps, target);
			if (targetIndex < 0 || targetIndex > state.maxReachedIndex) {
				return;
			}
			setCurrentStep(state, $app, String(target));
		});

		$app.find('[data-cart-qty-btn]').off('click').on('click', function () {
			var $btn = $(this);
			var $item = $btn.closest('[data-cart-item-key]');
			if (!$item.length) {
				return;
			}
			var localItem = getLocalCartItem(state, String($item.data('cart-item-key') || ''));
			var current = Number(localItem && localItem.quantity ? localItem.quantity : 0);
			var delta = Number($btn.data('cart-qty-btn') || 0);
			if (!delta) {
				return;
			}
			applyQuantityChange(state, $app, $item, current + delta);
		});

		$app.find('[data-cart-qty-input]').off('change blur').on('change blur', function () {
			var $input = $(this);
			var $item = $input.closest('[data-cart-item-key]');
			if (!$item.length) {
				return;
			}
			applyQuantityChange(state, $app, $item, Number($input.val() || 0));
		});

		$app.find('[data-cart-remove]').off('click').on('click', function () {
			var $btn = $(this);
			var $item = $btn.closest('[data-cart-item-key]');
			if (!$item.length) {
				return;
			}
			applyRemoveItem(state, $app, $item);
		});

		$app.find('[data-scenario-card]').off('click').on('click', function () {
			var targetScenario = String($(this).data('scenario-target') || '');
			if (!targetScenario) {
				return;
			}
			targetScenario = normalizeScenarioId(targetScenario);
			if (String(state.frontendStore.fulfillment.scenario || '') === targetScenario) {
				return;
			}
			resetDependentStateForScenario(state, targetScenario);
			render(state, $app);
			document.dispatchEvent(
				new CustomEvent('mp_cc_scenario_changed', {
					detail: { scenario: targetScenario }
				})
			);
		});

		$app.find('[data-scenario-card]').off('keydown').on('keydown', function (event) {
			var key = event.key || '';
			var $cards = $app.find('[data-scenario-card]');
			var currentIndex = $cards.index(this);
			var nextIndex = currentIndex;
			if (key === 'ArrowRight' || key === 'ArrowDown') {
				nextIndex = Math.min($cards.length - 1, currentIndex + 1);
				event.preventDefault();
			} else if (key === 'ArrowLeft' || key === 'ArrowUp') {
				nextIndex = Math.max(0, currentIndex - 1);
				event.preventDefault();
			} else if (key === ' ' || key === 'Enter') {
				$(this).trigger('click');
				event.preventDefault();
				return;
			} else {
				return;
			}
			var $next = $cards.eq(nextIndex);
			if ($next.length) {
				$next.trigger('click');
				$next.trigger('focus');
			}
		});

		$app.find('[data-pickup-point]').off('click').on('click', function () {
			var pointId = String($(this).data('pickup-point') || '');
			if (!pointId) {
				return;
			}
			var point = getPickupPointById(pointId);
			if (!point) {
				return;
			}
			var scenarioData = state.frontendStore.fulfillment.scenarioData || {};
			scenarioData.pickup_point = point;
			state.frontendStore.fulfillment.scenarioData = scenarioData;
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'scenario',
				context_id: state.flowContextId,
				answers: scenarioData
			}).fail(function () {
				notify('Не удалось сохранить точку самовывоза.', 'error');
			});
		});

		$app.find('[data-calendar-date]').off('click').on('click', function () {
			var $btn = $(this);
			if ($btn.is(':disabled')) {
				return;
			}
			var value = String($btn.data('calendar-date') || '');
			if (!value) {
				return;
			}
			var dateBox = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
				? state.frontendStore.fulfillment.date
				: {};
			dateBox.selected_date = value;
			var parsed = parseIsoDate(value);
			if (parsed) {
				dateBox.calendar_month = monthKeyFromDate(parsed);
			}
			state.frontendStore.fulfillment.date = dateBox;
			state.frontendStore.form.errors = state.frontendStore.form.errors || {};
			state.frontendStore.form.errors.date = '';
			setStepInvalidState(state, 'date', false);
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'date',
				context_id: state.flowContextId,
				answers: dateBox
			}).then(function (response) {
				if (!response || !response.success || !response.data) {
					throw new Error('date_save_empty_response');
				}
				if (response.data.flow) {
					syncFromFlow(state, response.data.flow, response.data.cart || {});
					render(state, $app);
				}
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				var message = payload.message || getStepThreeErrorCopy('invalid_date', 'Не удалось сохранить выбранную дату.');
				notify(message, 'error');
				syncStoreWithBackend(state, $app);
			});
		});

		$app.find('[data-calendar-nav]').off('click').on('click', function () {
			var shift = Number($(this).data('calendar-nav') || 0);
			if (!shift) {
				return;
			}
			var dateBox = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
				? state.frontendStore.fulfillment.date
				: {};
			var model = buildDateCalendarModel(state);
			var currentMonth = parseMonthKey(dateBox.calendar_month || model.monthKey);
			if (!currentMonth) {
				return;
			}
			var shiftedMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + shift, 1);
			var minMonth = parseMonthKey(model.minMonthKey);
			var maxMonth = parseMonthKey(model.maxMonthKey);
			if (minMonth && shiftedMonth < minMonth) {
				shiftedMonth = minMonth;
			}
			if (maxMonth && shiftedMonth > maxMonth) {
				shiftedMonth = maxMonth;
			}
			dateBox.calendar_month = monthKeyFromDate(shiftedMonth);
			state.frontendStore.fulfillment.date = dateBox;
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'date',
				context_id: state.flowContextId,
				answers: dateBox
			}).fail(function () {
				notify(getStepThreeErrorCopy('invalid_date', 'Не удалось сохранить выбранную дату.'), 'error');
			});
		});

		$app.find('[data-calendar-grid]').off('keydown').on('keydown', function (event) {
			var key = String(event.key || '');
			var $cells = $app.find('[data-calendar-date]').filter(function () {
				return !$(this).is(':disabled');
			});
			var current = document.activeElement;
			var currentIndex = $cells.index(current);
			if (currentIndex < 0) {
				return;
			}
			var nextIndex = currentIndex;
			if (key === 'ArrowRight') {
				nextIndex = Math.min($cells.length - 1, currentIndex + 1);
			} else if (key === 'ArrowLeft') {
				nextIndex = Math.max(0, currentIndex - 1);
			} else if (key === 'ArrowDown') {
				nextIndex = Math.min($cells.length - 1, currentIndex + 7);
			} else if (key === 'ArrowUp') {
				nextIndex = Math.max(0, currentIndex - 7);
			} else if (key === 'Home') {
				nextIndex = 0;
			} else if (key === 'End') {
				nextIndex = $cells.length - 1;
			} else if (key === 'Enter' || key === ' ') {
				$(current).trigger('click');
				event.preventDefault();
				return;
			} else {
				return;
			}
			event.preventDefault();
			var $target = $cells.eq(nextIndex);
			if ($target.length) {
				$target.trigger('focus');
			}
		});

		$app.find('[data-conditions-confirm]').off('change').on('change', function () {
			var isChecked = $(this).is(':checked');
			var dateBox = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
				? state.frontendStore.fulfillment.date
				: {};
			dateBox.conditions_confirmed = isChecked;
			state.frontendStore.fulfillment.date = dateBox;
			state.frontendStore.form.errors.conditions_unconfirmed = false;
			setStepInvalidState(state, 'conditions', false);
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'conditions',
				context_id: state.flowContextId,
				answers: dateBox
			}).fail(function () {
				notify(getUiText('common.error_generic', 'Произошла ошибка. Попробуйте ещё раз.'), 'error');
			});
		});

		$app.find('[data-contact-field]').off('input blur').on('input', function () {
			var key = String($(this).data('contact-field') || '');
			if (!key) {
				return;
			}
			var contact = state.frontendStore.form.contact || {};
			var val = $(this).val();
			if (key === 'order_notes') {
				var settings = getOrderNotesSettings();
				val = String(val || '');
				if (val.length > settings.maxLength) {
					val = val.slice(0, settings.maxLength);
					$(this).val(val);
				}
			}
			contact[key] = val;
			resetPrePaymentConfirm(state);
			state.frontendStore.form.contact = contact;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact[key];
			}
			contact.billing_phone = buildFullPhoneE164(contact);
			if (key === 'order_notes') {
				var cfgNotes = getOrderNotesSettings();
				var remain = Math.max(0, cfgNotes.maxLength - String(val || '').length);
				$app.find('[data-order-notes-counter="1"]').text('Осталось символов: ' + String(remain));
			}
			scheduleCurrentStepDraftSave(state, function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		}).on('blur', function () {
			ensureContactDefaults(state);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		});

		$app.find('[data-contact-phone-national]').off('input blur').on('input', function () {
			var $inp = $(this);
			var cfg = getStepFourConfig();
			var codes = cfg.contact_block && cfg.contact_block.phone_country_codes ? cfg.contact_block.phone_country_codes : [];
			var contact = state.frontendStore.form.contact || {};
			var meta = findPhoneCountryMeta(codes, contact.phone_country_iso);
			var maxLen = meta.national_digits || 10;
			var raw = String($inp.val() || '').replace(/\D/g, '').slice(0, maxLen);
			contact.billing_phone_national = raw;
			contact.billing_phone = buildFullPhoneE164(contact);
			state.frontendStore.form.contact = contact;
			var display = formatNationalPhoneDisplay(meta.dial, raw);
			if ($inp.val() !== display) {
				$inp.val(display);
			}
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.billing_phone_national;
			}
			scheduleCurrentStepDraftSave(state, function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		}).on('blur', function () {
			ensureContactDefaults(state);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		});

		$app.find('[data-payment-gateway]').off('change').on('change', function () {
			var gateway = trimNonEmpty($(this).val());
			if (!gateway) {
				return;
			}
			state.frontendStore.payment = state.frontendStore.payment || { gateway: '', state: 'idle' };
			state.frontendStore.payment.gateway = gateway;
			state.frontendStore.payment.state = 'syncing';
			resetPrePaymentConfirm(state);
			state.frontendStore.form.contact = state.frontendStore.form.contact || {};
			state.frontendStore.form.contact.payment_gateway = gateway;
			state.frontendStore.form.contact.gateway = gateway;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.payment_gateway;
			}
			render(state, $app);
			postCheckout('set_payment_gateway', {
				gateway: gateway,
				context_id: state.flowContextId
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				state.frontendStore.payment.state = 'success';
				if (data.flow || data.cart) {
					syncFromFlow(state, data.flow || {}, data.cart || {});
				}
				render(state, $app);
			}).fail(function (xhr) {
				state.frontendStore.payment.state = 'error';
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				var paymentCfg = getStepFourConfig().payment_block || {};
				var paymentMessages = paymentCfg.messages && typeof paymentCfg.messages === 'object' ? paymentCfg.messages : {};
				notify(trimNonEmpty(payload.message) || trimNonEmpty(paymentMessages.error) || getUiText('step_4.payment_error_switch', 'Не удалось переключить способ оплаты.'), 'error');
				syncStoreWithBackend(state, $app);
			});
		});

		$app.find('[data-coupon-code]').off('input').on('input', function () {
			ensureDiscountDefaults(state);
			var discounts = state.frontendStore.discounts || {};
			var rt = discounts.coupon_runtime || { code: '', state: 'empty', message: '' };
			rt.code = String($(this).val() || '');
			if (trimNonEmpty(rt.code)) {
				rt.state = 'empty';
				rt.message = '';
			}
			discounts.coupon_runtime = rt;
			state.frontendStore.discounts = discounts;
		});

		$app.find('[data-coupon-apply]').off('click').on('click', function () {
			ensureDiscountDefaults(state);
			var discounts = state.frontendStore.discounts || {};
			var rt = discounts.coupon_runtime || { code: '', state: 'empty', message: '' };
			var copy = getCouponCopy();
			var code = trimNonEmpty(rt.code);
			if (!code) {
				rt.state = 'error';
				rt.message = copy.emptyMessage;
				discounts.coupon_runtime = rt;
				state.frontendStore.discounts = discounts;
				render(state, $app);
				return;
			}
			postCheckout('apply_coupon', {
				coupon_code: code,
				context_id: state.flowContextId
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				rt.state = 'success';
				rt.message = trimNonEmpty(data.message) || copy.successMessage;
				discounts.coupons = Array.isArray(data.applied_coupons) ? data.applied_coupons : discounts.coupons;
				discounts.coupon_runtime = rt;
				state.frontendStore.discounts = discounts;
				if (data.flow || data.cart) {
					syncFromFlow(state, data.flow || {}, data.cart || {});
				}
				render(state, $app);
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				rt.state = 'error';
				rt.message = trimNonEmpty(payload.message) || copy.errorMessage;
				if (Array.isArray(payload.applied_coupons)) {
					discounts.coupons = payload.applied_coupons;
				}
				discounts.coupon_runtime = rt;
				state.frontendStore.discounts = discounts;
				if (payload.flow || payload.cart) {
					syncFromFlow(state, payload.flow || {}, payload.cart || {});
				}
				render(state, $app);
				notify(rt.message, 'error');
			});
		});

		$app.find('[data-gift-card-code]').off('input').on('input', function () {
			ensureDiscountDefaults(state);
			var discounts = state.frontendStore.discounts || {};
			var rt = discounts.gift_card_runtime || { code: '', state: 'empty', message: '' };
			rt.code = String($(this).val() || '');
			if (trimNonEmpty(rt.code)) {
				rt.state = 'empty';
				rt.message = '';
			}
			discounts.gift_card_runtime = rt;
			state.frontendStore.discounts = discounts;
		});

		$app.find('[data-gift-card-apply]').off('click').on('click', function () {
			ensureDiscountDefaults(state);
			var discounts = state.frontendStore.discounts || {};
			var rt = discounts.gift_card_runtime || { code: '', state: 'empty', message: '' };
			var copy = getGiftCardCopy();
			var code = trimNonEmpty(rt.code);
			if (!code) {
				rt.state = 'error';
				rt.message = copy.emptyMessage;
				discounts.gift_card_runtime = rt;
				state.frontendStore.discounts = discounts;
				render(state, $app);
				return;
			}
			postCheckout('apply_gift_card', {
				gift_card_code: code,
				context_id: state.flowContextId
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				rt.state = 'success';
				rt.message = trimNonEmpty(data.message) || copy.successMessage;
				discounts.gift_card = Array.isArray(data.applied_gift_cards) ? data.applied_gift_cards : discounts.gift_card;
				discounts.gift_card_runtime = rt;
				state.frontendStore.discounts = discounts;
				if (data.flow || data.cart) {
					syncFromFlow(state, data.flow || {}, data.cart || {});
				}
				render(state, $app);
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				rt.state = 'error';
				rt.message = trimNonEmpty(payload.message) || copy.errorMessage;
				if (Array.isArray(payload.applied_gift_cards)) {
					discounts.gift_card = payload.applied_gift_cards;
				}
				discounts.gift_card_runtime = rt;
				state.frontendStore.discounts = discounts;
				if (payload.flow || payload.cart) {
					syncFromFlow(state, payload.flow || {}, payload.cart || {});
				}
				render(state, $app);
				notify(rt.message, 'error');
			});
		});

		$app.find('[data-contact-phone-country]').off('change').on('change', function () {
			var iso = String($(this).val() || '');
			var contact = state.frontendStore.form.contact || {};
			contact.phone_country_iso = iso;
			var cfg = getStepFourConfig();
			var codes = cfg.contact_block && cfg.contact_block.phone_country_codes ? cfg.contact_block.phone_country_codes : [];
			var meta = findPhoneCountryMeta(codes, iso);
			contact.phone_dial_code = meta.dial;
			var raw = String(contact.billing_phone_national || '').replace(/\D/g, '');
			raw = raw.slice(0, meta.national_digits || 10);
			contact.billing_phone_national = raw;
			contact.billing_phone = buildFullPhoneE164(contact);
			state.frontendStore.form.contact = contact;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.billing_phone_national;
			}
			render(state, $app);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		});

		$app.find('[data-address-country]').off('change').on('change', function () {
			var v = String($(this).val() || '');
			var contact = state.frontendStore.form.contact || {};
			contact.country = v;
			contact.state = '';
			contact.city = '';
			state.frontendStore.form.contact = contact;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.country;
				delete state.frontendStore.form.errors.contact.state;
				delete state.frontendStore.form.errors.contact.city;
			}
			render(state, $app);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		});

		$app.find('[data-address-region]').off('change').on('change', function () {
			var v = String($(this).val() || '');
			var contact = state.frontendStore.form.contact || {};
			contact.state = v;
			contact.city = '';
			state.frontendStore.form.contact = contact;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.state;
				delete state.frontendStore.form.errors.contact.city;
			}
			render(state, $app);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		});

		$app.find('[data-address-city]').off('change').on('change', function () {
			var v = String($(this).val() || '');
			var contact = state.frontendStore.form.contact || {};
			contact.city = v;
			state.frontendStore.form.contact = contact;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.city;
			}
			render(state, $app);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		});
	}

	function clampQuantity(nextQty, minQty, maxQty) {
		var safeQty = Number(nextQty || 0);
		if (!Number.isFinite(safeQty)) {
			safeQty = minQty;
		}
		safeQty = Math.round(safeQty);
		if (safeQty < minQty) {
			safeQty = minQty;
		}
		if (safeQty > maxQty) {
			safeQty = maxQty;
		}
		return safeQty;
	}

	function updateLocalCartItem(state, itemKey, quantity, lineSubtotal) {
		if (!state || !state.frontendStore || !state.frontendStore.cart || !Array.isArray(state.frontendStore.cart.items)) {
			return;
		}
		for (var i = 0; i < state.frontendStore.cart.items.length; i += 1) {
			if (String(state.frontendStore.cart.items[i].key || '') === String(itemKey || '')) {
				state.frontendStore.cart.items[i].quantity = quantity;
				if (typeof lineSubtotal === 'string' && lineSubtotal !== '') {
					state.frontendStore.cart.items[i].line_subtotal = lineSubtotal;
				}
				break;
			}
		}
	}

	function getLocalCartItem(state, itemKey) {
		if (!state || !state.frontendStore || !state.frontendStore.cart || !Array.isArray(state.frontendStore.cart.items)) {
			return null;
		}
		for (var i = 0; i < state.frontendStore.cart.items.length; i += 1) {
			if (String(state.frontendStore.cart.items[i].key || '') === String(itemKey || '')) {
				return state.frontendStore.cart.items[i];
			}
		}
		return null;
	}

	function removeLocalCartItem(state, itemKey) {
		if (!state || !state.frontendStore || !state.frontendStore.cart || !Array.isArray(state.frontendStore.cart.items)) {
			return false;
		}
		var items = state.frontendStore.cart.items;
		for (var i = 0; i < items.length; i += 1) {
			if (String(items[i].key || '') === String(itemKey || '')) {
				items.splice(i, 1);
				state.frontendStore.cart.summary.items_count = Math.max(0, Number(state.frontendStore.cart.summary.items_count || 0) - 1);
				return true;
			}
		}
		return false;
	}

	function applyQuantityChange(state, $app, $item, requestedQty) {
		var itemKey = String($item.data('cart-item-key') || '');
		var $input = $item.find('[data-cart-qty-input]');
		var $decrease = $item.find('[data-qty-action="decrease"]');
		var $increase = $item.find('[data-qty-action="increase"]');
		if (!itemKey) {
			return;
		}

		var localItem = getLocalCartItem(state, itemKey);
		if (!localItem) {
			return;
		}
		var minQty = Number(localItem.min_quantity || ($input.length ? $input.attr('min') : 1) || 1);
		var maxQty = Number(localItem.max_quantity || ($input.length ? $input.attr('max') : 9999) || 9999);
		var prevQty = Number(localItem && localItem.quantity ? localItem.quantity : minQty);
		var nextQty = clampQuantity(requestedQty, minQty, maxQty);
		if (nextQty === prevQty || state.isTransitioning) {
			if ($input.length) {
				$input.val(nextQty);
			}
			return;
		}
		$item.addClass('is-updating');
		if ($input.length) {
			$input.val(nextQty);
		}
		$decrease.prop('disabled', nextQty <= minQty);
		$increase.prop('disabled', nextQty >= maxQty);
		updateLocalCartItem(state, itemKey, nextQty, '');
		render(state, $app);

		postCheckout('update_quantity', {
			context_id: state.flowContextId,
			item_key: itemKey,
			quantity: nextQty
		}).then(function (response) {
			if (!response || !response.success || !response.data) {
				throw new Error('update_quantity_failed');
			}
			var payload = response.data;
			var nextFlow = payload.flow || state.context.checkout_flow || {};
			var nextCart = normalizeCartPayload(payload.cart || {});
			syncFromFlow(state, nextFlow, nextCart);
			if (payload.item && payload.item.key) {
				updateLocalCartItem(state, payload.item.key, Number(payload.item.quantity || nextQty), String(payload.item.line_subtotal || ''));
			}
			render(state, $app);
		}).fail(function (xhr) {
			var errorPayload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			var message = errorPayload.message || 'Не удалось обновить количество. Попробуйте снова.';
			updateLocalCartItem(state, itemKey, prevQty, '');
			notify(message, 'error');
			syncStoreWithBackend(state, $app);
		}).always(function () {
			$item.removeClass('is-updating');
		});
	}

	function applyRemoveItem(state, $app, $item) {
		var itemKey = String($item.data('cart-item-key') || '');
		if (!itemKey || state.isTransitioning) {
			return;
		}

		var existingItem = getLocalCartItem(state, itemKey);
		if (!existingItem) {
			return;
		}
		var snapshotItem = $.extend({}, existingItem);
		var previousItemsCount = Number(state.frontendStore.cart.summary.items_count || 0);
		var previousSubtotal = String(state.frontendStore.cart.summary.subtotal || '');
		var previousFlowTotal = String(state.frontendStore.cart.snapshot && state.frontendStore.cart.snapshot.total ? state.frontendStore.cart.snapshot.total : '');

		$item.addClass('is-removing');
		removeLocalCartItem(state, itemKey);
		if (Number(state.frontendStore.cart.summary.items_count || 0) === 0) {
			state.frontendStore.cart.summary.subtotal = '';
			if (state.frontendStore.cart.snapshot && typeof state.frontendStore.cart.snapshot === 'object') {
				state.frontendStore.cart.snapshot.total = '';
			}
		}
		render(state, $app);

		postCheckout('remove_item', {
			context_id: state.flowContextId,
			item_key: itemKey
		}).then(function (response) {
			if (!response || !response.success || !response.data) {
				throw new Error('remove_item_failed');
			}
			var payload = response.data;
			var nextFlow = payload.flow || state.context.checkout_flow || {};
			var nextCart = normalizeCartPayload(payload.cart || {});
			syncFromFlow(state, nextFlow, nextCart);
			render(state, $app);
			if (payload.is_empty) {
				notify(getUiText('step_1.empty_cart', 'Cart is empty'), 'info');
			}
		}).fail(function (xhr) {
			var errorPayload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			var message = errorPayload.message || 'Не удалось удалить позицию. Попробуйте снова.';
			state.frontendStore.cart.items.push(snapshotItem);
			state.frontendStore.cart.summary.items_count = previousItemsCount;
			state.frontendStore.cart.summary.subtotal = previousSubtotal;
			if (state.frontendStore.cart.snapshot && typeof state.frontendStore.cart.snapshot === 'object') {
				state.frontendStore.cart.snapshot.total = previousFlowTotal;
			}
			render(state, $app);
			notify(message, 'error');
			syncStoreWithBackend(state, $app);
		});
	}

	function animateSummaryUpdate(state, $summary) {
		if (!$summary || !$summary.length || !state || !state.frontendStore || !state.frontendStore.meta) {
			return;
		}
		var cartSummary = state.frontendStore.cart && state.frontendStore.cart.summary ? state.frontendStore.cart.summary : {};
		var snapshot = state.frontendStore.cart && state.frontendStore.cart.snapshot ? state.frontendStore.cart.snapshot : {};
		var signature = String(cartSummary.items_count || 0) + '|' + String(cartSummary.subtotal || '') + '|' + String(snapshot.total || '');
		var prevSignature = String(state.frontendStore.meta.lastSummarySignature || '');
		state.frontendStore.meta.lastSummarySignature = signature;
		if (!prevSignature || prevSignature === signature) {
			return;
		}
		$summary.find('[data-summary-amount]').addClass('is-updated');
		window.setTimeout(function () {
			$summary.find('[data-summary-amount]').removeClass('is-updated');
		}, 320);
	}

	function isFlagEnabled(state, flag, fallback) {
		if (!state || !state.featureFlags || typeof state.featureFlags !== 'object') {
			return Boolean(fallback);
		}
		if (!Object.prototype.hasOwnProperty.call(state.featureFlags, flag)) {
			return Boolean(fallback);
		}
		return Boolean(state.featureFlags[flag]);
	}

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	$(function () {
		var $app = $(selectors.app);
		if (!$app.length) {
			return;
		}

		var context = parseContext();
		applyThemeVariant(context);
		var state = buildState(context);
		if (!state.frontendStore.fulfillment.scenario) {
			state.frontendStore.fulfillment.scenario = 'pickup';
		}
		applyScenarioFieldAvailability(state);
		ensurePickupScenarioData(state);
		ensureDateSelection(state);
		render(state, $app);

		syncStoreWithBackend(state, $app).fail(function () {
			notify(getStepFourAjaxMessage('step_sync_failed', 'step_4.contact_ajax_step_sync_failed', 'Не удалось синхронизировать шаг. Обновите страницу.'), 'error');
		});

		document.addEventListener('mp_cc_scenario_changed', function (event) {
			var nextScenario = event && event.detail ? String(event.detail.scenario || '') : '';
			if (!nextScenario) {
				return;
			}
			nextScenario = normalizeScenarioId(nextScenario);

			withTransitionLock(state, $app, function () {
				return postCheckout('session_set_scenario', { scenario: nextScenario, context_id: state.flowContextId })
					.then(function () {
						return syncStoreWithBackend(state, $app);
					}).fail(function (xhr) {
						var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
						notify(payload.message || getStepFourAjaxMessage('scenario_sync_failed', 'step_4.contact_ajax_scenario_sync_failed', 'Не удалось сохранить выбор сценария.'), 'error');
						document.dispatchEvent(
							new CustomEvent('mp_cc_scenario_error', {
								detail: { scenario: nextScenario, payload: payload }
							})
						);
						syncStoreWithBackend(state, $app);
					});
			});
		});

		document.addEventListener('mp_cc_store_update', function (event) {
			var detail = event && event.detail ? event.detail : {};
			var bucket = detail.bucket ? String(detail.bucket) : '';
			var payload = detail.payload || {};
			if (!bucket) {
				return;
			}
			if (bucket === 'step_one') {
				state.frontendStore.cart.snapshot = payload;
			} else if (bucket === 'date_conditions') {
				state.frontendStore.fulfillment.date = payload;
			} else if (bucket === 'contact_billing') {
				var cleanContact = {};
				var pk;
				for (pk in payload) {
					if (!Object.prototype.hasOwnProperty.call(payload, pk)) {
						continue;
					}
					if (pk.indexOf('__') === 0) {
						continue;
					}
					cleanContact[pk] = payload[pk];
				}
				state.frontendStore.form.contact = cleanContact;
				applyScenarioFieldAvailability(state);
				if (payload && typeof payload === 'object') {
					state.frontendStore.payment.gateway = payload.payment_gateway || payload.gateway || '';
				}
			} else if (bucket === 'scenario') {
				state.frontendStore.fulfillment.scenarioData = payload;
			} else if (bucket === 'discounts') {
				state.frontendStore.discounts = payload;
			}
			setRuntimeFlag(state, 'dirty', true);
		});

		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'hidden') {
				saveCurrentStepDraft(state);
			}
		});

		document.addEventListener('mp_cc_checkout_success', function () {
			setRuntimeFlag(state, 'success', true);
		});
	});
})(jQuery);
