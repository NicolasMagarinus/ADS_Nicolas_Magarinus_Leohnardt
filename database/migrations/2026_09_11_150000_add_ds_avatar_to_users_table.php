<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avatar do usuário: a URL segura devolvida pelo Cloudinary, como em
 * bebida.ds_imagem e ingrediente.ds_imagem. Nada é gravado em disco local.
 *
 * users é a única tabela com nomenclatura Laravel, mas id_admin já abriu o
 * precedente de usar os prefixos do projeto nas colunas acrescentadas aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ds_avatar')->nullable()->after('id_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ds_avatar');
        });
    }
};
