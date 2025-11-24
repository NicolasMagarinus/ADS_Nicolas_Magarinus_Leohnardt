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
        Schema::create('cadastro_bebida', function (Blueprint $table) {
            $table->id('cd_bebida_cadastro');
            $table->unsignedBigInteger('id_usuario');
            $table->string('nm_bebida', 255);
            $table->text('ds_preparo');
            $table->text('ds_imagem')->nullable();
            $table->smallInteger('id_status')->default(0);
            $table->text('ds_motivo_rejeicao')->nullable();
            $table->timestamps();
            $table->foreign('id_usuario')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cadastro_bebida');
    }
};
