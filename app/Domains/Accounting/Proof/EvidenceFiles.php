<?php

namespace App\Domains\Accounting\Proof;

use RuntimeException;

final class EvidenceFiles
{
    public static function read(string $path): string
    {
        $contents = file_get_contents(self::required($path));
        if ($contents === false) {
            throw new RuntimeException('Unable to read archive evidence.');
        }

        return $contents;
    }

    public static function hash(string $path, string $algorithm = 'sha256', bool $binary = false): string
    {
        $hash = hash_file($algorithm, self::required($path), $binary);
        if ($hash === false) {
            throw new RuntimeException('Unable to hash archive evidence.');
        }

        return $hash;
    }

    public static function size(string $path): int
    {
        $size = filesize(self::required($path));
        if ($size === false) {
            throw new RuntimeException('Unable to measure archive evidence.');
        }

        return $size;
    }

    public static function temporary(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to allocate temporary evidence storage.');
        }

        return $path;
    }

    public static function required(string $path): string
    {
        if ($path === '' || ! is_file($path) || is_link($path) || ! is_readable($path)) {
            throw new RuntimeException('Required archive proof configuration or evidence file is missing.');
        }

        return $path;
    }

    public static function writeOnce(string $path, string $contents): void
    {
        if (is_file($path)) {
            if (! hash_equals(hash('sha256', $contents), self::hash($path))) {
                throw new RuntimeException('An existing evidence file cannot be replaced.');
            }

            return;
        }
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Unable to create archive evidence directory.');
        }
        $temporary = $path.'.'.bin2hex(random_bytes(12)).'.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to persist complete archive evidence.');
        }
        chmod($temporary, 0600);
        if (! link($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to atomically preserve archive evidence.');
        }
        unlink($temporary);
    }
}
