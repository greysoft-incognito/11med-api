<?php

namespace Tests\Feature\Foundation;

use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_it_answers_each_platforms_minimum_without_an_account(): void
    {
        config([
            'elevenmed.app_versions.ios' => ['minimum' => '1.2.0', 'update_url' => 'https://apps.apple.com/app/id1'],
            'elevenmed.app_versions.android' => ['minimum' => '1.1.3', 'update_url' => 'https://play.google.com/store/apps/details?id=ng.elevenmed.app'],
        ]);

        $this->getJson('api/v1/app/version')
            ->assertOk()
            ->assertJsonPath('data', [
                'ios' => ['minimum_version' => '1.2.0', 'update_url' => 'https://apps.apple.com/app/id1'],
                'android' => ['minimum_version' => '1.1.3', 'update_url' => 'https://play.google.com/store/apps/details?id=ng.elevenmed.app'],
            ]);
    }
}
