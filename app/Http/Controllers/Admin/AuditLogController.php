<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\BusinessSchedule;
use App\Models\CleaningDutyRule;
use App\Models\ConstructionSchedule;
use App\Models\ConstructionSubcontractor;
use App\Models\InternalNotice;
use App\Models\SiteGuideFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AuditLogController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const array EVENT_LABELS = [
        'admin.users.created' => 'ユーザー作成',
        'admin.users.updated' => 'ユーザー更新',
        'admin.users.deleted' => 'ユーザー削除',
        'attendance_records.updated' => '出勤状況更新',
        'attendance_records.deleted' => '出勤状況削除',
        'auth.email.verified' => 'メール認証完了',
        'auth.login.attempted' => 'ログイン試行',
        'auth.login.failed' => 'ログイン失敗',
        'auth.login.locked_out' => 'ログイン制限',
        'auth.login.succeeded' => 'ログイン成功',
        'auth.logout.succeeded' => 'ログアウト',
        'auth.passkey_login.failed' => 'パスキーログイン失敗',
        'auth.passkey_login.succeeded' => 'パスキーログイン成功',
        'auth.password_reset.succeeded' => 'パスワード再設定完了',
        'auth.password_reset_link.sent' => 'パスワード再設定リンク送信',
        'auth.two_factor.challenged' => '二要素認証要求',
        'auth.two_factor.confirmed' => '二要素認証確認',
        'auth.two_factor.disabled' => '二要素認証無効化',
        'auth.two_factor.enabled' => '二要素認証有効化',
        'auth.two_factor.failed' => '二要素認証失敗',
        'auth.two_factor.recovery_code_used' => '二要素リカバリーコード使用',
        'auth.two_factor.recovery_codes_generated' => '二要素リカバリーコード再生成',
        'auth.two_factor.succeeded' => '二要素認証成功',
        'authorization.denied' => 'アクセス拒否',
        'business_schedules.created' => '業務予定作成',
        'business_schedules.updated' => '業務予定更新',
        'business_schedules.number_updated' => '業務予定番号更新',
        'business_schedules.deleted' => '業務予定削除',
        'cleaning_duty_rules.created' => '掃除当番設定作成',
        'cleaning_duty_rules.updated' => '掃除当番設定更新',
        'cleaning_duty_rules.deleted' => '掃除当番設定削除',
        'construction_schedules.created' => '工事予定作成',
        'construction_schedules.updated' => '工事予定更新',
        'construction_schedules.number_updated' => '工事予定番号更新',
        'construction_schedules.voucher_updated' => '伝票確認更新',
        'construction_schedules.deleted' => '工事予定削除',
        'construction_subcontractors.updated' => '下請け更新',
        'construction_subcontractors.deleted' => '下請け削除',
        'internal_notices.created' => '業務連絡作成',
        'internal_notices.updated' => '業務連絡更新',
        'internal_notices.deleted' => '業務連絡削除',
        'settings.passkeys.created' => 'パスキー登録',
        'settings.passkeys.deleted' => 'パスキー削除',
        'settings.password.updated' => 'パスワード変更',
        'settings.profile.updated' => 'プロフィール更新',
        'settings.profile.deleted' => 'プロフィール削除',
        'site_guide_files.created' => '現場案内図作成',
        'site_guide_files.updated' => '現場案内図更新',
        'site_guide_files.downloaded' => '現場案内図閲覧',
        'site_guide_files.deleted' => '現場案内図削除',
    ];

    /**
     * @var array<string, string>
     */
    private const array OUTCOME_LABELS = [
        'attempt' => '試行',
        'challenge' => '認証待ち',
        'failure' => '失敗',
        'success' => '成功',
    ];

    /**
     * @var array<string, string>
     */
    private const array SUBJECT_TYPE_LABELS = [
        AttendanceRecord::class => '出勤状況',
        BusinessSchedule::class => '業務予定',
        CleaningDutyRule::class => '掃除当番設定',
        ConstructionSchedule::class => '工事予定',
        ConstructionSubcontractor::class => '下請け',
        InternalNotice::class => '業務連絡',
        SiteGuideFile::class => '現場案内図',
        User::class => 'ユーザー',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canViewAuditLogs() === true, 403);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'event' => $request->string('event')->trim()->toString(),
            'outcome' => $request->string('outcome')->trim()->toString(),
            'actor_user_id' => $request->string('actor_user_id')->trim()->toString(),
            'subject_type' => $request->string('subject_type')->trim()->toString(),
            'ip_address' => $request->string('ip_address')->trim()->toString(),
            'date_from' => $request->string('date_from')->trim()->toString(),
            'date_to' => $request->string('date_to')->trim()->toString(),
        ];
        $dateFrom = $this->dateTimeFilter($filters['date_from']);
        $dateTo = $this->dateTimeFilter($filters['date_to']);

        $logs = AuditLog::query()
            ->with('actor:id,name,login_id,email')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('event', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('subject_label', 'like', "%{$search}%")
                        ->orWhere('route_name', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('request_id', 'like', "%{$search}%");
                });
            })
            ->when($filters['event'] !== '', fn ($query) => $query->where('event', $filters['event']))
            ->when($filters['outcome'] !== '', fn ($query) => $query->where('outcome', $filters['outcome']))
            ->when($filters['actor_user_id'] !== '', fn ($query) => $query->where('actor_user_id', $filters['actor_user_id']))
            ->when($filters['subject_type'] !== '', fn ($query) => $query->where('subject_type', $filters['subject_type']))
            ->when($filters['ip_address'] !== '', fn ($query) => $query->where('ip_address', 'like', "%{$filters['ip_address']}%"))
            ->when($dateFrom !== null, fn ($query) => $query->where('created_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->where('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'event_label' => $this->eventLabel($log->event),
                'outcome' => $log->outcome,
                'outcome_label' => $this->outcomeLabel($log->outcome),
                'description' => $log->description,
                'actor' => $log->actor === null ? null : [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'login_id' => $log->actor->login_id,
                    'email' => $log->actor->email,
                ],
                'actor_type' => $log->actor_type,
                'subject_type' => $log->subject_type,
                'subject_type_label' => $this->subjectTypeLabel($log->subject_type),
                'subject_id' => $log->subject_id,
                'subject_label' => $log->subject_label,
                'ip_address' => $log->ip_address,
                'method' => $log->method,
                'url' => $log->url,
                'route_name' => $log->route_name,
                'request_id' => $log->request_id,
                'metadata' => $log->metadata ?? [],
                'created_at' => $log->created_at?->toISOString(),
            ]);

        return Inertia::render('admin/audit-logs/index', [
            'logs' => $logs,
            'filters' => $filters,
            'options' => [
                'events' => AuditLog::query()
                    ->distinct()
                    ->orderBy('event')
                    ->pluck('event')
                    ->map(fn (string $event): array => [
                        'value' => $event,
                        'label' => $this->eventLabel($event),
                    ]),
                'outcomes' => AuditLog::query()
                    ->distinct()
                    ->orderBy('outcome')
                    ->pluck('outcome')
                    ->map(fn (string $outcome): array => [
                        'value' => $outcome,
                        'label' => $this->outcomeLabel($outcome),
                    ]),
                'subjectTypes' => AuditLog::query()
                    ->whereNotNull('subject_type')
                    ->distinct()
                    ->orderBy('subject_type')
                    ->pluck('subject_type')
                    ->map(fn (string $subjectType): array => [
                        'value' => $subjectType,
                        'label' => $this->subjectTypeLabel($subjectType),
                    ]),
            ],
        ]);
    }

    private function dateTimeFilter(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }

    private function eventLabel(?string $event): string
    {
        return $event === null ? '不明な操作' : self::EVENT_LABELS[$event] ?? $event;
    }

    private function outcomeLabel(?string $outcome): string
    {
        return $outcome === null ? '不明' : self::OUTCOME_LABELS[$outcome] ?? $outcome;
    }

    private function subjectTypeLabel(?string $subjectType): string
    {
        return $subjectType === null
            ? 'なし'
            : self::SUBJECT_TYPE_LABELS[$subjectType] ?? class_basename($subjectType);
    }
}
