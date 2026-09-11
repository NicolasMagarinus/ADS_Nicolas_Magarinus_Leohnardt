@extends('layouts.app')

@section('titulo', $ingrediente->nm_ingrediente)
@section('descricao', 'Drinks que levam '.$ingrediente->nm_ingrediente.'. Veja as receitas, as notas e o modo de preparo.')
@if($ingrediente->ds_imagem)
    @section('og_imagem', $ingrediente->ds_imagem)
@endif
@if($bebidas->total() === 0)
    {{-- Página sem receita nenhuma é conteúdo fino: existe para quem digitar a URL, mas não vai para o índice. --}}
    @section('robots', 'noindex')
@endif

@section('content')
<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
            <li class="breadcrumb-item"><a href="{{ route('ingrediente.index') }}">Ingredientes</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $ingrediente->nm_ingrediente }}</li>
        </ol>
    </nav>

    <div class="d-flex align-items-center gap-3 mb-4">
        @if($ingrediente->ds_imagem)
            <img src="{{ $ingrediente->ds_imagem }}" alt="{{ $ingrediente->nm_ingrediente }}"
                 class="rounded" width="96" height="96" style="object-fit: cover;" loading="lazy">
        @endif
        <div>
            <h1 class="h3 mb-1">{{ $ingrediente->nm_ingrediente }}</h1>
            <p class="text-muted mb-0">
                {{ $bebidas->total() }} {{ $bebidas->total() === 1 ? 'receita' : 'receitas' }} com este ingrediente
            </p>
        </div>
    </div>

    @if($bebidas->isEmpty())
        <div class="alert alert-light border text-center py-5">
            <i class="bi bi-cup-straw fs-2 d-block mb-2 text-muted"></i>
            <p class="mb-1">Nenhuma receita do catálogo usa {{ $ingrediente->nm_ingrediente }} ainda.</p>
            <a href="{{ route('ingrediente.index') }}" class="btn btn-sm btn-outline-dark mt-2">Ver outros ingredientes</a>
        </div>
    @else
        <div class="row">
            @foreach($bebidas as $bebida)
                <div class="col-6 col-md-3 mb-4">
                    <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100">
                            <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}"
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
