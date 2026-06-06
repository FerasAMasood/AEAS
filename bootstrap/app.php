<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Enable CORS for API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Don't redirect API requests - they will be handled by exception handler
        // For web routes, we'll define a login route in web.php
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle unauthenticated requests for API routes
        $exceptions->render(function (AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $origin = $request->headers->get('Origin');
                $response = response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
                
                if ($origin) {
                    $response->header('Access-Control-Allow-Origin', $origin)
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                        ->header('Access-Control-Allow-Credentials', 'true');
                }
                
                return $response;
            }
        });
        
        // Ensure CORS headers are added to all API error responses
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $origin = $request->headers->get('Origin');
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message = $e->getMessage() ?: 'Server Error';
                
                $response = response()->json([
                    'message' => $message,
                    'error' => config('app.debug') ? [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ] : null,
                ], $status);
                
                if ($origin) {
                    $response->header('Access-Control-Allow-Origin', $origin)
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                        ->header('Access-Control-Allow-Credentials', 'true');
                }
                
                return $response;
            }
        });
    })->create();
