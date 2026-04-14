<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;

class BusinessDate
{
    private const string TIMEZONE = 'Asia/Tokyo';

    public static function today(): Carbon
    {
        return Carbon::today(self::TIMEZONE);
    }
}
