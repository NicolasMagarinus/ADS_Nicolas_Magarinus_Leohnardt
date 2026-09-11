<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioIngrediente extends Model
{
    protected $table = 'usuario_ingrediente';

    protected $primaryKey = 'cd_usuario_ingrediente';

    protected $fillable = [
        'id_usuario',
        'cd_ingrediente',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function ingrediente()
    {
        return $this->belongsTo(Ingrediente::class, 'cd_ingrediente');
    }
}
