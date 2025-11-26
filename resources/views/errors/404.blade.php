@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center min-vh-75 align-items-center">
        <div class="col-md-8 text-center">
            <div class="error-page">
                <div class="mb-4">
                    <i class="fas fa-glass-martini-alt" style="font-size: 120px; color: #6c757d; opacity: 0.3;"></i>
                </div>
                
                <h1 class="display-1 fw-bold text-muted mb-3">404</h1>
                <h2 class="mb-4">Ops! Bebida não encontrada</h2>
                
                <p class="lead text-muted mb-4">
                    Parece que essa receita não existe ou foi removida do nosso cardápio.
                </p>
                
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="{{ route('home') }}" class="btn btn-primary btn-lg">
                        <i class="fas fa-home me-2"></i>Voltar para Home
                    </a>
                    <a href="{{ route('random') }}" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-random me-2"></i>Bebida Aleatória
                    </a>
                </div>
                
                <div class="mt-5">
                    <p class="text-muted">
                        Que tal explorar outras receitas incríveis? 🍸
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .min-vh-75 {
        min-height: 75vh;
    }

    .error-page {
        padding: 2rem 0;
    }

    .error-page h1 {
        font-size: 8rem;
        line-height: 1;
    }

    @media (max-width: 768px) {
        .error-page h1 {
            font-size: 5rem;
        }
    }
</style>
@endsection
