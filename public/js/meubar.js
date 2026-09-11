/**
 * Meu Bar: escolha de ingredientes e busca dos drinks possíveis.
 *
 * Os valores que só o servidor sabe — token CSRF, rota de gravação e o bar já
 * salvo — chegam em window.DrinkeritoMeuBar, montado pela view. É o que
 * permite este arquivo ser estático e cacheado entre visitas.
 */
(function () {
    'use strict';

    const config = window.DrinkeritoMeuBar || {};
    const csrfToken = config.csrfToken;
    const salvarUrl = config.salvarUrl;
    // Fonte da verdade: o bar gravado no banco para este usuário.
    const ingredientesSalvos = config.ingredientesSalvos || [];
    const escapeHtml = window.Drinkerito.escapeHtml;

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
            const defaultImage = 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png';

            // Quem já usava o Meu Bar antes de ele virar persistente tem os
            // ingredientes só no localStorage deste navegador. Se o servidor
            // não tem nada, adotamos o que houver aqui, uma vez.
            let ingredients = ingredientesSalvos.length > 0
                ? ingredientesSalvos
                : JSON.parse(localStorage.getItem('meubar_ingredientes') || '[]');

            localStorage.setItem('meubar_ingredientes', JSON.stringify(ingredients));

            if (ingredientesSalvos.length === 0 && ingredients.length > 0) {
                saveIngredients();
            }

            function saveIngredients() {
                // O localStorage segue espelhando o bar, inclusive quando ele
                // é esvaziado: um espelho desatualizado ressuscitaria a lista
                // antiga na recarga seguinte.
                localStorage.setItem('meubar_ingredientes', JSON.stringify(ingredients));

                fetch(salvarUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ingredientes: ingredients.map(i => i.cd_ingrediente) })
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
                        chip.innerHTML = `${escapeHtml(ing.nm_ingrediente)} <button type="button" class="meubar-chip-remove" data-index="${index}">&times;</button>`;
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
                const image = escapeHtml(drink.ds_imagem || defaultImage);
                const nome = escapeHtml(drink.nm_bebida);
                const nota = parseFloat(drink.nota) || 0;
                const qtAval = parseInt(drink.qt_avaliacao, 10) || 0;
                const cdBebida = parseInt(drink.cd_bebida, 10) || 0;

                let missingHtml = '';
                if (showMissing && drink.ingredientes_faltando && drink.ingredientes_faltando.length > 0) {
                    const badges = drink.ingredientes_faltando.map(i =>
                        `<span class="meubar-badge-faltando">${escapeHtml(i)}</span>`
                    ).join(' ');
                    missingHtml = `<div class="mt-2"><small class="text-muted">Falta:</small> ${badges}</div>`;
                }

                return `
                <div class="col-md-3 col-sm-6 mb-4">
                    <a href="/bebida/${cdBebida}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100">
                            <img src="${image}" class="card-img-top" alt="${nome}" height="200" style="object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title">${nome}</h5>
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

})();
