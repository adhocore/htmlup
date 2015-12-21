<?php

error_reporting(E_ALL);

foreach (array(
    dirname(__DIR__).'/vendor/autoload.php',
    dirname(dirname(dirname(__DIR__))).'/autoload.php',
) as $loader) {
    if (is_file($loader)) {
        return require $loader;
    }
}

// As a last resort
require dirname(__DIR__).'/src/HtmlUp.php';
