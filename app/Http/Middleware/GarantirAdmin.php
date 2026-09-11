<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fecha o painel de moderação. Aplicado no grupo de rotas 'admin', para que
 * uma rota nova nasça protegida sem depender de alguém repetir a conferência
 * dentro do método.
 */
class GarantirAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->id_admin) {
            abort(403, 'Acesso não autorizado.');
        }

        return $next($request);
    }
}
