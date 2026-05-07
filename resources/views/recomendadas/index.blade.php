@extends('layouts.app')

@section('content')
<div class="container mt-4">

    {{-- Page header --}}
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-1">
                <i class="fas fa-magic me-2 text-primary"></i>Bebidas Recomendadas
            </h2>
            <p class="text-muted mb-0">Selecionadas com base nos ingredientes das suas bebidas favoritas.</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('favoritos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-heart me-1"></i> Meus Favoritos
            </a>
        </div>
    </div>

    @if(!$hasFavorites)
        {{-- Empty state: no favorites yet --}}
        <div class="text-center py-5">
            <i class="fas fa-heart" style="font-size: 5rem; color: #e0c8ff;"></i>
            <h4 class="mt-4 fw-semibold">Você ainda não tem favoritos</h4>
            <p class="text-muted mb-4">
                Favorite pelo menos uma bebida para que possamos recomendar receitas com base no seu gosto!
            </p>
            <a href="{{ route('search') }}" class="btn btn-primary btn-lg">
                <i class="bi bi-compass me-2"></i>Explorar Bebidas
            </a>
        </div>

    @elseif($recomendadas->isEmpty())
        {{-- Has favorites but no recommendations found --}}
        <div class="text-center py-5">
            <i class="bi bi-emoji-neutral" style="font-size: 5rem; color: #ccc;"></i>
            <h4 class="mt-4 fw-semibold">Não encontramos recomendações ainda</h4>
            <p class="text-muted mb-4">
                Ainda não há bebidas similares disponíveis. Tente favoritar mais bebidas variadas!
            </p>
            <a href="{{ route('search') }}" class="btn btn-primary btn-lg">
                <i class="bi bi-compass me-2"></i>Explorar Bebidas
            </a>
        </div>

    @else
        {{-- Top ingredients used as basis --}}
        @if($topIngredientes->isNotEmpty())
            <div class="mb-4">
                <p class="text-muted mb-2 small fw-semibold text-uppercase" style="letter-spacing:.05em;">
                    <i class="bi bi-graph-up me-1"></i>Ingredientes mais usados nos seus favoritos
                </p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($topIngredientes as $ingrediente)
                        <span class="badge rounded-pill" style="background: linear-gradient(135deg, #6f42c1, #0d6efd); font-size: .8rem; padding: .45em .85em;">
                            {{ $ingrediente->nm_ingrediente }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recommendations grid --}}
        <div class="row">
            @foreach($recomendadas as $bebida)
                <div class="col-sm-6 col-md-4 col-lg-3 mb-4">
                    <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100 shadow-sm border-0" style="border-radius: 14px; transition: transform .2s, box-shadow .2s;"
                             onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,.12)';"
                             onmouseout="this.style.transform=''; this.style.boxShadow='';">
                            <div style="overflow: hidden; border-radius: 14px 14px 0 0; height: 190px;">
                                <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}"
                                     class="w-100 h-100"
                                     alt="{{ $bebida->nm_bebida }}"
                                     style="object-fit: cover; transition: transform .3s;"
                                     onmouseover="this.style.transform='scale(1.05)';"
                                     onmouseout="this.style.transform='';">
                            </div>
                            <div class="card-body pb-3">
                                <h5 class="card-title fw-semibold mb-1" style="font-size: .95rem;">{{ $bebida->nm_bebida }}</h5>
                                <p class="card-text text-muted mb-1" style="font-size: .82rem;">
                                    <i class="bi bi-star-fill text-warning me-1"></i>
                                    {{ $bebida->nota }} · {{ $bebida->qt_avaliacao }} {{ $bebida->qt_avaliacao == 1 ? 'avaliação' : 'avaliações' }}
                                </p>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:.72rem;">
                                    {{ $bebida->match_count }} ingrediente{{ $bebida->match_count == 1 ? '' : 's' }} em comum
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <p class="text-center text-muted mt-2 small">
            <i class="bi bi-info-circle me-1"></i>
            Mostrando até 8 bebidas. As bebidas que você já favoritou não aparecem aqui.
        </p>
    @endif

</div>
@endsection
