<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Str::limit(null, 157) devolve null, e @section('descricao', null) é a forma
 * de BLOCO do Blade: abre um ob_start() que nunca fecha. E o app:gerar-bebidas-ai
 * não grava ds_bebida, então toda bebida gerada por IA caía nesse caminho.
 */
class DescricaoNulaTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(?string $descricao): Bebida
    {
        return Bebida::create([
            'nm_bebida' => 'Mojito',
            'ds_preparo' => 'Macere a hortelã e complete com rum.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => $descricao,
        ]);
    }

    public function test_bebida_sem_descricao_nao_deixa_buffer_aberto(): void
    {
        $bebida = $this->bebida(null);
        $antes = ob_get_level();

        $this->get(route('bebida.show', $bebida->cd_bebida))->assertOk();

        $this->assertSame($antes, ob_get_level(), 'a view abriu um buffer de saída e não fechou');
    }

    public function test_aleatoria_sem_descricao_nao_deixa_buffer_aberto(): void
    {
        $this->bebida(null);
        $antes = ob_get_level();

        $this->get(route('random'))->assertOk();

        $this->assertSame($antes, ob_get_level(), 'a view abriu um buffer de saída e não fechou');
    }

    /**
     * Sem descrição própria, o card do WhatsApp mostrava o texto genérico do
     * site — o mesmo para todo drink. Dizer o nome da receita é pouco, mas é
     * específico.
     */
    public function test_sem_descricao_a_meta_fala_da_receita(): void
    {
        $bebida = $this->bebida(null);

        $html = $this->get(route('bebida.show', $bebida->cd_bebida))->assertOk()->getContent();

        preg_match('#<meta name="description" content="([^"]*)"#', $html, $m);
        $this->assertStringContainsString('Mojito', $m[1] ?? '');

        preg_match('#<meta property="og:description" content="([^"]*)"#', $html, $og);
        $this->assertStringContainsString('Mojito', $og[1] ?? '');
    }

    public function test_com_descricao_continua_usando_a_da_bebida(): void
    {
        $bebida = $this->bebida('O clássico cubano com hortelã e água com gás.');

        $this->get(route('bebida.show', $bebida->cd_bebida))
            ->assertOk()
            ->assertSee('<meta name="description" content="O clássico cubano com hortelã e água com gás.">', false);
    }

    public function test_descricao_longa_continua_sendo_cortada(): void
    {
        $bebida = $this->bebida(str_repeat('Muito saboroso. ', 40));

        $html = $this->get(route('bebida.show', $bebida->cd_bebida))->assertOk()->getContent();

        preg_match('#<meta name="description" content="([^"]*)"#', $html, $m);
        $this->assertLessThan(180, strlen($m[1] ?? str_repeat('x', 999)));
    }
}
