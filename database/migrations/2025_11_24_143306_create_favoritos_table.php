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
        Schema::create('favoritos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('cd_bebida');
            $table->timestamps();

            // Foreign keys
            $table->foreign('id_usuario')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cd_bebida')->references('cd_bebida')->on('bebida')->onDelete('cascade');

            // Unique constraint to prevent duplicate favorites
            $table->unique(['id_usuario', 'cd_bebida']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favoritos');
    }
};
