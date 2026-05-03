# Discovery: CDEK Widget 3.x на кастомном MP-checkout (§29.2)

Краткий маппинг по **официальному** плагину [`cdek-it/wordpress`](https://github.com/cdek-it/wordpress) и npm-пакету `@cdek-it/widget` (бандл UMD). Рабочие `build/*` на GitHub в `main` часто отсутствуют — на стенде сверять файлы в `wp-content/plugins/<папка-cdek>/build/`.

## 1. Script handle и бандл

| Что | Значение |
|-----|----------|
| **WP handle** | `cdek-widget` |
| **Файл** | `build/cdek-widget.umd.js` (относительно корня плагина СДЭК) |
| **Регистрация** | `\Cdek\UI\CdekWidget::registerScripts()` — `wp_register_script('cdek-widget', Loader::getPluginUrl('build/cdek-widget.umd.js'))` |
| **Глобал конструктора** | `window.CDEKWidget` (UMD присваивает `globalThis.CDEKWidget`) |

## 2. `wp_localize_script` оф. плагина

| Параметр | Значение |
|----------|----------|
| **Handle** | `cdek-widget` |
| **Имя JS-объекта** | `window.cdek` |
| **Поля** | `key` — Yandex API key; `close` — `map_auto_close`; `lang` — `eng` / `rus`; `saver` — `WC_AJAX::get_endpoint(Config::DELIVERY_NAME . '_save-office')` т.е. `...wc-ajax=official_cdek_save-office` |

`Config::DELIVERY_NAME` = `official_cdek` (см. `Cdek\Config` в плагине).

## 3. Woo Blocks / эталон `onChoose`

В `src/Frontend/CheckoutMapBlock/components/frontend.js` виджет создаётся так (импорт `cdekWidget` = UMD `CDEKWidget`):

- `apiKey`, `lang`, `debug`
- `defaultLocation` — строка города (из destination)
- `officesRaw` — `JSON.parse(points)` (массив офисов)
- `hideDeliveryOptions: { door: true }`
- `onChoose(_type, _tariff, address)` → `address.code`

В прод-сборке WooCommerce виджет открывается как **`popup: true`** без `root`: виджет сам рисует оверлей и карту. **Inline-режим** (`popup: false` + `root`) в MP-checkout **не используется** — в UMD-сборке карта в нашем модале оставалась пустой; паритет с эталоном — **нативный попап** [`assets/js/cdek-widget-bridge.js`](../assets/js/cdek-widget-bridge.js) (`openNativeCdekPopup`).

## 4. `servicePath` (виджет 3.x) — опционально для MP

В типах npm-пакета `@cdek-it/widget` поле **`servicePath`** часто помечено как обязательное; на **фактическом WooCommerce-checkout официального плагина** виджет создаётся **без** него: скрипт `cdek-checkout-map.js` вызывает `new CDEKWidget({ apiKey: window.cdek.key, lang, defaultLocation, officesRaw, hideDeliveryOptions: { door: true }, onChoose, popup: true })` — без `servicePath` (сверить с собранным JS в каталоге плагина СДЭК на хостинге).

**MP-checkout:**

1. **`map_ready`** в [`CdekMpCheckoutWidgetConfig.php`](../integrations/WooCommerce/CdekMpCheckoutWidgetConfig.php) — при активном способе СДЭК и заполненном **`yandex_map_api_key`** (не зависит от наличия `service.php`).
2. Опциональный URL прокси: фильтр `mp_custom_checkout_cdek_widget_service_path`, иначе автопоиск `build/service.php` / `dist/service.php` — поле **`service_path`** остаётся в **`mpCcCdekWidget`** для диагностики/будущего использования; в **`openNativeCdekPopup`** в **`CDEKWidget`** **не** передаются ни **`servicePath`**, ни **`goods`** (паритет с эталонным `cdek-checkout-map.js`, иначе маркеры ПВЗ могут не появиться без полного прокси тарифов).
3. Опционально для отладки консоли виджета: фильтр **`mp_custom_checkout_cdek_widget_debug`** (по умолчанию `false`) → поле **`debug`** в `mpCcCdekWidget`.
4. Если нет ключа Яндекс.Карт или не загрузился UMD — **fallback**: список офисов из **`offices_json`** (не точки самовывоза магазина).
5. Актуальный список офисов для карты и модала списка: перед открытием виджета клиент вызывает **`admin-ajax.php`** с **`action=mp_cc_checkout`**, **`sub_action=cdek_get_offices`**, **`city`**, **`postcode`**, **`context_id`** — сервер через `\Cdek\CdekApi::cityCodeGet` / **`officeListRaw`** возвращает массив **`offices_raw`** (паритет с подстановкой точек после **`update_checkout`** в эталонном checkout СДЭК). Статический **`mpCcCdekWidget.offices_json`** остаётся как начальный кэш и fallback при ошибке запроса.

## 5. Опции PHP, которые читает MP (без дублирования в своих option)

Через `\Cdek\ShippingMethod::factory()` и `\Cdek\CdekApi`:

- `yandex_map_api_key` — без ключа карта не включается.
- `map_auto_close` — пробрасывается в `cdek.close` и в `mpCcCdekWidget.map_auto_close`.

Список офисов для **начального** payload страницы: `CdekApi::cityCodeGet($city, $postcode)` → `officeListRaw($cityCode)` → JSON в `mpCcCdekWidget.offices_json` (город из flow и при необходимости из **`WC_Customer`**). После загрузки страницы список подтягивается клиентом через **`cdek_get_offices`** (см. §4 п.5).

## 6. Blockers

- Нет установленного плагина СДЭК или не отдаётся **`build/cdek-widget.umd.js`** по URL (не зарегистрирован handle `cdek-widget`) — конструктор `window.CDEKWidget` отсутствует; fallback — список из `offices_json` или пустое сообщение.
- Неверный/пустой **Yandex API key** — карта не поднимается; fallback как выше.
- Отсутствие **`build/service.php`** в сборке плагина **не** блокирует карту в текущей интеграции MP (как и у эталонного checkout СДЭК без `servicePath`).

См. также: [pvz-data-contract.md](./pvz-data-contract.md), [cdek-mp-checkout-checklist.md](./cdek-mp-checkout-checklist.md).
