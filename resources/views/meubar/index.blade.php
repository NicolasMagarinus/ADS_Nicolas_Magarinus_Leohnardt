@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="mb-4">
            <h2 class="mb-1"><i class="fa-solid fa-champagne-glasses"></i>Meu Bar</h2>
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
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('ingredientSearch');
            const autocomplete = document.getElementById('autocompleteResults');
            const chipsContainer = document.getElementById('ingredientChips');
            const emptyMsg = document.getElementById('emptyChipsMsg');
            const countBadge = document.getElementById('ingredientCount');
            const btnBuscar = document.getElementById('btnBuscarDrinks');
            const btnLimpar = document.getElementById('btnLimpar');
            const loadingEl = document.getElementById('loadingDrinks');
            const resultsSection = document.getElementById('resultsSection');
            const csrfToken = '{{ csrf_token() }}';
            const defaultImage = 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png';
            const syncSessionUrl = '{{ route("meubar.sync-session") }}';
            // Ingredientes salvos na sessão do servidor (prioridade)
            const sessionIngredients = @json($sessionIngredients);

            // Mescla sessão do servidor com localStorage (sessão tem prioridade se não estiver vazia)
            let ingredients = sessionIngredients.length > 0
                ? sessionIngredients
                : JSON.parse(localStorage.getItem('meubar_ingredientes') || '[]');
            // Garante que o localStorage está sincronizado
            localStorage.setItem('meubar_ingredientes', JSON.stringify(ingredients));

            function saveIngredients() {
                localStorage.setItem('meubar_ingredientes', JSON.stringify(ingredients));
                // Sincroniza com sessão do servidor em background
                fetch(syncSessionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ingredientes: ingredients })
                }).catch(() => {}); // falha silenciosa
            }

            function renderChips() {
                // Remove all chips but keep the empty message
                chipsContainer.querySelectorAll('.meubar-chip').forEach(el => el.remove());

                if (ingredients.length === 0) {
                    emptyMsg.style.display = '';
                    btnBuscar.disabled = true;
                    btnLimpar.style.display = 'none';
                } else {
                    emptyMsg.style.display = 'none';
                    btnBuscar.disabled = false;
                    btnLimpar.style.display = '';

                    ingredients.forEach((ing, index) => {
                        const chip = document.createElement('span');
                        chip.className = 'meubar-chip';
                        chip.innerHTML = `${ing.nm_ingrediente} <button type="button" class="meubar-chip-remove" data-index="${index}">&times;</button>`;
                        chipsContainer.appendChild(chip);
                    });
                }
                countBadge.textContent = ingredients.length;
            }

            // Remove ingredient on chip X click
            chipsContainer.addEventListener('click', function (e) {
                const removeBtn = e.target.closest('.meubar-chip-remove');
                if (removeBtn) {
                    const index = parseInt(removeBtn.dataset.index);
                    ingredients.splice(index, 1);
                    saveIngredients();
                    renderChips();
                }
            });

            // Autocomplete search
            let debounceTimer;
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const query = this.value.trim();

                if (query.length < 2) {
                    autocomplete.style.display = 'none';
                    autocomplete.innerHTML = '';
                    return;
                }

                debounceTimer = setTimeout(async () => {
                    try {
                        const response = await fetch(`/meu-bar/ingredientes/search?q=${encodeURIComponent(query)}`);
                        const data = await response.json();
                        autocomplete.innerHTML = '';

                        if (data.length === 0) {
                            autocomplete.innerHTML = '<div class="meubar-autocomplete-item text-muted">Nenhum ingrediente encontrado</div>';
                            autocomplete.style.display = 'block';
                            return;
                        }

                        data.forEach(item => {
                            const alreadyAdded = ingredients.some(i => i.cd_ingrediente === item.cd_ingrediente);
                            const div = document.createElement('div');
                            div.className = 'meubar-autocomplete-item' + (alreadyAdded ? ' disabled' : '');
                            div.textContent = item.nm_ingrediente + (alreadyAdded ? ' ✓' : '');

                            if (!alreadyAdded) {
                                div.addEventListener('click', function () {
                                    ingredients.push({
                                        cd_ingrediente: item.cd_ingrediente,
                                        nm_ingrediente: item.nm_ingrediente
                                    });
                                    saveIngredients();
                                    renderChips();
                                    searchInput.value = '';
                                    autocomplete.style.display = 'none';
                                    autocomplete.innerHTML = '';
                                });
                            }
                            autocomplete.appendChild(div);
                        });

                        autocomplete.style.display = 'block';
                    } catch (err) {
                        console.error('Erro ao buscar ingredientes:', err);
                    }
                }, 300);
            });

            // Close autocomplete on outside click
            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !autocomplete.contains(e.target)) {
                    autocomplete.style.display = 'none';
                }
            });

            // Clear all
            btnLimpar.addEventListener('click', function () {
                Swal.fire({
                    title: 'Limpar todos os ingredientes?',
                    text: 'Isso removerá todos os ingredientes do seu bar.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#333',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, limpar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        ingredients = [];
                        saveIngredients();
                        renderChips();
                        resultsSection.style.display = 'none';
                    }
                });
            });

            // Search drinks
            btnBuscar.addEventListener('click', async function () {
                if (ingredients.length === 0) return;

                loadingEl.style.display = '';
                resultsSection.style.display = 'none';
                btnBuscar.disabled = true;

                try {
                    const response = await fetch('/meu-bar/drinks', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            ingredientes: ingredients.map(i => i.cd_ingrediente)
                        })
                    });

                    const data = await response.json();
                    renderResults(data);
                } catch (err) {
                    console.error('Erro ao buscar drinks:', err);
                    Swal.fire({
                        title: 'Erro!',
                        text: 'Erro ao buscar drinks. Tente novamente.',
                        icon: 'error',
                        confirmButtonColor: '#333'
                    });
                } finally {
                    loadingEl.style.display = 'none';
                    btnBuscar.disabled = false;
                }
            });

            function renderResults(data) {
                const prontosSection = document.getElementById('prontosSection');
                const quaseLaSection = document.getElementById('quaseLaSection');
                const prontosGrid = document.getElementById('prontosGrid');
                const quaseLaGrid = document.getElementById('quaseLaGrid');
                const noResults = document.getElementById('noResults');

                prontosGrid.innerHTML = '';
                quaseLaGrid.innerHTML = '';
                resultsSection.style.display = '';

                if (data.prontos.length === 0 && data.quase_la.length === 0) {
                    prontosSection.style.display = 'none';
                    quaseLaSection.style.display = 'none';
                    noResults.style.display = '';
                    return;
                }

                noResults.style.display = 'none';

                // Render prontos
                if (data.prontos.length > 0) {
                    prontosSection.style.display = '';
                    document.getElementById('prontosCount').textContent = data.prontos.length;
                    data.prontos.forEach(drink => {
                        prontosGrid.innerHTML += createDrinkCard(drink, false);
                    });
                } else {
                    prontosSection.style.display = 'none';
                }

                // Render quase lá
                if (data.quase_la.length > 0) {
                    quaseLaSection.style.display = '';
                    document.getElementById('quaseLaCount').textContent = data.quase_la.length;
                    data.quase_la.forEach(drink => {
                        quaseLaGrid.innerHTML += createDrinkCard(drink, true);
                    });
                } else {
                    quaseLaSection.style.display = 'none';
                }
            }

            function createDrinkCard(drink, showMissing) {
                const image = drink.ds_imagem || defaultImage;
                const nota = parseFloat(drink.nota) || 0;
                const qtAval = drink.qt_avaliacao || 0;

                let missingHtml = '';
                if (showMissing && drink.ingredientes_faltando && drink.ingredientes_faltando.length > 0) {
                    const badges = drink.ingredientes_faltando.map(i =>
                        `<span class="meubar-badge-faltando">${i}</span>`
                    ).join(' ');
                    missingHtml = `<div class="mt-2"><small class="text-muted">Falta:</small> ${badges}</div>`;
                }

                return `
                <div class="col-md-3 col-sm-6 mb-4">
                    <a href="/bebida/${drink.cd_bebida}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100">
                            <img src="${image}" class="card-img-top" alt="${drink.nm_bebida}" height="200" style="object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title">${drink.nm_bebida}</h5>
                                <p class="card-text">
                                    <i class="bi bi-star-fill text-warning"></i>
                                    ${nota} (${qtAval} avaliações)
                                </p>
                                ${missingHtml}
                            </div>
                        </div>
                    </a>
                </div>
            `;
            }

            // Initial render
            renderChips();
        });
    </script>
@endsection