<?php

use App\Http\Controllers\TabletAccessController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/enrol');

Route::get('/enrol', [TabletAccessController::class, 'create'])->name('tablet.login');
Route::post('/enrol', [TabletAccessController::class, 'store'])->middleware('throttle:10,1')->name('tablet.authenticate');

Route::middleware('tablet')->group(function (): void {
    Route::view('/enroll', 'enrollment')->name('enrollment.create');
    Route::post('/enrol/logout', [TabletAccessController::class, 'destroy'])->middleware('throttle:10,1')->name('tablet.logout');
});
