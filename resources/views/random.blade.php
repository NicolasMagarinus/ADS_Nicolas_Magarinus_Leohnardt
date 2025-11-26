@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center mb-5">
        <div class="col-md-8 text-center">
            <button class="btn btn-random btn-lg mb-4" onclick="location.reload();">
                <i class="fas fa-random me-2"></i>Sortear Nova Bebida
            </button>
            <p class="text-muted">Clique para descobrir uma bebida aleatória</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="drink-card">
                <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}" class="card-img-top" alt="{{ $bebida->nm_bebida }}" style="height: 400px; object-fit: cover;">
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
                            {{ number_format($bebida->nota, 1) }} ({{ $bebida->qt_avaliacao }} avaliações)
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
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#shareModal"><i class="fas fa-share-alt me-1"></i> Compartilhar</button>
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
                        <div class="d-flex justify-content-between align-items-start">
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
                            <small class="text-muted">{{ $avaliacao->created_at }}</small>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-muted">Ainda não há avaliações. Seja o primeiro!</p>
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
    </div>

    <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareModalLabel">
                        <i class="fas fa-share-alt me-2"></i>Compartilhar Receita
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Compartilhe esta receita com seus amigos!</p>
                    
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="shareLink" value="{{ route('bebida.show', $bebida->cd_bebida) }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyShareLink(event)">
                            <i class="fas fa-copy me-1"></i>Copiar
                        </button>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="https://wa.me/?text={{ urlencode("Acabei de encontrar uma receita de " . $bebida->nm_bebida . " e recomendo demais!\nSe você curte drinks saborosos e fáceis de fazer, precisa testar essa!\nVale cada gole! 🍸✨\n\n" . route('bebida.show', $bebida->cd_bebida)) }}" 
                           target="_blank" 
                           class="btn btn-success">
                            <i class="fab fa-whatsapp me-2"></i>Compartilhar no WhatsApp
                        </a>
                        
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('bebida.show', $bebida->cd_bebida)) }}" 
                           target="_blank" 
                           class="btn"
                           style="background-color: #3c5a99; color: white;">
                            <i class="fab fa-facebook me-2"></i>Compartilhar no Facebook
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyShareLink(event) {
    const linkInput = document.getElementById('shareLink');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(linkInput.value).then(() => {
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copiado!';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar o link. Por favor, copie manualmente.');
    });
}
</script>
@endsection