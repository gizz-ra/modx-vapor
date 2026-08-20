# MODX Vapor

**Телепортер сайтов для MODX Revolution** — создаёт полный снимок MODX-сайта (база данных + файлы) в виде пакета `.transport.zip`, готового к импорту на другой сервер.

> Это порт [MODX-Club/vapor](https://github.com/MODX-Club/vapor) для **MODX 3.x / xPDO 3 / PHP 8.2+**.

---

## 📋 Содержание

- [Что такое Vapor](#что-такое-vapor)
- [Требования](#требования)
- [Установка](#установка)
- [Использование](#использование)
  - [Запуск через CLI](#запуск-через-cli)
  - [Запуск через браузер](#запуск-через-браузер)
  - [Пользовательские опции](#пользовательские-опции)
- [Импорт пакета](#импорт-пакета)
- [Решение проблем](#решение-проблем)
- [Адаптация под MODX 3 / xPDO 3](#адаптация-под-modx-3--xpdo-3)
  - [Сводка изменений](#сводка-изменений)
  - [Подробный технический справочник](#подробный-технический-справочник)
- [История изменений](#история-изменений)
- [Лицензия](#лицензия)

---

## Что такое Vapor

MODX Vapor — PHP-скрипт, который извлекает полный снимок MODX-сайта: всё содержимое базы данных, файлы и компоненты, упаковывая их в стандартный транспортный пакет MODX (`.transport.zip`). Этот пакет можно импортировать в любую другую установку MODX Revolution.

Типичные сценарии:

- **Миграция сайта** между серверами
- **Полный бэкап** в транспортабельном формате
- **Клонирование** сайта для разработки/staging
- **Деплой** готового сайта на MODX Cloud или любой хостинг с MODX

---

## Требования

- **MODX Revolution 3.x** (тестировалось на 3.2.3-pl)
- **PHP 8.1+** (тестировалось на 8.2)
- **PHP-расширение Zip** (`ext-zip`)
- Все стандартные [требования MODX 3](https://docs.modx.com/3.x/en/getting-started/server-requirements)

> ℹ️ Для MODX 2.x / PHP 5–7 используйте [оригинальный Vapor](https://github.com/MODX-Club/vapor).

---

## Установка

1. Скопируйте директорию `vapor/` в `MODX_BASE_PATH` (корень сайта, рядом с `index.php`).

   ```
   /var/www/html/
   ├── index.php
   ├── core/
   ├── manager/
   ├── connectors/
   ├── assets/
   └── vapor/          ← сюда
       ├── vapor.php
       ├── model/
       └── scripts/
   ```

2. Убедитесь, что директория доступна для чтения/записи пользователю веб-сервера или PHP CLI.

---

## Использование

Vapor можно запускать из **CLI** или через **браузер**. Для крупных сайтов рекомендуется CLI — чтобы избежать таймаутов.

Снимок создаётся в `MODX_CORE_PATH . 'packages/'` с именем, указанным в выводе скрипта.

### Запуск через CLI

```bash
cd /var/www/html
php vapor/vapor.php
```

Ожидаемый вывод:

```
Completed extracting package: localhost-260819.1759.23-3.2.3-pl
Vapor execution completed without exception in 12.7s
```

Файл `.transport.zip` будет в `core/packages/`:

```bash
ls -lh core/packages/localhost-*.transport.zip
```

### Запуск через браузер

Перейдите по адресу:

```
http://ваш-сайт/vapor/vapor.php
```

Дождитесь завершения процесса. Путь к файлу будет показан на экране.

> ⚠️ Для крупных сайтов выполнение через браузер может завершиться таймаутом. Используйте CLI.

### Пользовательские опции

Создайте файл `vapor/config.php` для настройки экспорта:

```php
<?php
return array(
    'excludeFiles' => array(),
    'excludeExtraTables' => array(),
    'excludeExtraTablePrefix' => array(),
);
```

| Опция | Описание |
|---|---|
| `excludeFiles` | Массив имён файлов/директорий для исключения из `MODX_BASE_PATH` (без `/` на конце для директорий). |
| `excludeExtraTables` | Массив некорневых таблиц БД для исключения из пакета. |
| `excludeExtraTablePrefix` | Массив некорневых таблиц, к которым **не нужно** добавлять `table_prefix` целевого сайта при импорте. Имеет смысл только если исходный сайт не использует `table_prefix`. |

---

## Импорт пакета

### Через менеджер MODX

1. Скопируйте файл `.transport.zip` в `core/packages/` на целевом сервере.
2. Перейдите в **Менеджер → Приложения → Установщик**.
3. Нажмите **Сканировать пакеты** — пакет появится в списке.
4. Нажмите **Установить** и следуйте инструкциям.

### Через CLI (import.php)

```bash
php vapor/import.php --core_path=/var/www/html/core/ --package=core/packages/ваш-пакет.transport.zip
```

### Что происходит при импорте

Транспортный пакет устанавливается в следующем порядке:

1. **Файловые vehicle** (`xPDOFileVehicle`) — копируют `core/components/`, `assets/`, `manager/components/` и `vapor/model/` на целевой сервер. Резолвер `resolve.vapor_model.php` загружает класс `vaporVehicle`.
2. **Объектные vehicle** (`xPDOObjectVehicle`) — устанавливают все объекты ядра MODX (настройки, чанки, сниппеты, плагины, шаблоны, TV, ресурсы, пользователи и т.д.).
3. **Vehicle нестандартных таблиц** (`vaporVehicle`) — создают и заполняют некорневые таблицы (например, `seosuite_*`, `ms3_*`, `counters_*`). Валидатор `validate.truncate_tables.php` выполняется первым, очищая существующие корневые таблицы.

> 💡 Класс `vaporVehicle` доступен на целевом сервере даже если Vapor туда не установлен — файл класса копируется на шаге 1.

---

## Решение проблем

### Просмотр лога Vapor

Каждый запуск создаёт лог-файл в `core/cache/logs/`:

```bash
cat core/cache/logs/vapor-*.log
```

Проверка ошибок:

```bash
grep -iE "error|fail|could not" core/cache/logs/vapor-*.log
```

### Известные некритичные предупреждения

| Предупреждение | Причина | Влияние |
|---|---|---|
| `Could not load package metadata for Sterc\FormIt\Model` | FormIt использует устаревший формат модели | Нет |
| `Creation of dynamic property modPackageBuilder::$modx is deprecated` | PHP 8.2 deprecation в ядре MODX | Нет |

### Частые проблемы

#### `Table doesn't exist` после импорта

Если после импорта отсутствуют некорневые таблицы (например, `seosuite_resource`, `ms3_customer_tokens`), убедитесь, что используете **адаптированную версию** Vapor (этот форк). В оригинальной версии есть баг: класс `vaporVehicle` некорректно загружается в xPDO 3 — см. [§ 2.11 в технической документации](VAPOR_MODX3_ADAPTATION.md#211-vaporvehicle--fqn-для-нестандартных-таблиц-критический-фикс-импорта).

#### `Call to a member function checkPolicy() on null`

Возникает в CLI-режиме, когда `$modx->user` — анонимный объект. Адаптированная версия устанавливает реального пользователя (ID 1) в CLI-режиме. Убедитесь, что запускаете адаптированный `vapor.php`.

---

## Адаптация под MODX 3 / xPDO 3

Оригинальный Vapor написан для MODX 2.x / PHP 5–7. Этот форк включает все изменения, необходимые для работы под MODX 3.2.x / xPDO 3 / PHP 8.2.

### Сводка изменений

| № | Изменение | Причина |
|---|---|---|
| 1 | `strftime()` → `date()` | Удалена в PHP 8.1 |
| 2 | `each()` → `foreach` | Удалена в PHP 8.0 |
| 3 | `xPDO::OPT_SETUP` убран | Блокирует `_initContext()` в MODX 3 |
| 4 | Инъекция пользователя в CLI | `hasPermission()` падает без реального пользователя |
| 5 | FQN для `modPackageBuilder` | Короткие alias работают лениво в xPDO 3 |
| 6 | FQN для `$modx->call()` | `call()` не резолвит короткие имена |
| 7 | FQN для `vehicle_class` (`xPDOFileVehicle`) | `loadClass()` требует имена с неймспейсом |
| 8 | **FQN `\vaporVehicle` для нестандартных таблиц** | **Критично: без этого таблицы не создаются при импорте** |
| 9 | Проверка пустого `getTableName()` | Абстрактные классы возвращают пустую строку |
| 10 | Fallback для `getOption('database')` | Может быть `NULL` в MODX 3 |
| 11 | Удалён блок `version_compare('2.2.0')` | Классы dashboard/media source всегда есть в MODX 3 |
| 12 | `vaporVehicle` наследует `xPDO\Transport\xPDOVehicle` | Корректный базовый класс в xPDO 3 |
| 13 | `install()` возвращает `true` | Оригинал всегда возвращал `false` |
| 14 | `instanceof` использует FQN в резолверах | Короткие имена не работают без предзагрузки |
| 15 | Добавлены новые классы MODX 3 | `modAccessNamespace`, `modExtensionPackage` и др. |
| 16 | Удалены устаревшие классы MODX 2 | `modAction`, `modAccessAction`, `modClassMap` |

### Подробный технический справочник

Полная техническая документация всех изменений — включая примеры кода (было/стало), анализ внутренностей xPDO 3 и пошаговое описание конвейера импорта — в файле **[VAPOR_MODX3_ADAPTATION.md](VAPOR_MODX3_ADAPTATION.md)**.

Ключевые разделы:

- **§1** — Изменения списка классов MODX (добавлены/удалены/без изменений)
- **§2** — Все изменения в `vapor.php` (11 пунктов с кодом до/после)
- **§3** — Изменения в `vaporvehicle.class.php`
- **§4** — Изменения в скриптах-резолверах/валидаторах
- **§5** — Конвейер импорта на целевом сервере (пошагово)
- **§7** — Справочная таблица совместимости API
- **§9** — История изменений

---

## История изменений

### 1.1.0-beta — Порт под MODX 3 / xPDO 3 / PHP 8.2

- **Совместимость с PHP 8.2:** `strftime()` → `date()`, `each()` → `foreach`
- **Неймспейсы xPDO 3:** все `vehicle_class`, `modPackageBuilder`, `modx->call()` и `instanceof` используют FQN
- **Критический фикс:** `vaporVehicle` использует `\vaporVehicle` (FQN) вместо `vehicle_package = 'vapor'` — исправляет отсутствие создания нестандартных таблиц при импорте
- **CLI-режим:** инъекция пользователя с ID 1 для предотвращения падения `checkPolicy()`
- **`xPDO::OPT_SETUP`** удалён — блокировал `_initContext()` в MODX 3
- **`vaporVehicle::install()`** теперь возвращает `true` при успехе
- **`vaporVehicle`** наследует `xPDO\Transport\xPDOVehicle` (не `xPDOTransportVehicle`)
- **Добавлены новые классы MODX 3:** `modAccessNamespace`, `modExtensionPackage`, `modDeprecatedCall`, `modDeprecatedMethod`, `modUserGroupSetting`
- **Удалены устаревшие классы MODX 2:** `modAction`, `modAccessAction`, `modClassMap`
- **Проверка `getTableName()`** на пустой результат для абстрактных классов
- **Fallback `getOption('database')`** на пустую строку
- **Резолверы** используют FQN для `instanceof`

---

## Лицензия

MODX Vapor — Copyright 2012 MODX, LLC.

GPL v2 или (по вашему выбору) любая более поздняя версия.
