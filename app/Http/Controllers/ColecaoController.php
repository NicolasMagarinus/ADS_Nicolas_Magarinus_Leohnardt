<?php

namespace App\Http\Controllers;

use App\Models\Bebida;
use App\Models\Colecao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ColecaoController extends Controller
{
    /**
     * Mínimo de bebidas para a coleção pública entrar no índice e sair do
     * noindex. Abaixo disso é página magra.
     */
    public const MINIMO_PARA_INDICE = 3;

    /**
     * Teto de coleções por conta. Coleção pública é superfície indexável
     * criada por usuário, e superfície indexável sem teto é convite a script.
     */
    public const LIMITE_POR_USUARIO = 50;

    /**
     * Índice das coleções públicas.
     *
     * Só entram as com no mínimo 3 bebidas, pela mesma razão que
     * IngredienteController::index deixa ingrediente órfão de fora: índice
     * cheio de página magra é pior que índice menor. A coleção pública magra
     * continua acessível por link direto e pelo perfil do dono.
     */
    public function index()
    {
        $colecoes = Colecao::query()
            ->with('usuario')
            ->withCount('bebidas')
            ->where('id_publica', true)
            ->has('bebidas', '>=', self::MINIMO_PARA_INDICE)
            ->orderByDesc('updated_at')
            ->paginate(24);

        return view('colecao.index', compact('colecoes'));
    }

    /**
     * Página da coleção.
     *
     * O parâmetro é híbrido: o id manda, o slug é enfeite. Qualquer forma que
     * não seja a canônica sai 301 — id puro, slug de antes do rename, ou lixo
     * no fim. Assim o link publicado continua valendo depois de renomear, sem
     * duplicar conteúdo no índice do buscador.
     */
    public function show(string $colecao)
    {
        // "12-drinks-de-verao" => "12". A restrição da rota garante que
        // começa com dígito, então o preg_match sempre casa.
        preg_match('/^\d+/', $colecao, $match);

        // cd_colecao é INTEGER no Postgres (máx. 2147483647), mas a rota só
        // exige "[0-9]+" — qualquer quantidade de dígitos casa. Um id de dez
        // dígitos ainda cabe no int64 do PHP e segue intacto até o bind do
        // prepared statement; um de vinte estoura o int64 e o (int) do PHP
        // satura em PHP_INT_MAX. Nos dois casos o Postgres recusa o bind com
        // "out of range for type integer" (SQLSTATE 22003), e sem essa
        // checagem essa QueryException não tratada vira 500 numa rota
        // pública, sem login — onde o certo é 404, como para qualquer id que
        // não exista. filter_var com max_range resolve os dois de uma vez:
        // um valor que não caiba no INTEGER do Postgres nunca corresponde a
        // uma coleção, então vira 404 antes de chegar à consulta.
        $id = filter_var($match[0], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647],
        ]);

        if ($id === false) {
            abort(404);
        }

        $registro = Colecao::with('usuario')->findOrFail($id);

        // 404, e não 403: 403 confirmaria que a coleção existe.
        if (! $registro->id_publica && $registro->id_usuario !== Auth::id()) {
            abort(404);
        }

        if ($colecao !== $registro->parametroUrl()) {
            return redirect()->to($registro->url(), 301);
        }

        $bebidas = Bebida::query()
            ->select(
                'bebida.*',
                DB::raw('COALESCE(ROUND(AVG(avaliacao.id_nota), 1), 0) AS nota'),
                DB::raw('COUNT(avaliacao.id_nota) AS qt_avaliacao'),
                DB::raw('MIN(cb.created_at) AS dt_adicionado')
            )
            ->join('colecao_bebida as cb', 'cb.cd_bebida', '=', 'bebida.cd_bebida')
            ->leftJoin('avaliacao', 'bebida.cd_bebida', '=', 'avaliacao.cd_bebida')
            ->where('cb.cd_colecao', $registro->cd_colecao)
            // Agrupar só pela PK basta no PostgreSQL (dependência funcional),
            // e é o que permite ordenar por MIN(cb.created_at) sem arrastar a
            // coluna para o GROUP BY. O projeto é Postgres only.
            ->groupBy('bebida.cd_bebida')
            ->orderBy('dt_adicionado')
            ->paginate(12);

        return view('colecao.show', ['colecao' => $registro, 'bebidas' => $bebidas]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        if (Colecao::where('id_usuario', Auth::id())->count() >= self::LIMITE_POR_USUARIO) {
            return back()->withInput()->withErrors([
                'nm_colecao' => 'Você chegou ao limite de '.self::LIMITE_POR_USUARIO.' coleções.',
            ]);
        }

        $colecao = Colecao::create($dados + ['id_usuario' => Auth::id()]);

        return redirect()->to($colecao->url())->with('sucesso', 'Coleção criada.');
    }

    public function update(Request $request, string $cd_colecao)
    {
        $colecao = $this->minhaColecao($cd_colecao);
        $colecao->update($this->validar($request, $colecao));

        return redirect()->to($colecao->fresh()->url())->with('sucesso', 'Coleção atualizada.');
    }

    public function destroy(string $cd_colecao)
    {
        // O cascade de colecao_bebida cuida dos vínculos.
        $this->minhaColecao($cd_colecao)->delete();

        return redirect()->route('perfil.index')->with('sucesso', 'Coleção apagada.');
    }

    /**
     * Carrega a coleção exigindo que seja de quem está autenticado.
     *
     * 404, e não 403, pelo mesmo motivo do show: 403 confirma que existe.
     *
     * O parâmetro chega como string, não como int, de propósito: a rota só
     * exige "[0-9]+", sem limite de dígitos, e um id de vinte dígitos nem
     * cabe no int64 do PHP — coagir direto para um parâmetro `int` faria o
     * PHP lançar TypeError antes mesmo de entrar no método, o que o
     * ExceptionHandler renderiza como 500, não 404. Com string na entrada, a
     * checagem de faixa é manual, igual ao show: um id de onze dígitos ainda
     * cabe no int64 do PHP e seguiria intacto até o bind, e o Postgres
     * recusaria com "out of range for type integer" (SQLSTATE 22003); um de
     * vinte nem chega a virar int. Nos dois casos o cd_colecao não pode
     * existir (é INTEGER no Postgres, máx. 2147483647), então vira 404 antes
     * de qualquer consulta.
     */
    private function minhaColecao(string $cd_colecao): Colecao
    {
        $id = filter_var($cd_colecao, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647],
        ]);

        if ($id === false) {
            abort(404);
        }

        return Colecao::where('cd_colecao', $id)
            ->where('id_usuario', Auth::id())
            ->firstOrFail();
    }

    private function validar(Request $request, ?Colecao $colecao = null): array
    {
        $unico = Rule::unique('colecao', 'nm_colecao')->where('id_usuario', Auth::id());

        if ($colecao) {
            $unico = $unico->ignore($colecao->cd_colecao, 'cd_colecao');
        }

        $dados = $request->validate([
            'nm_colecao' => ['required', 'string', 'max:60', $unico],
            'ds_colecao' => ['nullable', 'string', 'max:200'],
            'id_publica' => ['nullable', 'boolean'],
        ], [
            'nm_colecao.required' => 'Dê um nome à coleção.',
            'nm_colecao.unique' => 'Você já tem uma coleção com esse nome.',
        ]);

        // Checkbox desmarcado não chega no request.
        $dados['id_publica'] = $request->boolean('id_publica');

        return $dados;
    }
}
