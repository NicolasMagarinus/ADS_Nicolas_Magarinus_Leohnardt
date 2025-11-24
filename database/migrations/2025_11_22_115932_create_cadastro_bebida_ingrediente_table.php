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
        Schema::create('cadastro_bebida_ingrediente', function (Blueprint $table) {
            $table->id('cd_bebida_cadastro_ingrediente');
            $table->unsignedBigInteger('cd_bebida_cadastro');
            $table->string('nm_ingrediente', 255);
            $table->string('ds_medida', 255)->nullable();
            $table->timestamps();
            $table->foreign('cd_bebida_cadastro')->references('cd_bebida_cadastro')->on('cadastro_bebida')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cadastro_bebida_ingrediente');
    }
};
