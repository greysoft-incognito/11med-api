<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Events\Medications\DoseMissed;
use App\Models\AppNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MissedDoseNotificationTest extends TestCase
{
    private const TARGET = '0b7f0f0e-5d6a-4c43-9d1e-2f4a8f0c9a11';

    public function test_a_missed_dose_is_written_once_and_not_pushed(): void
    {
        Queue::fake();
        $user = User::factory()
            ->app()
            ->create();

        $event = new DoseMissed($user, self::TARGET, 'Amlodipine', '5 mg', CarbonImmutable::parse('2026-11-21T08:00:00Z'));

        event($event);
        event($event);

        $row = AppNotification::sole();
        $this->assertSame(NotificationCategory::MissedDose, $row->category);
        $this->assertSame('Missed dose of Amlodipine', $row->title);
        $this->assertSame('5 mg was due and has not been marked taken.', $row->body);
        $this->assertSame(self::TARGET, $row->target_id);
        $this->assertSame('missed_dose:' . self::TARGET . ':2026-11-21T08:00:00.000Z', $row->dedupe_key);
        Queue::assertNothingPushed();
    }

    public function test_a_patient_who_turned_missed_dose_history_off_gets_nothing(): void
    {
        $user = User::factory()
            ->app()
            ->create();

        $this->actingAs($user)
            ->patchJson("api/v1/notifications/{$user->uuid}/preferences", ['preferences' => [
                ['category' => 'missed_dose', 'push' => true, 'sms' => false, 'in_app' => false],
            ]])
            ->assertOk();

        Queue::fake();
        event(new DoseMissed($user, self::TARGET, 'Amlodipine', '5 mg', CarbonImmutable::parse('2026-11-21T08:00:00Z')));

        $this->assertSame(0, AppNotification::count());
        Queue::assertNothingPushed();
    }
}
