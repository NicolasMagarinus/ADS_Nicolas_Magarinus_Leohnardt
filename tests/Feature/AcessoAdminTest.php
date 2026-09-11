<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A proteção do painel precisa valer por rota, não por uma linha repetida
 * dentro de cada método: quem esquecer a linha num método novo abre o painel
 * inteiro para qualquer usuário logado.
 */
class AcessoAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_comum_nao_abre_o_painel(): void
    {
        $this->actingAs(User::factory()->create(['id_admin' => false]))
            ->get(route('admin.bebidas.index'))
            ->assertForbidden();
    }

    public function test_admin_abre_o_painel(): void
    {
        $this->actingAs(User::factory()->create(['id_admin' => true]))
            ->get(route('admin.bebidas.index'))
            ->assertOk();
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get(route('admin.bebidas.index'))->assertRedirect(route('login'));
    }

    /**
     * O ponto do item: uma rota nova no grupo admin nasce protegida, mesmo
     * que o método dela não repita a conferência à mão.
     */
    public function test_rota_nova_no_grupo_admin_ja_nasce_protegida(): void
    {
        Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')
            ->get('/rota-de-teste', fn () => 'ok')->name('rota-de-teste');

        $this->actingAs(User::factory()->create(['id_admin' => false]))
            ->get('/admin/rota-de-teste')
            ->assertForbidden();
    }
}
