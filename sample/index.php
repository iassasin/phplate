<?php

use Iassasin\Phplate\Template;

require_once '../vendor/autoload.php';
Template::init(__DIR__ . '/');

echo Template::build('tpl', []);
