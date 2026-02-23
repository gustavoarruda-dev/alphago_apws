<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ApwsApiException;
use App\Http\Middleware\HydrateApwsCustomerCredential;
use App\Http\Middleware\ValidateInternalApiKey;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Avoid redirecting unauthenticated API requests to a web login route.
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'internal.api' => ValidateInternalApiKey::class,
            'apws.credential' => HydrateApwsCustomerCredential::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApwsApiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'details' => $e->details(),
            ], $e->statusCode());
        });

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'error' => 'The requested resource does not exist.'
                ], 404);
            }
        });

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Route not found.',
                    'error' => 'The requested endpoint does not exist.'
                ], 404);
            }
        });

        $exceptions->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Method not allowed.',
                    'error' => 'The HTTP method used is not supported for this endpoint.'
                ], 405);
            }
        });

        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => 'You must be authenticated to access this resource.'
            ], 401);
        });

        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'error' => 'You do not have permission to perform this action.'
                ], 403);
            }
        });

        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->validator->errors()
                ], 422);
            }
        });

        $exceptions->renderable(function (HttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An error occurred.',
                    'error' => 'HTTP ' . $e->getStatusCode()
                ], $e->getStatusCode());
            }
        });

        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                Log::error('Unhandled exception', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error.',
                    'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred. Please try again later.'
                ], 500);
            }
        });
    })->create();
