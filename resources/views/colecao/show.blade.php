@extends('layouts.app')

@section('titulo', $colecao->nm_colecao)

@section('descricao', $colecao->ds_colecao
    ?: $bebidas->total().' drinks reunidos na coleção '.$colecao->nm_colecao.', no Drinkerito.')

@if($bebidas->isNotEmpty() && $bebidas->first()->ds_imagem)
    {{-- Sem transformação: o WhatsApp e o Facebook querem a imagem grande. --}}
    @section('og_imagem', $bebidas->first()->ds_imagem)
@endif

@if(! $colecao->id_publica || $bebidas->total() < \App\Http\Controllers\ColecaoController::MINIMO_PARA_INDICE)
    {{-- Privada nunca vai ao índice; pública magra é conteúdo fino, e o
         tratamento é o mesmo que ingrediente/show dá à página sem receita. --}}
    @section('robots', 'noindex')
@endif

@section('content')
<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
            <li class="breadcrumb-item"><a href="{{ route('colecao.index') }}">Coleções</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $colecao->nm_colecao }}</li>
        </ol>
    </nav>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif

    <div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">{{ $colecao->nm_colecao }}</h1>
            @if($colecao->ds_colecao)
                <p class="text-muted mb-1">{{ $colecao->ds_colecao }}</p>
            @endif
            <p class="text-muted small mb-0">
                {{ $bebidas->total() }} {{ $bebidas->total() === 1 ? 'drink' : 'drinks' }}
                · por {{ $colecao->usuario->name }}
                @unless($colecao->id_publica)
                    <span class="badge bg-secondary ms-1"><i class="bi bi-lock-fill me-1"></i>Privada</span>
                @endunless
            </p>
        </div>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#shareModal">
            <i class="fas fa-share-alt me-1"></i> Compartilhar
        </button>
    </div>

    @if($bebidas->isEmpty())
        <div class="alert alert-light border text-center py-5">
            <i class="bi bi-collection fs-2 d-block mb-2 text-muted"></i>
            <p class="mb-0">Esta coleção ainda não tem nenhum drink.</p>
        </div>
    @else
        <div class="row">
            @foreach($bebidas as $bebida)
                <div class="col-6 col-md-3 mb-4">
                    <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100">
                            <img src="@imagem($bebida->ds_imagem, 400)"
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

    {{-- Segue o padrão do #shareModal de bebida/show.blade.php, adaptado
         para a URL da coleção — é a página feita para ser compartilhada. --}}
    <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareModalLabel">
                        <i class="fas fa-share-alt me-2"></i>Compartilhar Coleção
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Compartilhe esta coleção com seus amigos!</p>

                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="shareLink" value="{{ url()->current() }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyShareLink(event)">
                            <i class="fas fa-copy me-1"></i>Copiar
                        </button>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="https://wa.me/?text={{ urlencode('Dá uma olhada na coleção "'.$colecao->nm_colecao.'" que eu montei no Drinkerito!'."\n\n".url()->current()) }}"
                           target="_blank"
                           class="btn btn-success">
                            <i class="fab fa-whatsapp me-2"></i>Compartilhar no WhatsApp
                        </a>

                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}"
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
