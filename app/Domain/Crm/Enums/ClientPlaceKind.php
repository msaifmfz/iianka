<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

enum ClientPlaceKind: string
{
    case Office = 'office';
    case Site = 'site';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Office => '事務所',
            self::Site => '現場',
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
            static fn (self $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ],
            self::cases(),
        );
    }
}
