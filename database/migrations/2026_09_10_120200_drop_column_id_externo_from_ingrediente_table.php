<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A coluna existia apenas para deduplicar o import da TheCocktailDB,
     * integração removida do projeto. Nenhum registro chegou a ser preenchido.
     */
    public function up(): void
    {
        Schema::table('ingrediente', function (Blueprint $table) {
            $table->dropColumn('id_externo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingrediente', function (Blueprint $table) {
            $table->integer('id_externo')->nullable();
        });
    }
};
