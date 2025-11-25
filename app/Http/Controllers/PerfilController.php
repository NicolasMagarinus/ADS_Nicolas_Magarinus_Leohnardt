<?php


namespace App\Http\Controllers;

use App\Models\CadastroBebida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $arrBebida = CadastroBebida::where('id_usuario', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('perfil.index', compact('user', 'arrBebida'));
    }

    public function changePassword(Request $request)
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

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Senha atual incorreta'
            ], 400);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso!'
        ]);
    }
}

