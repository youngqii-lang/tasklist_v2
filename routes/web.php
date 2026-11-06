<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskOptionController;
use App\Http\Controllers\TaskPicController;
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
        Route::apiResource('tasks', TaskController::class)->only(['index', 'store', 'show', 'update'])->whereNumber('task');
        Route::put('/tasks/{task}/pics', [TaskPicController::class, 'update'])->whereNumber('task')->name('tasks.pics.update');
    });
});
