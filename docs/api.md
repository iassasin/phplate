# API

Все доступные функции находятся в классе Template

---

```php
public static function Template::init($tplpath, TemplateOptions $options = null)
```

 Инициализирует phplate, задает каталог, хранящий шаблоны (файлы .html)
 Если настройки не заданы то шаблонизатор будет инициализирован с настройками по умолчанию

- **`string $tplpath`** - путь к каталогу, содержащему шаблоны сайта
- **`TemplateOptions $options`** - определяет [настройки Phplate](options.md)
- **Возвращаемое значение**: объект класса `TemplateEngine`.
 Использование объекта `TemplateEngine` может быть полезно в тех случаях,
 когда требуется работа *Phplate* в нескольких режимах, то есть с разными настройками.
 Важно отметить, что *API* всегда работает именно с теми настройками, которые были переданы в `Template::init` перед использованием *API*.

`TemplateOptions` содержит следующие методы:
- `getDateFormat()` и `setDateFormat(string $format)`
  - Управление форматом по-умолчанию для пайп-функции `date`
  - Значение по-умолчанию: `Y-m-d H:i:s`
- `getCacheEnabled()` и `setCacheEnabled(boolean $value)`
  - Управление кэшированием результата компиляции шаблона (в файлы .ctpl)
  - Значение по-умолчанию: `true`
- `getAutoSafeEnabled()` и `setAutoSafeEnabled(boolean $value)`
  - Управление автоматическим применением пайп-функции `safe` для всего вывода в [блоке аргументов](syntax.md)
  - Значение по-умолчанию: `true`

Пример настройки:
```php
Template::init($_SERVER['DOCUMENT_ROOT'].'/templates/', (new TemplateOptions)
    ->setCacheEnabled(true)
    ->setAutoSafeEnabled(true)
);
```

---

```php
public static function Template::addUserFunctionHandler($f)
```

Назначает пользовательский обработчик пайп-функций

- **`function $f`** - функция вида **`function ($val, $fargs)`**
  - **`mixed $val`** - исходное значение
  - **`array $fargs`** - массив переданных пайп-функции аргументов
  - **Возвращаемое значение**:  новое значение `$val`
- **Возвращаемое значение**:  нет

---

```php
public static function Template::addGlobalVar($name, $val)
```

Добавляет новый глобальный аргумент (глобальную переменную)

- **`string $name`** - имя глобального аргумента
- **`mixed $val`** - значение глобальной переменной, передается по значению
- **Возвращаемое значение**: нет

---

```php
public static function Template::build($tplname, $values)
```

Выполняет подстановку в аргументов в шаблон из указанного файла

- **`string $tplname`** - путь к шаблону, без расширения и базового пути (который задается функцией init)
- **`array $values`** - аргументы шаблона, могут быть чем угодно
- **Возвращаемое значение**: **`string`**, шаблон с подставленными аргументами

---

```php
public static function Template::buildStr($tplstr, $values)
```

Выполняет подстановку в аргументов в шаблон, переданный функции напрямую

- **`string $tplstr`** - код шаблона, в который подставляются аргументы, аналогично содержимому файлов шаблонов
- **`array $values`** - аргументы шаблона, могут быть чем угодно
- **Возвращаемое значение**: **`string`**, шаблон с подставленными аргументами
