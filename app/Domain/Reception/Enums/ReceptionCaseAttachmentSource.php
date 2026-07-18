<?php

declare(strict_types=1);

namespace App\Domain\Reception\Enums;

enum ReceptionCaseAttachmentSource: string
{
    case Upload = 'upload';
    case Capture = 'capture';
    case Recording = 'recording';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'アップロード',
            self::Capture => '撮影',
            self::Recording => '録音',
        };
    }
}
