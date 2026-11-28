<?php

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\DataExport;
use App\Models\Reading;
use App\Models\User;
use App\Notifications\Identity\DataExportReady;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class DataExportTest extends TestCase
{
    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->patient = User::factory()
            ->app()
            ->create(['full_name' => 'Ada Obi', 'email' => 'ada@example.com', 'email_verified_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exported(DataExport $export): array
    {
        $local = tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($local, Storage::disk('local')->get($export->path));

        $zip = new ZipArchive;
        $zip->open($local);
        $data = json_decode($zip->getFromName('data.json'), true, flags: JSON_THROW_ON_ERROR);
        $summary = $zip->getFromName('summary.html');
        $zip->close();

        return ['data' => $data, 'summary' => $summary];
    }

    public function test_a_request_builds_the_export_and_emails_a_link(): void
    {
        $this->actingAs($this->patient, 'sanctum')
            ->getJson('api/v1/auth/data-export')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->postJson('api/v1/auth/data-export')
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['id', 'status', 'requested_at', 'ready_at', 'expires_at', 'emailed', 'download_url']]);

        $export = DataExport::sole();

        $this->getJson('api/v1/auth/data-export')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.emailed', true);

        $this->assertNotNull($export->refresh()->expires_at);
        $this->assertTrue($export->expires_at->between(now()->addHours(47), now()->addHours(49)));

        $exported = $this->exported($export);

        $this->assertSame('Ada Obi', $exported['data']['sections']['account'][0]['full_name']);
        $this->assertArrayHasKey('medications', $exported['data']['sections']);
        $this->assertArrayHasKey('consultations', $exported['data']['sections']);
        $this->assertStringContainsString('Your 11med data', $exported['summary']);

        Notification::assertSentTo($this->patient, DataExportReady::class);
    }

    public function test_the_export_holds_the_persons_records_and_never_a_secret(): void
    {
        $stranger = User::factory()
            ->app()
            ->create();

        foreach ([$this->patient, $stranger] as $owner) {
            Reading::create(['user_id' => $owner->id, 'type' => 'weight', 'kg' => 70.5, 'taken_at' => now()]);
        }

        $this->patient->forceFill(['two_factor_secret' => 'SECRETSECRETSECRET', 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($this->patient, 'sanctum')
            ->postJson('api/v1/auth/data-export')
            ->assertStatus(202);

        $exported = $this->exported(DataExport::sole());
        $json = json_encode($exported['data']);

        $this->assertCount(1, $exported['data']['sections']['readings']);
        $this->assertArrayNotHasKey('user_id', $exported['data']['sections']['readings'][0]);
        $this->assertStringNotContainsString('SECRETSECRETSECRET', $json);
        $this->assertStringNotContainsString('password', $json);
        $this->assertTrue($exported['data']['sections']['account'][0]['two_factor_enabled']);
    }

    public function test_one_request_a_day(): void
    {
        $this->actingAs($this->patient, 'sanctum')->postJson('api/v1/auth/data-export')->assertStatus(202);

        $this->postJson('api/v1/auth/data-export')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'DATA_EXPORT_LIMIT');

        $this->travel(25)->hours();

        $this->postJson('api/v1/auth/data-export')->assertStatus(202);
    }

    public function test_the_signed_link_downloads_the_zip_until_it_lapses(): void
    {
        $this->actingAs($this->patient, 'sanctum')->postJson('api/v1/auth/data-export');

        $url = $this->getJson('api/v1/auth/data-export')->json('data.download_url');

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        $this->get(preg_replace('/signature=\w+/', 'signature=nope', $url))->assertForbidden();

        $this->travel(49)->hours();

        $this->get($url)->assertForbidden();

        $this->artisan('model:prune', ['--model' => [DataExport::class]]);

        $this->assertSame(0, DataExport::count());
        $this->assertSame([], Storage::disk('local')->allFiles('data-exports'));
    }

    public function test_no_email_without_a_confirmed_address(): void
    {
        $this->patient->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->patient, 'sanctum')
            ->postJson('api/v1/auth/data-export')
            ->assertStatus(202);

        $this->getJson('api/v1/auth/data-export')->assertJsonPath('data.emailed', false);

        Notification::assertNothingSent();
    }

    public function test_only_app_accounts_ask(): void
    {
        $this->postJson('api/v1/auth/data-export')->assertUnauthorized();

        $desk = User::factory()
            ->portal(Role::Pharmacy)
            ->create();

        $this->actingAs($desk, 'sanctum')
            ->postJson('api/v1/auth/data-export')
            ->assertForbidden();
    }
}
