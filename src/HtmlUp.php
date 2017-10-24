<?php

namespace Ahc;

/**
 * HtmlUp - A **lightweight** and **fast** `markdown` to HTML Parser.
 *
 * Supports most of the markdown specs except deep nested elements.
 * Check readme.md for the details of its features and limitations.
 * **Crazy Part:** it is _single class_, _single function_ library.
 *                 because hey! construct() and toString() are magics
 *
 * @author adhocore | Jitendra Adhikari <jiten.adhikary@gmail.com>
 * @copyright (c) 2014 Jitendra Adhikari
 */
class HtmlUp
{
    private $Lines;

    private $Pointer = -1;

    public function __construct($markdown)
    {
        // some normalisations
        $this->Lines =
            explode("\n",   // the lines !
                trim(       // trim trailing \n
                    str_replace(["\r\n", "\r"], "\n",   // use standard newline
                        str_replace("\t", '    ', $markdown) // use 4 spaces for tab
                    ), "\n"
                )
            );

        // Pad if NOT empty. Good for early return @self::parse()
        if (false === empty($this->Lines)) {
            array_unshift($this->Lines, '');
            $this->Lines[] = '';
        }

        unset($markdown);
    }

    public function __toString()
    {
        return $this->parse();
    }

    public function parse()
    {
        if (empty($this->Lines)) {
            return '';
        }

        $markup      = '';
        $nestLevel   = $quoteLevel   = 0;
        $indent      = $nextIndent      = 0;
        $stackList   = $stackBlock   = $stackTable   = [];
        $lastPointer = count($this->Lines) - 1;

        while (isset($this->Lines[++$this->Pointer])) {
            $line        = $this->Lines[$this->Pointer];
            $trimmedLine = trim($line);

            // flush stacks at the end of block
            if (empty($trimmedLine)) {
                while ($stackList) {
                    $markup .= array_pop($stackList);
                }
                while ($stackBlock) {
                    $markup .= array_pop($stackBlock);
                }
                while ($stackTable) {
                    $markup .= array_pop($stackTable);
                }

                $markup .= "\n";

                $inList    = $inQuote    = $inPara    = $inHtml    = null;
                $nestLevel = $quoteLevel = 0;
                continue;
            }

            // raw html
            if (preg_match('/^<\/?\w.*?\/?>/', $trimmedLine) or
                isset($inHtml)
            ) {
                $markup .= "\n$line";
                if (empty($inHtml) and
                    empty($this->Lines[$this->Pointer - 1])
                ) {
                    $inHtml = true;
                }
                continue;
            }

            $nextLine = $this->Pointer < $lastPointer
                ? $this->Lines[$this->Pointer + 1]
                : null;
            $trimmedNextLine = $nextLine ? trim($nextLine) : null;

            $indent     = strlen($line) - strlen(ltrim($line));
            $nextIndent = $nextLine ? strlen($nextLine) - strlen(ltrim($nextLine)) : 0;

            $nextMark1  = isset($trimmedNextLine[0]) ? $trimmedNextLine[0] : null;
            $nextMark12 = $trimmedNextLine ? substr($trimmedNextLine, 0, 2) : null;

            // blockquote
            if (preg_match('~^\s*(>+)\s+~', $line, $quoteMatch)) {
                $line        = substr($line, strlen($quoteMatch[0]));
                $trimmedLine = trim($line);
                if (empty($inQuote) or $quoteLevel < strlen($quoteMatch[1])) {
                    $markup .= "\n<blockquote>";
                    $stackBlock[] = "\n</blockquote>";
                    ++$quoteLevel;
                }
                $inQuote = true;
            }

            $mark1  = $trimmedLine[0];
            $mark12 = substr($trimmedLine, 0, 2);

            // atx
            if ($mark1 === '#') {
                $level = strlen($trimmedLine) - strlen(ltrim($trimmedLine, '#'));
                if ($level < 7) {
                    $markup .= "\n<h{$level}>" . ltrim($trimmedLine, '# ') . "</h{$level}>";
                    continue;
                }
            }

            // setext
            if (preg_match('~^\s*(={3,}|-{3,})\s*$~', $nextLine)) {
                $level = trim($nextLine, '- ') === '' ? '2' : '1';
                $markup .= "\n<h{$level}>{$trimmedLine}</h{$level}>";
                ++$this->Pointer;
                continue;
            }

            // fence code
            if ($codeBlock = preg_match('/^```\s*([\w-]+)?/', $line, $codeMatch)
                or (empty($inList) and empty($inQuote) and $indent >= 4)
            ) {
                $lang = ($codeBlock and isset($codeMatch[1]))
                    ? " class=\"language-{$codeMatch[1]}\" "
                    : '';
                $markup .= "\n<pre><code{$lang}>";
                if (!$codeBlock) {
                    $markup .= htmlspecialchars(substr($line, 4));
                }

                while (isset($this->Lines[$this->Pointer + 1]) and
                    (($line = htmlspecialchars($this->Lines[$this->Pointer + 1])) or true) and
                    (($codeBlock and substr(ltrim($line), 0, 3) !== '```') or substr($line, 0, 4) === '    ')
                ) {
                    $markup .= "\n"; // @todo: donot use \n for first line
                    $markup .= $codeBlock ? $line : substr($line, 4);
                    ++$this->Pointer;
                }
                ++$this->Pointer;
                $markup .= '</code></pre>';
                continue;
            }

            // rule
            if (isset($this->Lines[$this->Pointer - 1]) and
                trim($this->Lines[$this->Pointer - 1]) === '' and
                preg_match('~^(_{3,}|\*{3,}|\-{3,})$~', $trimmedLine)
            ) {
                $markup .= "\n<hr />";
                continue;
            }

            // list
            if ($ul = in_array($mark12, ['- ', '* ', '+ ']) or
                preg_match('/^\d+\. /', $trimmedLine)
            ) {
                $wrapper = $ul ? 'ul' : 'ol';
                if (empty($inList)) {
                    $stackList[] = "</$wrapper>";
                    $markup .= "\n<$wrapper>\n";
                    $inList = true;
                    ++$nestLevel;
                }

                $markup .= '<li>' . ltrim($trimmedLine, '-*0123456789. ');

                if ($ul = in_array($nextMark12, ['- ', '* ', '+ ']) or
                    preg_match('/^\d+\. /', $trimmedNextLine)
                ) {
                    $wrapper = $ul ? 'ul' : 'ol';
                    if ($nextIndent > $indent) {
                        $stackList[] = "</li>\n";
                        $stackList[] = "</$wrapper>";
                        $markup .= "\n<$wrapper>\n";
                        ++$nestLevel;
                    } else {
                        $markup .= "</li>\n";
                    }

                    // handle nested lists ending
                    if ($nextIndent < $indent) {
                        $shift = intval(($indent - $nextIndent) / 4);
                        while ($shift--) {
                            $markup .= array_pop($stackList);
                            if ($nestLevel > 2) {
                                $markup .= array_pop($stackList);
                            }
                        }
                    }
                } else {
                    $markup .= "</li>\n";
                }

                continue;
            }

            if (isset($inList)) {
                $markup .= $trimmedLine;
                continue;
            }

            // table
            if (empty($inTable)) {
                if ($hdrCt = substr_count(trim($trimmedLine, '|'), '|') and
                    $colCt = preg_match_all('~(\|\s*\:)?\s*\-{3,}\s*(\:\s*\|)?~', trim($trimmedNextLine, '|')) and
                    $hdrCt <= $colCt
                ) {
                    $inTable = true;
                    ++$this->Pointer;
                    $markup .= "<table>\n<thead>\n<tr>\n";
                    $trimmedLine = trim($trimmedLine, '|');
                    foreach (explode('|', $trimmedLine) as $hdr) {
                        $hdr = trim($hdr);
                        $markup .= "<th>{$hdr}</th>\n";
                    }
                    $markup .= "</tr>\n</thead>\n<tbody>\n";
                    continue;
                }
            } else {
                $markup .= "<tr>\n";
                foreach (explode('|', trim($trimmedLine, '|')) as $i => $col) {
                    if ($i > $hdrCt) {
                        break;
                    }
                    $col = trim($col);
                    $markup .= "<td>{$col}</td>\n";
                }
                $markup .= "</tr>\n";
                if (empty($trimmedNextLine) or
                    !substr_count(trim($trimmedNextLine, '|'), '|')
                ) {
                    $inTable      = null;
                    $stackTable[] = "</tbody>\n</table>";
                }

                continue;
            }

            // paragraph
            if (empty($inPara)) {
                $markup .= "\n<p>";
            } else {
                $markup .= "\n<br />";
            }
            $markup .= "{$trimmedLine}";
            if (empty($trimmedNextLine)) {
                $markup .= '</p>';
                $inPara = null;
            } else {
                $inPara = true;
            }
        }

        // urls
        $markup = preg_replace(
            '~<(https?:[\/]{2}[^\s]+?)>~',
            '<a href="$1">$1</a>',
            $markup
        );

        // emails
        $markup = preg_replace(
            '~<(\S+?@\S+?)>~',
            '<a href="mailto:$1">$1</a>',
            $markup
        );

        // images
        $markup = preg_replace_callback('~!\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~', function ($img) {
            $title = isset($img[3]) ? " title={$img[3]} " : '';
            $alt = $img[1] ? " alt=\"{$img[1]}\" " : '';

            return "<img src=\"{$img[2]}\"{$title}{$alt}/>";
        }, $markup);

        // anchors
        $markup = preg_replace_callback('~\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~', function ($a) {
            $title = isset($a[3]) ? " title={$a[3]} " : '';

            return "<a href=\"{$a[2]}\"{$title}>{$a[1]}</a>";
        }, $markup);

        // em/code/strong/del
        $markup = preg_replace_callback('!(\*{1,2}|_{1,2}|`|~~)(.+?)\\1!', function ($em) {
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
        }, $markup);

        return $markup;
    }
}
