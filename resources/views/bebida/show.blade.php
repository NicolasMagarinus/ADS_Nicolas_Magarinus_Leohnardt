@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="drink-card">
                <img src="{{ $bebida->ds_imagem }}" class="card-img-top" alt="{{ $bebida->nm_bebida }}" style="height: 400px; object-fit: cover;">
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="card h-100 drink-card">
                <div class="card-body">
                    <h2 class="card-title">{{ $bebida->nm_bebida }}</h2>

                    <div class="d-flex align-items-center mb-3">
                        @for($i = 1; $i <= 5; $i++)
                            @if($i <= floor($bebida->nota))
                                <i class="fas fa-star text-warning me-1"></i>
                            @elseif($i == floor($bebida->nota) + 1 && $bebida->nota - floor($bebida->nota) >= 0.5)
                                <i class="fas fa-star-half-alt text-warning me-1"></i>
                            @else
                                <i class="far fa-star text-warning me-1"></i>
                            @endif
                        @endfor
                        <span class="text-muted ms-2">
                            {{ number_format($bebida->nota, 1) }} / 5 ({{ $bebida->qt_avaliacao }} avaliações)
                        </span>
                    </div>

                    <h5 class="mt-4">Ingredientes</h5>
                    <ul class="list-unstyled">
                        @foreach($bebida->ingredientes as $ingrediente)
                            <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>{{ $ingrediente["nm_ingrediente"] }} ({{ $ingrediente["ds_medida"] }})</li>
                        @endforeach
                    </ul>

                    <h5 class="mt-4">Modo de Preparo</h5>
                    <div class="instructions">
                        @foreach($bebida->preparo as $passo)
                            <p class="mb-2">{{ trim($passo) }}</p>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary me-2"><i class="fas fa-heart me-1"></i> Favoritar</button>
                        <button class="btn btn-outline-primary"><i class="fas fa-share-alt me-1"></i> Compartilhar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-12">
            <h3 class="mb-4">Avaliações</h3>

            @forelse($bebida->avaliacoes as $avaliacao)
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>{{ $avaliacao->nm_usuario }}</strong>

                    <div class="mt-1">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star {{ $i <= $avaliacao->nota ? 'text-warning' : 'text-secondary' }}"></i>
                        @endfor
                    </div>

                    @if($avaliacao->ds_avaliacao)
                        <p class="mt-2 mb-0">{{ $avaliacao->ds_avaliacao }}</p>
                    @endif
                </div>

                @if(Auth::id() === $avaliacao->id_usuario)
                    <div class="text-end">

                        <button class="btn btn-sm btn-outline-primary mb-2">
                            Editar
                        </button>

                        <form action="{{ route('avaliacao.destroy', [$bebida->cd_bebida, $avaliacao->cd_avaliacao]) }}"
                              method="POST"
                              onsubmit="return confirm('Deseja realmente excluir sua avaliação?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                Excluir
                            </button>
                        </form>

                    </div>
                @endif
            </div>
        </div>
    </div>
@empty
    <p class="text-muted">Ainda não há avaliações.</p>
@endforelse

            @auth
                @php
                    $userRating = $bebida->avaliacoes->firstWhere('user_id', Auth::id());
                @endphp

                <div class="card p-4 mt-4">
                    <form action="{{ route('avaliacao.store', $bebida->cd_bebida) }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label fw-bold">Sua avaliação</label>
                            <div class="star-rating">
                                @for($i = 5; $i >= 1; $i--)
                                    <input type="radio" name="id_nota" value="{{ $i }}" id="star{{ $i }}"
                                           {{ $userRating && $userRating->nota == $i ? 'checked' : '' }} required>
                                    <label for="star{{ $i }}" title="{{ $i }} estrela{{ $i > 1 ? 's' : '' }}"></label>
                                @endfor
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Comentário (opcional)</label>
                            <textarea name="ds_avaliacao" class="form-control" rows="3"
                                      placeholder="O que achou dessa bebida?">{{ $userRating?->body ?? '' }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Enviar Avaliação</button>
                    </form>
                </div>
            @else
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Faça <a href="{{ route('login') }}">login</a> para avaliar esta bebida.
                </div>
            @endauth
        </div>
    </div>
</div>
@endsection