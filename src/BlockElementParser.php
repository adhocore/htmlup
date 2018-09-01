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

    public function codeInternal($codeBlock)
    {
        while (isset($this->lines[$this->pointer + 1])) {
            $this->line = $this->escape($this->lines[$this->pointer + 1]);

            if (($codeBlock && \substr(\ltrim($this->line), 0, 3) !== '```')
                || \strpos($this->line, $this->indentStr) === 0
            ) {
                $this->markup .= "\n"; // @todo: donot use \n for first line
                $this->markup .= $codeBlock ? $this->line : \substr($this->line, $this->indentLen);

                $this->pointer++;
            } else {
                break;
            }
        }
    }

    protected function listInternal()
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

    protected function tableInternal($headerCount)
    {
        $columnCount = \preg_match_all(static::RE_MD_TCOL, \trim($this->trimmedNextLine, '|'));

        if ($headerCount > 0 && $headerCount <= $columnCount) {
            $this->pointer++;

            $this->inTable = \true;
            $this->markup .= "<table>\n<thead>\n<tr>\n";
            $this->trimmedLine = \trim($this->trimmedLine, '|');

            foreach (\explode('|', $this->trimmedLine) as $hdr) {
                $this->markup .= '<th>' . \trim($hdr) . "</th>\n";
            }

            $this->markup .= "</tr>\n</thead>\n<tbody>\n";

            return \true;
        }
    }
}
