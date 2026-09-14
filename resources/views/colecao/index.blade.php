@extends('layouts.app')

@section('titulo', 'Coleções')
@section('descricao', 'Listas de drinks montadas pela comunidade do Drinkerito.')

@section('content')
<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Coleções</li>
        </ol>
    </nav>

    <h1 class="h3 mb-4">Coleções</h1>

    @if($colecoes->isEmpty())
        <div class="alert alert-light border text-center py-5">
            <i class="bi bi-collection fs-2 d-block mb-2 text-muted"></i>
            <p class="mb-0">Nenhuma coleção pública ainda.</p>
        </div>
    @else
        <div class="row">
            @foreach($colecoes as $colecao)
                <div class="col-12 col-md-6 col-lg-4 mb-4">
                    <a href="{{ $colecao->url() }}" class="text-decoration-none text-dark">
                        <div class="card h-100">
                            <div class="card-body">
                                <h2 class="card-title h5 mb-1">{{ $colecao->nm_colecao }}</h2>
                                <p class="card-text text-muted small mb-2">
                                    {{ $colecao->bebidas_count }} drinks · por {{ $colecao->usuario->name }}
                                </p>
                                @if($colecao->ds_colecao)
                                    <p class="card-text small mb-0">{{ Str::limit($colecao->ds_colecao, 100) }}</p>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-center">
            {{ $colecoes->links() }}
        </div>
    @endif
</div>
@endsection
