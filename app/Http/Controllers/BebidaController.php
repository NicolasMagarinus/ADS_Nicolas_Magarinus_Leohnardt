<?php

namespace App\Http\Controllers;

use App\Models\Avaliacao;
use App\Models\Bebida;
use Illuminate\Http\Request;

class BebidaController extends Controller
{

    public function show($cd_bebida = null)
    {
        $bebida = Bebida::getBebida($cd_bebida);

        dd($bebida);

        return view('bebida.show', compact('bebida'));
    }
}
