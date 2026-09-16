@extends('layouts.app')

@section('titulo', 'Meu Bar')
@section('descricao', 'Diga quais ingredientes você tem em casa e descubra os drinks que dá para preparar agora.')
@section('robots', 'noindex')

@section('content')
    <div class="container py-4">
        <div class="mb-4">
            <h2 class="mb-1"><i class="fas fa-glass-cheers me-2"></i>Meu Bar</h2>
            <p class="text-muted">Adicione os ingredientes que você tem em casa e descubra quais drinks pode preparar.</p>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <label for="ingredientSearch" class="form-label fw-bold"><i class="bi bi-search me-1"></i> Buscar
                    ingrediente</label>
                <div class="position-relative">
                    <input type="text" class="form-control" id="ingredientSearch"
                        placeholder="Digite o nome do ingrediente..." autocomplete="off">
                    <div id="autocompleteResults" class="meubar-autocomplete"></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Meus Ingredientes</h5>
                    <span id="ingredientCount" class="badge bg-dark">0</span>
                </div>
                <div id="ingredientChips" class="d-flex flex-wrap gap-2">
                    <p id="emptyChipsMsg" class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Nenhum ingrediente
                        adicionado. Use a busca acima para começar!</p>
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <button id="btnBuscarDrinks" class="btn btn-primary btn-lg px-5" disabled>
                <i class="fas fa-cocktail me-2"></i>Buscar Drinks
            </button>
            <button id="btnLimpar" class="btn btn-outline-secondary btn-lg ms-2 px-4" style="display:none;">
                <i class="fas fa-eraser me-2"></i>Limpar Tudo
            </button>
        </div>

        <div id="loadingDrinks" class="text-center py-5" style="display:none;">
            <div class="spinner-border text-dark" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-2 text-muted">Buscando drinks compatíveis...</p>
        </div>

        <div id="resultsSection" style="display:none;">
            <section id="prontosSection" class="mb-5" style="display:none;">
                <div class="d-flex align-items-center mb-3">
                    <h3 class="mb-0"><i class="fas fa-check-circle text-success me-2"></i>Drinks que você pode fazer</h3>
                    <span id="prontosCount" class="badge bg-success ms-2">0</span>
                </div>
                <div id="prontosGrid" class="row"></div>
            </section>

            <section id="quaseLaSection" class="mb-5" style="display:none;">
                <div class="d-flex align-items-center mb-3">
                    <h3 class="mb-0"><i class="fas fa-shopping-cart text-warning me-2"></i>Quase lá!</h3>
                    <span id="quaseLaCount" class="badge bg-warning text-dark ms-2">0</span>
                </div>
                <p class="text-muted">Compre só mais 1 ou 2 ingredientes e prepare esses drinks!</p>
                <div id="quaseLaGrid" class="row"></div>
            </section>

            <div id="noResults" class="text-center py-5" style="display:none;">
                <i class="fas fa-glass-whiskey" style="font-size: 4rem; color: #ddd;"></i>
                <h4 class="mt-3 text-muted">Nenhum drink encontrado</h4>
                <p class="text-muted">Tente adicionar mais ingredientes para encontrar receitas compatíveis.</p>
            </div>
        </div>
    </div>

    <script>
        window.DrinkeritoMeuBar = {
            csrfToken: '{{ csrf_token() }}',
            salvarUrl: '{{ route("meubar.salvar") }}',
            ingredientesSalvos: @json($ingredientesSalvos),
        };
    </script>
    @vite('resources/js/meubar.js')
@endsection