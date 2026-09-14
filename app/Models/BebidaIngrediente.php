<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BebidaIngrediente extends Model
{
    protected $table = 'bebida_ingrediente';

    protected $primaryKey = 'cd_bebida_ingrediente';

    protected $fillable = [
        'cd_bebida',
        'cd_ingrediente',
        'ds_medida',
    ];

    public function bebida()
    {
        return $this->belongsTo(Bebida::class, 'cd_bebida');
    }

    public function ingrediente()
    {
        return $this->belongsTo(Ingrediente::class, 'cd_ingrediente');
    }
}
