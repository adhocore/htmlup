<?php

namespace Ahc;

/**
 * HtmlUp - A **lightweight** && **fast** `markdown` to HTML Parser.
 *
 * Supports most of the markdown specs except deep nested elements.
 * Check readme.md for the details of its features && limitations.
 *
 * @author    adhocore | Jitendra Adhikari <jiten.adhikary@gmail.com>
 * @copyright (c) 2014 Jitendra Adhikari
 */
class HtmlUp
{
    const RE_URL       = '~<(https?:[\/]{2}[^\s]+?)>~';
    const RE_RAW       = '/^<\/?\w.*?\/?>/';
    const RE_EMAIL     = '~<(\S+?@\S+?)>~';
    const RE_MD_IMG    = '~!\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~';
    const RE_MD_URL    = '~\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~';
    const RE_MD_FONT   = '!(\*{1,2}|_{1,2}|`|~~)(.+?)\\1!';
    const RE_MD_QUOTE  = '~^\s*(>+)\s+~';
    const RE_MD_SETEXT = '~^\s*(={3,}|-{3,})\s*$~';
    const RE_MD_CODE   = '/^```\s*([\w-]+)?/';
    const RE_MD_RULE   = '~^(_{3,}|\*{3,}|\-{3,})$~';
    const RE_MD_TCOL   = '~(\|\s*\:)?\s*\-{3,}\s*(\:\s*\|)?~';
    const RE_MD_OL     = '/^\d+\. /';

    protected $lines       = [];
    protected $stackList   = [];
    protected $stackBlock  = [];
    protected $stackTable  = [];

    protected $pointer     = -1;
    protected $listLevel   = 0;
    protected $quoteLevel  = 0;
    protected $indent      = 0;
    protected $nextIndent  = 0;

    protected $indentStr       = '';
    protected $line            = '';
    protected $trimmedLine     = '';
    protected $prevLine        = '';
    protected $trimmedPrevLine = '';
    protected $nextLine        = '';
    protected $trimmedNextLine = '';
    protected $markup          = '';

    protected $inList  = false;
    protected $inQuote = false;
    protected $inPara  = false;
    protected $inHtml  = false;
    protected $inTable = false;

    /**
     * Constructor.
     *
     * @param string $markdown
     */
    public function __construct($markdown = null, $indentWidth = 4)
    {
        $this->indentStr = $indentWidth == 2 ? '  ' : '    ';

        if (null !== $markdown) {
            $this->scan($markdown);
        }
    }

    protected function scan($markdown)
    {
        if ('' === trim($markdown)) {
            return;
        }

        // Normalize whitespaces
        $markdown = str_replace("\t", $this->indentStr, $markdown);
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        $this->lines = array_merge([''], explode("\n", $markdown), ['']);
    }

    public function __toString()
    {
        return $this->parse();
    }

    public function parse($markdown = null)
    {
        if (null !== $markdown) {
            $this->reset(true);

            $this->scan($markdown);
        }

        if ([] === $this->lines) {
            return '';
        }

        $this->parseBlockElements();
        $this->parseSpanElements();

        return $this->markup;
    }

    protected function parseBlockElements()
    {
        while (isset($this->lines[++$this->pointer])) {
            $this->init();

            if ($this->flush() || $this->raw()) {
                continue;
            }

            $this->quote();

            if ($this->atx() || $this->setext() || $this->code() || $this->rule() || $this->listt()) {
                continue;
            }

            if ($this->inList) {
                $this->markup .= $this->trimmedLine;

                continue;
            }

            $this->table() || $this->paragraph();
        }
    }

    protected function init()
    {
        list($this->prevLine, $this->trimmedPrevLine) = [$this->line, $this->trimmedLine];

        $this->line        = $this->lines[$this->pointer];
        $this->trimmedLine = trim($this->line);

        $this->indent          = strlen($this->line) - strlen(ltrim($this->line));
        $this->nextLine        = isset($this->lines[$this->pointer + 1])
            ? $this->lines[$this->pointer + 1]
            : '';
        $this->trimmedNextLine = trim($this->nextLine);
        $this->nextIndent      = strlen($this->nextLine) - strlen(ltrim($this->nextLine));
    }


    protected function parseSpanElements()
    {
        $this->links();

        $this->anchors();

        $this->spans();
    }

    protected function links()
    {
        // URLs.
        $this->markup = preg_replace(
            static::RE_URL,
            '<a href="$1">$1</a>',
            $this->markup
        );

        // Emails.
        $this->markup = preg_replace(
            static::RE_EMAIL,
            '<a href="mailto:$1">$1</a>',
            $this->markup
        );
    }

    protected function anchors()
    {
        // Images.
        $this->markup = preg_replace_callback(static::RE_MD_IMG, function ($img) {
            $title = isset($img[3]) ? " title={$img[3]} " : '';
            $alt   = $img[1] ? " alt=\"{$img[1]}\" " : '';

            return "<img src=\"{$img[2]}\"{$title}{$alt}/>";
        }, $this->markup);

        // Anchors.
        $this->markup = preg_replace_callback(static::RE_MD_URL, function ($a) {
            $title = isset($a[3]) ? " title={$a[3]} " : '';

            return "<a href=\"{$a[2]}\"{$title}>{$a[1]}</a>";
        }, $this->markup);
    }

    protected function spans()
    {
        // em/code/strong/del
        $this->markup = preg_replace_callback(static::RE_MD_FONT, function ($em) {
            switch (substr($em[1], 0, 2)) {
                case  '**':
                case '__':
                    $tag = 'strong';
                    break;

                case '~~':
                    $tag = 'del';
                    break;

                case $em[1] === '*':
                case $em[1] === '_':
                    $tag = 'em';
                    break;

                default:
                    $tag = 'code';
                    $em[2] = $this->escape($em[2]);
            }

            return "<$tag>{$em[2]}</$tag>";
        }, $this->markup);
    }

    protected function escape($input)
    {
        return htmlspecialchars($input);
    }

    protected function reset($all = false)
    {
        $except = $all ? [] : array_fill_keys(['lines', 'pointer', 'markup'], true);

        // Reset all current values.
        foreach (get_class_vars(__CLASS__) as $prop => $value) {
            isset($except[$prop]) || $this->{$prop} = $value;
        }
    }

    protected function flush()
    {
        if ('' !== $this->trimmedLine) {
            return false;
        }

        while (!empty($this->stackList)) {
            $this->markup .= array_pop($this->stackList);
        }

        while (!empty($this->stackBlock)) {
            $this->markup .= array_pop($this->stackBlock);
        }

        while (!empty($this->stackTable)) {
            $this->markup .= array_pop($this->stackTable);
        }

        $this->markup .= "\n";

        $this->reset(false);

        return true;
    }

    protected function raw()
    {
        if ($this->inHtml || preg_match(static::RE_RAW, $this->trimmedLine)) {
            $this->markup .= "\n$this->line";
            if (!$this->inHtml && empty($this->lines[$this->pointer - 1])) {
                $this->inHtml = true;
            }

            return true;
        }
    }

    protected function quote()
    {
        if (preg_match(static::RE_MD_QUOTE, $this->line, $quoteMatch)) {
            $this->line        = substr($this->line, strlen($quoteMatch[0]));
            $this->trimmedLine = trim($this->line);

            if (!$this->inQuote || $this->quoteLevel < strlen($quoteMatch[1])) {
                $this->markup .= "\n<blockquote>";

                $this->stackBlock[] = "\n</blockquote>";

                ++$this->quoteLevel;
            }

            return $this->inQuote = true;
        }
    }

    protected function atx()
    {
        if (isset($this->trimmedLine[0]) && $this->trimmedLine[0] === '#') {
            $level = strlen($this->trimmedLine) - strlen(ltrim($this->trimmedLine, '#'));

            if ($level < 7) {
                $this->markup .= "\n<h{$level}>" . ltrim(ltrim($this->trimmedLine, '# ')) . "</h{$level}>";

                return true;
            }
        }
    }

    protected function setext()
    {
        if (preg_match(static::RE_MD_SETEXT, $this->nextLine)) {
            $level = trim($this->nextLine, '- ') === '' ? 2 : 1;

            $this->markup .= "\n<h{$level}>{$this->trimmedLine}</h{$level}>";

            ++$this->pointer;

            return true;
        }
    }

    protected function code()
    {
        $codeBlock = (bool) preg_match(static::RE_MD_CODE, $this->line, $codeMatch);

        if ($codeBlock || (empty($this->inList) && empty($this->inQuote) && $this->indent >= 4)) {
            $lang = isset($codeMatch[1])
                ? ' class="language-' . $codeMatch[1] . '"'
                : '';

            $this->markup .= "\n<pre><code{$lang}>";

            if (!$codeBlock) {
                $this->markup .= $this->escape(substr($this->line, 4));
            }

            $this->codeInternal($codeBlock);

            ++$this->pointer;

            $this->markup .= '</code></pre>';

            return true;
        }
    }

    public function codeInternal($codeBlock)
    {
        while (isset($this->lines[$this->pointer + 1])) {
            $this->line = $this->escape($this->lines[$this->pointer + 1]);

            if (($codeBlock && substr(ltrim($this->line), 0, 3) !== '```')
                || substr($this->line, 0, 4) === $this->indentStr
            ) {
                $this->markup .= "\n"; // @todo: donot use \n for first line
                $this->markup .= $codeBlock ? $this->line : substr($this->line, 4);

                ++$this->pointer;
            }
        }
    }

    protected function rule()
    {
        if ($this->trimmedPrevLine === ''
            && preg_match(static::RE_MD_RULE, $this->trimmedLine)
        ) {
            $this->markup .= "\n<hr />";

            return true;
        }
    }

    protected function listt()
    {
        $isUl = in_array(substr($this->trimmedLine, 0, 2), ['- ', '* ', '+ ']);

        if ($isUl || preg_match(static::RE_MD_OL, $this->trimmedLine)) {
            $wrapper = $isUl ? 'ul' : 'ol';

            if (!$this->inList) {
                $this->stackList[] = "</$wrapper>";
                $this->markup .= "\n<$wrapper>\n";
                $this->inList      = true;

                ++$this->listLevel;
            }

            $this->markup .= '<li>' . ltrim($this->trimmedLine, '-*0123456789. ');

            $isUl = in_array(substr($this->trimmedNextLine, 0, 2), ['- ', '* ', '+ ']);

            if ($isUl || preg_match(static::RE_MD_OL, $this->trimmedNextLine)) {
                $wrapper = $isUl ? 'ul' : 'ol';
                if ($this->nextIndent > $this->indent) {
                    $this->stackList[] = "</li>\n";
                    $this->stackList[] = "</$wrapper>";
                    $this->markup .= "\n<$wrapper>\n";

                    ++$this->listLevel;
                } else {
                    $this->markup .= "</li>\n";
                }

                if ($this->nextIndent < $this->indent) {
                    $shift = intval(($this->indent - $this->nextIndent) / 4);
                    while ($shift--) {
                        $this->markup .= array_pop($this->stackList);
                        if ($this->listLevel > 2) {
                            $this->markup .= array_pop($this->stackList);
                        }
                    }
                }
            } else {
                $this->markup .= "</li>\n";
            }

            return true;
        }
    }

    protected function table()
    {
        static $hdrCt;

        if (!$this->inTable) {
            $hdrCt = substr_count(trim($this->trimmedLine, '|'), '|');
            $colCt = preg_match_all(static::RE_MD_TCOL, trim($this->trimmedNextLine, '|'));

            if ($hdrCt > 0 && $colCt > 0 && $hdrCt <= $colCt) {
                ++$this->pointer;

                $this->inTable     = true;
                $this->markup .= "<table>\n<thead>\n<tr>\n";
                $this->trimmedLine = trim($this->trimmedLine, '|');

                foreach (explode('|', $this->trimmedLine) as $hdr) {
                    $this->markup .= '<th>' . trim($hdr) . "</th>\n";
                }

                $this->markup .= "</tr>\n</thead>\n<tbody>\n";

                return true;
            }
        } else {
            $this->markup .= "<tr>\n";

            foreach (explode('|', trim($this->trimmedLine, '|')) as $i => $col) {
                if ($i > $hdrCt) {
                    break;
                }
                $col           = trim($col);
                $this->markup .= "<td>{$col}</td>\n";
            }

            $this->markup .= "</tr>\n";

            if (empty($this->trimmedNextLine) || !substr_count(trim($this->trimmedNextLine, '|'), '|')) {
                $hdrCt              = 0;
                $this->inTable      = false;
                $this->stackTable[] = "</tbody>\n</table>";
            }

            return true;
        }
    }

    protected function paragraph()
    {
        $this->markup .= $this->inPara ? "\n<br />" : "\n<p>";
        $this->markup .= $this->trimmedLine;

        if (empty($this->trimmedNextLine)) {
            $this->markup .= '</p>';
            $this->inPara = false;
        } else {
            $this->inPara = true;
        }
    }
}
