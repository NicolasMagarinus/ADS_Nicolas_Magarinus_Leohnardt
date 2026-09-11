<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O Meu Bar guardava os ingredientes só na sessão e no localStorage, então
 * trocar de aparelho ou deixar a sessão expirar apagava o bar. Esta tabela
 * passa a ser a fonte da verdade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_ingrediente', function (Blueprint $table) {
            $table->increments('cd_usuario_ingrediente');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedInteger('cd_ingrediente');
            $table->timestamps();

            $table->foreign('id_usuario')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cd_ingrediente')->references('cd_ingrediente')->on('ingrediente')->onDelete('cascade');

            // Um ingrediente aparece no bar de alguém no máximo uma vez.
            $table->unique(['id_usuario', 'cd_ingrediente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_ingrediente');
    }
};
