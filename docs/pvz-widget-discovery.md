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

## 4. `servicePath` (виджет 3.x)

В типах `@cdek-it/widget` поле **`servicePath` обязательное**; дефолт в схеме типов — `"/service.php"` (относительный путь к PHP-прокси пакета).

У **официального WooCommerce-плагина** рядом с UMD должен лежать прокси **`build/service.php`**, уже привязанный к учётным данным СДЭК из настроек плагина (не копировать «голый» `service.php` из npm — там заглушки логина/пароля).

**MP-checkout:** URL прокси берётся как:

1. Фильтр `mp_custom_checkout_cdek_widget_service_path` (override).
2. Иначе — `\Cdek\Loader::getPluginUrl('build/service.php')` если файл читается (аналогично `dist/service.php` на нестандартных сборках).

Если `servicePath` недоступен, `map_ready = false` в `mpCcCdekWidget` — карта не подключается, остаётся **fallback** (список `getPickupConfig().points`).

## 5. Опции PHP, которые читает MP (без дублирования в своих option)

Через `\Cdek\ShippingMethod::factory()` и `\Cdek\CdekApi`:

- `yandex_map_api_key` — без ключа карта не включается.
- `map_auto_close` — пробрасывается в `cdek.close` и в `mpCcCdekWidget.map_auto_close`.

Список офисов для старта виджета: `CdekApi::cityCodeGet($city, $postcode)` → `officeListRaw($cityCode)` → JSON в `mpCcCdekWidget.offices_json`.

## 6. Blockers

- Нет установленного плагина СДЭК или нет `build/cdek-widget.umd.js` / `build/service.php` — только fallback-список.
- Неверный/пустой Yandex key — `enabled` / карта недоступны.

См. также: [pvz-data-contract.md](./pvz-data-contract.md), [cdek-mp-checkout-checklist.md](./cdek-mp-checkout-checklist.md).
