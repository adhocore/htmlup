## adhocore/htmlup

[![Latest Version](https://img.shields.io/github/release/adhocore/htmlup.svg?style=flat-square)](https://github.com/adhocore/htmlup/releases)
[![Travis Build](https://img.shields.io/travis/adhocore/htmlup/master.svg?style=flat-square)](https://travis-ci.org/adhocore/htmlup?branch=master)
[![Scrutinizer CI](https://img.shields.io/scrutinizer/g/adhocore/htmlup.svg?style=flat-square)](https://scrutinizer-ci.com/g/adhocore/htmlup/?branch=master)
[![Codecov branch](https://img.shields.io/codecov/c/github/adhocore/htmlup/master.svg?style=flat-square)](https://codecov.io/gh/adhocore/htmlup)
[![StyleCI](https://styleci.io/repos/20793745/shield)](https://styleci.io/repos/20793745)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg?style=flat-square)](https://www.paypal.me/ji10/50usd)
[![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=Lightweight+and+fast+markdown+to+HTML+parser+for+PHP&url=https://github.com/adhocore/htmlup&hashtags=php,markdown,markdownparser,phpmarkdown)


`htmlup` is ultra lightweight and uber speedy markdown to html parser written in PHP.
**Concept** - it splits the markdown into lines and parses to markup one by one, finally applies markdown syntaxes on the markup.
It supports most of the markdown as in [specs](https://github.com/adam-p/markdown-here/wiki/Markdown-Cheatsheet "cheatsheet").


# installation

Run `composer require adhocore/htmlup`


# usage

```php
<?php

use Ahc\HtmlUp;

// require '/path/to/vendor/autoload.php';

// Defaults to 4 space indentation.
echo new Ahc\HtmlUp($markdownText);

// Force 2 space indentation.
echo new HtmlUp($markdownText, 2);

// Also possible:
echo (new Htmlup)->parse($markdownText);
```


# features

## nesting

It provides limited support to deep nested elements, supported items are:

- lists inside lists
- blockquotes inside blockcodes
- lists inside blockquotes

## raw html

you can throw in your raw html but with a blank line at start and end to delimit the block at like so-

```html

<dl>
  <dt>
  	A
  </dt>
  <dd>Apple
  	</dd>
  	<dt>B
  </dt>
  <dd>
  Ball</dd>
</dl>

```

## table

supports [GFM table syntax](https://help.github.com/articles/github-flavored-markdown/#tables), example:

```
a | b | c
--- |----| ---
1 | 2  |3
 4| 5 | 6
```

is rendered as:

a | b | c
--- |----| ---
1 | 2  |3
 4| 5 | 6


# todo

- make robust, and provide full support of spec
- ~~handle markdown table syntax~~
- **markdown extra** however, is _not planned_ :(


## contributing

- fork and pull request for patch/fix
- create issue for _breaking_ bugs and severe markdown spec violation
- please check [the guide](./CONTRIBUTING.md)

## license

> &copy; 2014-2018 | **Jitendra Adhikari** | [MIT](./LICENSE)
