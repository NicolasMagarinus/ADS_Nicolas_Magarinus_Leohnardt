<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\BebidaController;
use App\Http\Controllers\CadastroBebidaController;
use App\Http\Controllers\RandomDrinkController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FavoritoController;
use App\Http\Controllers\RecomendadasController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');

Route::get('/register',  [RegisterController::class, 'index'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.submit');

Route::get('/login',   [LoginController::class, 'index'])->name('login');
Route::post('/login',  [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/random', [RandomDrinkController::class, 'index'])->name('random');
Route::get('/search', [SearchController::class, 'index'])->name('search');

Route::group(['prefix' => 'bebida'], function () {
    Route::get('/buscar-bebidas', [BebidaController::class, 'search'])->name('bebida.search');
    Route::get('/{cd_bebida?}', [BebidaController::class, 'show'])->name('bebida.show')->whereNumber('cd_bebida');

    //Avaliação
    Route::post('/{cd_bebida}/avaliacao', [AvaliacaoController::class, 'store'])->name('avaliacao.store')->middleware('auth');
    Route::put('/{cd_bebida}/avaliacao/{cd_avaliacao}',    [AvaliacaoController::class, 'update'])->name('avaliacao.update')->middleware('auth');
    Route::delete('/{cd_bebida}/avaliacao/{cd_avaliacao}', [AvaliacaoController::class, 'destroy'])->name('avaliacao.destroy')->middleware('auth');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/bebida/cadastrar', [CadastroBebidaController::class, 'create'])->name('bebida.create');
    Route::post('/bebida/cadastrar', [CadastroBebidaController::class, 'store'])->name('bebida.store');
    Route::get('/bebida/ingredientes/search', [CadastroBebidaController::class, 'searchIngredients'])->name('bebida.ingredientes.search');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/bebidas', [CadastroBebidaController::class, 'index'])->name('bebidas.index');
        Route::post('/bebidas/{id}/aprovar', [CadastroBebidaController::class, 'approve'])->name('bebidas.approve');
        Route::post('/bebidas/{id}/rejeitar', [CadastroBebidaController::class, 'reject'])->name('bebidas.reject');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [PerfilController::class, 'index'])->name('perfil.index');
    Route::post('/profile/change-password', [PerfilController::class, 'changePassword'])->name('perfil.change-password');
    
    Route::get('/favoritos', [FavoritoController::class, 'index'])->name('favoritos.index');
    Route::post('/favoritos/{cd_bebida}/toggle', [FavoritoController::class, 'toggle'])->name('favoritos.toggle');
    Route::get('/favoritos/{cd_bebida}/check', [FavoritoController::class, 'check'])->name('favoritos.check');

    Route::get('/recomendadas', [RecomendadasController::class, 'index'])->name('recomendadas.index');

    Route::post('/chatbot/message', [ChatbotController::class, 'message'])->name('chatbot.message');
    // Route::post('/rate-drink', [DrinkController::class, 'rate'])->name('drink.rate');
    // Route::post('/comment', [CommentController::class, 'store'])->name('comment.store');
});
