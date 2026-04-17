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
    }
}
