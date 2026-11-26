<?php

use App\Http\Controllers\AgricultureController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AgricultureController::class, 'loginPage'])->name('login');
Route::post('/login', [AgricultureController::class, 'login'])->middleware('throttle:6,1');
Route::middleware('auth')->controller(AgricultureController::class)->group(function () {
    Route::get('/', 'index')->name('dashboard');
    Route::post('/logout', 'logout')->name('logout');
    Route::get('/sync/session', 'syncSession')->name('sync.session');
    Route::get('/sector/{sector}', 'sector')->name('sector');
    Route::post('/sector/{sector}', 'store')->name('records.store');
    Route::patch('/sector/{sector}/{record}', 'update')->whereNumber('record')->name('records.update');
    Route::post('/rice/import', 'import')->name('rice.import');
    Route::get('/reports', 'reports')->name('reports');
    Route::get('/farmers/directory', 'farmerDirectory')->name('farmers.directory');
    Route::get('/access', 'access')->name('access');
    Route::post('/access', 'requestAccess')->name('access.request');
    Route::patch('/access/{grant}', 'decideAccess')->whereNumber('grant')->name('access.decide');
});
