<?php

use Ahc\HtmlUp;

require dirname(__DIR__).'/vendor/autoload.php';

$markdown = file_get_contents(dirname(__DIR__).'/readme.md');

/* You can use any of the three usage methods below */

// usage 1
// $h = new HtmlUp($markdown);
// echo $h->parse();

// usage 2
// $h = new HtmlUp($markdown);
// echo $h;

// usage 3
echo new HtmlUp($markdown);
