<?php

namespace Tests\Feature\Consultations;

use App\Enums\PaymentChannel;
use App\Enums\Role;
use App\Events\Payments\PaymentSucceeded;
use App\Models\Consultation;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Payments\Payments;
use Tests\Feature\Doctors\MakesDoctors;

trait MakesConsultations
{
    use MakesDoctors;

    protected FakePayments $payments;

    protected FakeNotifier $notifier;

    protected function fakeSeams(): void
    {
        $this->payments = new FakePayments;
        $this->notifier = new FakeNotifier;

        $this->app->instance(Payments::class, $this->payments);
        $this->app->instance(Notifier::class, $this->notifier);
    }

    /**
     * @param  list<string>  $allergies
     */
    protected function patient(array $allergies = []): User
    {
        $patient = $this->user(Role::Patient);

        $this->actingAs($patient)
            ->postJson('api/v1/profile', [
                'date_of_birth' => '1991-04-12',
                'gender' => 'female',
                'blood_type' => 'O+',
                'genotype' => 'AS',
                'state_of_residence' => 'Lagos',
                'lga' => 'Ikeja',
                'drug_allergies' => array_map(fn(string $drug) => ['drug_name' => $drug, 'reaction_type' => null, 'severity' => 'severe'], $allergies),
                'emergency_contact' => ['name' => 'Chidi Okafor', 'phone' => '+2348035550100', 'relationship' => 'spouse', 'address' => null],
            ])
            ->assertCreated();

        return $patient;
    }

    /**
     * An approved doctor taking consultations now.
     */
    protected function availableDoctor(array $practice = []): User
    {
        return $this->approvedDoctor(['is_available_now' => true] + $practice);
    }

    protected function book(User $patient, User $doctor, array $overrides = []): array
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/consultations', $overrides + [
                'doctor_id' => $doctor->uuid,
                'session_type' => 'chat',
                'complaint' => 'A sore throat for three days now.',
                'scheduled_for' => null,
                'profile_shared' => true,
            ])
            ->assertCreated()
            ->json('data');
    }

    protected function pay(string $consultationId): void
    {
        $consultation = Consultation::query()->where('uuid', $consultationId)->firstOrFail();

        PaymentSucceeded::dispatch($consultation, '11M-TEST', PaymentChannel::Card);
    }

    protected function doctorDoes(User $doctor, string $id, string $action): array
    {
        return $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/{$action}")
            ->assertOk()
            ->json('data');
    }

    /**
     * A paid, accepted, started consultation.
     */
    protected function openSession(User $patient, User $doctor, array $overrides = []): string
    {
        $id = $this->book($patient, $doctor, $overrides)['id'];

        $this->pay($id);
        $this->doctorDoes($doctor, $id, 'accept');
        $this->doctorDoes($doctor, $id, 'start');

        return $id;
    }
}
