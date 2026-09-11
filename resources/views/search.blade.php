@extends('layouts.app')

@section('titulo', $searchTerm ? 'Busca por “'.$searchTerm.'”' : 'Busca')
@section('descricao', 'Encontre drinks pelo nome ou por um ingrediente que você tem em casa.')

@section('content')
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-center mb-3">Explorar Bebidas</h2>
            
            <form method="GET" action="{{ route('search') }}" class="mb-4">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="input-group input-group-lg">
                            <input type="text" name="q" class="form-control"
                                   placeholder="Nome da bebida ou ingrediente..." value="{{ $searchTerm }}">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center mt-3 g-2">
                    <div class="col-6 col-md-2">
                        <select name="tipo" class="form-select" aria-label="Tipo">
                            <option value="">Alcoólica e sem álcool</option>
                            @foreach(App\Enums\TipoBebida::cases() as $opcao)
                                <option value="{{ $opcao->value }}" @selected($tipo === $opcao)>{{ $opcao->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="nota" class="form-select" aria-label="Nota mínima">
                            <option value="">Qualquer nota</option>
                            <option value="3" @selected($nota === 3)>Nota 3 ou mais</option>
                            <option value="4" @selected($nota === 4)>Nota 4 ou mais</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="max_ingredientes" class="form-select" aria-label="Número de ingredientes">
                            <option value="">Qualquer tamanho</option>
                            <option value="3" @selected($maxIngredientes === 3)>Até 3 ingredientes</option>
                            <option value="5" @selected($maxIngredientes === 5)>Até 5 ingredientes</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="ingrediente" class="form-select" aria-label="Ingrediente">
                            <option value="">Qualquer ingrediente</option>
                            @foreach($ingredientes as $opcao)
                                <option value="{{ $opcao->cd_ingrediente }}" @selected($ingredienteId === $opcao->cd_ingrediente)>
                                    {{ $opcao->nm_ingrediente }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-grid">
                        <button class="btn btn-outline-dark" type="submit">Filtrar</button>
                    </div>
                </div>

                @if($tipo || $nota || $maxIngredientes || $ingredienteId || $searchTerm)
                    <div class="row justify-content-center mt-2">
                        <div class="col-auto">
                            <a href="{{ route('search') }}" class="small text-muted">
                                <i class="bi bi-x-circle me-1"></i>Limpar filtros
                            </a>
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>

    @if($searchTerm || $tipo || $nota || $maxIngredientes || $ingredienteId)
        <div class="row mb-3">
            <div class="col-12">
                <p class="text-muted text-center">
                    Mostrando <strong>{{ $bebidas->total() }}</strong> {{ $bebidas->total() === 1 ? 'resultado' : 'resultados' }}
                    @if($searchTerm) para: <strong>"{{ $searchTerm }}"</strong> @endif
                </p>
            </div>
        </div>
    @endif

    <div class="row">
        @forelse($bebidas as $bebida)
            <div class="col-md-6 col-lg-3 mb-4">
                <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                    <div class="card h-100 drink-card">
                        <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}" 
                             class="card-img-top" alt="{{ $bebida->nm_bebida }}">
                        <div class="card-body">
                            <h5 class="card-title">{{ $bebida->nm_bebida }}</h5>
                            <p class="card-text">
                                <i class="bi bi-star-fill text-warning"></i>
                                {{ $bebida->nota }} ({{ $bebida->qt_avaliacao }} {{ $bebida->qt_avaliacao === 1 ? 'avaliação' : 'avaliações' }})
                            </p>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <i class="bi bi-search" style="font-size: 4rem; color: #ccc;"></i>
                <p class="text-muted mt-3 fs-5">
                    @if($searchTerm)
                        Nenhuma bebida encontrada para "{{ $searchTerm }}"
                    @else
                        Nenhuma bebida disponível no momento
                    @endif
                </p>
                @if($searchTerm)
                    <a href="{{ route('search') }}" class="btn btn-outline-primary mt-2">
                        <i class="bi bi-arrow-left"></i> Ver todas as bebidas
                    </a>
                @endif
            </div>
        @endforelse
    </div>

    @if($bebidas->hasPages())
        <div class="row mt-4">
            <div class="col-12 d-flex justify-content-center">
                {{ $bebidas->links() }}
            </div>
        </div>
    @endif
</div>
@endsection