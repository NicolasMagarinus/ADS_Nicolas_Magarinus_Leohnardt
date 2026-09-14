<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Id;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As chaves do catálogo são `increments`, ou seja, `integer` no Postgres, que
 * vai até 2147483647. As rotas restringem o parâmetro com `whereNumber`, que é
 * `[0-9]+` e não limita a quantidade de dígitos: um id de dez dígitos passa
 * pela rota, cabe folgado no int64 do PHP, e só estoura lá no banco, onde o
 * Postgres recusa o parâmetro bindado com SQLSTATE 22003 e a QueryException
 * não tratada vira HTTP 500.
 *
 * São rotas públicas (ou alcançáveis por qualquer conta autenticada), e o
 * defeito não aparece em tela nenhuma — só um crawler ou um link colado o
 * produz. Este arquivo existe para que ele não volte: o mesmo defeito já
 * reapareceu por quatro portas diferentes nas rotas de coleção.
 *
 * Dois tamanhos importam, e são caminhos diferentes:
 *   - 9999999999 cabe no int64 do PHP e chega inteiro ao banco;
 *   - 99999999999999999999 estoura o int64 e satura no cast.
 */
class IdForaDaFaixaTest extends TestCase
{
    use RefreshDatabase;

    public static function idsForaDaFaixa(): array
    {
        return [
            'cabe no int64 do PHP' => ['9999999999'],
            'estoura o int64 do PHP' => ['99999999999999999999'],
        ];
    }

    /** @dataProvider idsForaDaFaixa */
    public function test_pagina_da_bebida_da_404(string $id): void
    {
        $this->get('/bebida/'.$id)->assertNotFound();
    }

    /** @dataProvider idsForaDaFaixa */
    public function test_pagina_do_ingrediente_da_404(string $id): void
    {
        $this->get('/ingrediente/'.$id)->assertNotFound();
    }

    /** @dataProvider idsForaDaFaixa */
    public function test_alternar_favorito_da_404_em_json(string $id): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/favoritos/'.$id.'/toggle')
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('favorito', 0);
    }

    /** @dataProvider idsForaDaFaixa */
    public function test_verificar_favorito_da_404_em_json(string $id): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/favoritos/'.$id.'/check')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    /**
     * O limite exato é a borda que interessa: 2147483647 é id válido e precisa
     * chegar ao banco (não existe, então 404 vindo do findOrFail); 2147483648
     * é um a mais e precisa ser barrado antes da consulta.
     *
     * Os dois respondem 404, e é por isso que este teste não basta sozinho —
     * quem separa os casos é o de cima, que sem a correção estoura 500.
     */
    public function test_a_borda_exata_do_integer_responde_404_dos_dois_lados(): void
    {
        $this->get('/bebida/'.Id::MAX_INTEGER_POSTGRES)->assertNotFound();
        $this->get('/bebida/'.(Id::MAX_INTEGER_POSTGRES + 1))->assertNotFound();
    }

    /**
     * A rota da bebida declara o parâmetro como opcional (`{cd_bebida?}`), mas
     * `BebidaController::show()` o exige na assinatura. Sem valor padrão, o
     * Laravel chama o método sem argumento e o PHP lança ArgumentCountError:
     * `/bebida/` responde 500, não 404.
     *
     * Defeito irmão do resto deste arquivo — mesma rota pública, mesmo 500 onde
     * o certo é 404 — e apareceu justamente porque este teste foi escrito antes
     * da correção.
     */
    public function test_bebida_sem_id_da_404(): void
    {
        $this->get('/bebida/')->assertNotFound();
    }
}
