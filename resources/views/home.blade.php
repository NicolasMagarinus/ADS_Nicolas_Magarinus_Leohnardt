@extends('layouts.app')

@section('content')
    <div class="container">
        @if(isset($hasFavoritesForRecommend) && $hasFavoritesForRecommend)
            <div class="alert alert-primary mb-5 d-flex justify-content-between align-items-center rounded-3 shadow-sm border-0"
                style="background: linear-gradient(135deg, rgba(13,110,253,0.1), rgba(111,66,193,0.1));">
                <div>
                    <h4 class="alert-heading fw-bold mb-1"><i class="bi bi-magic me-2"></i>Recomendações para você</h4>
                    <p class="mb-0 text-muted">Bebidas escolhidas com base nos seus favoritos.</p>
                </div>
                <a href="{{ route('recomendadas.index') }}" class="btn btn-primary shadow-sm" style="white-space: nowrap;">
                    Ver recomendações <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        @endif

        <section class="mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <h2 class="mb-0">Mais Populares</h2>
                <div class="btn-group shadow-sm" role="group" id="filtroTipo">
                    <button type="button" class="btn btn-primary" data-filter="all">Todos</button>
                    <button type="button" class="btn btn-outline-primary" data-filter="1">Alcoólicos</button>
                    <button type="button" class="btn btn-outline-primary" data-filter="2">Sem Álcool</button>
                </div>
            </div>
            <div class="row" id="popularesGrid">
                @foreach($arrAvaliacao as $bebida)
                    <div class="col-md-3 mb-4 popular-card" data-tipo="{{ $bebida->id_tipo }}" style="display: none;">
                        <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                            <div class="card drink-card h-100">
                                <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}"
                                    class="card-img-top" alt="{{ $bebida->nm_bebida }}" height="200" style="object-fit: cover;">
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
                        <a href="{{ route('search', ['q' => $ingrediente->nm_ingrediente]) }}"
                            class="text-decoration-none text-dark">
                            <div class="card drink-card h-100 text-center" style="transition: transform .2s, box-shadow .2s;"
                                onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 18px rgba(0,0,0,.12)';"
                                onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                <img src="{{ $ingrediente->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}"
                                    class="card-img-top" alt="{{ $ingrediente->nm_ingrediente }}" height="200"
                                    style="object-fit: cover;">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $ingrediente->nm_ingrediente }}</h5>
                                    <p class="card-text text-muted small">Utilizado em {{ $ingrediente->qt_utilizado }} receitas
                                    </p>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border"
                                        style="font-size:.72rem;">
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
                                <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}"
                                    class="card-img-top" alt="{{ $bebida->nm_bebida }}" height="150" style="object-fit: cover;">
                                <div class="card-body">
                                    <h6 class="card-title">{{ $bebida->nm_bebida }}</h6>
                                </div>
                            </div>
                        </a>
                    </div>
                    @if(($index + 1) % 4 == 0 && $index + 1 < 8)
                        </div>
                        <div class="row">
                    @endif
                @endforeach
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterButtons = document.querySelectorAll('#filtroTipo button');
            const cards = document.querySelectorAll('.popular-card');

            if (filterButtons.length) {
                filterButtons.forEach(btn => {
                    btn.addEventListener('click', function () {
                        // Update active state
                        filterButtons.forEach(b => {
                            b.classList.remove('btn-primary');
                            b.classList.add('btn-outline-primary');
                        });
                        this.classList.remove('btn-outline-primary');
                        this.classList.add('btn-primary');

                        const filter = this.dataset.filter;
                        let count = 0;

                        cards.forEach(card => {
                            if (filter === 'all' || card.dataset.tipo === filter) {
                                if (count < 4) {
                                    card.style.display = 'block';
                                    count++;
                                } else {
                                    card.style.display = 'none';
                                }
                            } else {
                                card.style.display = 'none';
                            }
                        });
                    });
                });

                // Trigger inicial para Todos (renderiza max 4)
                filterButtons[0].click();
            }
        });
    </script>
@endsection