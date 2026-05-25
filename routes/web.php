<?php

use App\Http\Controllers\CoMakerResponseController;
use Illuminate\Support\Facades\Route;

// Co-maker consent response (email link — public, no auth)
Route::get('/co-maker/respond/{token}/{action}', [CoMakerResponseController::class, 'respond'])
    ->where('action', 'approve|decline')
    ->name('co-maker.respond');

// SPA — Vue handles all routing client-side
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
