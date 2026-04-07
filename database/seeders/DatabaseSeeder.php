<?php

namespace Database\Seeders;

use App\Models\ConstructionSchedule;
use App\Models\ConstructionSite;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $testUser = User::query()->firstOrNew(['email' => 'test@example.com']);
        $testUser->forceFill([
            'name' => 'Test User',
            'password' => 'password',
            'email_verified_at' => now(),
            'is_admin' => false,
        ])->save();

        $admin = User::query()->firstOrNew(['email' => 'admin@example.com']);
        $admin->forceFill([
            'name' => 'Admin User',
            'password' => 'password',
            'email_verified_at' => now(),
            'is_admin' => true,
        ])->save();

        $site = ConstructionSite::query()->updateOrCreate(
            ['name' => 'デモ高層ビル改修 現場'],
            [
                'address' => '東京都新宿区西新宿2-8-1',
                'notes' => 'ブラウザテスト用のダミー現場です。',
            ],
        );

        $dates = [
            today()->subMonthNoOverflow()->startOfMonth()->addDays(9),
            today(),
            today()->addDays(6),
            today()->addMonthNoOverflow()->startOfMonth()->addDays(4),
        ];

        $schedules = [
            [
                'scheduled_on' => $dates[0]->toDateString(),
                'status' => ConstructionSchedule::STATUS_CONFIRMED,
                'location' => '先月デモ現場 外壁調査',
                'content' => '外壁状態の確認と補修範囲の記録',
            ],
            [
                'scheduled_on' => $dates[1]->toDateString(),
                'status' => ConstructionSchedule::STATUS_SCHEDULED,
                'location' => '本日デモ現場 資材搬入',
                'content' => '仮設資材の搬入と作業導線確認',
            ],
            [
                'scheduled_on' => $dates[2]->toDateString(),
                'status' => ConstructionSchedule::STATUS_CONFIRMED,
                'location' => '今月デモ現場 足場点検',
                'content' => '足場の安全点検とチェックリスト更新',
            ],
            [
                'scheduled_on' => $dates[3]->toDateString(),
                'status' => ConstructionSchedule::STATUS_POSTPONED,
                'location' => '翌月デモ現場 仕上げ確認',
                'content' => '仕上げ範囲の確認と是正箇所の整理',
            ],
        ];

        foreach ($schedules as $schedule) {
            $constructionSchedule = ConstructionSchedule::query()->updateOrCreate(
                [
                    'scheduled_on' => $schedule['scheduled_on'],
                    'location' => $schedule['location'],
                ],
                [
                    'construction_site_id' => $site->id,
                    'starts_at' => '08:00',
                    'ends_at' => '17:00',
                    'time_note' => null,
                    'status' => $schedule['status'],
                    'meeting_place' => 'デモ現場 正面ゲート',
                    'personnel' => '4名',
                    'general_contractor' => 'デモ建設株式会社',
                    'person_in_charge' => '山田',
                    'content' => $schedule['content'],
                    'navigation_address' => $site->address,
                ],
            );

            $constructionSchedule->assignedUsers()->sync([$testUser->id, $admin->id]);
        }
    }
}
