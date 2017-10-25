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
        $lastPointer = count($this->lines) - 1;

        while (isset($this->lines[++$this->pointer])) {
            list($this->prevLine, $this->trimmedPrevLine) = [$this->line, $this->trimmedLine];
            $this->line        = $this->lines[$this->pointer];
            $this->trimmedLine = trim($this->line);

            // flush stacks at the end of block
            if ($this->flush() || $this->raw()) {
                continue;
            }

            $this->indent          = strlen($this->line) - strlen(ltrim($this->line));
            $this->nextLine        = isset($this->lines[$this->pointer + 1])
                                     ? $this->lines[$this->pointer + 1] : '';
            $this->trimmedNextLine = trim($this->nextLine);
            $this->nextIndent      = strlen($this->nextLine) - strlen(ltrim($this->nextLine));

            $nextMark1  = isset($this->trimmedNextLine[0]) ? $this->trimmedNextLine[0] : null;
            $nextMark12 = $this->trimmedNextLine ? substr($this->trimmedNextLine, 0, 2) : null;

            // blockquote
            $this->quote();

            $mark1  = $this->trimmedLine[0];
            $mark12 = substr($this->trimmedLine, 0, 2);

            if ($this->atx() || $this->setext()) {
                continue;
            }

            // fence code
            if ($codeBlock = preg_match('/^```\s*([\w-]+)?/', $this->line, $codeMatch)
                || (empty($inList) && empty($this->inQuote) && $this->indent >= 4)
            ) {
                $lang = ($codeBlock && isset($codeMatch[1]))
                    ? " class=\"language-{$codeMatch[1]}\" "
                    : '';
                $this->markup .= "\n<pre><code{$lang}>";

                if (!$codeBlock) {
                    $this->markup .= htmlspecialchars(substr($this->line, 4));
                }

                while (isset($this->lines[$this->pointer + 1]) and
                    (($this->line = htmlspecialchars($this->lines[$this->pointer + 1])) || true) and
                    (($codeBlock && substr(ltrim($this->line), 0, 3) !== '```') || substr($this->line, 0, 4) === '    ')
                ) {
                    $this->markup .= "\n"; # @todo: donot use \n for first line
                    $this->markup .= $codeBlock ? $this->line : substr($this->line, 4);
                    ++$this->pointer;
                }

                ++$this->pointer;
                $this->markup .= '</code></pre>';

                continue;
            }

            if ($this->rule()) {
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
                    preg_match('/^\d+\. /', $this->trimmedNextLine)
                ) {
                    $wrapper = $ul ? 'ul' : 'ol';
                    if ($this->nextIndent > $this->indent) {
                        $this->stackList[] = "</li>\n";
                        $this->stackList[] = "</$wrapper>";
                        $this->markup .= "\n<$wrapper>\n";
                        ++$nestLevel;
                    } else {
                        $this->markup .= "</li>\n";
                    }

                    // handle nested lists ending
                    if ($this->nextIndent < $this->indent) {
                        $shift = intval(($this->indent - $this->nextIndent) / 4);
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
                    $colCt = preg_match_all('~(\|\s*\:)?\s*\-{3,}\s*(\:\s*\|)?~', trim($this->trimmedNextLine, '|')) and
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
                if (empty($this->trimmedNextLine) or
                    !substr_count(trim($this->trimmedNextLine, '|'), '|')
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
            if (empty($this->trimmedNextLine)) {
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

    protected function raw()
    {
        if ($this->inHtml || preg_match('/^<\/?\w.*?\/?>/', $this->trimmedLine)) {
            $this->markup .= "\n$this->line";
            if (!$this->inHtml && empty($this->lines[$this->pointer - 1])) {
                $this->inHtml = true;
            }

            return true;
        }
    }

    protected function quote()
    {
        if (preg_match('~^\s*(>+)\s+~', $this->line, $quoteMatch)) {
            $this->line        = substr($this->line, strlen($quoteMatch[0]));
            $this->trimmedLine = trim($this->line);

            if (!$this->inQuote || $quoteLevel < strlen($quoteMatch[1])) {
                $this->markup .= "\n<blockquote>";

                $stackBlock[] = "\n</blockquote>";

                ++$this->quoteLevel;
            }

            return $this->inQuote = true;
        }
    }

    protected function atx()
    {
        if (isset($this->trimmedLine[0]) && $this->trimmedLine[0] === '#') {
            $level = strlen($this->trimmedLine) - strlen($segment = ltrim($this->trimmedLine, '#'));

            if ($level < 7) {
                $this->markup .= "\n<h{$level}>" . ltrim($segment) . "</h{$level}>";
                
                return true;
            }
        }
    }

    protected function setext()
    {
        if (preg_match('~^\s*(={3,}|-{3,})\s*$~', $this->nextLine)) {
            $level = trim($this->nextLine, '- ') === '' ? 2 : 1;

            $this->markup .= "\n<h{$level}>{$this->trimmedLine}</h{$level}>";

            ++$this->pointer;

            return true;
        }
    }

    protected function fence()
    {
        if ($codeBlock = preg_match('/^```\s*([\w-]+)?/', $this->line, $codeMatch)
            || (!$this->inList && !$this->inQuote && $this->indent >= strlen($this->indentStr))
        ) {
            $lang = ($codeBlock && isset($codeMatch[1]))
                ? " class=\"language-{$codeMatch[1]}\" "
                : '';

            $this->markup .= "\n<pre><code{$lang}>";

            if (!$codeBlock) {
                $this->markup .= $this->escape(substr($this->line, 4));
            }

            while (isset($this->lines[$this->pointer + 1]) and
                (($this->line = htmlspecialchars($this->lines[$this->pointer + 1])) || true) and
                (($codeBlock && substr(ltrim($this->line), 0, 3) !== '```') || substr($this->line, 0, 4) === $this->indentStr)
            ) {
                $this->markup .= "\n"; // @todo: donot use \n for first line
                $this->markup .= $codeBlock ? $this->line : substr($this->line, 4);

                ++$this->pointer;
            }

            ++$this->pointer;
            $this->markup .= '</code></pre>';

            return true;
        }
    }

    protected function rule()
    {
        if ($this->trimmedPrevLine === ''
            && preg_match('~^(_{3,}|\*{3,}|\-{3,})$~', $this->trimmedLine)
        ) {
            $this->markup .= "\n<hr />";

            return true;
        }
    }

    protected function listt()
    {
        if ($ul = in_array(substr($this->trimmedLine, 0, 2), ['- ', '* ', '+ '])
            || preg_match('/^\d+\. /', $this->trimmedLine)
        ) {
            $wrapper = $ul ? 'ul' : 'ol';

            if (!$this->inList) {
                $this->stackList[]   = "</$wrapper>";
                $this->markup .= "\n<$wrapper>\n";

                $inList = true;

                ++$this->listLevel;
            }

            $this->markup .= '<li>' . ltrim($this->trimmedLine, '-*0123456789. ');

            if ($ul = in_array(in_array(substr($this->trimmedNextLine, 0, 2), ['- ', '* ', '+ ']))
                || preg_match('/^\d+\. /', $this->trimmedNextLine)
            ) {
                $wrapper = $ul ? 'ul' : 'ol';

                if ($this->nextIndent > $this->indent) {
                    $this->stackList[] = "</li>\n";
                    $this->stackList[] = "</$wrapper>";

                    $this->markup .= "\n<$wrapper>\n";

                    ++$this->listLevel;
                } else {
                    $this->markup .= "</li>\n";
                }

                // Handle nested lists ending.
                if ($this->nextIndent < $this->indent) {
                    $shift = intval(($this->indent - $this->nextIndent) / strlen($this->indentStr));
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
        if (!$this->inTable) {
            if ($hdrCt = substr_count(trim($this->trimmedLine, '|'), '|') and
                $colCt = preg_match_all('~(\|\s*\:)?\s*\-{3,}\s*(\:\s*\|)?~', trim($this->trimmedNextLine, '|')) and
                $hdrCt <= $colCt
            ) {
                $this->inTable = true;
                ++$this->pointer;
                $this->markup .= "<table>\n<thead>\n<tr>\n";
                $this->trimmedLine = trim($this->trimmedLine, '|');
                foreach (explode('|', $this->trimmedLine) as $hdr) {
                    $hdr = trim($hdr);
                    $this->markup .= "<th>{$hdr}</th>\n";
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
                $col = trim($col);
                $this->markup .= "<td>{$col}</td>\n";
            }
            $this->markup .= "</tr>\n";
            if (empty($this->trimmedNextLine) or
                !substr_count(trim($this->trimmedNextLine, '|'), '|')
            ) {
                $this->inTable = null;
                $this->stackTable[] = "</tbody>\n</table>";
            }

            return true;
        }
    }

    protected function paragraph()
    {
        if (!$this->inPara) {
            $this->markup .= "\n<p>";
        } else {
            $this->markup .= "\n<br />";
        }

        $this->markup .= "{$this->trimmedLine}";

        if (empty($this->trimmedNextLine)) {
            $this->markup .= '</p>';
            $this->inPara = null;
        } else {
            $this->inPara = true;
        }
    }
}
