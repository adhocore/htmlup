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

trait HtmlHelper
{
    public function escape($input)
    {
        return \htmlspecialchars($input);
    }

    public function h($level, $line)
    {
        if (\is_string($level)) {
            $level = \trim($level, '- ') === '' ? 2 : 1;
        }

        if ($level < 7) {
            return "\n<h{$level}>" . \ltrim(\ltrim($line, '# ')) . "</h{$level}>";
        }

        return '';
    }

    public function tableStart($line, $delim = '|')
    {
        $table = "<table>\n<thead>\n<tr>\n";

        foreach (\explode($delim, \trim($line, $delim)) as $hdr) {
            $table .= '<th>' . \trim($hdr) . "</th>\n";
        }

        $table .= "</tr>\n</thead>\n<tbody>\n";

        return $table;
    }

    public function tableRow($line, $colCount, $delim = '|')
    {
        $row = "<tr>\n";

        foreach (\explode($delim, \trim($line, $delim)) as $i => $col) {
            if ($i > $colCount) {
                break;
            }

            $col  = \trim($col);
            $row .= "<td>{$col}</td>\n";
        }

        $row .= "</tr>\n";

        return $row;
    }
}
