<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColecaoBebida extends Model
{
    protected $table = 'colecao_bebida';

    protected $primaryKey = 'cd_colecao_bebida';

    protected $fillable = [
        'cd_colecao',
        'cd_bebida',
    ];

    public function colecao()
    {
        return $this->belongsTo(Colecao::class, 'cd_colecao');
    }

    public function bebida()
    {
        return $this->belongsTo(Bebida::class, 'cd_bebida');
    }
}
