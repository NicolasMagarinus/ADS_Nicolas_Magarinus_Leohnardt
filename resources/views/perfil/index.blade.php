@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-person-circle display-1 text-secondary"></i>
                    </div>
                    <h4 class="card-title">{{ $user->name }}</h4>
                    <p class="text-muted">{{ $user->email }}</p>
                    <p class="text-muted small">Membro desde {{ $user->created_at->format('d/m/Y') }}</p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-gear-fill me-2"></i>Configurações
                    </h5>
                    <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                        <i class="bi bi-key-fill me-2"></i>Alterar Senha
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            @forelse($arrBebida as $bebida)
                @if($loop->first)
                    <h3 class="mb-3">Minhas Receitas</h3>
                @endif
                <div class="card shadow-sm mb-3">
                    <div class="row g-0">
                        <div class="col-md-3">
                            @if($bebida->ds_imagem)
                                <img src="{{ $bebida->ds_imagem }}" class="img-fluid rounded-start h-100 object-fit-cover" alt="{{ $bebida->nm_bebida }}" style="min-height: 150px; max-height: 200px; width: 100%; object-fit: cover;">
                            @else
                                <div class="d-flex align-items-center justify-content-center bg-light rounded-start h-100" style="min-height: 150px;">
                                    <i class="fas fa-cocktail fa-3x text-muted"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-9">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">{{ $bebida->nm_bebida }}</h5>
                                    @if($bebida->id_status == 0)
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Pendente</span>
                                    @elseif($bebida->id_status == 1)
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Aprovada</span>
                                    @elseif($bebida->id_status == 2)
                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Rejeitada</span>
                                    @endif
                                </div>
                                
                                <p class="text-muted small mb-2">
                                    <i class="far fa-calendar me-1"></i>{{ $bebida->created_at->format('d/m/Y H:i') }}
                                </p>
                                
                                <p class="card-text text-muted small mb-2">
                                    {{ Str::limit($bebida->ds_preparo, 120) }}
                                </p>
                                
                                @if($bebida->id_status == 2 && $bebida->ds_motivo_rejeicao)
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#motivo{{ $bebida->cd_bebida_cadastro }}">
                                        <i class="fas fa-exclamation-circle me-1"></i>Ver Motivo da Rejeição
                                    </button>
                                    <div class="collapse mt-2" id="motivo{{ $bebida->cd_bebida_cadastro }}">
                                        <div class="alert alert-danger mb-0 small">
                                            <strong><i class="fas fa-info-circle me-1"></i>Motivo da rejeição:</strong> {{ $bebida->ds_motivo_rejeicao }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-cocktail fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhuma receita cadastrada</h5>
                        <p class="text-muted">Você ainda não submeteu nenhuma receita.</p>
                        <a href="{{ route('bebida.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Cadastrar Receita
                        </a>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">
                        <i class="bi bi-key-fill me-2"></i>Alterar Senha
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('perfil.change-password') }}" id="changePasswordForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Senha Atual</label>
                            <div class="position-relative">
                                <input type="password" name="current_password" id="current_password" class="form-control" required>
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                        onclick="togglePasswordVisibility('current_password', this)" 
                                        style="text-decoration: none; z-index: 10;">
                                    <i class="fas fa-eye text-muted"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nova Senha</label>
                            <div class="position-relative">
                                <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8">
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                        onclick="togglePasswordVisibility('new_password', this)" 
                                        style="text-decoration: none; z-index: 10;">
                                    <i class="fas fa-eye text-muted"></i>
                                </button>
                            </div>
                            <small class="text-muted">Mínimo de 8 caracteres</small>
                        </div>

                        <div class="mb-3">
                            <label for="new_password_confirmation" class="form-label">Confirmar Nova Senha</label>
                            <div class="position-relative">
                                <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control" required>
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                        onclick="togglePasswordVisibility('new_password_confirmation', this)" 
                                        style="text-decoration: none; z-index: 10;">
                                    <i class="fas fa-eye text-muted"></i>
                                </button>
                            </div>
                        </div>

                        <div id="passwordError" class="alert alert-danger d-none"></div>
                        <div id="passwordSuccess" class="alert alert-success d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            const errorDiv = document.getElementById('passwordError');
            const successDiv = document.getElementById('passwordSuccess');

            errorDiv.classList.add('d-none');
            successDiv.classList.add('d-none');
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message;
                    successDiv.classList.remove('d-none');
                    form.reset();

                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                        modal.hide();
                        successDiv.classList.add('d-none');
                    }, 2000);
                } else {
                    errorDiv.textContent = data.message || 'Erro ao alterar senha';
                    errorDiv.classList.remove('d-none');
                }
            })
            .catch(error => {
                errorDiv.textContent = 'Erro ao processar solicitação';
                errorDiv.classList.remove('d-none');
            });
        });
    </script>
@endsection
