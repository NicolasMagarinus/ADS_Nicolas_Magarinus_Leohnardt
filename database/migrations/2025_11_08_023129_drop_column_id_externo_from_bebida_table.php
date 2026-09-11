<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bebida', function (Blueprint $table) {
            $table->dropColumn('id_externo');
        });
    }

    /**
     * Inverso possível, e não o exato — de propósito.
     *
     * A coluna era `string` NOT NULL com índice único, preenchida com os ids
     * da TheCocktailDB. Aquela integração foi removida em ca3a0f1 e os valores
     * não existem mais em lugar nenhum, então recriar a coluna como NOT NULL
     * falharia em qualquer tabela com linhas: não há valor para inventar.
     *
     * Volta como nula e única, que reverte o esquema sem mentir sobre o dado.
     * No Postgres, o índice único aceita vários NULLs.
     */
    public function down(): void
    {
        Schema::table('bebida', function (Blueprint $table) {
            $table->string('id_externo')->nullable()->unique();
        });
    }
};
