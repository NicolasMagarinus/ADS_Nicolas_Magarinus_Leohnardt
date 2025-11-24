@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-person-circle display-1 text-secondary"></i>
                    </div>
                    <h4 class="card-title">{{ $user->name }}</h4>
                    <p class="text-muted">{{ $user->email }}</p>
                    <p class="text-muted small">Membro desde {{ $user->created_at->format('d/m/Y') }}</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <h3 class="mb-4 border-bottom pb-2">Minhas Receitas</h3>
            
            <div class="card shadow-sm">
                <div class="list-group list-group-flush">
                    @forelse($arrBebida as $bebida)
                        <div class="list-group-item p-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h5 class="mb-1">{{ $bebida->nm_bebida }}</h5>
                                <small class="text-muted">{{ $bebida->created_at->format('d/m/Y H:i') }}</small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    @if($bebida->id_status == 0)
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Pendente</span>
                                    @elseif($bebida->id_status == 1)
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Aprovada</span>
                                    @elseif($bebida->id_status == 2)
                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Rejeitada</span>
                                    @endif
                                </div>
                                
                                @if($bebida->id_status == 2 && $bebida->ds_motivo_rejeicao)
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#motivo{{ $bebida->cd_bebida_cadastro }}">
                                        Ver Motivo
                                    </button>
                                @endif
                            </div>
                            
                            @if($bebida->id_status == 2 && $bebida->ds_motivo_rejeicao)
                                <div class="collapse mt-2" id="motivo{{ $bebida->cd_bebida_cadastro }}">
                                    <div class="card card-body bg-light text-danger small">
                                        <strong>Motivo da rejeição:</strong> {{ $bebida->ds_motivo_rejeicao }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item p-3">
                            <div class="alert alert-info mb-0">
                                Você ainda não submeteu nenhuma receita. <a href="{{ route('bebida.create') }}">Cadastre uma agora!</a>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
