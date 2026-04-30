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

| Ключ | Назначение |
|------|------------|
| `WC()->session['official_cdek_office_code']` | Код ПВЗ для расчёта и пакетов (как `SaveOfficeToSessionAction`) |
| `WC()->session['chosen_shipping_methods'][0]` | Полный id выбранной ставки, например `official_cdek:137` |
| `answers.step_one.cdek_office_code` | Код ПВЗ в сессии MP checkout (сохраняется через `cdek_set_office` и `session_set_answers`) |

Сброс: при смене метода доставки фронт очищает `cdek_office_code`; бэкенд в `CdekWcSessionBridge` очищает `official_cdek_office_code`, если метод не `pvz` или ставка не `official_cdek:*`.

## 4. Заказ при оплате из MP

Перед созданием заказа вызывается `WcCustomerShippingSync::before_create_order_from_cart()` (контакт в customer + bridge + `calculate_totals()` корзины). На заказ копируются **shipping lines** из пакетов корзины вместе с **meta ставки** (`copy_cart_shipping_to_order`), чтобы поведение было ближе к нативному checkout.

## 5. Интеграция UI ПВЗ

Встроенной карты СДЭК на странице MP может не быть. Минимальный контракт:

- После выбора кода офиса вызвать **`window.mpCcSetCdekOfficeCode('КОД')`** (jQuery Deferred, как `postCheckout`), либо POST на `admin-ajax.php` с `action=mp_cc_checkout`, `sub_action=cdek_set_office`, `nonce`, `context_id`, `office_code`.

Пустая строка сбрасывает код в flow и в WC-сессии.
