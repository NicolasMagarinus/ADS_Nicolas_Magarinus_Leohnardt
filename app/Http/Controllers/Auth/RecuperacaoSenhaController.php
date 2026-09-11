<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\CodigoRecuperacaoSenha;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

/**
 * Recuperação de senha em três etapas: pedir o código, conferir o código,
 * escolher a senha nova.
 *
 * O e-mail em recuperação viaja na sessão, nunca na URL nem num campo do
 * formulário — assim não dá para pular a conferência do código trocando um
 * parâmetro, e as tentativas erradas de uma pessoa não gastam as de outra.
 */
class RecuperacaoSenhaController extends Controller
{
    private const MINUTOS_VALIDADE = 15;

    private const MAX_TENTATIVAS = 5;

    /** E-mail que pediu um código; ainda não provou ter recebido. */
    private const EMAIL_PENDENTE = 'recuperacao.email_pendente';

    /** E-mail que já acertou o código; pode trocar a senha. */
    private const EMAIL_VERIFICADO = 'recuperacao.email_verificado';

    // ---------- etapa 1: pedir o código ----------

    public function solicitar()
    {
        return view('auth.recuperacao.solicitar');
    }

    public function enviarCodigo(Request $request)
    {
        $request->validate(
            ['email' => 'required|email'],
            [
                'email.required' => 'Informe seu e-mail.',
                'email.email' => 'Informe um e-mail válido.',
            ]
        );

        $informado = mb_strtolower(trim($request->email));
        $usuario = User::whereRaw('lower(email) = ?', [$informado])->first();

        // Só existe envio se a conta existir, mas a resposta é idêntica nos
        // dois casos: dizer que o e-mail não existe permite descobrir quem
        // tem conta no site, um e-mail por vez.
        if ($usuario) {
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $usuario->email],
                [
                    'token' => Hash::make($codigo),
                    'tentativas' => 0,
                    'created_at' => now(),
                ]
            );

            Mail::to($usuario->email)->send(
                new CodigoRecuperacaoSenha($codigo, self::MINUTOS_VALIDADE)
            );
        }

        $request->session()->put(self::EMAIL_PENDENTE, $usuario->email ?? $informado);

        return redirect()->route('password.code')
            ->with('status', 'Se este e-mail estiver cadastrado, você receberá um código em instantes.');
    }

    // ---------- etapa 2: conferir o código ----------

    public function formularioCodigo(Request $request)
    {
        $email = $request->session()->get(self::EMAIL_PENDENTE);

        if (!$email) {
            return redirect()->route('password.request');
        }

        return view('auth.recuperacao.codigo', ['email' => $email]);
    }

    public function verificarCodigo(Request $request)
    {
        $email = $request->session()->get(self::EMAIL_PENDENTE);

        if (!$email) {
            return redirect()->route('password.request');
        }

        $request->validate(
            ['codigo' => 'required|digits:6'],
            [
                'codigo.required' => 'Digite o código que enviamos.',
                'codigo.digits' => 'O código tem 6 dígitos.',
            ]
        );

        if (!$this->codigoConfere($email, $request->codigo)) {
            return redirect()->route('password.code')
                ->withErrors(['codigo' => 'Código inválido ou expirado. Peça um novo código.']);
        }

        $request->session()->put(self::EMAIL_VERIFICADO, $email);
        $request->session()->forget(self::EMAIL_PENDENTE);

        return redirect()->route('password.reset');
    }

    /**
     * Confere o código do e-mail em recuperação. Cada erro gasta uma
     * tentativa; esgotadas, o código é descartado e é preciso pedir outro.
     */
    private function codigoConfere(string $email, string $codigo): bool
    {
        $registro = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$registro) {
            return false;
        }

        if ($this->expirou($registro)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return false;
        }

        if (Hash::check($codigo, $registro->token)) {
            return true;
        }

        if ($registro->tentativas + 1 >= self::MAX_TENTATIVAS) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        } else {
            DB::table('password_reset_tokens')->where('email', $email)->increment('tentativas');
        }

        return false;
    }

    private function expirou(object $registro): bool
    {
        return now()->diffInMinutes($registro->created_at, absolute: true) >= self::MINUTOS_VALIDADE;
    }

    // ---------- etapa 3: escolher a senha nova ----------

    public function formularioNovaSenha(Request $request)
    {
        if (!$request->session()->has(self::EMAIL_VERIFICADO)) {
            return redirect()->route('password.request');
        }

        return view('auth.recuperacao.redefinir');
    }

    public function redefinir(Request $request)
    {
        $email = $request->session()->get(self::EMAIL_VERIFICADO);

        if (!$email) {
            return redirect()->route('password.request');
        }

        $request->validate(
            ['password' => ['required', 'confirmed', Password::min(8)]],
            [
                'password.required' => 'Escolha uma senha.',
                'password.confirmed' => 'As senhas não coincidem.',
                'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            ]
        );

        $usuario = User::where('email', $email)->first();

        if (!$usuario) {
            return redirect()->route('password.request');
        }

        // A marca na sessão diz que o código foi conferido, não que ele ainda
        // vale: quem conferiu e deixou a aba aberta chegaria aqui depois dos
        // MINUTOS_VALIDADE. A validade tem de valer de ponta a ponta, então o
        // pedido é reconferido no banco antes de gravar a senha.
        $registro = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$registro || $this->expirou($registro)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $request->session()->forget(self::EMAIL_VERIFICADO);

            return redirect()->route('password.request')
                ->withErrors(['email' => 'O pedido expirou. Peça um novo código.']);
        }

        $usuario->password = Hash::make($request->password);
        $usuario->setRememberToken(null);
        $usuario->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        $request->session()->forget(self::EMAIL_VERIFICADO);

        return redirect()->route('login')
            ->with('success', 'Senha alterada com sucesso! Faça login com a nova senha.');
    }
}
