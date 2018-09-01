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

class SpanElementParser
{
    use HtmlHelper;

    const RE_URL       = '~<(https?:[\/]{2}[^\s]+?)>~';
    const RE_EMAIL     = '~<(\S+?@\S+?)>~';
    const RE_MD_IMG    = '~!\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~';
    const RE_MD_URL    = '~\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~';
    const RE_MD_FONT   = '!(\*{1,2}|_{1,2}|`|~~)(.+?)\\1!';

    public function parse($markup)
    {
        return $this->spans(
            $this->anchors(
                $this->links($markup)
            )
        );
    }

    protected function links($markup)
    {
        $markup = $this->emails($markup);

        return \preg_replace(
            static::RE_URL,
            '<a href="$1">$1</a>',
            $markup
        );
    }

    protected function emails($markup)
    {
        return \preg_replace(
            static::RE_EMAIL,
            '<a href="mailto:$1">$1</a>',
            $markup
        );
    }

    protected function anchors($markup)
    {
        $markup = $this->images($markup);

        return \preg_replace_callback(static::RE_MD_URL, function ($a) {
            $title = isset($a[3]) ? " title={$a[3]} " : '';

            return "<a href=\"{$a[2]}\"{$title}>{$a[1]}</a>";
        }, $markup);
    }

    protected function images($markup)
    {
        return \preg_replace_callback(static::RE_MD_IMG, function ($img) {
            $title = isset($img[3]) ? " title={$img[3]} " : '';
            $alt   = $img[1] ? " alt=\"{$img[1]}\" " : '';

            return "<img src=\"{$img[2]}\"{$title}{$alt}/>";
        }, $markup);
    }

    protected function spans($markup)
    {
        // em/code/strong/del
        return \preg_replace_callback(static::RE_MD_FONT, function ($em) {
            switch (\substr($em[1], 0, 2)) {
                case '**':
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
        }, $markup);
    }
}
