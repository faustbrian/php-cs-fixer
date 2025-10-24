<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\PhpCsFixer\Preset;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
interface PresetInterface
{
    /**
     * Returns the name of the rule set.
     */
    public function name(): string;

    /**
     * Returns an array of rules along with their configuration.
     *
     * @return array<string, array<string, mixed>|bool>
     */
    public function rules(): array;

    /**
     * Returns the minimum required PHP version (PHP_VERSION_ID).
     *
     * @see http://php.net/manual/en/reserved.constants.php
     */
    public function targetPhpVersion(): int;
}
