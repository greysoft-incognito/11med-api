<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\ConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (stripos($this->name(), 'Priority')) {
            $this->markTestSkipped('Temporarily Skipped!');
        }
    }

    /**
     * A super admin of the team.
     */
    protected function admin(): User
    {
        return User::factory()
            ->admin()
            ->create();
    }

    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @return void
     */
    // public function refreshDatabase()
    // {
    //     if (config('database.default')) {
    //         $this->beforeRefreshingDatabase();

    //         if ($this->usingInMemoryDatabase()) {
    //             $this->restoreInMemoryDatabase();
    //         }

    //         $this->refreshTestDatabase();

    //         $this->afterRefreshingDatabase();
    //     }
    // }

    /**
     * Perform any work that should take place once the database has finished refreshing.
     *
     * @return void
     */
    protected function afterRefreshingDatabase()
    {
        $this->seed();
        $this->seed(ConfigurationSeeder::class);
    }
}
