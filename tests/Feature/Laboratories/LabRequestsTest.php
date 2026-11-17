<?php

namespace Tests\Feature\Laboratories;

use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Laboratories\LabRequests;
use Tests\Concerns\MakesLaboratories;
use Tests\TestCase;

/**
 * `LabRequests::create()` and `forConsultation()`, which the Consultations module's doctor routes call.
 */
class LabRequestsTest extends TestCase
{
    use MakesLaboratories;

    public function test_a_request_names_catalogue_tests_once_per_consultation(): void
    {
        $this->fakeLabSeams();
        $this->labTest('FBC', ['name' => 'Full blood count']);
        $requests = app(LabRequests::class);
        $doctor = User::factory()->app(Role::Doctor)->create();
        $patient = $this->labPatient();
        $consultation = fake()->uuid();

        $this->assertRefused('NO_LAB_REQUEST', 404, fn() => $requests->forConsultation($consultation));

        $this->assertRefused('UNKNOWN_TEST', 422, fn() => $requests->create($doctor, $patient, $consultation, 'MDCN/1', [['test_code' => 'FBC'], ['test_code' => 'NOPE']], null));

        $created = $requests->create($doctor, $patient, $consultation, 'MDCN/1', [['test_code' => 'fbc'], ['test_code' => 'FBC', 'reason' => 'Again']], null);

        $this->assertSame([['test_code' => 'FBC', 'test_name' => 'Full blood count', 'reason' => 'Again']], $created->tests);
        $this->assertTrue($requests->forConsultation($consultation)->is($created));
        $this->assertCount(1, $this->notifier->sent);

        $this->assertRefused('LAB_REQUEST_EXISTS', 409, fn() => $requests->create($doctor, $patient, $consultation, 'MDCN/1', [['test_code' => 'FBC']], null));
    }

    private function assertRefused(string $code, int $status, callable $call): void
    {
        try {
            $call();
            $this->fail("Expected {$code}");
        } catch (ApiException $e) {
            $this->assertSame($code, $e->errorCode);
            $this->assertSame($status, $e->status->value);
        }
    }
}
