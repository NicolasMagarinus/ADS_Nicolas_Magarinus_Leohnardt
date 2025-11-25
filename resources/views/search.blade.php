@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-center mb-3">Explorar Bebidas</h2>
            
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="alert alert-info text-center" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Dica de busca:</strong> Você pode pesquisar por <strong>nome de bebidas</strong>, <strong>ingredientes</strong>, 
                        <strong>alcoólica</strong> ou <strong>não alcoólica</strong>.
                    </div>
                </div>
            </div>
            
            <form method="GET" action="{{ route('search') }}" class="mb-4">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="input-group input-group-lg">
                            <input type="text" name="q" class="form-control" 
                                   placeholder="Pesquisar..." value="{{ $searchTerm }}">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($searchTerm)
        <div class="row mb-3">
            <div class="col-12">
                <p class="text-muted text-center">
                    Mostrando <strong>{{ $bebidas->total() }}</strong> {{ $bebidas->total() === 1 ? 'resultado' : 'resultados' }} para: <strong>"{{ $searchTerm }}"</strong>
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