<?php

namespace Ahc;

trait HtmlHelper
{
    public function escape($input)
    {
        return \htmlspecialchars($input);
    }
}
