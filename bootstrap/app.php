<?php

use App\Models\Task;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $exception->getPrevious();
            $model = $previous instanceof ModelNotFoundException ? $previous->getModel() : null;

            return response()->json([
                'message' => $model === Task::class ? 'Tugas tidak ditemukan atau telah dihapus.' : 'Data tidak ditemukan.',
            ], 404);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*') || $exception->getMessage() !== 'This action is unauthorized.') {
                return null;
            }

            return response()->json(['message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.'], 403);
        });
    })->create();
