<?php

declare(strict_types=1);

namespace App;

enum StockIdentificationMethod: string
{
    case SlashSelection = 'slash_selection';
    case CurrentName = 'current_name';
    case Alias = 'alias';

    public function label(): string
    {
        return match ($this) {
            self::SlashSelection => 'スラッシュ選択',
            self::CurrentName => '現在名',
            self::Alias => '別名',
        };
    }
}
