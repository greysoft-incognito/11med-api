<?php

namespace Tests\Feature\Foundation;

use App\Models\Drug;
use App\Models\LaboratoryTest;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $image = imagecreatetruecolor(40, 40);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        Http::fake(['*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg'])]);
    }

    public function test_the_demo_accounts_sign_in_and_see_the_mock_data(): void
    {
        $this->seed(DemoSeeder::class);

        $session = $this->postJson('api/v1/auth/login', [
            'phone_number' => '08030000001',
            'password' => 'Password1',
            'device_id' => 'demo-test',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.has_medical_profile', true);

        $this->withToken($session->json('data.access_token'))
            ->getJson('api/v1/doctors')
            ->assertOk()
            ->assertJsonPath('data.total', 6);

        $this->postJson('api/v1/auth/login', ['phone_number' => '08030000003', 'password' => 'Password1', 'device_id' => 'demo-test'])
            ->assertOk()
            ->assertJsonPath('data.user.kinds', ['doctor']);

        $this->assertSame(19, Drug::query()->count());
        $this->assertSame(84, LaboratoryTest::query()->count());
        $this->assertSame(10, User::query()->whereNotNull('organisation_id')->count());
    }

    public function test_it_seeds_once(): void
    {
        $this->seed(DemoSeeder::class);
        $users = User::query()->count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($users, User::query()->count());
    }
}
