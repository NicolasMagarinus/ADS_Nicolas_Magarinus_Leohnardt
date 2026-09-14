<?php

namespace App\Http\Controllers;

use App\Models\Bebida;
use App\Models\Colecao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ColecaoController extends Controller
{
    /**
     * Mínimo de bebidas para a coleção pública entrar no índice e sair do
     * noindex. Abaixo disso é página magra.
     */
    public const MINIMO_PARA_INDICE = 3;

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
}
