<?php

/*
 * This file is part of the HTMLUP package.
 *
 * (c) Jitendra Adhikari <jiten.adhikary@gmail.com>
 *     <https//:github.com/adhocore>
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
}
