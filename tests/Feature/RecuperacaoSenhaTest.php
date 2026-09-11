<?php

namespace Tests\Feature;

use App\Mail\CodigoRecuperacaoSenha;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Recuperação de senha por código de 6 dígitos enviado por e-mail.
 *
 * Um código curto só é seguro enquanto ninguém puder chutá-lo à vontade,
 * então a contagem de tentativas e a expiração são parte da funcionalidade,
 * não um detalhe.
 */
class RecuperacaoSenhaTest extends TestCase
{
    use RefreshDatabase;

    private const SENHA_ANTIGA = 'senha-antiga-123';

    private function usuario(string $email = 'pessoa@example.test'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make(self::SENHA_ANTIGA),
        ]);
    }

    /** Dispara a etapa 1 e devolve o código que foi parar no e-mail. */
    private function pedirCodigo(string $email): string
    {
        $this->post(route('password.email'), ['email' => $email]);

        $codigo = null;
        Mail::assertSent(CodigoRecuperacaoSenha::class, function ($mail) use (&$codigo, $email) {
            $codigo = $mail->codigo;

            return $mail->hasTo($email);
        });

        return $codigo;
    }

    // ---------- etapa 1: pedir o código ----------

    public function test_envia_codigo_para_email_cadastrado(): void
    {
        Mail::fake();
        $user = $this->usuario();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.code'));

        Mail::assertSent(CodigoRecuperacaoSenha::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_email_desconhecido_responde_igual_e_nao_dispara_nada(): void
    {
        Mail::fake();

        $this->post(route('password.email'), ['email' => 'ninguem@example.test'])
            ->assertRedirect(route('password.code'));

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_codigo_nao_fica_em_texto_puro_no_banco(): void
    {
        Mail::fake();
        $user = $this->usuario();

        $codigo = $this->pedirCodigo($user->email);
        $guardado = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        $this->assertNotSame($codigo, $guardado);
        $this->assertTrue(Hash::check($codigo, $guardado));
    }

    // ---------- etapa 2: conferir o código ----------

    public function test_codigo_correto_libera_a_redefinicao(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $codigo = $this->pedirCodigo($user->email);

        $this->post(route('password.code.verify'), ['codigo' => $codigo])
            ->assertRedirect(route('password.reset'));
    }

    public function test_codigo_errado_e_recusado(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $this->pedirCodigo($user->email);

        $this->post(route('password.code.verify'), ['codigo' => '000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('codigo');
    }

    public function test_codigo_expirado_e_recusado(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $codigo = $this->pedirCodigo($user->email);

        $this->travel(16)->minutes();

        $this->post(route('password.code.verify'), ['codigo' => $codigo])
            ->assertSessionHasErrors('codigo');
    }

    public function test_cinco_tentativas_erradas_invalidam_o_codigo(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $codigo = $this->pedirCodigo($user->email);

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('password.code.verify'), ['codigo' => '000000'])
                ->assertSessionHasErrors('codigo');
        }

        $this->assertDatabaseCount('password_reset_tokens', 0);

        // Nem o código certo vale mais: é preciso pedir outro.
        $this->post(route('password.code.verify'), ['codigo' => $codigo])
            ->assertSessionHasErrors('codigo');
    }

    // ---------- etapa 3: trocar a senha ----------

    public function test_redefine_a_senha_e_volta_para_o_login(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $codigo = $this->pedirCodigo($user->email);
        $this->post(route('password.code.verify'), ['codigo' => $codigo]);

        $this->post(route('password.update'), [
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('senha-nova-456', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_codigo_expirado_nao_troca_a_senha_na_aba_aberta(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $codigo = $this->pedirCodigo($user->email);
        $this->post(route('password.code.verify'), ['codigo' => $codigo])
            ->assertRedirect(route('password.reset'));

        // Conferiu o código e deixou a aba aberta além da validade.
        $this->travel(16)->minutes();

        $this->post(route('password.update'), [
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertRedirect(route('password.request'));

        $this->assertFalse(Hash::check('senha-nova-456', $user->fresh()->password));
    }

    public function test_sem_a_linha_do_codigo_nao_troca_a_senha(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $codigo = $this->pedirCodigo($user->email);
        $this->post(route('password.code.verify'), ['codigo' => $codigo]);

        // O pedido foi encerrado por outro caminho — outra aba concluiu a
        // troca, ou uma limpeza apagou a linha.
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $this->post(route('password.update'), [
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertRedirect(route('password.request'));

        $this->assertFalse(Hash::check('senha-nova-456', $user->fresh()->password));
    }

    public function test_codigo_ja_usado_nao_vale_num_pedido_novo(): void
    {
        Mail::fake();
        $user = $this->usuario();

        $antigo = $this->pedirCodigo($user->email);
        $this->post(route('password.code.verify'), ['codigo' => $antigo]);
        $this->post(route('password.update'), [
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ]);

        $this->assertDatabaseCount('password_reset_tokens', 0);

        // Alguém com acesso ao e-mail antigo abre uma nova recuperação e
        // tenta o código que já foi gasto.
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->post(route('password.code.verify'), ['codigo' => $antigo])
            ->assertSessionHasErrors('codigo');

        $this->assertTrue(Hash::check('senha-nova-456', $user->fresh()->password));
    }

    public function test_fluxo_concluido_nao_deixa_a_etapa_dois_acessivel(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $codigo = $this->pedirCodigo($user->email);
        $this->post(route('password.code.verify'), ['codigo' => $codigo]);
        $this->post(route('password.update'), [
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ]);

        $this->get(route('password.code'))->assertRedirect(route('password.request'));
    }

    public function test_senha_curta_e_recusada(): void
    {
        Mail::fake();
        $user = $this->usuario();
        $codigo = $this->pedirCodigo($user->email);
        $this->post(route('password.code.verify'), ['codigo' => $codigo]);

        $this->post(route('password.update'), [
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::SENHA_ANTIGA, $user->fresh()->password));
    }

    public function test_nao_troca_a_senha_sem_ter_conferido_o_codigo(): void
    {
        $user = $this->usuario();

        $this->post(route('password.update'), [
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check(self::SENHA_ANTIGA, $user->fresh()->password));
    }

    public function test_a_tela_de_redefinir_nao_abre_sem_conferir_o_codigo(): void
    {
        $this->get(route('password.reset'))->assertRedirect(route('password.request'));
    }

    public function test_o_link_aparece_no_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'));
    }

    /**
     * Mail::fake() nunca monta a mensagem, então erros no template do e-mail
     * ou um remetente ausente passam batido nos outros testes. Aqui a
     * Mailable é renderizada de verdade.
     */
    public function test_o_email_e_montado_e_mostra_o_codigo(): void
    {
        $mailable = new CodigoRecuperacaoSenha('123456', 15);

        $mailable->assertHasSubject('Seu código de recuperação do Drinkerito');
        $mailable->assertSeeInHtml('123456');
        $mailable->assertSeeInHtml('15 minutos');
    }

    public function test_existe_remetente_configurado(): void
    {
        $this->assertNotEmpty(
            config('mail.from.address'),
            'MAIL_FROM_ADDRESS vazio faz todo envio estourar com "An email must have a From header".'
        );
    }
}
