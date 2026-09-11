<?php


namespace App\Http\Controllers;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\Ingrediente;
use App\Notifications\BebidaModerada;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CadastroBebidaController extends Controller
{
    public function create()
    {
        return view('cadastro_bebida.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nm_bebida' => 'required|string|max:255',
            'id_tipo' => ['required', Rule::enum(TipoBebida::class)],
            'ds_bebida' => 'nullable|string|max:1000',
            'ds_preparo' => 'required|string',
            'ds_imagem' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'ingredientes' => 'required|array|min:1',
            'ingredientes.*.nm_ingrediente' => 'required|string|max:255',
            'ingredientes.*.ds_medida' => 'nullable|string|max:255',
        ], [
            'id_tipo.required' => 'Informe se a bebida é alcoólica ou não alcoólica',
            'id_tipo.in' => 'Informe se a bebida é alcoólica ou não alcoólica',
            'ds_imagem.image' => 'O arquivo deve ser uma imagem',
            'ds_imagem.mimes' => 'A imagem deve ser nos formatos: JPEG, PNG ou JPG',
            'ds_imagem.max' => 'A imagem não pode ser maior que 5MB',
        ]);

        DB::transaction(function () use ($request) {
            $imageUrl = null;

            if ($request->hasFile('ds_imagem')) {
                try {
                    $uploadedFile = Cloudinary::upload($request->file('ds_imagem')->getRealPath(), [
                        'folder' => 'bebidas',
                        'transformation' => [
                            'width' => 1024,
                            'height' => 1024,
                            'crop' => 'limit',
                            'quality' => 'auto'
                        ]
                    ]);
                    $imageUrl = $uploadedFile->getSecurePath();
                } catch (\Exception $e) {
                    throw new \Exception('Erro ao fazer upload da imagem: ' . $e->getMessage());
                }
            }

            $cadastro = CadastroBebida::create([
                'id_usuario' => Auth::id(),
                'nm_bebida' => $request->nm_bebida,
                'id_tipo' => $request->id_tipo,
                'ds_bebida' => $request->ds_bebida,
                'ds_preparo' => $request->ds_preparo,
                'ds_imagem' => $imageUrl,
                'id_status' => StatusCadastro::Pendente,
            ]);

            foreach ($request->ingredientes as $ingrediente) {
                CadastroBebidaIngrediente::create([
                    'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
                    'nm_ingrediente' => $ingrediente['nm_ingrediente'],
                    'ds_medida' => $ingrediente['ds_medida'] ?? 'a gosto',
                ]);
            }
        });

        return redirect()->route('perfil.index')->with('success', 'Bebida enviada para aprovação!');
    }

    /** Aba do painel → estado do cadastro. */
    private const ABAS = [
        'pendentes' => StatusCadastro::Pendente,
        'aprovadas' => StatusCadastro::Aprovada,
        'rejeitadas' => StatusCadastro::Rejeitada,
    ];

    public function index(Request $request)
    {
        // Valor fora da lista cai na fila em vez de dar 404: é URL que a
        // pessoa edita à mão.
        $aba = array_key_exists($request->get('status'), self::ABAS)
            ? $request->get('status')
            : 'pendentes';

        $status = self::ABAS[$aba];

        // Nada sai da fila até um admin decidir, então a de pendentes é a
        // aba que mais cresce sem limite.
        //
        // O usuario entra no with() junto dos ingredientes: a view mostra quem
        // enviou cada receita, e sem isso era uma consulta por linha.
        $bebidas = CadastroBebida::where('id_status', $status)
            ->with(['ingredientes', 'usuario'])
            // Pendente é fila: a mais antiga primeiro, que é a que espera há
            // mais tempo. Já decidida é histórico, e histórico se lê de trás
            // para a frente.
            ->when($status === StatusCadastro::Pendente,
                fn ($q) => $q->orderBy('created_at', 'asc'),
                fn ($q) => $q->orderBy('updated_at', 'desc'))
            ->paginate(10)
            ->withQueryString();

        $contagens = CadastroBebida::selectRaw('id_status, COUNT(*) AS total')
            ->groupBy('id_status')
            ->pluck('total', 'id_status');

        return view('cadastro_bebida.index', [
            'bebidas' => $bebidas,
            'aba' => $aba,
            'abas' => self::ABAS,
            'contagens' => $contagens,
        ]);
    }

    public function aprovar($id)
    {
        $cadastro = CadastroBebida::with('ingredientes')->findOrFail($id);
        $cdBebida = null;

        DB::transaction(function () use ($cadastro, &$cdBebida) {
            $bebida = Bebida::create([
                'nm_bebida' => $cadastro->nm_bebida,
                'ds_preparo' => $cadastro->ds_preparo,
                'ds_imagem' => $cadastro->ds_imagem,
                'id_tipo' => $cadastro->id_tipo,
                'ds_bebida' => $cadastro->ds_bebida ?: 'Bebida cadastrada por usuário'
            ]);

            foreach ($cadastro->ingredientes as $item) {
                $ingrediente = Ingrediente::normalizar($item->nm_ingrediente);

                // Dois nomes diferentes no cadastro podem normalizar para o
                // mesmo ingrediente; o vínculo é único por (bebida, ingrediente).
                BebidaIngrediente::updateOrCreate(
                    [
                        'cd_bebida' => $bebida->cd_bebida,
                        'cd_ingrediente' => $ingrediente->cd_ingrediente,
                    ],
                    ['ds_medida' => $item->ds_medida]
                );
            }

            $cadastro->update(['id_status' => StatusCadastro::Aprovada]);
            $cdBebida = $bebida->cd_bebida;
        });

        // A home guarda os rankings por dez minutos. Sem limpar aqui, a bebida
        // recém-aprovada sumiria da tela por todo esse tempo — justo quando o
        // moderador vai conferir se a aprovação funcionou.
        foreach (HomeController::CHAVES_CACHE as $chave) {
            Cache::forget($chave);
        }

        $this->avisarAutor($cadastro, $cdBebida);

        return redirect()->route('admin.bebidas.index')->with('success', 'Bebida aprovada com sucesso!');
    }

    public function rejeitar(Request $request, $id)
    {
        $request->validate([
            'motivo_rejeicao' => 'required|string|max:1000',
        ]);

        $cadastro = CadastroBebida::findOrFail($id);
        $cadastro->update([
            'id_status' => StatusCadastro::Rejeitada,
            'ds_motivo_rejeicao' => $request->motivo_rejeicao,
        ]);

        $this->avisarAutor($cadastro);

        return redirect()->route('admin.bebidas.index')->with('success', 'Bebida rejeitada.');
    }

    /**
     * Avisa quem enviou a receita do desfecho da moderação.
     *
     * Chamado sempre DEPOIS do commit: dentro da transação, um SMTP lento a
     * seguraria aberta e uma exceção do mailer desfaria uma aprovação que
     * estava correta.
     *
     * E falha de envio não pode derrubar a moderação. Quando isto roda a
     * bebida já entrou no catálogo; devolver 500 ao admin o faria tentar
     * aprovar de novo algo que já está aprovado.
     *
     * O autor sempre existe: cadastro_bebida.id_usuario é NOT NULL e a FK
     * apaga em cascata, então apagar o usuário leva os cadastros dele junto.
     */
    private function avisarAutor(CadastroBebida $cadastro, ?int $cdBebida = null): void
    {
        try {
            $cadastro->usuario->notify(new BebidaModerada($cadastro, $cdBebida));
        } catch (\Throwable $e) {
            Log::error('Falha ao avisar o autor sobre a moderação: '.$e->getMessage(), [
                'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
                'id_usuario' => $cadastro->id_usuario,
                'exception' => $e,
            ]);
        }
    }

    public function buscarIngredientes(Request $request)
    {
        $query = $request->get('q');
        $ingredientes = Ingrediente::where('nm_ingrediente', 'ilike', "%{$query}%")
            ->limit(20)
            ->get(['cd_ingrediente', 'nm_ingrediente']);

        $results = $ingredientes->map(function ($item) {
            return [
                'id' => $item->nm_ingrediente,
                'text' => $item->nm_ingrediente
            ];
        });

        return response()->json(['results' => $results]);
    }
}
