<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colecao_bebida', function (Blueprint $table) {
            $table->increments('cd_colecao_bebida');
            $table->unsignedInteger('cd_colecao');

            // unsignedInteger, não unsignedBigInteger: bebida.cd_bebida é
            // increments (int4). A tabela favorito declarou bigint e o
            // Postgres aceitou, mas o tipo fica descasado — não copie de lá.
            $table->unsignedInteger('cd_bebida');
            $table->timestamps();

            $table->foreign('cd_colecao')->references('cd_colecao')->on('colecao')->onDelete('cascade');
            $table->foreign('cd_bebida')->references('cd_bebida')->on('bebida')->onDelete('cascade');

            $table->unique(['cd_colecao', 'cd_bebida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colecao_bebida');
    }
};
