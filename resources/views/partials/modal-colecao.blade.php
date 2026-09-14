{{--
    Modal de "adicionar a coleção", incluído pela página da bebida.

    A lista vem por fetch em vez de vir renderizada: a página da bebida é
    pública e cacheável, e as coleções são de quem está olhando.
--}}
<div class="modal fade" id="colecaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar a uma coleção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="colecaoLista" class="mb-3">
                    <p class="text-muted mb-0">Carregando...</p>
                </div>

                <hr>

                <label class="form-label" for="colecaoNova">Nova coleção</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="colecaoNova"
                           maxlength="60" placeholder="Drinks de verão">
                    <button class="btn btn-primary" type="button" id="colecaoCriarBtn">Criar e adicionar</button>
                </div>
                <div class="form-text" id="colecaoErro"></div>
            </div>
        </div>
    </div>
</div>
