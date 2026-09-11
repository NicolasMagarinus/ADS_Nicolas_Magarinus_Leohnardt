<?php

namespace App\Models;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use Illuminate\Database\Eloquent\Model;

class CadastroBebida extends Model
{
    protected $table = 'cadastro_bebida';

    protected $primaryKey = 'cd_bebida_cadastro';

    protected $fillable = [
        'id_usuario',
        'nm_bebida',
        'id_tipo',
        'ds_bebida',
        'ds_preparo',
        'ds_imagem',
        'id_status',
        'ds_motivo_rejeicao'
    ];

    protected $casts = [
        'id_tipo' => TipoBebida::class,
        'id_status' => StatusCadastro::class,
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function ingredientes()
    {
        return $this->hasMany(CadastroBebidaIngrediente::class, 'cd_bebida_cadastro');
    }
}
