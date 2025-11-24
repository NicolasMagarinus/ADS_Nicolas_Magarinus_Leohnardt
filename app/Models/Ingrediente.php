<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingrediente extends Model
{
    protected $table = 'ingrediente';

    protected $primaryKey = 'cd_ingrediente';

    protected $fillable = ['nm_ingrediente'];
}
