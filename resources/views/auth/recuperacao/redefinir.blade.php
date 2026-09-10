@extends('auth.recuperacao.layout', ['passo' => 3])

@section('titulo', 'Nova senha')

@section('conteudo')
    <h1 class="form-title">Crie uma senha nova</h1>
    <p class="form-subtitle">Código confirmado. Escolha uma senha de pelo menos 8 caracteres.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <div class="mb-3">
            <label for="password" class="form-label">Nova senha</label>
            <input type="password" name="password" id="password"
                   class="form-control @error('password') is-invalid @enderror" required autofocus>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirme a nova senha</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                   class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-lock me-2"></i>Salvar nova senha
        </button>
    </form>
@endsection
