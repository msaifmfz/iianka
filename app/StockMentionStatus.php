<?php

declare(strict_types=1);

namespace App;

enum StockMentionStatus: string
{
    case Recognized = 'recognized';
    case Ignored = 'ignored';
    case InvalidQuantity = 'invalid_quantity';
    case InactiveStock = 'inactive_stock';

    public function label(): string
    {
        return match ($this) {
            self::Recognized => '認識済み',
            self::Ignored => '無視',
            self::InvalidQuantity => '数量不正',
            self::InactiveStock => '無効在庫',
        };
    }
}
