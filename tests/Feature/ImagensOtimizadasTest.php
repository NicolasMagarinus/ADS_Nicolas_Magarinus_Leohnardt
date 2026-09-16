<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As telas pediam ao Cloudinary a imagem em tamanho cheio — até 1024px — para
 * exibir em cards de 200px. Dói no celular, que é o aparelho de quem está
 * preparando um drink com a receita aberta na bancada.
 */
class ImagensOtimizadasTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(): Bebida
    {
        return Bebida::create([
            'nm_bebida' => 'Mojito',
            'ds_preparo' => 'Macere a hortelã e complete com rum.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Cubano.',
            'ds_imagem' => 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/bebidas/mojito.jpg',
        ]);
    }

    public function test_grade_da_busca_pede_imagem_menor_e_adiada(): void
    {
        $this->bebida();

        $this->get(route('search'))
            ->assertOk()
            ->assertSee('w_400,f_auto,q_auto', false)
            ->assertSee('loading="lazy"', false);
    }

    public function test_grades_nao_pedem_a_imagem_em_tamanho_cheio(): void
    {
        $this->bebida();

        $html = $this->get(route('search'))->assertOk()->getContent();

        // A URL crua, sem transformação, não deve sobrar em nenhuma grade.
        $this->assertStringNotContainsString('/upload/v1763919598/bebidas/mojito.jpg', $html);
    }

    /**
     * A imagem do topo do detalhe é o elemento que a pessoa foi ver: adiar o
     * carregamento dela atrasaria justamente o conteúdo principal.
     */
    public function test_imagem_do_topo_do_detalhe_e_maior_e_nao_e_adiada(): void
    {
        $bebida = $this->bebida();

        $html = $this->get(route('bebida.show', $bebida->cd_bebida))->assertOk()->getContent();

        $this->assertStringContainsString('w_800,f_auto,q_auto', $html);

        $tagHeroi = substr($html, strpos($html, 'w_800'), 200);
        $this->assertStringNotContainsString('loading="lazy"', $tagHeroi);
    }

    /**
     * WhatsApp e Facebook querem a imagem grande no card da prévia. Reduzir
     * para 400px estragaria o compartilhamento que o QA-07 montou.
     */
    public function test_og_image_sai_sem_transformacao(): void
    {
        $bebida = $this->bebida();

        $this->get(route('bebida.show', $bebida->cd_bebida))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.$bebida->ds_imagem.'">', false);
    }

    public function test_bebida_sem_imagem_cai_no_placeholder_redimensionado(): void
    {
        Bebida::create([
            'nm_bebida' => 'Sem foto',
            'ds_preparo' => 'Misture.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Teste.',
            'ds_imagem' => null,
        ]);

        $this->get(route('search'))
            ->assertOk()
            ->assertSee('sem-imagem', false)
            ->assertSee('w_400,f_auto,q_auto', false);
    }

    public function test_utilitario_javascript_tambem_redimensiona(): void
    {
        $js = file_get_contents(resource_path('js/drinkerito.js'));

        $this->assertStringContainsString('f_auto,q_auto', $js);
        $this->assertStringContainsString('imagem:', $js);
    }
}
