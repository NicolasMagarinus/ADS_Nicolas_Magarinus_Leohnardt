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
        Schema::create('bebida_ingrediente', function (Blueprint $table) {
            $table->increments('cd_bebida_ingrediente');
            $table->integer('cd_bebida');
            $table->integer('cd_ingrediente');
            $table->string('ds_medida');
            $table->timestamps();
            $table->unique(['cd_bebida', 'cd_ingrediente']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bebida_ingrediente');
    }
};
