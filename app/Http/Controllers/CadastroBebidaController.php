<?php


namespace App\Http\Controllers;

use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\Ingrediente;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'ds_preparo' => 'required|string',
            'ds_imagem' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'ingredientes' => 'required|array|min:1',
            'ingredientes.*.nm_ingrediente' => 'required|string|max:255',
            'ingredientes.*.ds_medida' => 'nullable|string|max:255',
        ], [
            'ds_imagem.image' => 'O arquivo deve ser uma imagem',
            'ds_imagem.mimes' => 'A imagem deve ser nos formatos: JPEG, PNG ou JPG',
            'ds_imagem.max' => 'A imagem não pode ser maior que 5MB',
        ]);

        DB::transaction(function () use ($request) {
            $imageUrl = null;

            // Upload image to Cloudinary if provided
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
                'ds_preparo' => $request->ds_preparo,
                'ds_imagem' => $imageUrl,
                'id_status' => 0
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

    public function index()
    {
        if (!Auth::user()->id_admin) {
            abort(403, 'Acesso não autorizado.');
        }

        $bebidas = CadastroBebida::where('id_status', 0)
            ->with('ingredientes')
            ->orderBy('created_at', 'asc')
            ->get();

        return view('cadastro_bebida.index', compact('bebidas'));
    }

    public function approve($id)
    {
        if (!Auth::user()->id_admin) {
            abort(403, 'Acesso não autorizado.');
        }

        $cadastro = CadastroBebida::with('ingredientes')->findOrFail($id);

        DB::transaction(function () use ($cadastro) {
            $bebida = Bebida::create([
                'nm_bebida' => $cadastro->nm_bebida,
                'ds_preparo' => $cadastro->ds_preparo,
                'ds_imagem' => $cadastro->ds_imagem,
                'id_tipo' => 1,
                'ds_bebida' => 'Bebida cadastrada por usuário'
            ]);

            foreach ($cadastro->ingredientes as $item) {
                $ingrediente = Ingrediente::firstOrCreate(
                    ['nm_ingrediente' => $item->nm_ingrediente]
                );

                BebidaIngrediente::create([
                    'cd_bebida' => $bebida->cd_bebida,
                    'cd_ingrediente' => $ingrediente->cd_ingrediente,
                    'ds_medida' => $item->ds_medida,
                ]);
            }

            $cadastro->update(['id_status' => 1]);
        });

        return redirect()->route('admin.bebidas.index')->with('success', 'Bebida aprovada com sucesso!');
    }

    public function reject(Request $request, $id)
    {
        if (!Auth::user()->id_admin) {
            abort(403, 'Acesso não autorizado.');
        }

        $request->validate([
            'motivo_rejeicao' => 'required|string|max:1000',
        ]);

        $cadastro = CadastroBebida::findOrFail($id);
        $cadastro->update([
            'id_status' => 2,
            'ds_motivo_rejeicao' => $request->motivo_rejeicao,
        ]);

        return redirect()->route('admin.bebidas.index')->with('success', 'Bebida rejeitada.');
    }

    public function searchIngredients(Request $request)
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
