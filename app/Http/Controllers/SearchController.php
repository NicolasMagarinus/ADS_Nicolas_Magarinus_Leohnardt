<?php

namespace App\Http\Controllers;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\Ingrediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /** Notas mínimas oferecidas no formulário. */
    private const NOTAS = [3, 4];

    /** Limites de ingredientes oferecidos no formulário. */
    private const MAX_INGREDIENTES = [3, 5];

    public function index(Request $request)
    {
        // textoDaQuery cobre "?q=" (que o middleware transforma em null) e
        // "?q[]=abc" (que chega como array). Ver a Controller base.
        $searchTerm = $this->textoDaQuery($request, 'q');

        if ($redirecionamento = $this->traduzirFraseAntiga($searchTerm)) {
            return $redirecionamento;
        }

        $tipo = TipoBebida::tryFrom((int) $this->textoDaQuery($request, 'tipo'));
        $nota = (int) $this->textoDaQuery($request, 'nota');
        $nota = in_array($nota, self::NOTAS, true) ? $nota : null;

        $maxIngredientes = (int) $this->textoDaQuery($request, 'max_ingredientes');
        $maxIngredientes = in_array($maxIngredientes, self::MAX_INGREDIENTES, true) ? $maxIngredientes : null;

        $ingredienteId = $this->ingredienteValido($this->textoDaQuery($request, 'ingrediente'));

        $query = Bebida::query()
            ->select('bebida.*',
                DB::raw('COALESCE(ROUND(AVG(avaliacao.id_nota), 1), 0) AS nota'),
                DB::raw('COUNT(avaliacao.id_nota) AS qt_avaliacao'))
            ->leftJoin('avaliacao', 'bebida.cd_bebida', '=', 'avaliacao.cd_bebida')
            ->groupBy('bebida.cd_bebida', 'bebida.nm_bebida', 'bebida.ds_preparo', 'bebida.id_tipo', 'bebida.ds_bebida', 'bebida.ds_imagem', 'bebida.created_at', 'bebida.updated_at');

        if ($searchTerm) {
            // Nada de tirar acento do termo aqui: o unaccent() do SQL já roda
            // nos dois lados da comparação e cobre mais casos que um mapa
            // manual de caracteres.
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('unaccent(LOWER(bebida.nm_bebida)) LIKE unaccent(LOWER(?))', ["%{$searchTerm}%"])
                    ->orWhereExists(function ($subQ) use ($searchTerm) {
                        $subQ->select(DB::raw(1))
                            ->from('bebida_ingrediente')
                            ->join('ingrediente', 'bebida_ingrediente.cd_ingrediente', '=', 'ingrediente.cd_ingrediente')
                            ->whereColumn('bebida_ingrediente.cd_bebida', 'bebida.cd_bebida')
                            ->whereRaw('unaccent(LOWER(ingrediente.nm_ingrediente)) LIKE unaccent(LOWER(?))', ["%{$searchTerm}%"]);
                    });
            });
        }

        if ($tipo) {
            $query->where('bebida.id_tipo', $tipo);
        }

        if ($ingredienteId) {
            $query->whereExists(function ($subQ) use ($ingredienteId) {
                $subQ->select(DB::raw(1))
                    ->from('bebida_ingrediente')
                    ->whereColumn('bebida_ingrediente.cd_bebida', 'bebida.cd_bebida')
                    ->where('bebida_ingrediente.cd_ingrediente', $ingredienteId);
            });
        }

        if ($maxIngredientes) {
            // Subconsulta, e não mais um join: juntar bebida_ingrediente ao
            // lado do leftJoin de avaliacao multiplica as linhas, e o
            // COUNT(avaliacao.id_nota) do select passaria a contar cada
            // avaliação uma vez por ingrediente.
            $query->whereRaw(
                '(SELECT COUNT(*) FROM bebida_ingrediente bi WHERE bi.cd_bebida = bebida.cd_bebida) <= ?',
                [$maxIngredientes]
            );
        }

        if ($nota) {
            // Agregado, então HAVING. AVG de bebida sem avaliação é NULL, e
            // NULL >= 3 é falso: quem nunca foi avaliado não passa, que é o
            // comportamento esperado de uma nota mínima.
            $query->havingRaw('AVG(avaliacao.id_nota) >= ?', [$nota]);
        }

        if ($searchTerm) {
            $query->orderByDesc('nota')->orderBy('bebida.cd_bebida', 'desc');
        } else {
            $query->orderBy('bebida.created_at', 'desc')->orderBy('bebida.cd_bebida', 'desc');
        }

        $bebidas = $query->paginate(12)->withQueryString();

        $ingredientes = Ingrediente::usadosEmReceitas()->get();

        return view('search', compact(
            'bebidas', 'searchTerm', 'tipo', 'nota', 'maxIngredientes', 'ingredienteId', 'ingredientes'
        ));
    }

    /**
     * Antes de existirem filtros, digitar "não alcoólica" no campo de texto
     * era o jeito de filtrar por tipo, e o chatbot instruía exatamente isso.
     * A adivinhação some do caminho de consulta e sobrevive só aqui, como
     * tradução, para os links que já circulam não pararem de funcionar.
     *
     * O /u é obrigatório: sem ele [aã] compara byte a byte e nunca casa com o
     * "ã" de "não", que ocupa dois bytes em UTF-8.
     */
    private function traduzirFraseAntiga(string $termo)
    {
        if (! preg_match('/^(n[aã]o\s+)?alco[oó]lica?$/iu', $termo)) {
            return null;
        }

        $tipo = preg_match('/^n[aã]o/iu', $termo) ? TipoBebida::NaoAlcoolica : TipoBebida::Alcoolica;

        return redirect()->route('search', ['tipo' => $tipo->value]);
    }

    private function ingredienteValido($valor): ?int
    {
        if (! is_numeric($valor)) {
            return null;
        }

        return Ingrediente::whereKey((int) $valor)->exists() ? (int) $valor : null;
    }
}
