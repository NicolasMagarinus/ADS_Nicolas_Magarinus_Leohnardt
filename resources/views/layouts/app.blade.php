<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.meta')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="//use.fontawesome.com/releases/v5.0.7/css/all.css">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

    {{-- No <head> de propósito: scripts com defer executam na ordem do
         documento, e o parcial do chatbot aparece antes do rodapé. Carregado
         mais abaixo, ele rodaria depois de quem lê window.Drinkerito. --}}
    @js('drinkerito.js')
</head>
<body>
    @include('partials.header')
    
    @include('partials.navigation')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.chatbot')

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>