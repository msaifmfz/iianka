<?php

declare(strict_types=1);

namespace App;

enum ReceptionCaseAttachmentKind: string
{
    case Document = 'document';
    case Image = 'image';
    case Audio = 'audio';

    public function label(): string
    {
        return match ($this) {
            self::Document => '書類',
            self::Image => '写真',
            self::Audio => '音声',
        };
    }
}
