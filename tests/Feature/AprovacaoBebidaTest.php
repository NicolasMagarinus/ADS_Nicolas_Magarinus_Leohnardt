<?php

namespace Tests\Feature;

use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A aprovação é o único caminho pelo qual conteúdo de usuário entra no
 * catálogo. Perder o tipo aqui contamina a busca de forma cumulativa, e
 * gravar ingrediente sem normalizar recria as duplicatas que já custaram
 * uma migração de fusão.
 */
class AprovacaoBebidaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['id_admin' => true]);
    }

    private function cadastroPendente(array $atributos = [], array $ingredientes = []): CadastroBebida
    {
        $cadastro = CadastroBebida::create(array_merge([
            'id_usuario' => User::factory()->create()->id,
            'nm_bebida' => 'Limonada Suíça',
            'id_tipo' => 2,
            'ds_bebida' => 'Refrescante e sem álcool.',
            'ds_preparo' => 'Bata tudo no liquidificador. Coe e sirva.',
            'id_status' => 0,
        ], $atributos));

        foreach ($ingredientes ?: [['nm_ingrediente' => 'Limão', 'ds_medida' => '2 unidades']] as $ingrediente) {
            CadastroBebidaIngrediente::create(array_merge(
                ['cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro],
                $ingrediente
            ));
        }

        return $cadastro;
    }

    public function test_bebida_sem_alcool_chega_ao_catalogo_sem_alcool(): void
    {
        $cadastro = $this->cadastroPendente();

        $this->actingAs($this->admin())
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro))
            ->assertRedirect(route('admin.bebidas.index'));

        $this->assertDatabaseHas('bebida', [
            'nm_bebida' => 'Limonada Suíça',
            'id_tipo' => 2,
            'ds_bebida' => 'Refrescante e sem álcool.',
        ]);

        $this->assertDatabaseHas('cadastro_bebida', [
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'id_status' => 1,
        ]);
    }

    public function test_descricao_em_branco_cai_no_texto_padrao(): void
    {
        $cadastro = $this->cadastroPendente(['ds_bebida' => null]);

        $this->actingAs($this->admin())
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        $this->assertDatabaseHas('bebida', [
            'nm_bebida' => 'Limonada Suíça',
            'ds_bebida' => 'Bebida cadastrada por usuário',
        ]);
    }

    public function test_ingrediente_equivalente_reusa_a_linha_existente(): void
    {
        $existente = Ingrediente::create(['nm_ingrediente' => 'Água com gás']);

        $cadastro = $this->cadastroPendente([], [
            ['nm_ingrediente' => 'AGUA COM GAS', 'ds_medida' => '200 ml'],
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        $this->assertSame(1, Ingrediente::count(), 'a grafia diferente não pode criar um segundo ingrediente');
        $this->assertDatabaseHas('bebida_ingrediente', [
            'cd_ingrediente' => $existente->cd_ingrediente,
            'ds_medida' => '200 ml',
        ]);
    }

    public function test_duas_grafias_no_mesmo_cadastro_viram_um_vinculo(): void
    {
        $cadastro = $this->cadastroPendente([], [
            ['nm_ingrediente' => 'Açúcar', 'ds_medida' => '2 colheres'],
            ['nm_ingrediente' => 'Acucar', 'ds_medida' => 'a gosto'],
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        $this->assertSame(1, Ingrediente::count());
        $this->assertDatabaseCount('bebida_ingrediente', 1);
    }

    public function test_usuario_comum_nao_aprova(): void
    {
        $cadastro = $this->cadastroPendente();

        $this->actingAs(User::factory()->create(['id_admin' => false]))
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro))
            ->assertForbidden();

        $this->assertDatabaseCount('bebida', 0);
        $this->assertDatabaseHas('cadastro_bebida', [
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'id_status' => 0,
        ]);
    }
}
