<?php

include 'HtmlUp.php';

$markdown = <<<'MD'


# htmlup

`htmlup` is ultra lightweight (packed in just _250lines_) and uber speedy markdown to html parser written in PHP.
**Concept** - it splits the markdown into lines and parses to markup one by one, finally applies markdown syntaxes on the markup.
It supports most of the markdown as in [specs](https://github.com/adam-p/markdown-here/wiki/Markdown-Cheatsheet "cheatsheet"). 

**Crazy Part:** it is _single class_, _single function_ library, because hey! construct() and toString() are magics 


# usage

- include HtmlUp (`include '/path/to/HtmlUp.php';`)
- `echo new HtmlUp($markdownText);`
- for more styles see `example.php`


# features

## nesting

It provides limited support to deep nested elements, supported items are:

- lists inside lists 
- blockquotes inside blockcodes 
- lists inside blockquotes 

## raw html

you can throw in your raw html but with all the lines with a `tag`. so there cant be a text node like such-

```html
<div>
	<p>
	this line is _not_ parsed as raw html by `htmlup`, rather as free text (codeblock mostly)
	</p>
</div>
```

That _should_ be supported as per the markdown spec but for `htmlup` the raw html shoule be like such-

```html
<div>
	<p>this line _is_ parsed as raw html by `htmlup`</p>
</div>
```


# copyright and licence

- &copy; 2014 Jitendra Adhikari
- licence: WTFPL


# todo

- make robust, and provide full support of spec
- handle markdown table syntax
- **markdown extra** however, is _not planned_ :(


# contribution

- fork and pull request for patch/fix
- create issue for _breaking_ bugs and severe markdown spec violation


that's all folks !

MD;

/* You can use any of the three usage methods below */

# usage 1
// $h = new HtmlUp($markdown);
// echo $h->parse(); 

# usage 2
// $h = new HtmlUp($markdown);
// echo $h; 

# usage 3
echo new HtmlUp($markdown);