# Адаптация MODX Vapor под MODX 3.2.3-pl + PHP 8.2

## Обзор

MODX Vapor — утилита для создания полного снапшота MODX-сайта в транспортный пакет `.transport.zip`, пригодный для импорта на другой сервер (например, MODX Cloud). Оригинальный Vapor написан для MODX 2.x / PHP 5–7. Документ описывает все изменения, необходимые для работы под MODX 3.2.3-pl / xPDO 3 / PHP 8.2.

## Исходные файлы

```
vapor/
├── vapor.php                              # Главный скрипт (экспорт)
├── model/vapor/vaporvehicle.class.php     # Кастомный vehicle для нестандартных таблиц
└── scripts/
    ├── validate.truncate_tables.php       # Валидатор: очистка таблиц перед импортом
    ├── resolve.vapor_model.php            # Резолвер: загрузка vaporVehicle при установке
    ├── resolve.media_source.php           # Резолвер: пути медиа-источников
    └── resolve.extension_packages.php     # Резолвер: extension_packages
```

---

## 1. Список классов MODX (`$classes` в vapor.php)

### Удалены (не существуют в MODX 3)

| Класс | Причина |
|---|---|
| `modAccessAction` | Удалён в MODX 3 (система действий переписана) |
| `modAction` | Удалён в MODX 3 |
| `modClassMap` | Удалён в MODX 3 |

### Добавлены (новые в MODX 3)

| Класс | Таблица | Описание |
|---|---|---|
| `modAccessNamespace` | `access_namespace` | ACL для неймспейсов |
| `modExtensionPackage` | `extension_packages` | Расширенные пакеты |
| `modDeprecatedCall` | `deprecated_call` | Лог устаревших вызовов |
| `modDeprecatedMethod` | `deprecated_method` | Лог устаревших методов |
| `modUserGroupSetting` | `user_group_settings` | Настройки групп пользователей |

### Без изменений (существуют в MODX 2 и 3)

Все остальные классы из оригинального списка: `modAccessActionDom`, `modAccessCategory`, `modAccessContext`, `modAccessElement`, `modAccessMenu`, `modAccessPermission`, `modAccessPolicy`, `modAccessPolicyTemplate`, `modAccessPolicyTemplateGroup`, `modAccessResource`, `modAccessResourceGroup`, `modAccessTemplateVar`, `modActionDom`, `modActionField`, `modActiveUser`, `modCategory`, `modCategoryClosure`, `modChunk`, `modContentType`, `modContext`, `modContextResource`, `modContextSetting`, `modElementPropertySet`, `modEvent`, `modFormCustomizationProfile`, `modFormCustomizationProfileUserGroup`, `modFormCustomizationSet`, `modLexiconEntry`, `modManagerLog`, `modMenu`, `modNamespace`, `modPlugin`, `modPluginEvent`, `modPropertySet`, `modResource`, `modResourceGroup`, `modResourceGroupResource`, `modSession`, `modSnippet`, `modSystemSetting`, `modTemplate`, `modTemplateVar`, `modTemplateVarResource`, `modTemplateVarResourceGroup`, `modTemplateVarTemplate`, `modUser`, `modUserProfile`, `modUserGroup`, `modUserGroupMember`, `modUserGroupRole`, `modUserMessage`, `modUserSetting`, `modWorkspace`, `modDashboard`, `modDashboardWidget`, `modDashboardWidgetPlacement`, `sources.modAccessMediaSource`, `sources.modMediaSource`, `sources.modMediaSourceElement`, `sources.modMediaSourceContext`, `registry.db.modDbRegisterMessage`, `registry.db.modDbRegisterTopic`, `registry.db.modDbRegisterQueue`, `transport.modTransportProvider`, `transport.modTransportPackage`.

### Неймспейсы в MODX 3

MODX 3 использует PSR-4 неймспейсы. Полные имена классов:

| Короткое имя | Полное имя (FQN) |
|---|---|
| `modWorkspace` | `MODX\Revolution\modWorkspace` |
| `modSystemSetting` | `MODX\Revolution\modSystemSetting` |
| `modTransportPackage` | `MODX\Revolution\Transport\modTransportPackage` |
| `modMediaSource` | `MODX\Revolution\Sources\modMediaSource` |
| `modFileMediaSource` | `MODX\Revolution\Sources\modFileMediaSource` |
| `modDbRegisterMessage` | `MODX\Revolution\Registry\Db\modDbRegisterMessage` |
| `modPackageBuilder` | `MODX\Revolution\Transport\modPackageBuilder` |

**Важно:** короткие имена (class aliases) работают **лениво** — только после загрузки объекта через `getObject()`/`getIterator()`. Методы `call()`, `new ClassName()` и `instanceof` требуют полных имён, если класс не был предварительно загружен.

---

## 2. Изменения в `vapor.php`

### 2.1. `strftime()` → `date()` (PHP 8.1+)

`strftime()` удалена в PHP 8.1. Два места:

```php
// Было:
'filename' => 'vapor-' . strftime('%Y%m%dT%H%M%S', $startTime) . '.log'
define('PKG_VERSION', strftime("%y%m%d.%H%M.%S", $startTime));

// Стало:
'filename' => 'vapor-' . date('Ymd\THis', (int)$startTime) . '.log'
define('PKG_VERSION', date("ymd.Hi.s", (int)$startTime));
```

### 2.2. `each()` → `foreach` (PHP 8.0+)

`each()` удалён в PHP 8.0. В цикле сбора данных нестандартных таблиц:

```php
// Было:
while (list($key, $value) = each($row)) {

// Стало:
foreach ($row as $key => $value) {
```

### 2.3. `xPDO::OPT_SETUP` — убран

**Критическая проблема.** В MODX 3 `modX::initialize()` при `OPT_SETUP = true` пропускает `_initContext()`, из-за чего `$modx->context` остаётся `null`. Это ломает `hasPermission()` и `modMediaSource::initialize()`.

```php
// Было:
$options = array(
    ...
    xPDO::OPT_SETUP => true   // ← блокирует _initContext в MODX 3
);
$modx->initialize('mgr', $options);

// Стало:
$options = array(
    ...
    // OPT_SETUP убран
);
$modx->initialize('mgr', $options);
```

Все `$modx->setOption(xPDO::OPT_SETUP, true)` также удалены.

### 2.4. CLI-режим: установка пользователя

В CLI-режиме нет сессии, `$modx->user` — анонимный объект с `id = 0`. `hasPermission()` и `modMediaSource::initialize()` вызывают `$this->context->checkPolicy()`, который обращается к `$this->xpdo->user`. Без реального пользователя падает с `Call to a member function checkPolicy() on null`.

```php
// Добавлено после initialize('mgr'):
if (XPDO_CLI_MODE && (!$modx->user || !$modx->user->get('id'))) {
    $cliUser = $modx->getObject('modUser', 1);
    if ($cliUser) {
        $modx->user = $cliUser;
    }
}
```

Проверка `hasPermission('Vapor')` обёрнута в `if (!XPDO_CLI_MODE)` — в CLI доступ всегда разрешён.

### 2.5. `modPackageBuilder` — полное имя

Короткое имя `modPackageBuilder` не существует как class alias до загрузки. `loadClass('transport.modPackageBuilder', ...)` загружает класс, но не создаёт alias.

```php
// Было:
$modx->loadClass('transport.modPackageBuilder', '', false, true);
$builder = new modPackageBuilder($modx);

// Стало:
$modx->loadClass('MODX\\Revolution\\Transport\\modPackageBuilder', '', false, true);
$builder = new \MODX\Revolution\Transport\modPackageBuilder($modx);
```

### 2.6. `$modx->call()` — полное имя класса

`call()` с коротким именем возвращает пустой результат (не находит класс).

```php
// Было:
$response = $modx->call('modTransportPackage', 'listPackages', array(&$modx, $workspace->get('id')));

// Стало:
$response = $modx->call('MODX\\Revolution\\Transport\\modTransportPackage', 'listPackages', array(&$modx, $workspace->get('id')));
```

### 2.7. `vehicle_class` — полные имена

В xPDO 3 метод `xPDOTransport::put()` резолвит `vehicle_class` через `loadClass()`. Короткие имена (без неймспейса) не резолвятся, если класс не был предварительно загружен.

```php
// Было:
'vehicle_class' => 'xPDOFileVehicle'

// Стало:
'vehicle_class' => 'xPDO\\Transport\\xPDOFileVehicle'
```

### 2.11. `vaporVehicle` — FQN для нестандартных таблиц (критический фикс импорта)

**Проблема.** При упаковке нестандартных таблиц (seosuite, miniShop3 и др.) использовались атрибуты:

```php
$attributes = array(
    'vehicle_package' => 'vapor',
    'vehicle_class' => 'vaporVehicle'
);
```

В `xPDOTransport::get()` (xPDO 3, строка ~329) при **импорте** пакета на целевом сервере:

```php
$legacyClass = (strpos($vehicleClass, '.') !== false);  // false для 'vaporVehicle'
$fqClass = (strpos($vehicleClass, '\\') !== false);     // false
// $vehiclePackage = 'vapor' → 'vapor\'
// loadClass("vapor\vaporVehicle", ...) — ищет класс в НЕЙМСПЕЙСЕ vapor\
```

Но `resolve.vapor_model.php` загружает класс как **глобальный** `vaporVehicle` (через `loadClass('vapor.vaporVehicle', ...)`). Имя `vapor\vaporVehicle` не совпадает → `get()` возвращает `null` → vehicle **молча пропускается** (без ошибки в лог) → таблицы не создаются → компоненты падают с `Table doesn't exist`.

**Решение.** Использовать FQN `\vaporVehicle` без `vehicle_package`:

```php
// Было:
$attributes = array(
    'vehicle_package' => 'vapor',
    'vehicle_class' => 'vaporVehicle'
);

// Стало:
$attributes = array(
    'vehicle_class' => '\vaporVehicle'
);
```

Теперь в `get()`: `$fqClass = true` → `loadClass("\vaporVehicle")` → `class_exists('\vaporVehicle')` → `true` (класс уже загружен резолвером). Vehicle загружается, `install()` выполняется, таблицы создаются.

### 2.8. `getTableName()` — проверка пустого результата

Абстрактные классы (например `modAccess`) возвращают пустую строку. В блоке сбора нестандартных таблиц добавлена проверка:

```php
// Добавлено:
foreach ($classes as $class) {
    $tableName = $modx->getTableName($class);
    if (!empty($tableName)) {
        $coreTables[$class] = $modx->quote($modx->literal($tableName));
    }
}
```

### 2.9. `$modx->getOption('database')` — fallback

В MODX 3 конфиг `database` может быть `NULL`. Используется `dbname`.

```php
// Было:
$modxDatabase = $modx->getOption('dbname', $options, $modx->getOption('database', $options));

// Стало (с fallback на пустую строку):
$modxDatabase = $modx->getOption('dbname', $options, $modx->getOption('database', $options, ''));
```

### 2.10. Удалён блок версии 2.2.0

Оригинал проверял `version_compare($modxVersion, '2.2.0', '>=')` для добавления dashboard/media source классов. В MODX 3 эти классы всегда существуют — проверка убрана, классы включены в основной список.

---

## 3. Изменения в `vaporvehicle.class.php`

### 3.1. Наследование

`vaporVehicle` наследуется от `xPDO\Transport\xPDOVehicle` (не `xPDOTransportVehicle`). `xPDOVehicle` — базовый абстрактный класс для всех vehicle. `xPDOTransportVehicle` предназначен для вложенных транспортных пакетов и требует `source`/`target` в объекте — vapor работает с произвольными таблицами (`table`/`tableName`/`data`).

```php
// Добавлен use:
use xPDO\Transport\xPDOVehicle;

class vaporVehicle extends xPDOVehicle {
```

### 3.2. `install()` — возврат `true`

Оригинальный `install()` всегда возвращал `false` (переменная `$installed` никогда не устанавливалась в `true`). Добавлено:

```php
// После успешного создания таблицы и вставки данных:
$installed = true;
```

### 3.3. Сигнатуры методов

Сигнатуры `put(&$transport, &$object, $attributes)`, `install(&$transport, $options)`, `uninstall(&$transport, $options)` — **не изменились** между xPDO 2 и xPDO 3. Метод `get(&$transport, $options, $element)` также совместим.

---

## 4. Изменения в скриптах

### 4.1. `validate.truncate_tables.php`

Добавлена проверка на пустое имя таблицы (абстрактные классы):

```php
// Добавлено:
$tableName = $transport->xpdo->getTableName($class);
if (!empty($tableName)) {
    $results[$class] = $transport->xpdo->exec('TRUNCATE TABLE ' . $tableName);
}
```

### 4.2. `resolve.media_source.php`

`instanceof modFileMediaSource` заменён на полное имя — короткое не работает без предварительной загрузки:

```php
// Было:
if ($object instanceof modFileMediaSource && ...)

// Стало:
if ($object instanceof \MODX\Revolution\Sources\modFileMediaSource && ...)
```

### 4.3. `resolve.extension_packages.php`

`instanceof modSystemSetting` заменён на полное имя:

```php
// Было:
if ($object instanceof modSystemSetting && ...)

// Стало:
if ($object instanceof \MODX\Revolution\modSystemSetting && ...)
```

### 4.4. `resolve.vapor_model.php`

Без изменений. `loadClass('vapor.vaporVehicle', MODX_CORE_PATH . 'components/vapor/model/', true, true)` работает корректно в xPDO 3.

---

## 5. Порядок установки пакета на целевой сервер

При установке `.transport.zip` на целевом сервере (через MODX Installer или `xPDOTransport::install()`):

1. **xPDOFileVehicle** (первые 4 vehicle) — копируют:
   - `core/components/` → `MODX_CORE_PATH . 'components/'`
   - `assets/` → `MODX_BASE_PATH . 'assets/'`
   - `manager/components/` → `MODX_MANAGER_PATH . 'components/'`
   - `vapor/model/` → `MODX_CORE_PATH . 'components/vapor/model/'`
   - **resolve.vapor_model.php** выполняется → `loadClass('vapor.vaporVehicle', ...)` — класс загружен

2. **xPDOObjectVehicle** (классы MODX) — устанавливают объекты (modMenu, modChunk, modPlugin, etc.)

3. **vaporVehicle** (нестандартные таблицы) — `xPDOTransport::get()` вызывает `loadClass("\\vaporVehicle", "", true)`. Поскольку `vehicle_class = '\vaporVehicle'` содержит `\`, срабатывает ветка `class_exists($fqn)` в `loadClass()` — класс найден (загружен на шаге 1). Vehicle загружается, `install()` выполняет `CREATE TABLE` и `INSERT`.

4. **validate.truncate_tables.php** — выполняется перед установкой vaporVehicle, очищает таблицы MODX.

**Важно:** если vapor не установлен как компонент на целевом сервере, класс `vaporVehicle` всё равно будет доступен, т.к. файл `vaporvehicle.class.php` копируется на шаге 1.

---

## 6. Тестирование

### Запуск экспорта

```bash
docker exec modx3-php-1 php /var/www/html/vapor/vapor.php
```

### Ожидаемый вывод

```
Completed extracting package: localhost-YYMMDD.HHMM.SS-3.2.3-pl
Vapor execution completed without exception in 12.7s
```

### Проверка результата

```bash
# Пакет
ls -lh /var/www/html/core/packages/localhost-*.transport.zip

# Лог
cat /var/www/html/core/cache/logs/vapor-*.log

# Ошибки в логе
grep -iE "error|fail|could not" /var/www/html/core/cache/logs/vapor-*.log
```

### Известные некритичные предупреждения

| Предупреждение | Причина | Влияние |
|---|---|---|
| `Could not load package metadata for Sterc\FormIt\Model` | FormIt использует устаревший формат модели | Нет |
| `Creation of dynamic property modPackageBuilder::$modx is deprecated` | PHP 8.2 deprecation в ядре MODX | Нет |

---

## 7. Совместимость API (справочно)

### Методы, работающие в MODX 3 / xPDO 3 без изменений

| Метод | Статус |
|---|---|
| `$modx->getVersionData()` | ✅ Работает, возвращает `['full_version' => '3.2.3-pl']` |
| `$modx->hasPermission('Vapor')` | ✅ Работает (требует `$modx->user` и `$modx->context`) |
| `$modx->getObject($class, $pk)` | ✅ Короткие имена работают (ленивый alias) |
| `$modx->getIterator($class, $criteria)` | ✅ Короткие имена работают |
| `$modx->getTableName($class)` | ✅ Короткие имена работают |
| `$modx->loadClass($fqn, $path, $ignorePkg, $transient)` | ✅ Работает |
| `$modx->quote()`, `escape()`, `literal()` | ✅ Без изменений |
| `$modx->fromJSON()`, `toJSON()` | ✅ Без изменений |
| `$modx->query()`, `exec()` | ✅ Без изменений |
| `$modx->call($class, $method, $args)` | ⚠️ Только полные имена классов |
| `new modPackageBuilder($modx)` | ⚠️ Только полное имя `\MODX\Revolution\Transport\modPackageBuilder` |
| `XPDO_CLI_MODE` | ✅ Определён при запуске из CLI |

### Классы xPDO 3

| Класс | FQN | Существует |
|---|---|---|
| `xPDOVehicle` | `xPDO\Transport\xPDOVehicle` | ✅ Да (базовый, абстрактный) |
| `xPDOTransportVehicle` | `xPDO\Transport\xPDOTransportVehicle` | ✅ Да (для вложенных транспортных пакетов) |
| `xPDOFileVehicle` | `xPDO\Transport\xPDOFileVehicle` | ✅ Да |
| `xPDOObjectVehicle` | `xPDO\Transport\xPDOObjectVehicle` | ✅ Да (по умолчанию) |
| `xPDOTransport` | `xPDO\Transport\xPDOTransport` | ✅ Да |

### Структура классов MODX 3

```
core/src/Revolution/
├── modAccess.php, modChunk.php, modPlugin.php, ...     # Основные классы
├── Transport/modPackageBuilder.php                      # Транспорт
├── Transport/modTransportPackage.php
├── Sources/modMediaSource.php                           # Медиа-источники
├── Sources/modFileMediaSource.php
├── Registry/Db/modDbRegisterMessage.php                 # Реестр
├── Registry/Db/modDbRegisterTopic.php
├── Registry/Db/modDbRegisterQueue.php
└── metadata.mysql.php                                   # Метаданные (вместо schema XML)
```

`core/schema/modx.mysql.schema.xml` — **отсутствует** в MODX 3. Вместо него используется `core/src/Revolution/metadata.mysql.php`.

---

## 8. Локальные копии адаптированных файлов

```
C:\Users\Rashid\vapor_modx3\vapor\
├── vapor.php
├── model/vapor/vaporvehicle.class.php
└── scripts/
    ├── validate.truncate_tables.php
    ├── resolve.vapor_model.php
    ├── resolve.media_source.php
    └── resolve.extension_packages.php
```

На сервере: `/var/www/html/vapor/` (в контейнере `modx3-php-1`).

---

## 9. История изменений

### 19.08.2026 — Фикс импорта нестандартных таблиц на целевом сервере

**Симптом:** при импорте `.transport.zip` на целевой сервер таблицы нестандартных компонентов (`seosuite_resource`, `seosuite_social`, `ms3_customer_tokens` и др.) не создавались. Ошибки:

```
Error 42S02: Table 'des1gner_stgizzatov.g177_seosuite_resource' doesn't exist
Error 42S02: Table 'des1gner_stgizzatov.g177_ms3_customer_tokens' doesn't exist
```

**Диагностика:**
- Проверен `.transport.zip` — все таблицы присутствуют в виде `vaporVehicle` (CREATE TABLE + INSERT данные)
- Проверен `vaporvehicle.class.php` — `install()` корректно выполняет `CREATE TABLE` и `INSERT`
- Проверен `resolve.vapor_model.php` — корректно загружает класс `vaporVehicle` через `loadClass('vapor.vaporVehicle', ...)`
- Проверен `xPDOTransport::get()` — обнаружено несовпадение: xPDO 3 ищет `vapor\vaporVehicle` (в неймспейсе), а класс загружен как глобальный `vaporVehicle`

**Исправление:**
- В `vapor.php` (блок упаковки нестандартных таблиц, ~строка 535) заменены атрибуты:
  - `'vehicle_package' => 'vapor'` — удалён
  - `'vehicle_class' => 'vaporVehicle'` → `'vehicle_class' => '\vaporVehicle'` (FQN)
- Пакет перегенерирован: `localhost-260819.1759.23-3.2.3-pl.transport.zip`
- Манифест подтверждён: `'vehicle_class' => '\\vaporVehicle'`, `'vehicle_package' => ''`

**Дополнительно исправлено в этот день:**
- Плагин AdminTools (ID 4): `Attempt to read property "blocked" on null` — `$modx->user->Profile` равен `null` у анонимного пользователя. Добавлена проверка `$userProfile = $modx->user->Profile; ($userProfile && $userProfile->blocked)`. Код обновлён в БД (`seWEJyWn_site_plugins`) и в исходных файлах (`admintools/` и `admintools0/`). Убран лишний `<?php` из `plugincode` (вызывал `Parse error: unexpected token "<"` из-за двойного `<?php` в кэше).