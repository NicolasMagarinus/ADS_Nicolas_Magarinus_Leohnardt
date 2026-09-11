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
        Schema::table('bebida', function (Blueprint $table) {
            $table->text('ds_imagem')->nullable()->change();
        });
    }

    /**
     * Volta ao `string` (varchar 255) da migration original.
     *
     * Se alguma URL do Cloudinary passar de 255 caracteres, o Postgres recusa
     * e o rollback falha — o que é o comportamento certo. Truncar caminho de
     * imagem em silêncio seria pior que parar.
     */
    public function down(): void
    {
        Schema::table('bebida', function (Blueprint $table) {
            $table->string('ds_imagem')->nullable()->change();
        });
    }
};
