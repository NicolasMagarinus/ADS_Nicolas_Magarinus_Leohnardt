<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Funde ingredientes que só diferem em acento ou caixa ("Água" / "Agua"),
     * limpa órfãos em bebida_ingrediente e só então amarra as constraints que
     * impedem o problema de voltar.
     *
     * A fusão de dados não é revertida pelo down().
     */
    public function up(): void
    {
        // unaccent() é STABLE e não pode ir direto num índice; este wrapper
        // fixa o dicionário e é IMMUTABLE, o que o torna indexável.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION f_unaccent(text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT AS
            $func$ SELECT public.unaccent('public.unaccent', $1) $func$
        SQL);

        // Linhas apontando para bebida ou ingrediente inexistente: impedem a FK.
        DB::statement(<<<'SQL'
            DELETE FROM bebida_ingrediente bi
             WHERE NOT EXISTS (SELECT 1 FROM bebida b WHERE b.cd_bebida = bi.cd_bebida)
                OR NOT EXISTS (SELECT 1 FROM ingrediente i WHERE i.cd_ingrediente = bi.cd_ingrediente)
        SQL);

        // O mapa perdedor -> vencedor é calculado UMA vez e materializado: os
        // passos seguintes alteram as contagens de uso, e recalcular a cada
        // statement poderia eleger vencedores diferentes entre um e outro.
        // Vencedor do grupo: o mais usado, depois o mais acentuado, depois o mais antigo.
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE fusao_ingrediente AS
            WITH g AS (
                SELECT i.cd_ingrediente,
                       lower(f_unaccent(i.nm_ingrediente)) AS chave,
                       (SELECT count(*) FROM bebida_ingrediente bi
                         WHERE bi.cd_ingrediente = i.cd_ingrediente) AS usos,
                       length(regexp_replace(i.nm_ingrediente, '[a-zA-Z0-9 ]', '', 'g')) AS acentos
                  FROM ingrediente i
            ),
            dup AS (SELECT chave FROM g GROUP BY chave HAVING count(*) > 1),
            v AS (
                SELECT DISTINCT ON (g.chave) g.chave, g.cd_ingrediente AS vencedor
                  FROM g JOIN dup ON dup.chave = g.chave
                 ORDER BY g.chave, g.usos DESC, g.acentos DESC, g.cd_ingrediente ASC
            )
            SELECT g.cd_ingrediente AS perdedor, v.vencedor
              FROM g JOIN v ON v.chave = g.chave
             WHERE g.cd_ingrediente <> v.vencedor
        SQL);

        // Se a bebida já usa vencedor e perdedor, repontar violaria a unique.
        DB::statement(<<<'SQL'
            DELETE FROM bebida_ingrediente bi
             USING fusao_ingrediente f
             WHERE bi.cd_ingrediente = f.perdedor
               AND EXISTS (SELECT 1 FROM bebida_ingrediente x
                            WHERE x.cd_bebida = bi.cd_bebida
                              AND x.cd_ingrediente = f.vencedor)
        SQL);

        DB::statement(<<<'SQL'
            UPDATE bebida_ingrediente bi
               SET cd_ingrediente = f.vencedor
              FROM fusao_ingrediente f
             WHERE bi.cd_ingrediente = f.perdedor
        SQL);

        DB::statement(<<<'SQL'
            DELETE FROM ingrediente i
             USING fusao_ingrediente f
             WHERE i.cd_ingrediente = f.perdedor
        SQL);

        DB::statement('DROP TABLE IF EXISTS fusao_ingrediente');

        DB::statement('CREATE UNIQUE INDEX ingrediente_nome_unico ON ingrediente (lower(f_unaccent(nm_ingrediente)))');

        Schema::table('bebida_ingrediente', function (Blueprint $table) {
            $table->index('cd_ingrediente');
            $table->foreign('cd_bebida')->references('cd_bebida')->on('bebida')->onDelete('cascade');
            $table->foreign('cd_ingrediente')->references('cd_ingrediente')->on('ingrediente')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Desfaz apenas as constraints; os ingredientes fundidos não voltam.
     */
    public function down(): void
    {
        Schema::table('bebida_ingrediente', function (Blueprint $table) {
            $table->dropForeign(['cd_bebida']);
            $table->dropForeign(['cd_ingrediente']);
            $table->dropIndex(['cd_ingrediente']);
        });

        DB::statement('DROP INDEX IF EXISTS ingrediente_nome_unico');
        DB::statement('DROP FUNCTION IF EXISTS f_unaccent(text)');
    }
};
