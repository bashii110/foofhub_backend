<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Sentry integration would go here
        });
    }

    /**
     * Render all exceptions as consistent JSON for API routes.
     */
    public function render($request, Throwable $e): JsonResponse|\Illuminate\Http\Response
    {
        // Always return JSON for API routes
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($e);
        }

        return parent::render($request, $e);
    }

    private function handleApiException(Throwable $e): JsonResponse
    {
        // JWT Exceptions
        if ($e instanceof TokenExpiredException) {
            return response()->json(['message' => 'Token has expired. Please login again.'], 401);
        }

        if ($e instanceof TokenInvalidException) {
            return response()->json(['message' => 'Token is invalid.'], 401);
        }

        if ($e instanceof JWTException) {
            return response()->json(['message' => 'Token is missing or malformed.'], 401);
        }

        // Unauthenticated
        if ($e instanceof AuthenticationException) {
            return response()->json(['message' => 'Unauthenticated. Please login.'], 401);
        }

        // Validation errors
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // Model not found → 404
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            $model   = $e instanceof ModelNotFoundException
                ? class_basename($e->getModel())
                : 'Resource';
            return response()->json(['message' => "$model not found."], 404);
        }

        // HTTP exceptions (403, 429, etc.)
        if ($e instanceof HttpException) {
            return response()->json(
                ['message' => $e->getMessage() ?: 'HTTP Error'],
                $e->getStatusCode()
            );
        }

        // Generic server error — never expose stack trace in production
        if (! config('app.debug')) {
            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }

        // Debug mode: expose details
        return response()->json([
            'message'   => $e->getMessage(),
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ], 500);
    }
}