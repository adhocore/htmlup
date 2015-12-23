# htmlup [![build status](https://travis-ci.org/adhocore/htmlup.svg?branch=master)](https://travis-ci.org/adhocore/htmlup)

`htmlup` is ultra lightweight (packed in just _250 ncloc_) and uber speedy markdown to html parser written in PHP.
**Concept** - it splits the markdown into lines and parses to markup one by one, finally applies markdown syntaxes on the markup.
It supports most of the markdown as in [specs](https://github.com/adam-p/markdown-here/wiki/Markdown-Cheatsheet "cheatsheet"). 

**Crazy Part:** it is _single class_, _single function_ library, because hey! construct() and toString() are magics 


# installation

edit your `composer.json` to include `"adhocore/htmlup": "1.0.*@dev"` in the `require` section and run `composer update`, or run `composer require adhocore/htmlup:1.0.*@dev`


# usage

```php
require '/path/to/vendor/autoload.php';

echo new Ahc\HtmlUp($markdownText);
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


# copyright and licence

- &copy; 2014 Jitendra Adhikari
- licence: WTFPL


# todo

- make robust, and provide full support of spec
- ~~handle markdown table syntax~~
- **markdown extra** however, is _not planned_ :(


# contribution

- fork and pull request for patch/fix
- create issue for _breaking_ bugs and severe markdown spec violation


that's all folks !
