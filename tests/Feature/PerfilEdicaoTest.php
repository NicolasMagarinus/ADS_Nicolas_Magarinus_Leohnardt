<?php

namespace Tests\Feature;

use App\Models\User;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Até aqui só dava para trocar a senha: quem entrou pelo Google ficava preso
 * ao nome da conta Google, e ninguém corrigia o próprio nome.
 */
class PerfilEdicaoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Evita tocar a API do Cloudinary; devolve a URL que o controller grava.
     *
     * CloudinaryEngine::upload() é fluente — declara retorno da própria engine
     * —, então quem responde ao getSecurePath() é o mesmo mock.
     */
    private function cloudinaryDevolvendo(string $url): void
    {
        Cloudinary::shouldReceive('upload')->once()->andReturnSelf();
        Cloudinary::shouldReceive('getSecurePath')->andReturn($url);
    }

    public function test_troca_o_proprio_nome(): void
    {
        $usuario = User::factory()->create(['name' => 'Nome da Conta Google']);

        $this->actingAs($usuario)
            ->post(route('perfil.update'), ['name' => 'Nicolas'])
            ->assertRedirect(route('perfil.index'));

        $this->assertSame('Nicolas', $usuario->fresh()->name);

        $this->actingAs($usuario)->get(route('perfil.index'))->assertSee('Nicolas');
    }

    public function test_nome_vazio_e_recusado(): void
    {
        $usuario = User::factory()->create(['name' => 'Joana']);

        $this->actingAs($usuario)
            ->post(route('perfil.update'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame('Joana', $usuario->fresh()->name);
    }

    public function test_avatar_enviado_vira_url_no_perfil(): void
    {
        $usuario = User::factory()->create();
        $this->cloudinaryDevolvendo('https://res.cloudinary.com/test/avatares/abc.jpg');

        $this->actingAs($usuario)->post(route('perfil.update'), [
            'name' => $usuario->name,
            'ds_avatar' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertRedirect(route('perfil.index'));

        $this->assertSame('https://res.cloudinary.com/test/avatares/abc.jpg', $usuario->fresh()->ds_avatar);

        $this->actingAs($usuario)->get(route('perfil.index'))
            ->assertSee('https://res.cloudinary.com/test/avatares/abc.jpg', false);
    }

    public function test_salvar_sem_imagem_preserva_o_avatar_atual(): void
    {
        $usuario = User::factory()->create(['ds_avatar' => 'https://res.cloudinary.com/test/antigo.jpg']);

        $this->actingAs($usuario)->post(route('perfil.update'), ['name' => 'Outro Nome']);

        $usuario = $usuario->fresh();
        $this->assertSame('Outro Nome', $usuario->name);
        $this->assertSame('https://res.cloudinary.com/test/antigo.jpg', $usuario->ds_avatar);
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado(): void
    {
        $usuario = User::factory()->create(['name' => 'Joana']);

        $this->actingAs($usuario)->post(route('perfil.update'), [
            'name' => 'Nicolas',
            'ds_avatar' => UploadedFile::fake()->create('receita.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('ds_avatar');

        $this->assertSame('Joana', $usuario->fresh()->name);
    }

    public function test_edicao_atinge_so_o_proprio_perfil(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);

        $this->actingAs($bruno)->post(route('perfil.update'), ['name' => 'Bruno Editado']);

        $this->assertSame('Ana', $ana->fresh()->name);
        $this->assertSame('Bruno Editado', $bruno->fresh()->name);
    }

    public function test_rota_exige_login(): void
    {
        $this->post(route('perfil.update'), ['name' => 'Qualquer'])
            ->assertRedirect(route('login'));
    }

    public function test_avatar_aparece_no_menu_do_header(): void
    {
        $usuario = User::factory()->create([
            'name' => 'Nicolas',
            'ds_avatar' => 'https://res.cloudinary.com/test/avatares/abc.jpg',
        ]);

        $this->actingAs($usuario)->get(route('home'))
            ->assertOk()
            ->assertSee('https://res.cloudinary.com/test/avatares/abc.jpg', false)
            ->assertSee('Nicolas');
    }

    /**
     * O menu do topo tem um ramo para logado e outro para visitante, e os dois
     * mostravam o mesmo rótulo. Ler Auth::user() no ramo errado derruba toda
     * página pública com 500.
     */
    public function test_header_do_visitante_nao_le_usuario(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Login');
    }

    public function test_perfil_sem_avatar_mostra_o_icone_padrao(): void
    {
        $this->actingAs(User::factory()->create(['ds_avatar' => null]))
            ->get(route('perfil.index'))
            ->assertOk()
            ->assertSee('bi-person-circle', false);
    }
}
