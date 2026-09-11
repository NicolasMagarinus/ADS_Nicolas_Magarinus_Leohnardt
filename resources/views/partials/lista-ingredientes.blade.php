{{--
    Lista de ingredientes de uma receita, usada pela tela de detalhe e pela
    aleatória — as duas montavam o mesmo markup.

    Espera $ingredientes no formato que Bebida::getBebida() devolve:
    cd_ingrediente, nm_ingrediente e ds_medida. O id pode faltar (receita
    montada por outro caminho), e nesse caso o nome fica em texto puro, em vez
    de virar um link para /ingrediente/ sem id.

    A medida fica fora do link de propósito: clicar em "(50 ml)" não deve
    navegar para lugar nenhum.
--}}
<ul class="list-unstyled">
    @foreach($ingredientes as $ingrediente)
        <li class="mb-1">
            <i class="fas fa-check-circle text-success me-2"></i>
            @if(!empty($ingrediente['cd_ingrediente']))
                <a href="{{ route('ingrediente.show', $ingrediente['cd_ingrediente']) }}"
                   class="text-decoration-none text-dark border-bottom border-secondary-subtle">
                    {{ $ingrediente['nm_ingrediente'] }}
                </a>
            @else
                {{ $ingrediente['nm_ingrediente'] }}
            @endif
            <span class="text-muted">({{ $ingrediente['ds_medida'] }})</span>
        </li>
    @endforeach
</ul>
