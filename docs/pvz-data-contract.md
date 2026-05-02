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

**Слияние для расчёта:** `CdekWcSessionBridge::get_merged_delivery_answers()` = `array_replace( step_one, date_conditions )` — поля даты перекрывают одноимённые ключи в `step_one`, если такие есть; `cdek_office_code` берётся из `step_one`.

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

---

## 4. Частичные сохранения (`session_set_answers`) и офис

- Для `contact_billing` уже используется **`array_replace`** с предыдущим массивом.  
- Для **`step_one`** (шаг `address_delivery`) после правки §29.1 используется **`array_replace`** с предыдущим `step_one`: частичный payload **не удаляет** `cdek_office_code`, если ключ не передан.  
- Перед записью для шагов `address_delivery` / `date` / `conditions` ответы по-прежнему дополняются сохранённым `date_conditions` (`array_replace_recursive` в `CheckoutAjaxHooks`) — это про дату, не про ПВЗ.  
- `session_get_state` только читает flow (и синхронизирует купоны); **не затирает** офис.

---

## 5. `context_id` и `stale_context`

Поддействия сессии, включая **`cdek_set_office`**, проходят через `handle_session_sub_action` → **`validate_context_id()`**. Неверный `context_id` → ответ **409** `stale_context`, запись в flow не выполняется — офис **не «переезжает»** на чужой контекст.

---

## 6. Пустая корзина и TTL flow

- Отправка оплаты с пустой корзиной блокируется в `handle_submit_payment`.  
- Полный сброс flow: `CheckoutSessionService::clear()` (успех, abandon, часть сценариев отмены заказа) — вместе с ним исчезает и `step_one`.  
- Если корзина стала пустой **без** явного `clear`, старый flow может ещё жить до TTL (`is_flow_stale` в `get_public_state`) — поведение офиса тогда определяется следующим успешным `session_get_state` / перезаходом; отдельной очистки только `cdek_office_code` при `cart->is_empty()` в коде MP на момент §29.1 **нет** (зафиксировано как наблюдение для §29.5 при необходимости).

---

## 7. Логирование

При успешном сохранении через **`cdek_set_office`** пишется событие в `mp_custom_checkout_log`: `event_type` = `pvz_office_saved`, `context_id`, `shipping_method_id` из flow, `office_len`, `office_fp` (первые 4 символа кода + длина — без передачи полного кода в лог при длинном коде; для пустого сброса — отдельный маркер).

---

## 8. Константы в коде

- `MP\CustomCheckout\Integrations\WooCommerce\CdekWcSessionBridge::SESSION_OFFICE_KEY` = `official_cdek_office_code`  
- `CdekWcSessionBridge::OFFICIAL_CDEK_PREFIX` = `official_cdek:`

---

## 9. §29.3 — порядок сохранения, сериализация, ошибки

Чек-лист (без изменения логики `post_russia`):

1. **Ordering сервера:** `handle_cdek_set_office` → `CheckoutSessionService::set_step_answers( address_delivery, step_one )` → `WcCustomerShippingSync::after_session_set_answers` → `CdekWcSessionBridge::sync_session_before_cart_totals` → `WC_Cart::calculate_totals()` (см. `CheckoutAjaxHooks::handle_cdek_set_office`).
2. **Запись WC-сессии без лишних перезаписей:** `CdekWcSessionBridge::sync_session_before_cart_totals` обновляет `official_cdek_office_code` и `chosen_shipping_methods[0]` только если значение изменилось (меньше шумных пересчётов при смене ПВЗ в том же городе).
3. **Валидация кода на AJAX:** непустой `office_code` должен удовлетворять мягкому формату (длина 1–32, `[A-Za-z0-9_-]`); иначе **400** `invalid_office_code`, лог `event_type` = `pvz_office_save_failed`, flow **не** меняется.
4. **Фронт:** `window.mpCcSetCdekOfficeCode` использует общий флаг `shippingMutationInFlight` с выбором метода/тарифа; при конфликте офис ставится в очередь `pendingCdekOfficeCode` и отправляется после завершения цепочки доставки; повтор того же кода после trim — **no-op** без AJAX; при ответе с `success: false` или HTTP-ошибке — notify, локальный откат `cdek_office_code`, `syncStoreWithBackend({ force: true })`.
5. **Виджет карты:** двойной `onChoose` блокируется флагом `chosenInFlight` в `cdek-widget-bridge.js`; успешное закрытие модала только после успешного deferred от `mpCcSetCdekOfficeCode`.
