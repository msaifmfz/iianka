<?php

declare(strict_types=1);

namespace App;

enum StockExtractionStatus: string
{
    case NotProcessed = 'not_processed';
    case Processed = 'processed';
    case ProcessedWithIgnoredText = 'processed_with_ignored_text';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotProcessed => '未処理',
            self::Processed => '処理済み',
            self::ProcessedWithIgnoredText => '一部未認識あり',
            self::Failed => '失敗',
        };
    }
}
