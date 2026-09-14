{{--
    Título, descrição e Open Graph de todas as páginas que estendem
    layouts.app. A página declara só o nome curto em @section('titulo'); o
    sufixo "— Drinkerito" mora aqui, num lugar só.

    Sobre o {!! !!} abaixo: o conteúdo das seções JÁ CHEGA ESCAPADO. O Blade
    aplica e() na forma inline — @section('titulo', $bebida->nm_bebida) — e na
    forma de bloco quem escapa é o {{ }} de dentro dela. Reimprimir com {{ }}
    escaparia de novo, e um drink chamado Gin & Tonic "Especial" apareceria
    como Gin &amp;amp; Tonic &amp;quot;Especial&amp;quot; na aba do navegador
    e no card do WhatsApp. Por isso a convenção: declare estas seções com a
    forma inline, e o escape acontece uma vez só, no Blade.
--}}
@php
    $nomePagina = trim($__env->yieldContent('titulo'));
    $titulo = $nomePagina !== ''
        ? $nomePagina.' — Drinkerito'
        : 'Drinkerito — sua rede social de receitas de bebidas';

    $descricao = trim($__env->yieldContent('descricao'))
        ?: 'Descubra, avalie e compartilhe receitas de drinks alcoólicos e sem álcool.';

    $ogImagem = trim($__env->yieldContent('og_imagem'));
    $ogTipo = trim($__env->yieldContent('og_tipo')) ?: 'website';
    $robots = trim($__env->yieldContent('robots'));

    // Fecha a URL híbrida das coleções: /colecao/12 e /colecao/12-nome-velho
    // redirecionam 301 para a forma canônica, e o canonical diz qual é ela
    // para quem linkar a forma antiga.
    //
    // O e() mora aqui, e não no {!! !!} lá embaixo, porque o conteúdo de
    // seção já chega escapado (a regra do topo deste arquivo) e url()->current()
    // chega cru. Escapando os dois no mesmo ponto, a saída sai escapada
    // exatamente uma vez.
    $canonical = trim($__env->yieldContent('canonical'));

    if ($canonical === '') {
        $atual = url()->current();
        $pagina = (int) request()->query('page');

        // Página 2 não é duplicata da 1.
        $canonical = e($pagina > 1 ? $atual.'?page='.$pagina : $atual);
    }
@endphp
    <title>{!! $titulo !!}</title>
    <meta name="description" content="{!! $descricao !!}">
    <link rel="canonical" href="{!! $canonical !!}">
@if($robots !== '')
    <meta name="robots" content="{!! $robots !!}">
@endif

    <meta property="og:site_name" content="Drinkerito">
    <meta property="og:type" content="{!! $ogTipo !!}">
    <meta property="og:url" content="{!! $canonical !!}">
    <meta property="og:title" content="{!! $titulo !!}">
    <meta property="og:description" content="{!! $descricao !!}">
@if($ogImagem !== '')
    <meta property="og:image" content="{!! $ogImagem !!}">
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif
