<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

enum ClientPlaceLogAttachmentKind: string
{
    case Image = 'image';
    case Audio = 'audio';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Image => '写真',
            self::Audio => '音声',
            self::Document => '書類',
        };
    }
}
