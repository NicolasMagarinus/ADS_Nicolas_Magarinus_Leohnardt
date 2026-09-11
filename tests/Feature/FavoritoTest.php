<?php

namespace Tests\Feature;

use App\Models\Bebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O botão de favoritar é chamado por fetch e espera JSON de volta. Um id que
 * não existe não pode virar violação de chave estrangeira: o front recebe uma
 * página de erro onde esperava JSON e trava sem dizer nada.
 */
class FavoritoTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(): Bebida
    {
        return Bebida::create([
            'nm_bebida' => 'Mojito',
            'ds_preparo' => 'Macere hortelã e limão, complete com rum e água com gás.',
            'id_tipo' => 1,
            'ds_bebida' => 'Cubano.',
        ]);
    }

    public function test_alterna_favorito_nos_dois_sentidos(): void
    {
        $usuario = User::factory()->create();
        $bebida = $this->bebida();

        $this->actingAs($usuario)
            ->postJson(route('favoritos.toggle', $bebida->cd_bebida))
            ->assertOk()
            ->assertJson(['favorited' => true]);

        $this->assertDatabaseCount('favorito', 1);

        $this->actingAs($usuario)
            ->postJson(route('favoritos.toggle', $bebida->cd_bebida))
            ->assertOk()
            ->assertJson(['favorited' => false]);

        $this->assertDatabaseCount('favorito', 0);
    }

    public function test_bebida_inexistente_devolve_404_em_json(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('favoritos.toggle', 999999))
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('favorito', 0);
    }

    public function test_id_nao_numerico_nao_chega_ao_controller(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/favoritos/abc/toggle')
            ->assertNotFound();
    }
}
