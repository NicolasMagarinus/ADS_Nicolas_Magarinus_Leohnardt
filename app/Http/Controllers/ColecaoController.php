<?php

namespace App\Http\Controllers;

use App\Models\Bebida;
use App\Models\Colecao;
use App\Models\ColecaoBebida;
use App\Support\Id;
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

        // Ver App\Support\Id para o porquê da checagem de faixa.
        $id = Id::validar($match[0]);

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
            $mensagem = 'Você chegou ao limite de '.self::LIMITE_POR_USUARIO.' coleções.';

            return $request->expectsJson()
                ? response()->json(['message' => $mensagem], 422)
                : back()->withInput()->withErrors(['nm_colecao' => $mensagem]);
        }

        // O modal da página da bebida cria e já vincula numa tacada. Os dois
        // passos rodam na mesma transação: sem ela, Colecao::create() já
        // tinha sido commitada antes de bebidaValidada() estourar 404 para
        // um cd_bebida inexistente (ou fora da faixa do INTEGER do
        // Postgres) — o cliente lia "não achei a bebida" e achava que nada
        // tinha acontecido, mas ficava uma coleção órfã na conta,
        // contando para o teto de 50 e aparecendo no perfil sem a bebida
        // que motivou a criação. bebidaValidada() é a mesma checagem de
        // faixa que os parâmetros de URL de paraBebida()/alternarBebida()
        // já usam — reaproveitada aqui em vez de reescrita para o corpo
        // JSON.
        // cd_bebida já chegou validado como inteiro positivo (ou ausente) por
        // validar() — não faz mais parte dos atributos da coleção, então sai
        // de $dados antes do create() para não depender do fillable do model
        // para descartá-lo silenciosamente.
        $cdBebida = $dados['cd_bebida'] ?? null;
        unset($dados['cd_bebida']);

        $colecao = DB::transaction(function () use ($cdBebida, $dados) {
            $colecao = Colecao::create($dados + ['id_usuario' => Auth::id()]);

            if ($cdBebida !== null) {
                $bebida = $this->bebidaValidada((string) $cdBebida);
                ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);
            }

            return $colecao;
        });

        return $request->expectsJson()
            ? response()->json(['cd_colecao' => $colecao->cd_colecao])
            : redirect()->to($colecao->url())->with('success', 'Coleção criada.');
    }

    /**
     * As coleções de quem está autenticado, marcando quais já têm a bebida.
     * É o que o modal lê ao abrir.
     */
    public function paraBebida(string $cd_bebida)
    {
        $bebida = $this->bebidaValidada($cd_bebida);

        $colecoes = Colecao::where('id_usuario', Auth::id())
            ->orderBy('nm_colecao')
            ->get();

        // Uma consulta só para os vínculos desta bebida com as coleções de
        // quem está autenticado, em vez de um exists() por coleção dentro do
        // map abaixo: com o teto de 50, abrir o modal eram 51 consultas.
        $comABebida = ColecaoBebida::where('cd_bebida', $bebida->cd_bebida)
            ->whereIn('cd_colecao', $colecoes->pluck('cd_colecao'))
            ->pluck('cd_colecao');

        $colecoes = $colecoes->map(fn (Colecao $colecao) => [
            'cd_colecao' => $colecao->cd_colecao,
            'nm_colecao' => $colecao->nm_colecao,
            'contem' => $comABebida->contains($colecao->cd_colecao),
        ]);

        return response()->json(['colecoes' => $colecoes]);
    }

    public function alternarBebida(string $cd_colecao, string $cd_bebida)
    {
        $colecao = $this->minhaColecao($cd_colecao);
        $bebida = $this->bebidaValidada($cd_bebida);

        $vinculo = ColecaoBebida::where('cd_colecao', $colecao->cd_colecao)
            ->where('cd_bebida', $bebida->cd_bebida)
            ->first();

        if ($vinculo) {
            $vinculo->delete();

            return response()->json([
                'success' => true,
                'contem' => false,
                'message' => 'Removido de '.$colecao->nm_colecao,
            ]);
        }

        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);

        // O updated_at ordena o índice /colecoes; sem o touch, a coleção que
        // acabou de ganhar drink não sobe.
        $colecao->touch();

        return response()->json([
            'success' => true,
            'contem' => true,
            'message' => 'Adicionado a '.$colecao->nm_colecao,
        ]);
    }

    public function update(Request $request, string $cd_colecao)
    {
        $colecao = $this->minhaColecao($cd_colecao);
        $colecao->update($this->validar($request, $colecao));

        return redirect()->to($colecao->fresh()->url())->with('success', 'Coleção atualizada.');
    }

    public function destroy(string $cd_colecao)
    {
        // O cascade de colecao_bebida cuida dos vínculos.
        $this->minhaColecao($cd_colecao)->delete();

        return redirect()->route('perfil.index')->with('success', 'Coleção apagada.');
    }

    /**
     * Carrega a coleção exigindo que seja de quem está autenticado.
     *
     * 404, e não 403, pelo mesmo motivo do show: 403 confirma que existe.
     */
    private function minhaColecao(string $cd_colecao): Colecao
    {
        return Colecao::where('cd_colecao', Id::validar($cd_colecao))
            ->where('id_usuario', Auth::id())
            ->firstOrFail();
    }

    /**
     * Carrega a bebida a partir de um parâmetro de rota, com a mesma checagem
     * de faixa de minhaColecao(). bebida.cd_bebida também é INTEGER no
     * Postgres.
     */
    private function bebidaValidada(string $cd_bebida): Bebida
    {
        return Bebida::findOrFail(Id::validar($cd_bebida));
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
            // Só o store() usa isto (o modal da página da bebida, que cria e
            // já vincula numa tacada), mas validar aqui — em vez de deixar
            // (string) $request->input('cd_bebida') converter o que vier —
            // é o que faz um array virar 422 em vez de "Array to string
            // conversion" (500), e "abc"/"0" virarem 422 com mensagem em vez
            // do 404 mais opaco. bebidaValidada() continua responsável só
            // por inexistente e fora da faixa do INTEGER do Postgres.
            'cd_bebida' => ['nullable', 'integer', 'min:1'],
        ], [
            'nm_colecao.required' => 'Dê um nome à coleção.',
            'nm_colecao.unique' => 'Você já tem uma coleção com esse nome.',
        ]);

        // Checkbox desmarcado não chega no request.
        $dados['id_publica'] = $request->boolean('id_publica');

        return $dados;
    }
}
