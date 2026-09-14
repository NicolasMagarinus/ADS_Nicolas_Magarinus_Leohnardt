<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('avaliacao', function (Blueprint $table) {
            $table->increments('cd_avaliacao');
            $table->bigInteger('id_usuario');
            $table->integer('cd_bebida');
            $table->tinyInteger('id_nota');
            $table->timestamp('dt_avaliacao')->default(now());
            $table->text('ds_avaliacao');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE avaliacao ADD CONSTRAINT chk_id_nota CHECK (id_nota BETWEEN 1 AND 10)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avaliacao');
    }
};
