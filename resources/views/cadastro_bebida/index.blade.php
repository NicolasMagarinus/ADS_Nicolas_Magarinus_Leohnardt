@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary"><i class="fas fa-tasks me-2"></i>Fila de Aprovação</h2>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar para Tela Inicial
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card shadow-sm border-0 rounded-lg">
                <div class="card-body p-0">
                    @forelse($bebidas as $bebida)
                        <div class="list-group list-group-flush">
                            <div class="list-group-item p-4">
                                <div class="row align-items-center">
                                    <div class="col-md-2 text-center">
                                        <img src="{{ $bebida->ds_imagem ?: 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png' }}" 
                                             alt="{{ $bebida->nm_bebida }}" 
                                             class="img-fluid rounded" 
                                             style="max-height: 100px; object-fit: cover;">
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="mb-2">{{ $bebida->nm_bebida }}</h5>
                                        <p class="text-muted small mb-2 text-truncate" style="max-width: 500px;">
                                            {{ $bebida->ds_preparo }}
                                        </p>
                                        <div class="mb-2">
                                            <strong class="small">Ingredientes:</strong>
                                            <div class="small text-muted">
                                                @foreach($bebida->ingredientes as $ingrediente)
                                                    <span class="badge bg-light text-dark me-1">
                                                        {{ $ingrediente->nm_ingrediente }} 
                                                        @if($ingrediente->ds_medida)
                                                            ({{ $ingrediente->ds_medida }})
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>{{ $bebida->usuario->name ?? 'N/A' }} • 
                                            <i class="fas fa-calendar me-1"></i>{{ $bebida->created_at->format('d/m/Y H:i') }}
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <form action="{{ route('admin.bebidas.approve', $bebida->cd_bebida_cadastro) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success me-2" title="Aprovar">
                                                <i class="fas fa-check me-1"></i>Aprovar
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $bebida->cd_bebida_cadastro }}" title="Rejeitar">
                                            <i class="fas fa-times me-1"></i>Rejeitar
                                        </button>

                                        <!-- Modal de Rejeição -->
                                        <div class="modal fade" id="rejectModal{{ $bebida->cd_bebida_cadastro }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('admin.bebidas.reject', $bebida->cd_bebida_cadastro) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>Rejeitar Bebida
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="motivo_rejeicao{{ $bebida->cd_bebida_cadastro }}" class="form-label">
                                                                    Motivo da Rejeição <span class="text-danger">*</span>
                                                                </label>
                                                                <textarea class="form-control" 
                                                                          id="motivo_rejeicao{{ $bebida->cd_bebida_cadastro }}"
                                                                          name="motivo_rejeicao" 
                                                                          rows="3" 
                                                                          required
                                                                          placeholder="Explique o motivo da rejeição..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="fas fa-times me-1"></i>Cancelar
                                                            </button>
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="fas fa-ban me-1"></i>Rejeitar
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Nenhuma bebida pendente de aprovação.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: '{{ session('success') }}',
            confirmButtonColor: '#28a745'
        });
    @endif
</script>
@endsection
