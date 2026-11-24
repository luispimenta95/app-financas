<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\PrivateAttachmentController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landing de vendas (opcional via LANDING_ENABLED)
|--------------------------------------------------------------------------
|
| Quando habilitada, `/` vira a vitrine comercial e o Filament sobe para `/app`.
| Quando desabilitada, nenhuma rota de landing é registrada e o painel
| continua na raiz — comportamento atual.
|
*/
if (financas_landing_enabled()) {
    Route::get('/', LandingController::class)->name('landing');
}

/*
|--------------------------------------------------------------------------
| Anexos privados (boletos, faturas, contracheques)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/anexos/{path}', PrivateAttachmentController::class)
        ->where('path', '.*')
        ->name('attachments.show');
});


/*
|--------------------------------------------------------------------------
| Fallback POST do login Filament
|--------------------------------------------------------------------------
|
| O login Filament é servido via GET (Livewire). Se o JS falhar, o browser
| pode cair em POST e receber 405 — redirecionamos de volta ao formulário.
|
*/
$loginPath = trim(financas_panel_path() . '/login', '/');

Route::post('/' . $loginPath, function () use ($loginPath): RedirectResponse {
    return redirect('/' . $loginPath);
})->withoutMiddleware([VerifyCsrfToken::class]);
