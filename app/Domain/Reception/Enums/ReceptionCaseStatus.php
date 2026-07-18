<?php

declare(strict_types=1);

namespace App\Domain\Reception\Enums;

enum ReceptionCaseStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case InProgress = 'in_progress';
    case Handover = 'handover';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '受付下書き',
            self::Received => '受付済み',
            self::InProgress => '対応中',
            self::Handover => '引継ぎ',
            self::Completed => '完了',
        };
    }

    /**
     * The post-submission, pre-completion states: a case in one of these is
     * "active" — it can be assigned, worked on, and have attachments managed.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Received, self::InProgress, self::Handover], true);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $labels, self $status): array {
                $labels[$status->value] = $status->label();

                return $labels;
            },
            [],
        );
    }

    public function canTransitionTo(self $nextStatus): bool
    {
        return match ($this) {
            self::Draft => $nextStatus === self::Received,
            self::Received => in_array($nextStatus, [self::InProgress, self::Completed], true),
            self::InProgress => in_array($nextStatus, [self::Handover, self::Completed], true),
            self::Handover => in_array($nextStatus, [self::InProgress, self::Completed], true),
            self::Completed => false,
        };
    }
}
