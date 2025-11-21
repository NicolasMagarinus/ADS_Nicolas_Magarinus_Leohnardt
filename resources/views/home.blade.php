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
                            <img src="{{ $bebida->ds_imagem }}" class="card-img-top" alt="{{ $bebida->nm_bebida }}" height="200" style="object-fit: cover;">
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
                    <div class="card drink-card h-100 text-center">
                        <img src="{{ $ingrediente->ds_imagem }}" class="card-img-top" alt="{{ $ingrediente->nm_ingrediente }}" height="200" style="object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title">{{ $ingrediente->nm_ingrediente }}</h5>
                            <p class="card-text">Utilizado em {{ $ingrediente->qt_utilizado }} receitas</p>
                        </div>
                    </div>
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
                            <img src="{{ $bebida->ds_imagem }}" class="card-img-top" alt="{{ $bebida->nm_bebida }}" height="150" style="object-fit: cover;">
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