@extends('layouts.app')

@section('content')
<div class="container">
    <section class="mb-5">
        <h2 class="mb-4">Mais Populares</h2>
        <div class="row">
            @foreach($arrAvaliacao as $bebida)
                <div class="col-md-3 mb-4">
                    <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100">
                            <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}" class="card-img-top" alt="{{ $bebida->nm_bebida }}" height="200" style="object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title">{{ $bebida->nm_bebida }}</h5>
                                <p class="card-text">
                                    <i class="bi bi-star-fill text-warning"></i>
                                    {{ $bebida->nota }} ({{ $bebida->qt_avaliacao }} avaliações)
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-5">
        <h2 class="mb-4">Ingredientes Populares</h2>
        <div class="row">
            @foreach($arrIngrediente as $ingrediente)
                <div class="col-6 col-md-3 mb-4">
                    <a href="{{ route('search', ['q' => $ingrediente->nm_ingrediente]) }}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100 text-center" style="transition: transform .2s, box-shadow .2s;"
                             onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 18px rgba(0,0,0,.12)';"
                             onmouseout="this.style.transform=''; this.style.boxShadow='';">
                            <img src="{{ $ingrediente->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}" class="card-img-top" alt="{{ $ingrediente->nm_ingrediente }}" height="200" style="object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title">{{ $ingrediente->nm_ingrediente }}</h5>
                                <p class="card-text text-muted small">Utilizado em {{ $ingrediente->qt_utilizado }} receitas</p>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:.72rem;">
                                    <i class="bi bi-search me-1"></i>Ver drinks
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-5">
        <h2 class="mb-4">Adicionados Recentemente</h2>
        <div class="row">
            @foreach($arrRecente as $index => $bebida)
                <div class="col-md-3 mb-4">
                    <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100">
                            <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}" class="card-img-top" alt="{{ $bebida->nm_bebida }}" height="150" style="object-fit: cover;">
                            <div class="card-body">
                                <h6 class="card-title">{{ $bebida->nm_bebida }}</h6>
                            </div>
                        </div>
                    </a>
                </div>
                @if(($index + 1) % 4 == 0 && $index + 1 < 8)
                    </div><div class="row">
                @endif
            @endforeach
        </div>
    </section>
</div>
@endsection