<?php

use App\Http\Middleware\RoleAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleAccess::class,
        ]);

        // Mencegah redirect ke halaman login (yang menyebabkan error 500)
        // Pastikan response selalu JSON 401 jika unauthenticated
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($status < 500) {
                return null;
            }

            $errorId = (string) Str::uuid();
            Log::error('Unhandled API exception', [
                'error_id' => $errorId,
                'path' => $request->path(),
                'method' => $request->method(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $payload = [
                'status' => 'error',
                'message' => 'Terjadi kesalahan server. Silakan coba lagi atau hubungi admin.',
                'error_id' => $errorId,
            ];

            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            }

            return response()->json($payload, $status);
        });
    })->create();

$app->useEnvironmentPath(dirname(__DIR__, 2));

return $app;
