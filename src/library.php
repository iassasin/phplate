<?php
/**
 * Author: Assasin (assasin@sinair.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

$PDEBUG = false;

function DEBUG($str)
{
    global $PDEBUG;
    if ($PDEBUG) {
        echo htmlspecialchars($str) . "\n<br>";
    }
}
