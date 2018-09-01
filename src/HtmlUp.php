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

/**
 * HtmlUp - A **lightweight** && **fast** `markdown` to HTML Parser.
 *
 * Supports most of the markdown specs except deep nested elements.
 * Check readme.md for the details of its features && limitations.
 *
 * @author    adhocore | Jitendra Adhikari <jiten.adhikary@gmail.com>
 * @copyright (c) 2014 Jitendra Adhikari
 */
class HtmlUp extends BlockElementParser
{
    /**
     * Constructor.
     *
     * @param string $markdown
     * @param int    $indentWidth
     */
    public function __construct($markdown = \null, $indentWidth = 4)
    {
        $this->scan($markdown, $indentWidth);
    }

    protected function scan($markdown, $indentWidth = 4)
    {
        if ('' === \trim($markdown)) {
            return;
        }

        $this->indentLen = $indentWidth == 2 ? 2 : 4;
        $this->indentStr = $indentWidth == 2 ? '  ' : '    ';

        // Normalize whitespaces
        $markdown = \str_replace("\t", $this->indentStr, $markdown);
        $markdown = \str_replace(["\r\n", "\r"], "\n", $markdown);

        $this->lines = \array_merge([''], \explode("\n", $markdown), ['']);
    }

    public function __toString()
    {
        return $this->parse();
    }

    /**
     * Parse markdown.
     *
     * @param string $markdown
     * @param int    $indentWidth
     *
     * @return string
     */
    public function parse($markdown = \null, $indentWidth = 4)
    {
        if (\null !== $markdown) {
            $this->reset(\true);

            $this->scan($markdown, $indentWidth);
        }

        if (empty($this->lines)) {
            return '';
        }

        $this->parseBlockElements();

        return (new SpanElementParser)->parse($this->markup);
    }

    protected function init()
    {
        list($this->prevLine, $this->trimmedPrevLine) = [$this->line, $this->trimmedLine];

        $this->line        = $this->lines[$this->pointer];
        $this->trimmedLine = \trim($this->line);

        $this->indent   = \strlen($this->line) - \strlen(\ltrim($this->line));
        $this->nextLine = isset($this->lines[$this->pointer + 1])
            ? $this->lines[$this->pointer + 1]
            : '';
        $this->trimmedNextLine = \trim($this->nextLine);
        $this->nextIndent      = \strlen($this->nextLine) - \strlen(\ltrim($this->nextLine));
    }

    protected function reset($all = \false)
    {
        $except = $all ? [] : \array_flip(['lines', 'pointer', 'markup', 'indentStr', 'indentLen']);

        // Reset all current values.
        foreach (\get_class_vars(__CLASS__) as $prop => $value) {
            isset($except[$prop]) || $this->{$prop} = $value;
        }
    }

    protected function flush()
    {
        if ('' !== $this->trimmedLine) {
            return \false;
        }

        while (!empty($this->stackList)) {
            $this->markup .= \array_pop($this->stackList);
        }

        while (!empty($this->stackBlock)) {
            $this->markup .= \array_pop($this->stackBlock);
        }

        while (!empty($this->stackTable)) {
            $this->markup .= \array_pop($this->stackTable);
        }

        $this->markup .= "\n";

        $this->reset(\false);

        return \true;
    }

    protected function raw()
    {
        if ($this->inHtml || \preg_match(static::RE_RAW, $this->trimmedLine)) {
            $this->markup .= "\n$this->line";
            if (!$this->inHtml && empty($this->lines[$this->pointer - 1])) {
                $this->inHtml = \true;
            }

            return \true;
        }
    }

    protected function quote()
    {
        if (\preg_match(static::RE_MD_QUOTE, $this->line, $quoteMatch)) {
            $this->line        = \substr($this->line, \strlen($quoteMatch[0]));
            $this->trimmedLine = \trim($this->line);

            if (!$this->inQuote || $this->quoteLevel < \strlen($quoteMatch[1])) {
                $this->markup .= "\n<blockquote>";

                $this->stackBlock[] = "\n</blockquote>";

                $this->quoteLevel++;
            }

            return $this->inQuote = \true;
        }
    }

    protected function paragraph()
    {
        $this->markup .= $this->inPara ? "\n<br />" : "\n<p>";
        $this->markup .= $this->trimmedLine;

        if (empty($this->trimmedNextLine)) {
            $this->markup .= '</p>';
            $this->inPara = \false;
        } else {
            $this->inPara = \true;
        }
    }
}
