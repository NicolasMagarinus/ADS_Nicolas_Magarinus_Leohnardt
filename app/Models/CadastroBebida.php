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
        'ds_motivo_rejeicao',
        'id_moderador',
        'dt_moderacao',
    ];

    protected $casts = [
        'id_tipo' => TipoBebida::class,
        'id_status' => StatusCadastro::class,
        'dt_moderacao' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function moderador()
    {
        return $this->belongsTo(User::class, 'id_moderador');
    }

    public function ingredientes()
    {
        return $this->hasMany(CadastroBebidaIngrediente::class, 'cd_bebida_cadastro');
    }
}
