<?php

namespace Tests\Feature\Foundation;

use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Tests\TestCase;

class EnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')
            ->prefix('api/v1/_test')
            ->group(function (): void {
                Route::post('validate', fn(Request $request) => Validator::make($request->all(), [
                    'phone_number' => ['required'],
                    'password' => ['required'],
                ])->validate());
                Route::get('refuse', fn() => throw ApiException::unprocessable('DOCUMENTS_OUTSTANDING', null, ['kinds' => ['mdcn']]));
                Route::get('throttled', fn() => 'ok')->middleware('throttle:1,1');
                Route::get('crash', fn() => throw new RuntimeException('boom'));
                Route::get('users', fn(Request $request) => new PagedUsers(ListQuery::from($request, 2)->paginate(User::query()->orderBy('id'))));
            });
    }

    public function test_success_wraps_data(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure(['success', 'data', 'message', 'timestamp']);
    }

    public function test_unknown_route_is_404_in_the_envelope(): void
    {
        $this->getJson('/api/v1/nowhere')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonStructure(['success', 'error' => ['code', 'message'], 'timestamp']);
    }

    public function test_validation_is_400_naming_the_first_field(): void
    {
        $this->postJson('/api/v1/_test/validate', [])
            ->assertBadRequest()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'phone_number');
    }

    public function test_a_refusal_carries_its_code_message_and_meta(): void
    {
        $this->getJson('/api/v1/_test/refuse')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCUMENTS_OUTSTANDING')
            ->assertJsonPath('error.meta.kinds.0', 'mdcn')
            ->assertJsonMissingPath('error.field');
    }

    public function test_a_crash_is_500_without_internals_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/v1/_test/crash')
            ->assertInternalServerError()
            ->assertJsonPath('error.code', 'SERVER_ERROR')
            ->assertJsonMissingPath('error.meta');
    }

    public function test_rate_limit_is_429_with_retry_after(): void
    {
        $this->getJson('/api/v1/_test/throttled');

        $this->getJson('/api/v1/_test/throttled')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonPath('error.meta.retry_after_ms', fn(int $ms) => $ms > 0);
    }

    public function test_lists_page_by_cursor(): void
    {
        User::factory()->count(5)->create();

        $total = User::query()->count();

        $first = $this->getJson('/api/v1/_test/users')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.total', $total);

        $cursor = $first->json('data.next_cursor');

        $this->assertNotNull($cursor);

        $second = $this->getJson('/api/v1/_test/users?cursor=' . urlencode($cursor))->assertOk();

        $this->assertNotEquals($first->json('data.items.0.id'), $second->json('data.items.0.id'));
    }

    public function test_a_bad_cursor_is_a_validation_error(): void
    {
        $this->getJson('/api/v1/_test/users?cursor=nonsense')
            ->assertBadRequest()
            ->assertJsonPath('error.field', 'cursor');
    }

    public function test_limit_is_capped(): void
    {
        $this->assertSame(ListQuery::MAX_LIMIT, ListQuery::from(Request::create('/', 'GET', ['limit' => 500]))->limit);
    }
}

class PagedUsers extends ApiCollection
{
    public $collects = PagedUser::class;
}

class PagedUser extends ApiResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
