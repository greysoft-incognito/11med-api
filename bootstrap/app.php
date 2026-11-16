<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Services\ExceptionHandler;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Toneflix\FakerPicTwo\FakerPicTwoProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('v1.')
                ->group(base_path('routes/v1.php'));
        },
    )
    ->withSingletons([
        Generator::class => function () {
            $faker = Factory::create();
            $faker->addProvider(new FakerPicTwoProvider($faker));

            return $faker;
        },
    ])
    ->withBindings(
        [
            Generator::class . ':en_NG' => Generator::class,
            Generator::class . ':en_GB' => Generator::class,
            Generator::class . ':en_US' => Generator::class,
        ]
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api.*',
        ]);

        $middleware->preventRequestsDuringMaintenance();

        $middleware->convertEmptyStringsToNull();

        $middleware->trimStrings(
            except: [
                'current_password',
                'password',
                'password_confirmation',
            ]
        );

        $middleware->trustProxies(
            at: null,
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->api(append: [
            EnsureFrontendRequestsAreStateful::class,
            ThrottleRequests::class . ':api',
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'verified' => EnsureEmailIsVerified::class,
            'guest' => RedirectIfAuthenticated::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/v1', 'api/v1/*')) {
                return ApiExceptionRenderer::render($e);
            }

            if (config('app.testing', false) === false) {
                return (new ExceptionHandler)->render($request, $e);
            }

            return null;
        });
    })->create();
