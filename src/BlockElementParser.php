<?php

/*
 * This file is part of the HTMLUP package.
 *
 * (c) Jitendra Adhikari <jiten.adhikary@gmail.com>
 *     <https://github.com/adhocore>
 *
 * Licensed under MIT license.
 */

namespace Ahc;

abstract class BlockElementParser
{
    use HtmlHelper;

    const RE_MD_QUOTE  = '~^\s*(>+)\s+~';
    const RE_RAW       = '/^<\/?\w.*?\/?>/';
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
    protected $indentLen   = 4;

    protected $indentStr       = '    ';
    protected $line            = '';
    protected $trimmedLine     = '';
    protected $prevLine        = '';
    protected $trimmedPrevLine = '';
    protected $nextLine        = '';
    protected $trimmedNextLine = '';
    protected $markup          = '';

    protected $inList  = \false;
    protected $inQuote = \false;
    protected $inPara  = \false;
    protected $inHtml  = \false;
    protected $inTable = \false;

    protected function parseBlockElements()
    {
        while (isset($this->lines[++$this->pointer])) {
            $this->init();

            if ($this->flush() || $this->raw()) {
                continue;
            }

            $this->quote();

            if (($block = $this->isBlock()) || $this->inList) {
                $this->markup .= $block ? '' : $this->trimmedLine;

                continue;
            }

            $this->table() || $this->paragraph();
        }
    }

    protected function isBlock()
    {
        return $this->atx() || $this->setext() || $this->code() || $this->rule() || $this->listt();
    }

    protected function atx()
    {
        if (\substr($this->trimmedLine, 0, 1) === '#') {
            $level = \strlen($this->trimmedLine) - \strlen(\ltrim($this->trimmedLine, '#'));
            $head  = $this->h($level, $this->trimmedLine);

            $this->markup .= $head;

            return (bool) $head;
        }
    }

    protected function setext()
    {
        if (\preg_match(static::RE_MD_SETEXT, $this->nextLine)) {
            $this->markup .= $this->h($this->nextLine, $this->trimmedLine);

            $this->pointer++;

            return \true;
        }
    }

    protected function code()
    {
        $isShifted = ($this->indent - $this->nextIndent) >= $this->indentLen;
        $codeBlock = \preg_match(static::RE_MD_CODE, $this->line, $codeMatch);

        if ($codeBlock || (!$this->inList && !$this->inQuote && $isShifted)) {
            $this->markup .= $this->codeStart($codeMatch);

            if (!$codeBlock) {
                $this->markup .= $this->escape(\substr($this->line, $this->indentLen));
            }

            $this->codeInternal($codeBlock);

            $this->pointer++;

            $this->markup .= '</code></pre>';

            return \true;
        }
    }

    private function codeInternal($codeBlock)
    {
        while (isset($this->lines[$this->pointer + 1])) {
            $this->line = $this->escape($this->lines[$this->pointer + 1]);

            if (($codeBlock && \substr(\ltrim($this->line), 0, 3) !== '```')
                || \strpos($this->line, $this->indentStr) === 0
            ) {
                $this->markup .= $this->codeLine($this->line, $codeBlock, $this->indentLen);

                $this->pointer++;

                continue;
            }

            break;
        }
    }

    protected function rule()
    {
        $this->markup .= $hr = $this->hr($this->trimmedPrevLine, $this->trimmedLine);

        return (bool) $hr;
    }

    protected function listt()
    {
        $isUl = \in_array(\substr($this->trimmedLine, 0, 2), ['- ', '* ', '+ ']);

        if ($isUl || \preg_match(static::RE_MD_OL, $this->trimmedLine)) {
            $wrapper = $isUl ? 'ul' : 'ol';

            if (!$this->inList) {
                $this->stackList[] = "</$wrapper>";

                $this->markup .= "\n<$wrapper>\n";
                $this->inList  = \true;

                $this->listLevel++;
            }

            $this->markup .= '<li>' . \ltrim($this->trimmedLine, '+-*0123456789. ');

            $this->listInternal();

            return \true;
        }
    }

    private function listInternal()
    {
        $isUl = \in_array(\substr($this->trimmedNextLine, 0, 2), ['- ', '* ', '+ ']);

        if ($isUl || \preg_match(static::RE_MD_OL, $this->trimmedNextLine)) {
            $wrapper = $isUl ? 'ul' : 'ol';
            if ($this->nextIndent > $this->indent) {
                $this->stackList[] = "</li>\n";
                $this->stackList[] = "</$wrapper>";
                $this->markup .= "\n<$wrapper>\n";

                $this->listLevel++;
            } else {
                $this->markup .= "</li>\n";
            }

            if ($this->nextIndent < $this->indent) {
                $shift = \intval(($this->indent - $this->nextIndent) / $this->indentLen);

                while ($shift--) {
                    $this->markup .= \array_pop($this->stackList);

                    if ($this->listLevel > 2) {
                        $this->markup .= \array_pop($this->stackList);
                    }
                }
            }
        } else {
            $this->markup .= "</li>\n";
        }
    }

    protected function table()
    {
        static $headerCount = 0;

        if (!$this->inTable) {
            $headerCount = \substr_count(\trim($this->trimmedLine, '|'), '|');

            return $this->tableInternal($headerCount);
        }

        $this->markup .= $this->tableRow($this->trimmedLine, $headerCount);

        if (empty($this->trimmedNextLine)
            || !\substr_count(\trim($this->trimmedNextLine, '|'), '|')
        ) {
            $headerCount        = 0;
            $this->inTable      = \false;
            $this->stackTable[] = "</tbody>\n</table>";
        }

        return \true;
    }

    private function tableInternal($headerCount)
    {
        $columnCount = \preg_match_all(static::RE_MD_TCOL, \trim($this->trimmedNextLine, '|'));

        if ($headerCount > 0 && $headerCount <= $columnCount) {
            $this->pointer++;

            $this->inTable = \true;
            $this->markup .= $this->tableStart($this->trimmedLine);

            return \true;
        }
    }
}
