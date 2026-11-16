<?php

namespace Tests\Unit;

use App\Http\Resources\UserCollection;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AllCollectionTest extends TestCase
{
    public function test_can_load_collection()
    {
        User::factory(10)->create();

        Route::get('test/users', function () {
            return new UserCollection(User::paginate(6));
        });

        $response = $this->get('/test/users');

        $response->assertOk();

        $this->assertArrayHasKey('links', $response->collect());
        $this->assertArrayHasKey('meta', $response->collect());
        $this->assertArrayHasKey('data', $response->collect());
    }

    public function test_can_load_collection_with_meta_as_configured()
    {
        config([
            'resource-modifier.paginated_response_meta' => [
                'to' => 'to',
                'from' => 'from',
                'links' => 'links',
                'path' => 'path',
                'total' => 'total',
                'per_page' => 'perPage',
                'last_page' => 'lastPage',
                'current_page' => 'currentPage',
            ],
            'resource-modifier.paginated_response_links' => [],
        ]);

        User::factory(10)->create();

        Route::get('test/users', function () {
            return new UserCollection(User::paginate(6));
        });

        $response = $this->get('/test/users');

        $response->assertOk();

        $this->assertArrayHasKey('currentPage', $response->collect('meta'));
        $this->assertArrayHasKey('lastPage', $response->collect('meta'));
        $this->assertArrayHasKey('perPage', $response->collect('meta'));
    }

    public function test_can_load_collection_with_links_as_configured()
    {
        config([
            'resource-modifier.paginated_response_links' => [
                'first' => 'firstItem',
                'last' => 'lastItem',
                'prev' => 'previousItem',
                'next' => 'nextItem',
            ],
            'resource-modifier.paginated_response_meta' => [],
        ]);

        User::factory(10)->create();

        Route::get('test/users', function () {
            return new UserCollection(User::paginate(6));
        });

        $response = $this->get('/test/users');

        $response->assertOk();

        $this->assertArrayHasKey('firstItem', $response->collect('links'));
        $this->assertArrayHasKey('lastItem', $response->collect('links'));
        $this->assertArrayHasKey('nextItem', $response->collect('links'));
    }

    public function test_can_load_collection_without_links_as_configured()
    {
        config([
            'resource-modifier.paginated_response_extra' => ['meta'],
        ]);

        User::factory(10)->create();

        Route::get('test/users', function () {
            return new UserCollection(User::paginate(6));
        });

        $response = $this->get('/test/users');

        $response->assertOk();

        $this->assertArrayNotHasKey('links', $response->collect());
    }

    public function test_can_load_collection_without_meta_as_configured()
    {
        config([
            'resource-modifier.paginated_response_extra' => ['links'],
        ]);

        User::factory(10)->create();

        Route::get('test/users', function () {
            return new UserCollection(User::paginate(6));
        });

        $response = $this->get('/test/users');

        $response->assertOk();

        $this->assertArrayNotHasKey('meta', $response->collect());
    }

    public function test_can_load_collection_without_meta_and_links_as_configured()
    {
        config([
            'resource-modifier.paginated_response_extra' => [],
        ]);

        User::factory(10)->create();

        Route::get('test/users', function () {
            return new UserCollection(User::paginate(6));
        });

        $response = $this->get('/test/users');

        $response->assertOk();

        $this->assertArrayNotHasKey('meta', $response->collect());
        $this->assertArrayNotHasKey('links', $response->collect());
    }
}
