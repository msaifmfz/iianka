<?php

declare(strict_types=1);

namespace App\Domain\Stock\Enums;

enum StockTransactionType: string
{
    case StockIn = 'stock_in';
    case ScheduleStockOut = 'schedule_stock_out';
    case ScheduleReversal = 'schedule_reversal';
    case ManualIncrease = 'manual_increase';
    case ManualDecrease = 'manual_decrease';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => '入庫',
            self::ScheduleStockOut => '予定使用',
            self::ScheduleReversal => '予定戻し',
            self::ManualIncrease => '手動増加',
            self::ManualDecrease => '手動減少',
            self::Correction => '訂正',
        };
    }
}
