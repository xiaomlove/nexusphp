<?php

/*
 * This file is part of the "laravel-lang/publisher" project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Andrey Helldar <helldar@ai-rus.com>
 *
 * @copyright 2021 Andrey Helldar
 *
 * @license MIT
 *
 * @see https://github.com/Laravel-Lang/publisher
 */

declare(strict_types=1);

return [
    /*
     * Determines what type of files to use when updating language files.
     *
     * `true` means inline files will be used.
     * `false` means that default files will be used.
     *
     * The difference between them can be seen here:
     * @see https://github.com/Laravel-Lang/lang/blob/master/source/validation.php
     * @see https://github.com/Laravel-Lang/lang/blob/master/source/validation-inline.php
     *
     * By default, `false`.
     */

    'inline' => false,

    /*
     * Do arrays need to be aligned by keys before processing arrays?
     *
     * By default, true
     */

    'alignment' => true,

    // Key exclusion when combining.

    'excludes' => [
        // 'auth' => ['throttle'],
        // 'pagination' => ['previous'],
        // 'passwords' => ['reset', 'throttled', 'user'],
        // '{locale}' => ['Confirm Password'],
    ],

    /*
     * Change key case.
     *
     * Available values:
     *
     *   0 - Case does not change
     *   1 - camelCase
     *   2 - snake_case
     *   3 - kebab-case
     *   4 - PascalCase
     *
     * By default, 0
     */

    'case' => 0,
];
