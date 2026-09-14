<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listas nomeadas de drinks. O favorito continua sendo o salvar rápido e
 * binário, em tabela própria: coleção é outra coisa, curada e compartilhável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colecao', function (Blueprint $table) {
            $table->increments('cd_colecao');
            $table->unsignedBigInteger('id_usuario');
            $table->string('nm_colecao', 60);
            $table->string('ds_colecao', 200)->nullable();
            $table->boolean('id_publica')->default(false);
            $table->timestamps();

            $table->foreign('id_usuario')->references('id')->on('users')->onDelete('cascade');

            // Ninguém tem duas "Drinks de verão".
            $table->unique(['id_usuario', 'nm_colecao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colecao');
    }
};
