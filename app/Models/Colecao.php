<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Colecao extends Model
{
    protected $table = 'colecao';

    protected $primaryKey = 'cd_colecao';

    protected $fillable = [
        'id_usuario',
        'nm_colecao',
        'ds_colecao',
        'id_publica',
    ];

    protected $casts = [
        'id_publica' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function bebidas()
    {
        return $this->belongsToMany(Bebida::class, 'colecao_bebida', 'cd_colecao', 'cd_bebida');
    }

    /**
     * Parte legível da URL, derivada do nome.
     *
     * Não é coluna de propósito: guardada, precisaria ser ressincronizada em
     * todo rename, e slug velho no banco é o defeito que a URL híbrida existe
     * para não ter. Como quem resolve a coleção é o id, recalcular sempre
     * nunca erra.
     */
    public function getSlugAttribute(): string
    {
        return Str::slug($this->nm_colecao);
    }

    /**
     * O parâmetro canônico da rota: "12-drinks-de-verao".
     *
     * Nome sem letra nenhuma ("???") deixa o Str::slug vazio, e aí a URL é só
     * o id — "12-" teria um hífen solto no fim.
     */
    public function parametroUrl(): string
    {
        $slug = $this->slug;

        return $slug === '' ? (string) $this->cd_colecao : $this->cd_colecao.'-'.$slug;
    }

    /**
     * URL absoluta canônica. Depende da rota colecao.show (Tarefa 3).
     */
    public function url(): string
    {
        return route('colecao.show', $this->parametroUrl());
    }
}
