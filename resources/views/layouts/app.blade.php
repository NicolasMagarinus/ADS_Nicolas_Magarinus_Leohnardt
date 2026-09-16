<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.meta')
    {{-- Bootstrap, os dois pacotes de ícones, o SweetAlert2 e o custom.css vêm
         daqui, bundlados: eram sete tags, quatro delas apontando para CDN de
         terceiro no caminho crítico de renderização.

         No <head> de propósito. @vite emite type="module", que é deferido, e
         módulos executam na ordem do documento — o parcial do chatbot aparece
         antes do rodapé e precisa encontrar window.Drinkerito já definido. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @include('partials.header')
    
    @include('partials.navigation')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.chatbot')

</body>
</html>