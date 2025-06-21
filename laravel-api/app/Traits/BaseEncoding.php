<?php

declare(strict_types=1);

namespace App\Traits;

trait BaseEncoding
{
    /**
     * generate token random $length-size string composed of BASE62.
     */
    public function base62(int $length): string
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle($pool), 0, $length);
    }

    public function base36(int $length): string
    {
        $pool = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle($pool), 0, $length);
    }
}
