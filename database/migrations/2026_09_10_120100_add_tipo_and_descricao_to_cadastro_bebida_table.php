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
        Schema::table('cadastro_bebida', function (Blueprint $table) {
            $table->smallInteger('id_tipo')->default(1);
            $table->text('ds_bebida')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cadastro_bebida', function (Blueprint $table) {
            $table->dropColumn(['id_tipo', 'ds_bebida']);
        });
    }
};
