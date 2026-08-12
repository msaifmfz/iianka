<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Stock\ScheduleStockReconciliationService;
use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\ReceptionCase;
use App\Models\ReceptionDocumentType;
use App\Models\Stock;
use App\Models\User;
use App\Services\BusinessDate;
use App\UserRole;
use Illuminate\Database\Seeder;

class E2eSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(ReceptionDocumentTypeSeeder::class);

        $user = new User;
        $user->forceFill([
            'name' => 'E2E Login User',
            'login_id' => 'e2e-login',
            'email' => 'e2e-login@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => UserRole::Viewer,
            'is_hidden_from_workers' => false,
        ])->save();

        $editor = new User;
        $editor->forceFill([
            'name' => 'E2E Editor User',
            'login_id' => 'e2e-editor',
            'email' => 'e2e-editor@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => UserRole::Editor,
            'is_hidden_from_workers' => false,
        ])->save();

        $admin = new User;
        $admin->forceFill([
            'name' => 'E2E Admin User',
            'login_id' => 'e2e-admin',
            'email' => 'e2e-admin@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_hidden_from_workers' => true,
        ])->save();

        $worker = new User;
        $worker->forceFill([
            'name' => 'E2E Timeline Worker',
            'login_id' => 'e2e-timeline-worker',
            'email' => 'e2e-timeline-worker@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => UserRole::Viewer,
            'is_hidden_from_workers' => false,
        ])->save();

        Stock::factory()
            ->named('E2E養生テープ')
            ->quantity('25.000')
            ->create();

        $usageStock = Stock::factory()
            ->named('E2E在庫使用予定')
            ->quantity('25.000')
            ->create();
        $usageSchedule = ConstructionSchedule::create([
            'scheduled_on' => BusinessDate::today()->toDateString(),
            'schedule_number' => 9001,
            'starts_at' => '09:00',
            'ends_at' => '10:30',
            'status' => ConstructionSchedule::STATUS_CONFIRMED,
            'meeting_place' => 'E2E Meeting Place',
            'personnel' => '2名',
            'location' => 'E2E 在庫使用予定 現場',
            'general_contractor' => 'E2E Stock Contractor',
            'person_in_charge' => 'E2E Person',
            'content' => "{$usageStock->name} 2",
            'carry_out_note' => 'E2E 持ち出し確認',
            'navigation_address' => 'Tokyo',
        ]);
        $usageSchedule->assignedUsers()->attach($worker);
        app(ScheduleStockReconciliationService::class)->reconcile($usageSchedule, $admin);

        ReceptionCase::create([
            'case_number' => 'WJA-C-20260703-9001',
            'status' => ReceptionCaseStatus::Completed,
            'company_name' => 'E2E Archive Width Company',
            'site_name' => 'E2E Archive Width Site',
            'reception_document_type_id' => ReceptionDocumentType::query()
                ->where('name', '見積依頼')
                ->firstOrFail()
                ->id,
            'reception_content' => 'Archive width fixture',
            'due_on' => '2026-07-03',
            'scheduled_on' => '2026-07-04',
            'receptor_user_id' => $editor->id,
            'assigned_user_id' => $worker->id,
            'completed_at' => '2026-07-03 09:00:00',
            'completed_by_user_id' => $worker->id,
            'last_activity_at' => '2026-07-03 09:00:00',
        ]);

        $overlapEarly = ConstructionSchedule::create([
            'scheduled_on' => '2026-05-13',
            'schedule_number' => 101,
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'meeting_place' => 'E2E Meeting Place',
            'personnel' => '2名',
            'location' => 'E2E Overlap Early',
            'general_contractor' => 'E2E Contractor',
            'person_in_charge' => 'E2E Person',
            'content' => 'Overlapping timeline fixture',
            'navigation_address' => 'Tokyo',
        ]);
        $overlapEarly->assignedUsers()->attach($worker);

        $overlapLate = BusinessSchedule::create([
            'scheduled_on' => '2026-05-13',
            'schedule_number' => 102,
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'personnel' => '1名',
            'location' => 'E2E Overlap Late',
            'general_contractor' => 'E2E Contractor',
            'person_in_charge' => 'E2E Person',
            'content' => 'Overlapping timeline fixture',
        ]);
        $overlapLate->assignedUsers()->attach($worker);

        $backToBackFirst = ConstructionSchedule::create([
            'scheduled_on' => '2026-05-13',
            'schedule_number' => 103,
            'starts_at' => '12:00',
            'ends_at' => '13:00',
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'meeting_place' => 'E2E Meeting Place',
            'personnel' => '2名',
            'location' => 'E2E Back To Back First',
            'general_contractor' => 'E2E Contractor',
            'person_in_charge' => 'E2E Person',
            'content' => 'Back to back timeline fixture',
            'navigation_address' => 'Tokyo',
        ]);
        $backToBackFirst->assignedUsers()->attach($worker);

        $backToBackSecond = BusinessSchedule::create([
            'scheduled_on' => '2026-05-13',
            'schedule_number' => 104,
            'starts_at' => '13:00',
            'ends_at' => '14:00',
            'personnel' => '1名',
            'location' => 'E2E Back To Back Second',
            'general_contractor' => 'E2E Contractor',
            'person_in_charge' => 'E2E Person',
            'content' => 'Back to back timeline fixture',
        ]);
        $backToBackSecond->assignedUsers()->attach($worker);

        for ($day = 1; $day <= 30; $day++) {
            $searchResult = ConstructionSchedule::create([
                'scheduled_on' => sprintf('2026-06-%02d', $day),
                'schedule_number' => 200 + $day,
                'starts_at' => '09:00',
                'ends_at' => '10:00',
                'status' => ConstructionSchedule::STATUS_SCHEDULED,
                'meeting_place' => 'E2E Meeting Place',
                'personnel' => '1名',
                'location' => sprintf('E2E Search Deep %02d', $day),
                'general_contractor' => 'E2E Search Contractor',
                'person_in_charge' => 'E2E Person',
                'content' => 'Search return fixture',
                'navigation_address' => 'Tokyo',
            ]);
            $searchResult->assignedUsers()->attach($worker);
        }

        foreach ([
            '2026-07-02' => 4,
            '2026-07-03' => 5,
            '2026-07-04' => 7,
            '2026-07-05' => 8,
            '2026-07-06' => 10,
            '2026-07-07' => 11,
            '2026-07-08' => 12,
            '2026-07-09' => 13,
        ] as $date => $count) {
            $this->createHeatLevelSchedules($date, $count);
        }
    }

    private function createHeatLevelSchedules(string $date, int $count): void
    {
        for ($index = 1; $index <= $count; $index++) {
            ConstructionSchedule::create([
                'scheduled_on' => $date,
                'schedule_number' => 3000 + (int) str_replace('-', '', $date) + $index,
                'starts_at' => '09:00',
                'ends_at' => '10:00',
                'status' => ConstructionSchedule::STATUS_SCHEDULED,
                'meeting_place' => 'E2E Meeting Place',
                'personnel' => '1名',
                'location' => sprintf('E2E Heat Level %s %02d', $date, $index),
                'general_contractor' => 'E2E Heat Contractor',
                'person_in_charge' => 'E2E Person',
                'content' => 'Heat level fixture',
                'navigation_address' => 'Tokyo',
            ]);
        }
    }
}
