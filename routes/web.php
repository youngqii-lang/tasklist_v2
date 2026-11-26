<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\TasklistController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => to_route('tasks.index'))->name('home');
    Route::get('/tasks', TasklistController::class)->name('tasks.index');
    Route::resource('accounts', AccountController::class)->except('show');
    Route::resource('divisions', DivisionController::class)->except('show');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
