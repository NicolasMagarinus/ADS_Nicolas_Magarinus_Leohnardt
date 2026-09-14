@extends('layouts.app')

@section('titulo', 'Meu perfil')
@section('robots', 'noindex')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        @if($user->ds_avatar)
                            <img src="@imagem($user->ds_avatar, 240)" alt="Foto de {{ $user->name }}"
                                 class="rounded-circle" width="120" height="120" style="object-fit: cover;" loading="lazy">
                        @else
                            <i class="bi bi-person-circle display-1 text-secondary"></i>
                        @endif
                    </div>
                    <h4 class="card-title">{{ $user->name }}</h4>
                    <p class="text-muted">{{ $user->email }}</p>
                    <p class="text-muted small">Membro desde {{ $user->created_at->format('d/m/Y') }}</p>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-bar-chart-fill me-2"></i>Estatísticas
                    </h5>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted"><i class="bi bi-heart text-danger me-2"></i>Favoritos</span>
                        <span class="badge bg-danger rounded-pill">{{ $cntFavoritos }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted"><i class="bi bi-star text-warning me-2"></i>Avaliações</span>
                        <span class="badge bg-warning text-dark rounded-pill">{{ $cntAvaliacoes }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted"><i class="bi bi-cup-straw me-2"></i>Receitas</span>
                        <span class="badge bg-primary rounded-pill">{{ $arrBebida->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted"><i class="bi bi-collection me-2"></i>Coleções</span>
                        <span class="badge bg-primary rounded-pill">{{ $colecoes->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-gear-fill me-2 text-secondary"></i>Configurações
                    </h5>
                    <button type="button" class="btn btn-outline-dark w-100 mb-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil-fill me-2"></i>Editar Perfil
                    </button>
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
                                <img src="@imagem($bebida->ds_imagem, 400)" class="img-fluid rounded-start h-100 object-fit-cover" alt="{{ $bebida->nm_bebida }}" style="min-height: 150px; max-height: 200px; width: 100%; object-fit: cover;" loading="lazy">
                            @else
                                <div class="d-flex align-items-center justify-content-center bg-light rounded-start h-100" style="min-height: 150px;">
                                    <img src="@imagem(null, 400)" class="img-fluid" alt="Imagem não disponível" loading="lazy">
                                </div>
                            @endif
                        </div>
                        <div class="col-md-9">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">{{ $bebida->nm_bebida }}</h5>
                                    <span class="badge {{ $bebida->id_status->classeBadge() }}"><i class="bi {{ $bebida->id_status->icone() }} me-1"></i> {{ $bebida->id_status->label() }}</span>
                                </div>
                                
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-calendar me-1"></i>{{ $bebida->created_at->format('d/m/Y H:i') }}
                                </p>
                                
                                <p class="card-text text-muted small mb-2">
                                    {{ Str::limit($bebida->ds_preparo, 120) }}
                                </p>
                                
                                @if($bebida->id_status === App\Enums\StatusCadastro::Rejeitada && $bebida->ds_motivo_rejeicao)
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#motivo{{ $bebida->cd_bebida_cadastro }}">
                                        <i class="bi bi-exclamation-circle me-1"></i>Ver Motivo da Rejeição
                                    </button>
                                    <div class="collapse mt-2" id="motivo{{ $bebida->cd_bebida_cadastro }}">
                                        <div class="alert alert-danger mb-0 small">
                                            <strong><i class="bi bi-info-circle me-1"></i>Motivo da rejeição:</strong> {{ $bebida->ds_motivo_rejeicao }}
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
                        <i class="bi bi-cup-straw fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhuma receita cadastrada</h5>
                        <p class="text-muted">Você ainda não submeteu nenhuma receita.</p>
                        <a href="{{ route('bebida.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus me-2"></i>Cadastrar Receita
                        </a>
                    </div>
                </div>
            @endforelse

                <h3 class="mb-3 mt-5">Minhas Coleções</h3>

                <button class="btn btn-sm btn-outline-primary mb-3"
                        data-bs-toggle="modal" data-bs-target="#novaColecaoModal">
                    <i class="bi bi-plus-lg me-1"></i>Nova coleção
                </button>

                @forelse($colecoes as $colecao)
                    <div class="card mb-2">
                        <div class="card-body d-flex justify-content-between align-items-center py-2">
                            <div>
                                <a href="{{ $colecao->url() }}" class="text-decoration-none">
                                    {{ $colecao->nm_colecao }}
                                </a>
                                <span class="text-muted small ms-2">{{ $colecao->bebidas_count }} drinks</span>
                                @if($colecao->id_publica)
                                    <span class="badge bg-success ms-1">Pública</span>
                                @else
                                    <span class="badge bg-secondary ms-1">Privada</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('colecao.destroy', $colecao->cd_colecao) }}"
                                  onsubmit="return confirm('Apagar a coleção {{ $colecao->nm_colecao }}?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">Nenhuma coleção ainda.</p>
                @endforelse

                <div class="modal fade" id="novaColecaoModal" tabindex="-1">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('colecao.store') }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Nova coleção</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label" for="nm_colecao">Nome</label>
                                    <input class="form-control" id="nm_colecao" name="nm_colecao"
                                           maxlength="60" required value="{{ old('nm_colecao') }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="ds_colecao">Descrição (opcional)</label>
                                    <input class="form-control" id="ds_colecao" name="ds_colecao"
                                           maxlength="200" value="{{ old('ds_colecao') }}">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1"
                                           id="id_publica" name="id_publica">
                                    <label class="form-check-label" for="id_publica">
                                        Pública — qualquer pessoa com o link pode ver
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-primary" type="submit">Criar</button>
                            </div>
                        </form>
                    </div>
                </div>
        </div>
    </div>
</div>

    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProfileModalLabel">
                            <i class="bi bi-pencil-fill me-2"></i>Editar Perfil
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="ds_avatar" class="form-label">Foto <span class="text-muted">(opcional)</span></label>
                            <input type="file" class="form-control @error('ds_avatar') is-invalid @enderror"
                                   id="ds_avatar" name="ds_avatar" accept="image/jpeg,image/png,image/jpg">
                            @error('ds_avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">JPEG, PNG ou JPG, até 5 MB. Deixe em branco para manter a atual.</div>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle me-1"></i>O e-mail não pode ser alterado por aqui.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark">Salvar</button>
                    </div>
                </div>
            </form>
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
                                    <i class="bi bi-eye text-muted"></i>
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
                                    <i class="bi bi-eye text-muted"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <div class="progress" style="height: 5px;">
                                    <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%; transition: width .3s, background-color .3s;"></div>
                                </div>
                                <small id="passwordStrengthLabel" class="text-muted d-block mt-1"></small>
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
                                    <i class="bi bi-eye text-muted"></i>
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
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Password strength meter
        document.getElementById('new_password').addEventListener('input', function () {
            const val = this.value;
            const bar = document.getElementById('passwordStrengthBar');
            const label = document.getElementById('passwordStrengthLabel');
            let score = 0;
            if (val.length >= 8)  score++;
            if (val.length >= 12) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const levels = [
                { pct: 0,   cls: '',          txt: '' },
                { pct: 20,  cls: 'bg-danger',  txt: 'Muito fraca' },
                { pct: 40,  cls: 'bg-warning', txt: 'Fraca' },
                { pct: 60,  cls: 'bg-info',    txt: 'Média' },
                { pct: 80,  cls: 'bg-primary', txt: 'Forte' },
                { pct: 100, cls: 'bg-success', txt: 'Muito forte 💪' },
            ];
            const lvl = val.length === 0 ? levels[0] : levels[Math.min(score, 5)];
            bar.style.width = lvl.pct + '%';
            bar.className = 'progress-bar ' + lvl.cls;
            label.textContent = lvl.txt;
        });

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
