<?php
/**
 * Tiny "legacy page" fixture used by LegacyPageControllerTest.
 *
 * This file deliberately mimics the shape of a legacy `public/*.php`
 * script: it `echo`s output and reads `$CURUSER` from the global
 * scope. It does NOT live under `public/` because `Legacy freeze`
 * CI would flag any new file there.
 */
echo 'sample-fixture-rendered';
if (isset($GLOBALS['CURUSER']) && is_array($GLOBALS['CURUSER'])) {
    echo '|user='.$GLOBALS['CURUSER']['username'];
} else {
    echo '|user=guest';
}
