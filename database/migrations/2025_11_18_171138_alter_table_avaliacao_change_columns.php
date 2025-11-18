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
        Schema::table('avaliacao', function (Blueprint $table) {
            $table->text('ds_avaliacao')->nullable()->change();
            $table->bigInteger('id_usuario')->unsigned()->change();
            $table->integer('cd_bebida')->unsigned()->change();
            $table->foreign('id_usuario')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cd_bebida')->references('cd_bebida')->on('bebida')->onDelete('cascade');
            $table->unique(['id_usuario', 'cd_bebida'], 'avaliacao_unique_user_bebida');
        });

        DB::statement('ALTER TABLE avaliacao DROP CONSTRAINT IF EXISTS chk_id_nota');
        DB::statement('ALTER TABLE avaliacao ADD CONSTRAINT chk_id_nota CHECK (id_nota BETWEEN 1 AND 5)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('avaliacao', function (Blueprint $table) {
            $table->dropUnique('avaliacao_unique_user_bebida');
            $table->dropForeign(['id_usuario']);
            $table->dropForeign(['cd_bebida']);
            $table->text('ds_avaliacao')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE avaliacao DROP CONSTRAINT IF EXISTS chk_id_nota');
        DB::statement('ALTER TABLE avaliacao ADD CONSTRAINT chk_id_nota CHECK (id_nota BETWEEN 1 AND 10)');
    }
};
