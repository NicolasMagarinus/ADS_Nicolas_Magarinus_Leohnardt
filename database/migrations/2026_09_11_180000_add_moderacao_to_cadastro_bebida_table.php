<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem moderou a receita e quando.
 *
 * O painel mostrava o updated_at do cadastro, que é só a última escrita na
 * linha — qualquer alteração posterior o move. Sem registro do moderador não
 * dá para rever uma decisão sabendo de quem ela foi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cadastro_bebida', function (Blueprint $table) {
            // Nula: as receitas decididas antes desta migration não têm como
            // ganhar um moderador retroativo.
            $table->unsignedBigInteger('id_moderador')->nullable()->after('id_status');
            $table->timestamp('dt_moderacao')->nullable()->after('id_moderador');

            // nullOnDelete, e não cascade: apagar um admin não pode levar
            // junto as receitas que ele moderou.
            $table->foreign('id_moderador')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cadastro_bebida', function (Blueprint $table) {
            $table->dropForeign(['id_moderador']);
            $table->dropColumn(['id_moderador', 'dt_moderacao']);
        });
    }
};
