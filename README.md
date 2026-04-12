# Sandbox

Приложение для Webasyst Framework — интерактивная «песочница» для разработки и отладки PHP и Smarty-кода прямо в бэкенде.

[![PHP Compatibility](https://github.com/Syrnik/sandbox/actions/workflows/main.yml/badge.svg)](https://github.com/Syrnik/sandbox/actions/workflows/main.yml)

## Возможности

- Редактор PHP-кода с подсветкой синтаксиса (ACE editor)
- Редактор Smarty-шаблонов с шпаргалкой по доступным переменным
- Окружения — именованные наборы переменных, которые инжектируются при выполнении
- Каталог сниппетов с папками, общим доступом и быстрым поиском
- Персональные переменные пользователя, доступные между запусками

---

## PHP API — `sandboxHelper`

Хелпер для работы с персональными переменными пользователя из PHP-кода сниппетов.

```php
$helper = new sandboxHelper($contactId);
```

| Метод | Описание |
|-------|----------|
| `set(string $name, mixed $value): void` | Сохранить переменную. Нестроковые значения сериализуются автоматически |
| `get(string $name, mixed $default = null): mixed` | Получить переменную. Возвращает `$default`, если не найдена |
| `delete(string $name): void` | Удалить переменную |
| `getAll(): array` | Получить все переменные пользователя |

### Пример (PHP-редактор)

```php
$helper = new sandboxHelper(wa()->getUser()->getId());

$helper->set('counter', 42);
$helper->set('last_run', new DateTime());

echo $helper->get('counter');        // 42
echo $helper->get('missing', 'n/a'); // n/a

print_r($helper->getAll());

$helper->delete('counter');
```

---

## PHP API — `sandboxConfig` / `sandbox()`

Глобальная функция `sandbox()` возвращает объект `sandboxConfig` — тонкую обёртку над `sandboxHelper`, привязанную к текущему авторизованному пользователю.

```php
sandbox()->setVar('key', $value);
$value = sandbox()->getVar('key', $default);
sandbox()->deleteVar('key');
$all = sandbox()->getAllVars();
```

| Метод | Описание |
|-------|----------|
| `setVar(string $name, mixed $value): void` | Сохранить переменную текущего пользователя |
| `getVar(string $name, mixed $default = null): mixed` | Получить переменную текущего пользователя |
| `deleteVar(string $name): void` | Удалить переменную текущего пользователя |
| `getAllVars(): array` | Получить все переменные текущего пользователя |

### Пример (PHP-редактор)

```php
// Накопительный счётчик запусков
$count = sandbox()->getVar('runs', 0) + 1;
sandbox()->setVar('runs', $count);
echo "Запуск #$count";
```

---

## Smarty API — `$wa->sandbox`

`sandboxViewHelper` предоставляет те же методы в шаблонах Smarty через стандартный механизм `waAppViewHelper`. Доступен как `$wa->sandbox`.

| Выражение | Описание |
|-----------|----------|
| `{$wa->sandbox->getVar('name')}` | Вывести переменную текущего пользователя |
| `{$wa->sandbox->getVar('name', 'default')}` | Вывести переменную с fallback-значением |
| `{$wa->sandbox->getAllVars()}` | Получить массив всех переменных |
| `{$wa->sandbox->setVar('name', $value)}` | Сохранить переменную |
| `{$wa->sandbox->deleteVar('name')}` | Удалить переменную |

### Пример (Smarty-редактор)

```smarty
{* Прочитать переменную, установленную из PHP *}
<p>Последнее значение: {$wa->sandbox->getVar('last_value', '—')}</p>

{* Перебрать все сохранённые переменные *}
{foreach $wa->sandbox->getAllVars() as $var}
    <b>{$var.name}</b>: {$var.value}<br>
{/foreach}
```

---

## Окружения

Окружение — именованный набор переменных (ключ → значение). При выполнении переменные инжектируются напрямую через `extract()` — как обычные PHP-переменные и Smarty-переменные. Удобно для параметризации сниппетов без изменения кода.

```php
// Если выбрано окружение с переменными host=db.local, port=3306
echo $host; // db.local
echo $port; // 3306
```

В Smarty-шаблоне:

```smarty
{$host} {* db.local *}
{$port} {* 3306 *}
```

> Значения переменных окружения — **всегда строки**. UI не поддерживает массивы, объекты и `null`: пустое поле сохраняется как пустая строка `""`. Если нужен массив, храните JSON-строку и парсите вручную: `$data = json_decode($json_var, true)`.

> При конфликте имён с внутренними переменными исполнителя переменная окружения получает префикс `env_` (например, `$env_code`).

Общие окружения видны всем пользователям, личные — только владельцу.
