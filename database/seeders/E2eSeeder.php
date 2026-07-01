<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class E2eSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = new User;
        $user->forceFill([
            'name' => 'E2E Login User',
            'login_id' => 'e2e-login',
            'email' => 'e2e-login@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => UserRole::Viewer,
            'is_admin' => false,
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
            'is_admin' => false,
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
            'is_admin' => true,
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
            'is_admin' => false,
            'is_hidden_from_workers' => false,
        ])->save();

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
