<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorito extends Model
{
    protected $table = 'favorito';
    protected $primaryKey = 'cd_favorito';

    protected $fillable = [
        'id_usuario',
        'cd_bebida',
    ];

    /**
     * Get the user that owns the favorite.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    /**
     * Get the drink that is favorited.
     */
    public function bebida()
    {
        return $this->belongsTo(Bebida::class, 'cd_bebida');
    }
}
