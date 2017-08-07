# Phplate
[![Build Status](https://travis-ci.org/iassasin/phplate.svg?branch=dev)](https://travis-ci.org/iassasin/phplate)
[![Coverage Status](https://coveralls.io/repos/github/iassasin/phplate/badge.svg?branch=dev)](https://coveralls.io/github/iassasin/phplate?branch=dev)

Phplate - это легковесный и функциональный препроцессор шаблонов, написанный на php и не требующий никаких зависимостей.

# Быстрый старт

Чтобы начать использовать phplate, нужно установить его, как composer-пакет (либо вручную сделать `require_once` для всех файлов из каталога `src/`), после чего создать свой php-файл с настройками, например:

```php
require_once 'vendor/autoload.php';
use Iassasin\Phplate\TemplateEngine;

// Настройка пути к каталогу с шаблонами
TemplateEngine::init($_SERVER['DOCUMENT_ROOT'].'/resources/templates/');
```

Полный список настроек вы можете найти в [документации](docs/api.md).

Используя статический метод `TemplateEngine::build`, производится подстановка аргументов шаблона в шаблон. Результатом является строка, которую можно отдать клиенту.  
Аргументы передаются шаблону с помощью массива любого уровня вложенности.

Конечно же, возможности phplate не ограничиваются только подстановкой переменных в указанные места, но также есть возможность условного вывода ([if](docs/constructions/if.md)), написания циклов ([for](docs/constructions/for.md)), вычисления выражений перед выводом (в частности, препроцессинг параметров с помощью [пайп-функций](docs/pipe-functions.md)), вынос повторяющегося кода в [виджеты](docs/constructions/widget.md) и другие возможности.

## Пример использования

Допустим, файлы шаблонов хранятся в каталоге `%site_root%/resources/templates/`, а phplate сконфигурирован, как в примере выше.  
Шаблон, файл `%site_root%/resources/templates/order.html`:
```
Здравствуйте, {{name}}!
Ваш заказ №{{order.id}} поступил в пункт выдачи по адресу {{order.address}}.
```

Использование шаблона, файл `%site_root%/index.php`:

```php
require_once 'phplate_config.php'; // созданный нами выше файл конфига

echo TemplateEngine::build('order', [
	'name' => 'Петя',
	'order' => [
		'id' => 489,
		'address' => 'ул. Шаблонная, д.1, кв. 1',
	],
]);
```

В результате, когда произойдет переход на главную страницу сайта, пользователь увидит:
```
Здравствуйте, Петя!
Ваш заказ №489 поступил в пункт выдачи по адресу ул. Шаблонная, д.1, кв. 1.
```
