<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>@yield('titulo') - Drinkerito</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #333333;
            --accent-color: #555555;
            --light-bg: #f8f9fa;
        }

        body {
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: start;
            padding: 20px;
            font-family: 'Arial', sans-serif;
        }

        .drinkerito-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 520px;
            margin-top: 40px;
        }

        .card-topo {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 28px 40px;
            text-align: center;
        }

        .brand-logo {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .form-section {
            padding: 36px 40px 40px;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .form-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 28px;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(85, 85, 85, 0.15);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: bold;
            width: 100%;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .voltar-link {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .voltar-link:hover {
            text-decoration: underline;
        }

        .campo-codigo {
            font-size: 2rem;
            letter-spacing: 14px;
            text-align: center;
            font-weight: bold;
            padding: 14px 15px;
        }

        .passos {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .passo {
            width: 34px;
            height: 4px;
            border-radius: 2px;
            background-color: #dee2e6;
        }

        .passo.ativo {
            background-color: var(--primary-color);
        }

        .alert {
            border-radius: 10px;
            max-width: 520px;
            width: 100%;
        }

        @media (max-width: 576px) {
            .form-section { padding: 28px 24px 32px; }
            .card-topo { padding: 24px; }
            .campo-codigo { font-size: 1.5rem; letter-spacing: 10px; }
        }
    </style>
</head>
<body>

    @if(session('status'))
        <div class="alert alert-success text-center mt-3">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger text-center mt-3">
            <ul class="mb-0 list-unstyled">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="drinkerito-card">
        <div class="card-topo">
            <div class="brand-logo"><i class="fas fa-cocktail me-2"></i>Drinkerito</div>
        </div>

        <div class="form-section">
            <div class="passos" aria-hidden="true">
                <span class="passo {{ $passo >= 1 ? 'ativo' : '' }}"></span>
                <span class="passo {{ $passo >= 2 ? 'ativo' : '' }}"></span>
                <span class="passo {{ $passo >= 3 ? 'ativo' : '' }}"></span>
            </div>

            @yield('conteudo')
        </div>
    </div>

</body>
</html>
