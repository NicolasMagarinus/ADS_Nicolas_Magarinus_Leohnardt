<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Avaliacao extends Model
{
    protected $table = 'avaliacao';

    protected $primaryKey = 'cd_avaliacao';

    protected $fillable = [
        'ds_avaliacao',
        'id_usuario',
        'id_nota',
        'cd_bebida',
        'dt_avaliacao',
    ];

    public function bebida()
    {
        return $this->belongsTo(Bebida::class, 'cd_bebida');
    }

    public function user()
    {
        // users é a única tabela com PK 'id', e a coluna daqui é id_usuario.
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
