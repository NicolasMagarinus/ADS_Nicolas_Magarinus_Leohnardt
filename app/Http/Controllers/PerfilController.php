<?php

namespace App\Http\Controllers;

use App\Models\CadastroBebida;
use App\Models\Colecao;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Só as colunas que a tela usa, e o preparo já cortado no banco: a
        // view mostra 120 caracteres, e modo de preparo é texto livre que pode
        // ser longo. O corte em 200 dá folga para o Str::limit da view
        // continuar decidindo as reticências como decidia antes.
        $arrBebida = CadastroBebida::where('id_usuario', $user->id)
            ->select([
                'cd_bebida_cadastro',
                'nm_bebida',
                'ds_imagem',
                'id_status',
                'ds_motivo_rejeicao',
                'created_at',
                DB::raw('LEFT(ds_preparo, 200) AS ds_preparo'),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $cntFavoritos = DB::table('favorito')
            ->where('id_usuario', $user->id)
            ->count();

        $cntAvaliacoes = DB::table('avaliacao')
            ->where('id_usuario', $user->id)
            ->count();

        $colecoes = Colecao::where('id_usuario', $user->id)
            ->withCount('bebidas')
            ->orderBy('nm_colecao')
            ->get();

        return view('perfil.index', compact(
            'user', 'arrBebida', 'cntFavoritos', 'cntAvaliacoes', 'colecoes'
        ));
    }

    public function atualizar(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ds_avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'name.required' => 'O nome é obrigatório',
            'ds_avatar.image' => 'O arquivo deve ser uma imagem',
            'ds_avatar.mimes' => 'A imagem deve ser nos formatos: JPEG, PNG ou JPG',
            'ds_avatar.max' => 'A imagem não pode ser maior que 5MB',
        ]);

        $user = Auth::user();
        $user->name = $request->name;

        if ($request->hasFile('ds_avatar')) {
            try {
                // Quadrado, diferente do drink: lá o crop é 'limit' em 1024,
                // que preserva a proporção da foto da receita. Avatar precisa
                // fechar no quadro, e gravity 'face' evita cortar a cabeça.
                $upload = Cloudinary::upload($request->file('ds_avatar')->getRealPath(), [
                    'folder' => 'avatares',
                    'transformation' => [
                        'width' => 400,
                        'height' => 400,
                        'crop' => 'fill',
                        'gravity' => 'face',
                        'quality' => 'auto',
                    ],
                ]);

                $user->ds_avatar = $upload->getSecurePath();
            } catch (\Exception $e) {
                Log::error('Erro ao enviar avatar para o Cloudinary: '.$e->getMessage(), [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);

                return back()->withInput()
                    ->withErrors(['ds_avatar' => 'Não foi possível enviar a imagem. Tente novamente.']);
            }
        }

        $user->save();

        return redirect()->route('perfil.index')->with('success', 'Perfil atualizado!');
    }

    public function alterarSenha(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'A senha atual é obrigatória',
            'new_password.required' => 'A nova senha é obrigatória',
            'new_password.confirmed' => 'As senhas não coincidem',
            'new_password.min' => 'A senha deve ter no mínimo 8 caracteres',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Senha atual incorreta',
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso!',
        ]);
    }
}
