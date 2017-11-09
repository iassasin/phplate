# Опции Phplate

Работу *Phplate* можно настроить при помощи опций.
Для этого нужно создать объект класса `TemplateOptions`, у которого доступные следующие методы:

`TemplateOptions` содержит следующие методы:
- `getDateFormat()` и `setDateFormat(string $format)`
  - Задает формат по-умолчанию для пайп-функции `date`
  - Значение по-умолчанию: `Y-m-d H:i:s`
  - Исключения: `PhplateConfigException` в случае попытки установки некорректного формата даты.
- `getCacheEnabled()` и `setCacheEnabled(boolean $value)`
  - Включение/отключение кэширования результата компиляции шаблона (в файлы .ctpl)
  - Значение по-умолчанию: `true`
- `getAutoSafeEnabled()` и `setAutoSafeEnabled(boolean $value)`
  - Включение/выключение автоэкранирования (аналогично пайп-функции `safe`) выводимых данных _только_ в [блоке аргументов](syntax.md)
  - Значение по-умолчанию: `true`
- `getTemplateFileExtension()` и `setTemplateFileExtension(string $ext)`
  - Задает расширение для файлов-шаблонов (без точки). Используется при поиске файла-шаблона в вызовах `Template::build`
  - Значение по-умолчанию: `html`
- `getCacheDir()` и `setCacheDir(string $dir)`
  - Позволяет задать каталог, в котором будут храниться скомпилированные шаблоны *Phplate*. Если каталог не задан (пустая строка), кэш для каждого шаблона будет сохраняться в том же каталоге, что и сам шаблон
  - Значение по-умолчанию: пустая строка
  - Исключения: `PhplateConfigException` в случае попытки задать недоступный каталог.


Пример создания объекта с опциями:
```php
$options = (new TemplateOptions())
    ->setDateFormat('Y-m-d H:i:s')
    ->setAutoSafeEnabled(true)
;
```

Для того, чтобы настроить *Phplate* используя новые опции, небходимо передать объект `TemplateOptions` в статический метод `init()`:
```php
Template::init('/path/to/templates/', $options);
```
