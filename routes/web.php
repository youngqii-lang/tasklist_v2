<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\TaskActivityController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskEvidenceController;
use App\Http\Controllers\TaskOptionController;
use App\Http\Controllers\TaskPicController;
use App\Http\Controllers\TaskReviewController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TaskSummaryController;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::view('/', 'dashboard')->name('dashboard');
    Route::resource('accounts', AccountController::class)->except('show');
    Route::resource('divisions', DivisionController::class)->except('show');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('api/v1')->name('api.')->group(function () {
        Route::get('/task-options', [TaskOptionController::class, 'index'])->name('task-options.index');
        Route::get('/task-options/pics', [TaskOptionController::class, 'pics'])->name('task-options.pics');
        Route::get('/timeline', TimelineController::class)->name('timeline');
        Route::get('/calendar', [CalendarController::class, 'month'])->name('calendar.month');
        Route::get('/calendar/deadlines', [CalendarController::class, 'deadlines'])->name('calendar.deadlines');
        Route::get('/tasks/summary', TaskSummaryController::class)->name('tasks.summary');
        Route::apiResource('tasks', TaskController::class)->whereNumber('task');
        Route::put('/tasks/{task}/pics', [TaskPicController::class, 'update'])->whereNumber('task')->name('tasks.pics.update');
        Route::patch('/tasks/{task}/status', [TaskStatusController::class, 'update'])->whereNumber('task')->name('tasks.status.update');

        Route::get('/tasks/{task}/preview', [TaskController::class, 'preview'])->whereNumber('task')->name('tasks.preview');
        Route::get('/tasks/{task}/activities', [TaskActivityController::class, 'index'])->whereNumber('task')->name('tasks.activities.index');
        Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index'])->whereNumber('task')->name('tasks.comments.index');
        Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->whereNumber('task')->name('tasks.comments.store');
        Route::get('/tasks/{task}/reviews', [TaskReviewController::class, 'index'])->whereNumber('task')->name('tasks.reviews.index');
        Route::post('/tasks/{task}/reviews', [TaskReviewController::class, 'store'])->whereNumber('task')->name('tasks.reviews.store');

        Route::whereNumber(['task', 'evidence'])->group(function () {
            Route::get('/tasks/{task}/evidences', [TaskEvidenceController::class, 'index'])->name('tasks.evidences.index');
            Route::post('/tasks/{task}/evidences', [TaskEvidenceController::class, 'store'])->name('tasks.evidences.store');
            Route::get('/tasks/{task}/evidences/{evidence}/download', [TaskEvidenceController::class, 'download'])->name('tasks.evidences.download');
        });
    });
});
