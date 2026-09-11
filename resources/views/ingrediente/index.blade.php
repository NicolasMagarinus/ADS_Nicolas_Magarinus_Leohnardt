@extends('layouts.app')

@section('titulo', 'Ingredientes')
@section('descricao', 'Todos os ingredientes usados nas receitas do Drinkerito. Escolha um e veja os drinks que dá para preparar.')

@section('content')
<div class="container mt-4">
    <h1 class="h3 mb-1">Ingredientes</h1>
    <p class="text-muted">Escolha um ingrediente e veja todos os drinks que o usam.</p>

    @if($ingredientes->isEmpty())
        <div class="alert alert-light border text-center py-5">
            <p class="mb-0">Nenhum ingrediente no catálogo ainda.</p>
        </div>
    @else
        <div class="row">
            @foreach($ingredientes as $ingrediente)
                <div class="col-6 col-md-3 mb-4">
                    <a href="{{ route('ingrediente.show', $ingrediente->cd_ingrediente) }}"
                       class="text-decoration-none text-dark">
                        <div class="card drink-card h-100 text-center">
                            <img src="@imagem($ingrediente->ds_imagem, 400)"
                                 class="card-img-top" alt="{{ $ingrediente->nm_ingrediente }}" height="180"
                                 style="object-fit: cover;" loading="lazy">
                            <div class="card-body">
                                <h5 class="card-title h6 mb-1">{{ $ingrediente->nm_ingrediente }}</h5>
                                <p class="card-text text-muted small mb-0">
                                    {{ $ingrediente->qt_receitas }} {{ $ingrediente->qt_receitas == 1 ? 'receita' : 'receitas' }}
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-center">
            {{ $ingredientes->links() }}
        </div>
    @endif
</div>
@endsection
