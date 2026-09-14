@extends('layouts.app')

@section('titulo', $colecao->nm_colecao)

@section('content')
<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
            <li class="breadcrumb-item">Coleções</li>
            <li class="breadcrumb-item active" aria-current="page">{{ $colecao->nm_colecao }}</li>
        </ol>
    </nav>

    <div class="mb-4">
        <h1 class="h3 mb-1">{{ $colecao->nm_colecao }}</h1>
        @if($colecao->ds_colecao)
            <p class="text-muted mb-1">{{ $colecao->ds_colecao }}</p>
        @endif
        <p class="text-muted small mb-0">
            {{ $bebidas->total() }} {{ $bebidas->total() === 1 ? 'drink' : 'drinks' }}
            · por {{ $colecao->usuario->name }}
            @unless($colecao->id_publica)
                <span class="badge bg-secondary ms-1"><i class="bi bi-lock-fill me-1"></i>Privada</span>
            @endunless
        </p>
    </div>

    @if($bebidas->isEmpty())
        <div class="alert alert-light border text-center py-5">
            <i class="bi bi-collection fs-2 d-block mb-2 text-muted"></i>
            <p class="mb-0">Esta coleção ainda não tem nenhum drink.</p>
        </div>
    @else
        <div class="row">
            @foreach($bebidas as $bebida)
                <div class="col-6 col-md-3 mb-4">
                    <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100">
                            <img src="@imagem($bebida->ds_imagem, 400)"
                                 class="card-img-top" alt="{{ $bebida->nm_bebida }}" height="200"
                                 style="object-fit: cover;" loading="lazy">
                            <div class="card-body">
                                <h5 class="card-title h6">{{ $bebida->nm_bebida }}</h5>
                                <p class="card-text text-muted small mb-0">
                                    @if($bebida->qt_avaliacao > 0)
                                        <i class="bi bi-star-fill text-warning"></i> {{ $bebida->nota }}
                                        <span class="ms-1">({{ $bebida->qt_avaliacao }})</span>
                                    @else
                                        Sem avaliações
                                    @endif
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-center">
            {{ $bebidas->links() }}
        </div>
    @endif
</div>
@endsection
