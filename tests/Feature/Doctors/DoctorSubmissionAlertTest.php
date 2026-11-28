<?php

namespace Tests\Feature\Doctors;

use App\Enums\NotificationCategory;
use App\Enums\Role;
use App\Models\AppNotification;
use Tests\TestCase;

class DoctorSubmissionAlertTest extends TestCase
{
    use MakesDoctors;

    public function test_a_submission_tells_the_team_once_while_it_waits(): void
    {
        $admin = $this->user(Role::Admin);
        $doctor = $this->submittedDoctor();

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertOk();

        $alerts = AppNotification::query()
            ->where('user_id', $admin->id)
            ->get();

        $this->assertCount(1, $alerts);
        $this->assertSame(NotificationCategory::DoctorReview, $alerts[0]->category);
        $this->assertSame($doctor->uuid, $alerts[0]->target_id);
        $this->assertSame("{$doctor->full_name} submitted their documents.", $alerts[0]->body);
    }
}
