<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->name('heatmap.index');

Route::prefix('api')->group(function (): void {
    Route::get('/events', [EventController::class, 'list'])->name('api.events.index');
    Route::post('/events', [EventController::class, 'store'])->name('api.events.store');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('api.events.destroy');
});
