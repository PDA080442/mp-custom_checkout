# СДЭК + кастомный checkout MP: чеклист

Краткая памятка для паритета с классическим WooCommerce checkout (`/zakaz/`) и плагином **CDEKDelivery** (`official_cdek`). Подробнее: [shipping-wc-integration.md](./shipping-wc-integration.md), эталонный код в каталоге `cdek-wordpress-plugin/` репозитория.

### Важно: что включать в админке WordPress

- **Включайте только** плагин **MP Custom Checkout** — главный файл: `wp-content/plugins/mp-custom_checkout/mp-custom-checkout.php`.
- Папка **`mp-custom_checkout/cdek-wordpress-plugin/`** — **справочная копия исходников** (`src/` и т.д.). Корневой **`cdek.php`** в этой копии — **заглушка без заголовка плагина**, чтобы WordPress не регистрировал вложенный «второй плагин» и не было путаницы с активацией. Рабочий СДЭК на сайте — только **официальный** архив с `vendor/`, отдельная папка в `wp-content/plugins/`.
- **СДЭК на сайте:** установите **официальный** архив CDEKDelivery для WooCommerce (с сайта СДЭК / полный релиз с `vendor`) в `wp-content/plugins/` **отдельной** папкой, как обычный плагин — не внутрь `mp-custom_checkout`.

## 1. Сравнение в браузере (Network)

На одном стенде откройте **два URL** с одной и той же корзиной и сценарием «другой город»:

| Действие | `/zakaz/` (классика) | `/mp-checkout/` (MP) |
|----------|----------------------|----------------------|
| После ввода города | В ответах WC / пересчёте корзины есть ставки `official_cdek:<instance_id>` | В AJAX `action=mp_cc_checkout`, `sub_action=session_set_answers` / `session_get_state` в `data.cart` те же `wc_shipping_rates` (ids совпадают с эталоном при `delivery.pricing_mode = woocommerce` и заполненных `wc_rate_id`) |
| ПВЗ | Запрос `wc-ajax=official_cdek_save-office` с полем `code` (см. `SaveOfficeToSessionAction`) | `sub_action=cdek_set_office` с `office_code` + `context_id` **или** вызов `window.mpCcSetCdekOfficeCode(code)` после выбора точки на своей карте/виджете |
| Итог доставки | Сессия `chosen_shipping_methods`, пакеты с `_official_cdek_office_code` в destination (фильтр `woocommerce_cart_shipping_packages`) | После сохранения шага доставки и после `cdek_set_office` — пересчёт через `WcCustomerShippingSync` + `CdekWcSessionBridge` |

**Критерий:** для одного адреса (например, Москва) в MP в `wc_shipping_rates` должны быть те же `id`, что на `/zakaz/`, при совпадении веса/корзины и настроек метода СДЭК в WC.

## 2. Настройки MP (каталог и цены)

1. **Каталог доставки** (`delivery.shipping_catalog`): для каждого метода/тарифа СДЭК задайте **`wc_rate_id`** ровно как в WC, например `official_cdek:137`, `official_cdek:368`.
2. **Режим цен:** для полного совпадения итогов с WC установите **`delivery.pricing_mode` = `woocommerce`** (см. [shipping-wc-integration.md §2](./shipping-wc-integration.md)).
3. На проде плагин СДЭК должен быть **установлен как отдельный плагин** (релиз с `vendor/`), а не только копия исходников из репозитория.

## 3. Сессия и данные flow

Полный контракт ПВЗ (слияние `step_one`, `context_id`, заказ, логи): [pvz-data-contract.md](./pvz-data-contract.md).

| Ключ | Назначение |
|------|------------|
| `WC()->session['official_cdek_office_code']` | Код ПВЗ для расчёта и пакетов (как `SaveOfficeToSessionAction`) |
| `WC()->session['chosen_shipping_methods'][0]` | Полный id выбранной ставки, например `official_cdek:137` |
| `answers.step_one.cdek_office_code` | Код ПВЗ в сессии MP checkout (сохраняется через `cdek_set_office` и `session_set_answers`) |

Сброс: при смене метода доставки фронт очищает `cdek_office_code`; бэкенд в `CdekWcSessionBridge` очищает `official_cdek_office_code`, если метод не `pvz` или ставка не `official_cdek:*`.

## 4. Заказ при оплате из MP

Перед созданием заказа вызывается `WcCustomerShippingSync::before_create_order_from_cart()` (контакт в customer + bridge + `calculate_totals()` корзины). На заказ копируются **shipping lines** из пакетов корзины вместе с **meta ставки** (`copy_cart_shipping_to_order`), чтобы поведение было ближе к нативному checkout.

### §29.6 — паритет заказа с ПВЗ (side-by-side и импорт)

**Side-by-side `/zakaz/` и `/mp-checkout/`:** оформите одинаковую корзину и один и тот же ПВЗ на обоих URL и сравните заказы:

- **Shipping line:** `method_id = official_cdek`, `instance_id` совпадает, итоговая стоимость доставки идентична.
- **Meta на shipping item:** ключ нативного плагина (`_official_cdek_office_code` или эквивалент с подстрокой `office_code`) присутствует в обоих заказах.
- **Доп. mp-cc meta:** на заказе из MP checkout дополнительно могут быть `_mp_cc_cdek_office_code` и `_mp_cc_cdek_rate_id` (удобно для отчётов и QA); на нативном `/zakaz/` этих ключей нет — это ожидаемо. **`_mp_cc_cdek_rate_id`** берётся из **реальной** shipping line заказа (`method_id = official_cdek`, `instance_id`), а не из каталога настроек — паритет с тем, что реально попало в заказ.

При успешном копировании ставки `official_cdek:*` в логах mp-cc появляется `[pvz] order_shipping_line_persisted` (поле `office_meta_present` отражает наличие meta офиса на ставке до переноса).

**Гард перед созданием заказа (§29.6):** если в flow выбран метод `pvz`, но в сессии нет выбранной ставки `official_cdek:*` из пакета 0 (например после `purge_ghost_official_cdek_chosen_rate`), `submit_payment` отвечает **422** с кодом `pvz_rate_unavailable`, лог `[pvz] order_shipping_line_missing` с `phase=guard`. Defense-in-depth: если гард когда-либо обойдён, при копировании доставки без CDEK-линии — тот же лог с `phase=copy`. Если на заказе нет shipping item с `official_cdek`, оба `_mp_cc_cdek_*` **не** пишутся; лог `[pvz] order_meta_skipped_no_cdek_line`.

**Acceptance ЛК СДЭК / трекинга** — проверки вне кода плагина (если на стенде нет ключей API).

**Export → import конфигурации:** после round-trip файла `mp-cc-config-*.json` у каждого метода и тарифа в каталоге должны сохраниться строковые `wc_rate_id`. Санитизация при импорте: `admin/Hooks/AdminMenuHooks.php`, `normalize_delivery_settings()` — для метода и каждого тарифа `wc_rate_id` проходит через `sanitize_text_field` (формат `official_cdek:<instance>`).

### §29.7 — Ops, флаги, kill switch

#### Map API rate sanity

- Запросы к API СДЭК (офисы / тарифы внутри виджета) идут **из виджета CDEK Widget 3.x** по `apiKey` и `servicePath` ([`assets/js/cdek-widget-bridge.js`](../assets/js/cdek-widget-bridge.js)) — лимиты и квоты задаёт СДЭК, не наш PHP.
- Наш бэкенд получает только финальный AJAX `cdek_set_office` — по сути **один запрос на успешный выбор ПВЗ**; есть защита от повторов: `chosenInFlight` в bridge, `shippingMutationInFlight` и очередь отложенного офиса в [`assets/js/checkout-frontend.js`](../assets/js/checkout-frontend.js).
- Модалка карты singleton (`activeModal`), повторная инициализация виджета на одно открытие не дублируется.

#### Prod / DevTools: карта ПВЗ не открывается (например `metaphysica-parfums.com/mp-checkout/`)

На странице чекаута в консоли браузера:

1. **`window.mpCcCdekWidget`** — смотреть `enabled`, **`map_ready`**, **`reason`** (для логов), **`reason_hint`** (краткий текст для покупателя), длину **`offices_json`** (строка JSON; после парса — число офисов для текущего города из flow).
2. **`typeof window.CDEKWidget`** — должен быть `'function'`, если MP подключил UMD виджет (зависит от `map_ready` и регистрации скрипта в [`frontend/Hooks/FrontendAssetsHooks.php`](../frontend/Hooks/FrontendAssetsHooks.php)).
3. **Админка WooCommerce → доставка СДЭК (официальный плагин):** плагин активен; заполнен ключ Яндекс.Карт (`yandex_map_api_key`); в `wp-content/plugins/<cdek>/` есть **`build/cdek-widget.umd.js`** и **`build/service.php`** (или **`dist/service.php`** на нестандартной сборке).
4. **Город в шаге 1 / контактах:** если город пуст или СДЭК не находит код города, **`offices_json`** может быть `'[]'` — тогда fallback-список ПВЗ пуст и показывается сообщение «выберите другой способ доставки» (не точки самовывоза магазина).

При fallback карты в лог валидации может попасть **`event_type`: `pvz_map_fallback_to_list`** с полем **`reason`** (см. [`assets/js/cdek-widget-bridge.js`](../assets/js/cdek-widget-bridge.js)).

#### Лог-плейбук `chosen_method_not_in_rates`

- **Источник:** [`integrations/WooCommerce/WcCustomerShippingSync.php`](../integrations/WooCommerce/WcCustomerShippingSync.php) — `mp_custom_checkout_log`, уровень `warning`, сообщение с префиксом `[wc_customer_shipping_sync] chosen_method_not_in_rates`.
- **Поля:** `package_index`, `chosen_method_id`, `rate_id_count`, `rate_id_sample` (до 15 id ставок пакета).
- **Алерт:** зафиксировать baseline по частоте до/после релиза ПВЗ; тревога при заметном росте (например по grep в файле логов плагина или по агрегации в вашей системе логов — поминутно/почасово).
- **Типичные причины:** неверный `wc_rate_id` в каталоге относительно активного `instance_id` в WooCommerce; метод СДЭК отключён в WC, но метод `pvz` активен у нас; расхождение зон доставки. **Шаги:** проверить `delivery.shipping_catalog.methods.*.wc_rate_id` и тарифы, зоны WC, при необходимости export/import конфигурации.

#### Kill switch ПВЗ через каталог (без деплоя кода)

- В админке плагина: **Доставка** → каталог методов → для метода **`pvz`** снять флаг **active** → сохранить настройки. Реализация фильтра: [`core/Checkout/Hooks/CheckoutAjaxHooks.php`](../core/Checkout/Hooks/CheckoutAjaxHooks.php), `shipping_catalog()` — неактивные методы не попадают в выдачу чекаута.
- На витрине метод ПВЗ исчезает из выбора; валидация `pvz_required` для этого метода не применяется, пока клиент не выберет другой доступный метод.
- Уже сохранённый в сессии `cdek_office_code` при отключении метода сам по себе не очищается, но используется только при `shipping_method_id === pvz`.

#### Soft-режим валидации (`pvz_office_required`)

- Флаг в **Служебное → Feature flags**: `pvz_office_required` (по умолчанию **вкл.** — поведение как до §29.7: 422 `pvz_required` без офиса).
- **Выключение:** сервер пропускает жёсткую проверку в `assert_pvz_has_office_or_fail()`; в логах info `[pvz] office_required_flag_off_skip_validation`. Только для аварийных инцидентов (поломка виджета/фронта). **Фронт:** `isPvzMissingOfficeRequired` в [`assets/js/checkout-frontend.js`](../assets/js/checkout-frontend.js) читает тот же флаг (`state.featureFlags.pvz_office_required`, fallback `true`) — preflight «Далее» на шагах доставки не блокирует `pvz` без офиса, в паритете с сервером.

## 5. Интеграция UI ПВЗ

Встроенной карты СДЭК на странице MP может не быть. Минимальный контракт:

- После выбора кода офиса вызвать **`window.mpCcSetCdekOfficeCode('КОД')`** (jQuery Deferred, как `postCheckout`), либо POST на `admin-ajax.php` с `action=mp_cc_checkout`, `sub_action=cdek_set_office`, `nonce`, `context_id`, `office_code`.

Пустая строка сбрасывает код в flow и в WC-сессии.
