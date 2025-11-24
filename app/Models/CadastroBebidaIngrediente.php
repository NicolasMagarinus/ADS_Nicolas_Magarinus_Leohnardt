<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadastroBebidaIngrediente extends Model
{
    protected $table = 'cadastro_bebida_ingrediente';

    protected $primaryKey = 'cd_bebida_cadastro_ingrediente';

    protected $fillable = [
        'cd_bebida_cadastro',
        'nm_ingrediente',
        'ds_medida',
    ];
}
