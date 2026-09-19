<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

enum ClientPlaceLogType: string
{
    case Visit = 'visit';
    case Meeting = 'meeting';
    case Call = 'call';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Visit => '訪問',
            self::Meeting => '打合せ',
            self::Call => '電話',
            self::Other => 'その他',
        };
    }

    /**
     * The value/label option list, in display order, for selects.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
