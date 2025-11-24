@extends('layouts.app')

@section('content')
<div class="container">
    <section class="mb-5">
        <h2 class="mb-4">Meus Favoritos</h2>
        
        @if($favoritos->count() > 0)
            <div class="row">
                @foreach($favoritos as $bebida)
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
                                    <button class="btn btn-sm btn-outline-danger remove-favorite" data-bebida-id="{{ $bebida->cd_bebida }}" onclick="event.preventDefault(); removeFavorite({{ $bebida->cd_bebida }});">
                                        <i class="fas fa-heart-broken me-1"></i> Remover
                                    </button>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-heart" style="font-size: 4rem; color: #ddd;"></i>
                <h4 class="mt-3 text-muted">Você ainda não tem favoritos</h4>
                <p class="text-muted">Explore nossas bebidas e adicione suas favoritas!</p>
                <a href="{{ route('search') }}" class="btn btn-primary mt-3">
                    <i class="bi bi-compass me-1"></i> Explorar Bebidas
                </a>
            </div>
        @endif
    </section>
</div>

<script>
function removeFavorite(bebidaId) {
    Swal.fire({
        title: 'Remover favorito?',
        text: 'Deseja remover esta bebida dos favoritos?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/favoritos/${bebidaId}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Removido!',
                        text: 'A bebida foi removida dos favoritos.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao remover favorito. Tente novamente.',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                });
            });
        }
    });
}
</script>
@endsection
