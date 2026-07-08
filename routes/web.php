<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

// Filament login is served by GET /login and handled by Livewire.
// If JS fails to boot, browsers can fallback to POST /login, which would throw 405.
Route::post('/login', function (): RedirectResponse {
	return redirect('/login');
})->withoutMiddleware([VerifyCsrfToken::class]);
