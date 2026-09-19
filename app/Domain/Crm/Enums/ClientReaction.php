<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

enum ClientReaction: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => '好感触',
            self::Neutral => '普通',
            self::Negative => '難色',
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
            static fn (self $reaction): array => [
                'value' => $reaction->value,
                'label' => $reaction->label(),
            ],
            self::cases(),
        );
    }
}
