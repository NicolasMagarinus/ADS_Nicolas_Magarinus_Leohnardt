<?php

namespace App\Http\Controllers;

use App\Models\CadastroBebida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}
