@extends('auth.recuperacao.layout', ['passo' => 2])

@section('titulo', 'Confirme o código')

@section('conteudo')
    <h1 class="form-title">Digite o código</h1>
    <p class="form-subtitle">
        Enviamos um código de 6 dígitos para <strong>{{ $email }}</strong>.
        Ele vale por 15 minutos. Confira também a caixa de spam.
    </p>

    <form method="POST" action="{{ route('password.code.verify') }}">
        @csrf

        <div class="mb-4">
            <label for="codigo" class="form-label">Código</label>
            <input type="text" name="codigo" id="codigo"
                   class="form-control campo-codigo @error('codigo') is-invalid @enderror"
                   inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                   placeholder="000000" required autofocus>
            @error('codigo')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-check me-2"></i>Confirmar código
        </button>
    </form>

    <div class="text-center mt-4">
        <a href="{{ route('password.request') }}" class="voltar-link">
            <i class="fas fa-redo-alt me-1"></i>Pedir um novo código
        </a>
    </div>
@endsection
