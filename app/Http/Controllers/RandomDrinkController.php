<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bebida;

class RandomDrinkController extends Controller
{
    public function index()
    {
        $random = Bebida::getBebida();

        if (!$random) {
            abort(404);
        }

        return view('random')->with('bebida', $random);
    }
}
