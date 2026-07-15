<?php

declare(strict_types=1);

namespace App;

enum ScheduleStockSourceType: string
{
    case ConstructionSchedule = 'construction_schedule';
    case BusinessSchedule = 'business_schedule';
    case StockPurchase = 'stock_purchase';

    public function label(): string
    {
        return match ($this) {
            self::ConstructionSchedule => '工事予定',
            self::BusinessSchedule => '業務予定',
            self::StockPurchase => '仕入',
        };
    }
}
