<?php

use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Task\TaskMonitoringController;
use App\Models\Task;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::prefix('tasks')->name('api.tasks.')->group(function () {
        Route::get('/meta', [TaskController::class, 'meta'])->name('meta');
        Route::get('/monitoring', TaskMonitoringController::class)
            ->can('viewAny', Task::class)
            ->name('monitoring');
        Route::get('/', [TaskController::class, 'index'])
            ->can('viewAny', Task::class)
            ->name('index');
        Route::get('/schedule', [TaskController::class, 'schedule'])
            ->can('viewAny', Task::class)
            ->name('schedule');
        Route::get('/calendar', [TaskController::class, 'calendar'])
            ->can('viewAny', Task::class)
            ->name('calendar');
        Route::get('/create-options', [TaskController::class, 'createOptions'])
            ->can('create', Task::class)
            ->name('create-options');
        Route::post('/', [TaskController::class, 'store'])
            ->can('create', Task::class)
            ->name('store');
        Route::get('/{task}', [TaskController::class, 'show'])
            ->whereNumber('task')
            ->can('view', 'task')
            ->name('show');
        Route::match(['put', 'patch'], '/{task}', [TaskController::class, 'update'])
            ->whereNumber('task')
            ->can('update', 'task')
            ->name('update');
        Route::delete('/{task}', [TaskController::class, 'destroy'])
            ->whereNumber('task')
            ->can('delete', 'task')
            ->name('destroy');
        Route::get('/{task}/preview', [TaskController::class, 'preview'])
            ->whereNumber('task')
            ->can('view', 'task')
            ->name('preview');
        Route::patch('/{task}/status', [TaskController::class, 'changeStatus'])
            ->whereNumber('task')
            ->can('changeStatus', 'task')
            ->name('change-status');
        Route::get('/{task}/activities', [TaskController::class, 'activities'])
            ->whereNumber('task')
            ->can('view', 'task')
            ->name('activities');
        Route::get('/{task}/evidences', [TaskController::class, 'evidences'])
            ->whereNumber('task')
            ->can('view', 'task')
            ->name('evidences.index');
        Route::post('/{task}/evidences', [TaskController::class, 'storeEvidence'])
            ->whereNumber('task')
            ->can('submitEvidence', 'task')
            ->name('evidences.store');
        Route::delete('/{task}/evidences/{evidence}', [TaskController::class, 'destroyEvidence'])
            ->whereNumber(['task', 'evidence'])
            ->can('deleteEvidence', 'task')
            ->name('evidences.destroy');
        Route::get('/{task}/evidences/{evidence}/download', [TaskController::class, 'downloadEvidence'])
            ->whereNumber(['task', 'evidence'])
            ->can('view', 'task')
            ->name('evidences.download');
        Route::get('/{task}/evidences/{evidence}/preview', [TaskController::class, 'previewEvidence'])
            ->whereNumber(['task', 'evidence'])
            ->can('view', 'task')
            ->name('evidences.preview');
        Route::get('/{task}/comments', [TaskController::class, 'comments'])
            ->whereNumber('task')
            ->can('view', 'task')
            ->name('comments.index');
        Route::post('/{task}/comments', [TaskController::class, 'storeComment'])
            ->whereNumber('task')
            ->can('comment', 'task')
            ->name('comments.store');
        Route::get('/{task}/reviews', [TaskController::class, 'reviews'])
            ->whereNumber('task')
            ->can('view', 'task')
            ->name('reviews.index');
        Route::post('/{task}/reviews', [TaskController::class, 'review'])
            ->whereNumber('task')
            ->can('review', 'task')
            ->name('reviews.store');
        Route::get('/{task}/assignment-options', [TaskController::class, 'assignmentOptions'])
            ->whereNumber('task')
            ->can('assign', 'task')
            ->name('assignment-options');
        Route::patch('/{task}/assignment', [TaskController::class, 'assign'])
            ->whereNumber('task')
            ->can('assign', 'task')
            ->name('assign');
    });
});
