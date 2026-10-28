<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

/** Short numbers for tiles: "1.4 GB", "12%", "-" when unknown. */
final class Format
{
    public static function bytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '-';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 || $value >= 10 ? (string) round($value) : number_format($value, 1)).' '.$units[$unit];
    }

    /** A limit in MiB as Pelican stores it; 0 means unlimited. */
    public static function limit(int $mebibytes): string
    {
        return $mebibytes > 0 ? self::bytes($mebibytes * 1048576) : '∞';
    }

    public static function percent(?float $percent): string
    {
        return $percent === null ? '-' : round($percent).'%';
    }
}
