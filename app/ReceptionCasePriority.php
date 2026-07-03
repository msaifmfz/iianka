<?php

declare(strict_types=1);

namespace App;

enum ReceptionCasePriority: string
{
    case Normal = 'normal';
    case Middle = 'middle';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Normal => '通常',
            self::Middle => '中',
            self::High => '高',
        };
    }

    /**
     * The value/label option list, in display order, for priority selects.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $priority): array => [
                'value' => $priority->value,
                'label' => $priority->label(),
            ],
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $labels, self $priority): array {
                $labels[$priority->value] = $priority->label();

                return $labels;
            },
            [],
        );
    }
}
