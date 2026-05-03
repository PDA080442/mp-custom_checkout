# Контракт данных ПВЗ (СДЭК) — MP Custom Checkout

Документ закрывает **§29.1** эпика `dp.md`: единая карта полей, жизненный цикл, краевые случаи, связь с WC и заказом.  
**Почта России:** этот документ и связанные правки сессии относятся к **общему** слою `answers.step_one` / WC session; отдельная бизнес-логика тарифов `post_russia` не меняется.

См. также: [cdek-mp-checkout-checklist.md](./cdek-mp-checkout-checklist.md), [shipping-wc-integration.md](./shipping-wc-integration.md).

---

## 1. Карта полей (источник правды)

| Слой | Ключ / поле | Тип | Кто пишет | Назначение |
|------|----------------|-----|-----------|------------|
| MP flow | `answers.step_one.cdek_office_code` | string | `CheckoutAjaxHooks::handle_cdek_set_office`, фронт через `session_set_answers` (шаг `address_delivery`) | Код ПВЗ в домене MP; при частичном сохранении шага **сливается** с предыдущим `step_one` (см. §4). |
| MP flow | `answers.step_one.shipping_method_id` | string | Фронт / `session_set_answers` | Ключ метода из каталога MP (`pvz`, `courier`, …). |
| MP flow | `answers.step_one.shipping_tariff_id` | string | Фронт | Тариф внутри метода (если есть). |
| MP flow | `answers.date_conditions` | array | Отдельный шаг / merge при `address_delivery` | Дата/условия; **не** хранит код ПВЗ. |
| WC session | `official_cdek_office_code` | string | `CdekWcSessionBridge::sync_session_before_cart_totals` | Как у нативного `SaveOfficeToSessionAction` плагина СДЭК. |
| WC session | `chosen_shipping_methods[0]` | string | `CdekWcSessionBridge` | Полный id ставки, напр. `official_cdek:137`. |
| Каталог настроек | `delivery.shipping_catalog.methods.*.wc_rate_id` / `tariffs.*.wc_rate_id` | string | Админка | Связка `pvz` + тариф → строка ставки WC. |

**Слияние для расчёта:** `CdekWcSessionBridge::get_merged_delivery_answers()` строит merge как `array_replace( step_one, date_conditions )`, но **перед этим удаляет `cdek_office_code` из копии `date_conditions`** — код ПВЗ всегда берётся только из `step_one`; поля даты по-прежнему могут перекрывать другие одноимённые ключи в `step_one`.

---

## 2. Жизненный цикл (текстовая схема)

1. Пользователь выбирает метод **ПВЗ** и (при необходимости) тариф → в `step_one` попадают `shipping_method_id` / `shipping_tariff_id` → `session_set_answers` → `WcCustomerShippingSync::after_session_set_answers` → пересчёт корзины.  
2. Пользователь выбирает пункт на карте / в списке → фронт вызывает **`cdek_set_office`** (`office_code`, `context_id`, nonce) **или** полный драфт с `cdek_office_code` в `session_set_answers`.  
3. `handle_cdek_set_office` читает текущий `step_one`, выставляет/снимает `cdek_office_code`, пишет через `CheckoutSessionService::set_step_answers( address_delivery, … )` → снова `WcCustomerShippingSync::after_session_set_answers`.  
4. Перед `calculate_totals()` (в цепочке sync) вызывается `CdekWcSessionBridge::sync_session_before_cart_totals`: при `shipping_method_id === 'pvz'`, `wc_rate_id` начинается с `official_cdek:` и непустой офис → в сессию WC пишется `official_cdek_office_code`; иначе ключ **очищается**.  
5. WC/плагин СДЭК считают пакеты и ставки → в ответах MP в `cart` отдаются `wc_shipping_rates` и итоги.  
6. При **submit** `before_create_order_from_cart` снова синхронизирует customer/bridge/totals → `copy_cart_shipping_to_order` копирует **все meta** выбранной `WC_Shipping_Rate` на `WC_Order_Item_Shipping` — специфичные для СДЭК meta попадают сами, без дублирования в `OrderMetaHooks`.

---

## 3. Заказ и meta СДЭК (паритет §28.6)

MP **не** дублирует произвольный список meta СДЭК в `OrderMetaHooks`: источник для строки доставки заказа — **meta ставки в корзине** после расчёта WC.

| Ожидание нативного checkout | Источник в MP |
|------------------------------|---------------|
| Shipping line с `method_id` / `instance_id` / cost как у WC | `copy_cart_shipping_to_order` из текущих `packages` + `chosen_shipping_methods` |
| Meta пункта / тарифа на item (что вешает плагин СДЭК на rate) | Копируется из `$rate->get_meta_data()` |
| Сценарий / дата / купоны в custom meta MP | `OrderMetaHooks::on_checkout_order_created` (отдельно от СДЭК) |

Проверка на стенде: сравнить два заказа ( `/zakaz/` vs `/mp-checkout/` ) при одном ПВЗ — diff по `wc_order_itemmeta` для shipping item.

После каждого `WC_Cart::calculate_totals()` в цепочке `WcCustomerShippingSync::sync_customer_from_flow_and_recalculate_cart`: если `chosen_shipping_methods[0]` начинается с `official_cdek:` и **нет** среди ставок пакета 0 — ключ снимается (ghost rate из каталога), лог `[pvz] chosen_rate_purged_ghost`.

---

## 4. Частичные сохранения (`session_set_answers`) и офис

- Для `contact_billing` уже используется **`array_replace`** с предыдущим массивом.  
- Для **`step_one`** (шаг `address_delivery`) после правки §29.1 используется **`array_replace`** с предыдущим `step_one`: частичный payload **не удаляет** `cdek_office_code`, если ключ не передан.  
- **Полная очистка офиса:** вызов `mpCcSetCdekOfficeCode('')` шлёт AJAX `cdek_set_office` с `office_code=''`. Сервер записывает в `step_one` **`cdek_office_code` как пустую строку** (`''`), а не `unset`: если ключ из payload отсутствует, `array_replace` оставил бы прежнее значение — «липкий» офис в MP и повторная запись в WC-сессию.  
- Перед записью для шагов `address_delivery` / `date` / `conditions` ответы по-прежнему дополняются сохранённым `date_conditions` (`array_replace_recursive` в `CheckoutAjaxHooks`) — это про дату, не про ПВЗ. При этом из **снимка** `existing_date` перед merge **`cdek_office_code` выбрасывается** (legacy не должен подмешиваться в payload шага доставки); при непустом удалённом значении пишется warning `[pvz] existing_date_office_dropped_pre_merge`.  
- Фронт для шага `date` / `conditions` не шлёт `cdek_office_code` в payload `date_conditions` (копия без ключа в `getDraftPayloadByStorageKey`).  
- `session_get_state` только читает flow (и синхронизирует купоны); **не затирает** офис.

---

## 5. `context_id` и `stale_context`

Поддействия сессии, включая **`cdek_set_office`**, проходят через `handle_session_sub_action` → **`validate_context_id()`**. Неверный `context_id` → ответ **409** `stale_context`, запись в flow не выполняется — офис **не «переезжает»** на чужой контекст.

**Контракт фронта:** в каждом запросе session-sub-actions (в т.ч. `cdek_set_office`) нужно передавать **`context_id`** текущего flow. Если поле отсутствует или пустое, общий слой валидации может пропустить проверку — это не замена изоляции контекста; интеграции должны всегда слать `context_id` (как делает `mpCcSetCdekOfficeCode` в `checkout-frontend.js`).

---

## 6. Пустая корзина и TTL flow

- Отправка оплаты с пустой корзиной блокируется в `handle_submit_payment`.  
- Полный сброс flow: `CheckoutSessionService::clear()` (успех, abandon, часть сценариев отмены заказа) — вместе с ним исчезает и `step_one`; также в WC-сессии обнуляется **`official_cdek_office_code`** (иначе при следующем заходе возможен «призрачный» офис до первого sync). Лог: `[pvz] wc_office_cleared_with_flow`.  
- Если корзина стала пустой **без** явного `clear`, старый flow может ещё жить до TTL (`is_flow_stale` в `get_public_state`) — поведение офиса тогда определяется следующим успешным `session_get_state` / перезаходом; отдельной очистки только `cdek_office_code` при `cart->is_empty()` в коде MP **нет**. Инвалидация офиса при смене города/метода — см. §10 (§29.5).

---

## 7. Логирование

При успешном сохранении через **`cdek_set_office`** пишется событие в `mp_custom_checkout_log`: `event_type` = `pvz_office_saved`, поля `context_id_posted` / `context_id_flow`, `shipping_method_id` из merged delivery, **`office_fp`**: для непустого кода — **префикс** из первых 4 символов + длина строки (`substr + ':' + strlen`), полный код в лог не попадает; для явной очистки офиса — строковый маркер **`cleared`** (не криптографический hash).

Если в payload шага `date` / `conditions` ошибочно передан ключ `cdek_office_code`, сервер удаляет его из `date_conditions` и пишет предупреждение `[pvz] date_conditions_office_stripped` (`had_value`, без самого кода).

---

## 8. Константы в коде

- `MP\CustomCheckout\Integrations\WooCommerce\CdekWcSessionBridge::SESSION_OFFICE_KEY` = `official_cdek_office_code`  
- `CdekWcSessionBridge::OFFICIAL_CDEK_PREFIX` = `official_cdek:`

---

## 9. §29.3 — порядок сохранения, сериализация, ошибки

Чек-лист (без изменения логики `post_russia`):

1. **Ordering сервера:** `handle_cdek_set_office` → `CheckoutSessionService::set_step_answers( address_delivery, step_one )` → `WcCustomerShippingSync::after_session_set_answers` → `CdekWcSessionBridge::sync_session_before_cart_totals` → `WC_Cart::calculate_totals()` (см. `CheckoutAjaxHooks::handle_cdek_set_office`). Ошибка в WC-синке после записи flow: **rollback** прежнего `step_one`, лог `[pvz] office_save_wc_sync_failed`, ответ **500** `wc_sync_failed`.
2. **Запись WC-сессии без лишних перезаписей:** `CdekWcSessionBridge::sync_session_before_cart_totals` обновляет `official_cdek_office_code` и `chosen_shipping_methods[0]` только если значение изменилось (меньше шумных пересчётов при смене ПВЗ в том же городе).
3. **Валидация кода на AJAX:** непустой `office_code` должен удовлетворять мягкому формату (длина 1–32, `[A-Za-z0-9_-]`); иначе **400** `invalid_office_code`, лог `event_type` = `pvz_office_save_failed`, flow **не** меняется.
4. **Фронт:** `window.mpCcSetCdekOfficeCode` использует общий флаг `shippingMutationInFlight` с выбором метода/тарифа; при конфликте офис ставится в очередь `pendingCdekOfficeCode` и возвращается **отложенный** promise (`pendingCdekOfficeDeferred`), резолв только после реального AJAX; повтор того же кода после trim — **no-op** без AJAX; при успехе без `flow` в ответе — локально выставляется `fulfillment.date.cdek_office_code`; при ответе с `success: false` или HTTP-ошибке — notify, локальный откат `cdek_office_code`, `syncStoreWithBackend({ force: true })`. Submit оплаты и «Далее» (legacy + V2 `delivery_screen`) ждут `awaitShippingMutationFlush` (таймаут 8 с). Смена города в `[data-city-input]` перед двумя `session_set_answers` при активной доставке ждёт тот же flush; после локального `delete cdek_office_code` оба save (`contact_payment` + `scenario`) выполняются под **`shippingMutationInFlight`** до `$.when(...).always`, чтобы «Далее»/«Оплатить» не обгоняли серверный `maybe_invalidate_pvz_office_on_city_change`. DaData onSelect города (`afterDadataContactGeocode`: `touchedKeys` содержит `city`, оба города непустые после нормализации и различаются) — локально `delete fulfillment.date.cdek_office_code`, сброс `cart.summary.shipping` / `shipping_total`, `invalidateV2DownstreamFrom(state, 0)`. AJAX checkout: `postCheckout` с **timeout 30 с**. `applyShippingSelectionToState` **не** удаляет `cdek_office_code` (чип не пропадает после `syncFromFlow`); локально офис сбрасывается при уходе с метода `pvz` в `applyShippingMethodUserChoice`. Отказ `session_set_step` с кодом `pvz_required` обрабатывается тем же `handlePvzRequiredFailureUi`, что и submit (V2 + legacy invalid state); при смене метода `pvz` → не-`pvz` дополнительно снимаются `invalid_v2_steps.delivery_screen` и `invalid_steps.address_delivery`, очищается `form.errors.cdek_office_code`.
5. **Виджет карты:** двойной `onChoose` блокируется флагом `chosenInFlight` в `cdek-widget-bridge.js`; закрытие модала после promise от `mpCcSetCdekOfficeCode`, который соответствует реальному сохранению (не «мгновенный» resolve при постановке в очередь).

---

## 10. §29.5 — Инвалидация ПВЗ при смене города или метода

Частичный merge в `CheckoutSessionService::set_step_answers` для `step_one` и `contact_billing` не удаляет ключи, которых нет в payload (`array_replace`). Из-за этого старый `cdek_office_code` мог «прилипать» к flow после смены города или после ухода с метода `pvz`.

**Сервер (`CheckoutSessionService::set_step_answers`):**

- После merge **`contact_billing`**: если нормализованный `city` до и после merge оба непустые и различаются — `unset( $answers['step_one']['cdek_office_code'] )`. Лог: `[pvz] office_invalidated`, `reason` = `city_change` (в payload длины строк города без полного текста, плюс `scenario`, `prev_method` — метод из `step_one` до сброса офиса).
- После merge **`step_one`**: если новый `shipping_method_id` непустой и **не** `pvz` — ключ `cdek_office_code` удаляется из `step_one` (**в том числе** если значение уже пустая строка); лог `[pvz] office_invalidated` с `reason` = `method_change` только если до удаления значение было непустым после trim; в payload дополнительно `scenario`, `prev_tariff`.
- При смене сценария на **самовывоз** (`pickup`) в `CheckoutSessionService::reset_dependent_answers_for_scenario_switch` из `step_one` сбрасываются поля доставки (`shipping_method_id`, тарифы/заголовки, `cdek_office_code`, цена/ETA, `shipping_requires_address`). Если до сброса `cdek_office_code` был непустым — лог `[pvz] office_invalidated`, `reason` = `scenario_switch_to_pickup`, в payload — `prev_method` (без значения кода офиса).

Далее обычная цепочка `WcCustomerShippingSync::after_session_set_answers` → `CdekWcSessionBridge::sync_session_before_cart_totals` очищает `WC()->session['official_cdek_office_code']`, когда метод не `pvz` или офис пуст.

**Фронт:** при реальном изменении города в `[data-city-input]` (оба значения непустые, регистр игнорируется) — локально `delete fulfillment.date.cdek_office_code`, `invalidateV2DownstreamFrom(state, 0)`, сброс строки доставки в summary; два `session_set_answers` под **`shippingMutationInFlight`** до завершения обоих запросов; без дополнительных `session_get_state`. Та же локальная инвалидация при смене города через DaData (`afterDadataContactGeocode`).

**§29.4 (выравнивание):** серверный гвард `assert_pvz_has_office_or_fail` читает метод и офис из `CdekWcSessionBridge::get_merged_delivery_answers( $flow )`, а не только из `date_conditions`.
