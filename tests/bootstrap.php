<?php

error_reporting(E_ALL);

// If we dont have globally autoloaded phpunit (maybe a phar) then 
// autoload from composer installed local vendor path
if (false === class_exists('PHPUnit_Framework_TestCase')) {
    require __DIR__ . "/../vendor/autoload.php";
}

require __DIR__ . "/../src/HtmlUp.php";
