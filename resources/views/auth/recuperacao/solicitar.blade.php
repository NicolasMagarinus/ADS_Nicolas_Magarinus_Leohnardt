@extends('auth.recuperacao.layout', ['passo' => 1])

@section('titulo', 'Recuperar senha')

@section('conteudo')
    <h1 class="form-title">Esqueceu sua senha?</h1>
    <p class="form-subtitle">
        Informe o e-mail da sua conta e enviaremos um código de 6 dígitos para você criar uma senha nova.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" placeholder="seu@email.com" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane me-2"></i>Enviar código
        </button>
    </form>

    <div class="text-center mt-4">
        <a href="{{ route('login') }}" class="voltar-link">
            <i class="fas fa-arrow-left me-1"></i>Voltar para o login
        </a>
    </div>
@endsection
