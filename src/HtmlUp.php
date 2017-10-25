<?php

namespace Ahc;

/**
 * HtmlUp - A **lightweight** && **fast** `markdown` to HTML Parser.
 *
 * Supports most of the markdown specs except deep nested elements.
 * Check readme.md for the details of its features && limitations.
 *
 * @author adhocore | Jitendra Adhikari <jiten.adhikary@gmail.com>
 * @copyright (c) 2014 Jitendra Adhikari
 */
class HtmlUp
{
    protected $lines       = [];
    protected $stackList   = [];
    protected $stackBlock  = [];
    protected $stackTable  = [];

    protected $pointer     = -1;
    protected $listLevel   = 0;
    protected $quoteLevel  = 0;
    protected $indent      = 0;
    protected $nextIndent  = 0;
    protected $lastPointer = 0;

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

        $this->lines       = array_merge([''], explode("\n", $markdown), ['']);
        $this->lastPointer = count($this->lines) - 1;
    }

    public function __toString()
    {
        return $this->parse();
    }

    public function parse($markdown = null)
    {
        if (null !== $markdown) {
            $this->resetExcept();

            $this->scan($markdown);
        }

        if ([] === $this->lines) {
            return '';
        }

        $nestLevel   = $quoteLevel = 0;
        $indent      = $nextIndent = 0;
        $lastPointer = count($this->lines) - 1;

        while (isset($this->lines[++$this->pointer])) {
            $line        = $this->lines[$this->pointer];
            $this->trimmedLine = trim($line);

            // flush stacks at the end of block
            if ($this->flush()) {
                continue;
            }

            // raw html
            if (preg_match('/^<\/?\w.*?\/?>/', $this->trimmedLine) || isset($inHtml)) {
                $this->markup .= "\n$line";
                if (empty($inHtml) && empty($this->lines[$this->pointer-1])) {
                    $inHtml = true;
                }

                continue;
            }

            $nextLine = $this->pointer < $lastPointer
                ? $this->lines[$this->pointer + 1]
                : null;
            $trimmedNextLine = $nextLine ? trim($nextLine) : null;

            $indent     = strlen($line) - strlen(ltrim($line));
            $nextIndent = $nextLine ? strlen($nextLine) - strlen(ltrim($nextLine)) : 0;

            $nextMark1  = isset($trimmedNextLine[0]) ? $trimmedNextLine[0] : null;
            $nextMark12 = $trimmedNextLine ? substr($trimmedNextLine, 0, 2) : null;

            // blockquote
            if (preg_match('~^\s*(>+)\s+~', $line, $quoteMatch)) {
                $line = substr($line, strlen($quoteMatch[0]));
                $this->trimmedLine = trim($line);

                if (empty($inQuote) || $quoteLevel < strlen($quoteMatch[1])) {
                    $this->markup .= "\n<blockquote>";
                    $this->stackBlock[] = "\n</blockquote>";
                    ++$quoteLevel;
                }

                $inQuote = true;
            }

            $mark1  = $this->trimmedLine[0];
            $mark12 = substr($this->trimmedLine, 0, 2);

            // atx
            if ($mark1 === '#') {
                $level = strlen($this->trimmedLine) - strlen(ltrim($this->trimmedLine, '#'));
                if ($level < 7) {
                    $this->markup .= "\n<h{$level}>".ltrim($this->trimmedLine, '# ')."</h{$level}>";

                    continue;
                }
            }

            // setext
            if (preg_match('~^\s*(={3,}|-{3,})\s*$~', $nextLine)) {
                $level = trim($nextLine, '- ') === '' ? '2' : '1';
                $this->markup .= "\n<h{$level}>{$this->trimmedLine}</h{$level}>";
                ++$this->pointer;

                continue;
            }

            // fence code
            if ($codeBlock = preg_match('/^```\s*([\w-]+)?/', $line, $codeMatch)
                || (empty($inList) && empty($inQuote) && $indent >= 4)
            ) {
                $lang = ($codeBlock && isset($codeMatch[1]))
                    ? " class=\"language-{$codeMatch[1]}\" "
                    : '';
                $this->markup .= "\n<pre><code{$lang}>";

                if (!$codeBlock) {
                    $this->markup .= htmlspecialchars(substr($line, 4));
                }

                while (isset($this->lines[$this->pointer + 1]) and
                    (($line = htmlspecialchars($this->lines[$this->pointer + 1])) || true) and
                    (($codeBlock && substr(ltrim($line), 0, 3) !== '```') || substr($line, 0, 4) === '    ')
                ) {
                    $this->markup .= "\n"; # @todo: donot use \n for first line
                    $this->markup .= $codeBlock ? $line : substr($line, 4);
                    ++$this->pointer;
                }

                ++$this->pointer;
                $this->markup .= '</code></pre>';

                continue;
            }

            // rule
            if (isset($this->lines[$this->pointer - 1]) and
                trim($this->lines[$this->pointer - 1]) === '' and
                preg_match('~^(_{3,}|\*{3,}|\-{3,})$~', $this->trimmedLine)
            ) {
                $this->markup .= "\n<hr />";
                continue;
            }

            // list
            if ($ul = in_array($mark12, array('- ', '* ', '+ ')) or
                preg_match('/^\d+\. /', $this->trimmedLine)
            ) {
                $wrapper = $ul ? 'ul' : 'ol';
                if (empty($inList)) {
                    $this->stackList[] = "</$wrapper>";
                    $this->markup .= "\n<$wrapper>\n";
                    $inList = true;
                    ++$nestLevel;
                }

                $this->markup .= '<li>'.ltrim($this->trimmedLine, '-*0123456789. ');

                if ($ul = in_array($nextMark12, array('- ', '* ', '+ ')) or
                    preg_match('/^\d+\. /', $trimmedNextLine)
                ) {
                    $wrapper = $ul ? 'ul' : 'ol';
                    if ($nextIndent > $indent) {
                        $this->stackList[] = "</li>\n";
                        $this->stackList[] = "</$wrapper>";
                        $this->markup .= "\n<$wrapper>\n";
                        ++$nestLevel;
                    } else {
                        $this->markup .= "</li>\n";
                    }

                    // handle nested lists ending
                    if ($nextIndent < $indent) {
                        $shift = intval(($indent - $nextIndent) / 4);
                        while ($shift--) {
                            $this->markup .= array_pop($this->stackList);
                            if ($nestLevel > 2) {
                                $this->markup .= array_pop($this->stackList);
                            }
                        }
                    }
                } else {
                    $this->markup .= "</li>\n";
                }

                continue;
            }

            if (isset($inList)) {
                $this->markup .= $this->trimmedLine;
                continue;
            }

            // table
            if (empty($inTable)) {
                if ($hdrCt = substr_count(trim($this->trimmedLine, '|'), '|') and
                    $colCt = preg_match_all('~(\|\s*\:)?\s*\-{3,}\s*(\:\s*\|)?~', trim($trimmedNextLine, '|')) and
                    $hdrCt <= $colCt
                ) {
                    $inTable = true;
                    ++$this->pointer;
                    $this->markup .= "<table>\n<thead>\n<tr>\n";
                    $this->trimmedLine = trim($this->trimmedLine, '|');
                    foreach (explode('|', $this->trimmedLine) as $hdr) {
                        $hdr = trim($hdr);
                        $this->markup .= "<th>{$hdr}</th>\n";
                    }
                    $this->markup .= "</tr>\n</thead>\n<tbody>\n";
                    continue;
                }
            } else {
                $this->markup .= "<tr>\n";
                foreach (explode('|', trim($this->trimmedLine, '|')) as $i => $col) {
                    if ($i > $hdrCt) {
                        break;
                    }
                    $col = trim($col);
                    $this->markup .= "<td>{$col}</td>\n";
                }
                $this->markup .= "</tr>\n";
                if (empty($trimmedNextLine) or
                    !substr_count(trim($trimmedNextLine, '|'), '|')
                ) {
                    $inTable = null;
                    $this->stackTable[] = "</tbody>\n</table>";
                }

                continue;
            }

            // paragraph
            if (empty($inPara)) {
                $this->markup .= "\n<p>";
            } else {
                $this->markup .= "\n<br />";
            }
            $this->markup .= "{$this->trimmedLine}";
            if (empty($trimmedNextLine)) {
                $this->markup .= '</p>';
                $inPara = null;
            } else {
                $inPara = true;
            }
        }

        // urls
        $this->markup = preg_replace(
            '~<(https?:[\/]{2}[^\s]+?)>~',
            '<a href="$1">$1</a>',
            $this->markup
        );

        // emails
        $this->markup = preg_replace(
            '~<(\S+?@\S+?)>~',
            '<a href="mailto:$1">$1</a>',
            $this->markup
        );

        // images
        $this->markup = preg_replace_callback('~!\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~', function ($img) {
            $title = isset($img[3]) ? " title={$img[3]} " : '';
            $alt = $img[1] ? " alt=\"{$img[1]}\" " : '';

            return "<img src=\"{$img[2]}\"{$title}{$alt}/>";
        }, $this->markup);

        // anchors
        $this->markup = preg_replace_callback('~\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~', function ($a) {
            $title = isset($a[3]) ? " title={$a[3]} " : '';

            return "<a href=\"{$a[2]}\"{$title}>{$a[1]}</a>";
        }, $this->markup);

        // em/code/strong/del
        $this->markup = preg_replace_callback('!(\*{1,2}|_{1,2}|`|~~)(.+?)\\1!', function ($em) {
            switch (true) {
                case substr($em[1], 0, 2) === '**':
                case substr($em[1], 0, 2) === '__':
                    $tag = 'strong';
                    break;
                case substr($em[1], 0, 2) === '~~':
                    $tag = 'del';
                    break;
                case $em[1] === '*': case $em[1] === '_':
                    $tag = 'em';
                    break;
                default:
                    $tag = 'code';
                    $em[2] = htmlspecialchars($em[2]);
            }

            return "<$tag>{$em[2]}</$tag>";
        }, $this->markup);

        return $this->markup;
    }

    public function reset($all = false)
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

        while ($this->stackList) {
            $this->markup .= array_pop($this->stackList);
        }
        while ($this->stackBlock) {
            $this->markup .= array_pop($this->stackBlock);
        }
        while ($this->stackTable) {
            $this->markup .= array_pop($this->stackTable);
        }

        $this->markup .= "\n";

        $this->reset(false);

        return true;
    }
}
