<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperacaoSenhaController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\BebidaController;
use App\Http\Controllers\CadastroBebidaController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ColecaoController;
use App\Http\Controllers\FavoritoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IngredienteController;
use App\Http\Controllers\MeuBarController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\RandomDrinkController;
use App\Http\Controllers\RecomendadasController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');

Route::get('/register', [RegisterController::class, 'index'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.submit')->middleware('throttle:5,1');

Route::get('/login', [LoginController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit')->middleware('throttle:5,1');

// Recuperação de senha por código de 6 dígitos enviado por e-mail.
// O throttle de verificação é mais folgado que o limite de 5 tentativas do
// controller, de propósito: quem barra o chute é a contagem por código,
// que é precisa; o throttle é só a rede grossa contra automação.
Route::prefix('esqueci-senha')->group(function () {
    Route::get('/', [RecuperacaoSenhaController::class, 'solicitar'])->name('password.request');
    Route::post('/', [RecuperacaoSenhaController::class, 'enviarCodigo'])
        ->name('password.email')->middleware('throttle:5,1');

    Route::get('/codigo', [RecuperacaoSenhaController::class, 'formularioCodigo'])->name('password.code');
    Route::post('/codigo', [RecuperacaoSenhaController::class, 'verificarCodigo'])
        ->name('password.code.verify')->middleware('throttle:10,1');

    Route::get('/redefinir', [RecuperacaoSenhaController::class, 'formularioNovaSenha'])->name('password.reset');
    Route::post('/redefinir', [RecuperacaoSenhaController::class, 'redefinir'])->name('password.update');
});
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/random', [RandomDrinkController::class, 'index'])->name('random');

Route::get('/ingredientes', [IngredienteController::class, 'index'])->name('ingrediente.index');
Route::get('/ingrediente/{cd_ingrediente}', [IngredienteController::class, 'show'])
    ->name('ingrediente.show')->whereNumber('cd_ingrediente');

Route::get('/colecoes', [ColecaoController::class, 'index'])->name('colecao.index');
Route::get('/colecao/{colecao}', [ColecaoController::class, 'show'])
    ->name('colecao.show')->where('colecao', '[0-9]+(-.*)?');

Route::get('/search', [SearchController::class, 'index'])->name('search');

Route::group(['prefix' => 'bebida'], function () {
    Route::get('/buscar-bebidas', [BebidaController::class, 'search'])->name('bebida.search');
    Route::get('/{cd_bebida?}', [BebidaController::class, 'show'])->name('bebida.show')->whereNumber('cd_bebida');

    // Avaliação
    Route::post('/{cd_bebida}/avaliacao', [AvaliacaoController::class, 'store'])->name('avaliacao.store')->middleware('auth');
    Route::put('/{cd_bebida}/avaliacao/{cd_avaliacao}', [AvaliacaoController::class, 'update'])->name('avaliacao.update')->middleware('auth');
    Route::delete('/{cd_bebida}/avaliacao/{cd_avaliacao}', [AvaliacaoController::class, 'destroy'])->name('avaliacao.destroy')->middleware('auth');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/bebida/cadastrar', [CadastroBebidaController::class, 'create'])->name('bebida.create');
    Route::post('/bebida/cadastrar', [CadastroBebidaController::class, 'store'])->name('bebida.store');
    Route::get('/bebida/ingredientes/search', [CadastroBebidaController::class, 'buscarIngredientes'])->name('bebida.ingredientes.search');

    // A conferência de admin vive no middleware, não dentro de cada método:
    // rota nova neste grupo já nasce protegida.
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/bebidas', [CadastroBebidaController::class, 'index'])->name('bebidas.index');
        Route::post('/bebidas/{id}/aprovar', [CadastroBebidaController::class, 'aprovar'])->name('bebidas.approve');
        Route::post('/bebidas/{id}/rejeitar', [CadastroBebidaController::class, 'rejeitar'])->name('bebidas.reject');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [PerfilController::class, 'index'])->name('perfil.index');
    Route::post('/profile', [PerfilController::class, 'atualizar'])->name('perfil.update');
    Route::post('/profile/change-password', [PerfilController::class, 'alterarSenha'])->name('perfil.change-password');

    Route::post('/colecoes', [ColecaoController::class, 'store'])->name('colecao.store');
    Route::put('/colecao/{cd_colecao}', [ColecaoController::class, 'update'])
        ->name('colecao.update')->whereNumber('cd_colecao');
    Route::delete('/colecao/{cd_colecao}', [ColecaoController::class, 'destroy'])
        ->name('colecao.destroy')->whereNumber('cd_colecao');
    Route::get('/colecoes/para-bebida/{cd_bebida}', [ColecaoController::class, 'paraBebida'])
        ->name('colecao.para-bebida')->whereNumber('cd_bebida');
    Route::post('/colecao/{cd_colecao}/bebida/{cd_bebida}/alternar', [ColecaoController::class, 'alternarBebida'])
        ->name('colecao.bebida.alternar')->whereNumber('cd_colecao')->whereNumber('cd_bebida');

    Route::get('/favoritos', [FavoritoController::class, 'index'])->name('favoritos.index');
    Route::post('/favoritos/{cd_bebida}/toggle', [FavoritoController::class, 'alternar'])
        ->name('favoritos.toggle')->whereNumber('cd_bebida');
    Route::get('/favoritos/{cd_bebida}/check', [FavoritoController::class, 'verificar'])
        ->name('favoritos.check')->whereNumber('cd_bebida');

    Route::get('/recomendadas', [RecomendadasController::class, 'index'])->name('recomendadas.index');

    Route::prefix('meu-bar')->name('meubar.')->group(function () {
        Route::get('/', [MeuBarController::class, 'index'])->name('index');
        Route::get('/ingredientes/search', [MeuBarController::class, 'buscarIngredientes'])->name('ingredientes.search');
        Route::post('/drinks', [MeuBarController::class, 'obterBebidasPossiveis'])->name('drinks');
        Route::post('/ingredientes', [MeuBarController::class, 'salvar'])->name('salvar');
    });

    Route::post('/chatbot/message', [ChatbotController::class, 'mensagem'])->name('chatbot.message');
    Route::post('/chatbot/salvar-bebida', [ChatbotController::class, 'salvarBebida'])->name('chatbot.salvar-bebida');
    // Route::post('/rate-drink', [DrinkController::class, 'rate'])->name('drink.rate');
    // Route::post('/comment', [CommentController::class, 'store'])->name('comment.store');
});
