<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\BebidaController;
use App\Http\Controllers\RandomDrinkController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);

Route::get('/register',  [RegisterController::class, 'index'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.submit');

Route::get('/login',   [LoginController::class, 'index'])->name('login');
Route::post('/login',  [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/random', [RandomDrinkController::class, 'index'])->name('random');
Route::get('/search', [SearchController::class, 'index'])->name('search');

Route::group(['prefix' => 'bebida'], function () {
    Route::get('/buscar-bebidas', [BebidaController::class, 'search'])->name('bebida.search');
    Route::get('/{cd_bebida?}', [BebidaController::class, 'show'])->name('bebida.show');

    //Avaliação
    Route::post('/{cd_bebida}/avaliacao', [AvaliacaoController::class, 'store'])->name('avaliacao.store')->middleware('auth');
    Route::put('/{cd_bebida}/avaliacao/{cd_avaliacao}',    [AvaliacaoController::class, 'update'])->name('avaliacao.update');
    Route::delete('/{cd_bebida}/avaliacao/{cd_avaliacao}', [AvaliacaoController::class, 'destroy'])->name('avaliacao.destroy');
});

Route::middleware(['auth'])->group(function () {
    // Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    // Route::post('/rate-drink', [DrinkController::class, 'rate'])->name('drink.rate');
    // Route::post('/comment', [CommentController::class, 'store'])->name('comment.store');
});
