<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\CadastroBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A suíte roda com QUEUE_CONNECTION=sync, então o caminho da fila de verdade
 * — serializar a notificação, gravar na tabela jobs, desserializar no worker
 * — nunca é exercitado, e é justamente ele que BebidaModerada passou a usar
 * ao virar ShouldQueue. Uma quebra ali não aparece em nenhum outro teste: o
 * job falha calado e o autor simplesmente não recebe o aviso.
 */
class FilaModeracaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_fila_de_verdade_entrega_o_aviso(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);

        $autor = User::factory()->create(['email' => 'autor@teste.test']);
        $cadastro = CadastroBebida::create([
            'id_usuario' => $autor->id,
            'nm_bebida' => 'Gin & Tonic "Especial"',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Clássico.',
            'ds_preparo' => 'Misture.',
            'id_status' => StatusCadastro::Pendente,
        ]);
        DB::table('cadastro_bebida_ingrediente')->insert([
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'nm_ingrediente' => 'Gin',
            'ds_medida' => '50ml',
        ]);

        $this->actingAs(User::factory()->create(['id_admin' => true]))
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro))
            ->assertRedirect();

        $this->assertSame(1, DB::table('jobs')->count(), 'nada foi enfileirado');

        // Roda o worker de verdade: é aqui que a desserializacao acontece.
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])
            ->assertExitCode(0);

        $this->assertSame(0, DB::table('failed_jobs')->count(), 'o job falhou na fila');
        $this->assertSame(0, DB::table('jobs')->count(), 'o job nao saiu da fila');

        $enviados = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados, 'nenhum e-mail saiu do worker');
        $corpo = $enviados[0]->toString();
        $this->assertStringContainsString('autor@teste.test', $corpo);
        $this->assertStringContainsString('aprovada', mb_strtolower($corpo));
    }
}
