/**
 * Modal de "adicionar a coleção" da página da bebida.
 *
 * A lista chega por fetch: a página é pública, e as coleções são de quem está
 * olhando. O escapeHtml vem do window.Drinkerito, carregado pelo layout — os
 * nomes de coleção são texto livre do usuário.
 */
(function () {
    'use strict';

    const config = window.DrinkeritoColecao;
    if (!config) return;

    const lista = document.getElementById('colecaoLista');
    const erro = document.getElementById('colecaoErro');
    const campoNovo = document.getElementById('colecaoNova');
    const modal = document.getElementById('colecaoModal');

    function cabecalho() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': config.csrfToken,
        };
    }

    function desenhar(colecoes) {
        if (!colecoes.length) {
            lista.innerHTML = '<p class="text-muted mb-0">Você ainda não tem nenhuma coleção.</p>';
            return;
        }

        lista.innerHTML = colecoes.map(function (colecao) {
            const marcado = colecao.contem ? ' checked' : '';
            return '<div class="form-check">'
                + '<input class="form-check-input js-colecao" type="checkbox" value="'
                + colecao.cd_colecao + '" id="colecao-' + colecao.cd_colecao + '"' + marcado + '>'
                + '<label class="form-check-label" for="colecao-' + colecao.cd_colecao + '">'
                + window.Drinkerito.escapeHtml(colecao.nm_colecao)
                + '</label></div>';
        }).join('');

        lista.querySelectorAll('.js-colecao').forEach(function (caixa) {
            caixa.addEventListener('change', function () {
                alternar(caixa.value, caixa);
            });
        });
    }

    function carregar() {
        erro.textContent = '';
        lista.innerHTML = '<p class="text-muted mb-0">Carregando...</p>';

        fetch(config.paraBebidaUrl, { headers: cabecalho() })
            .then(function (r) { return r.json(); })
            .then(function (dados) { desenhar(dados.colecoes); })
            .catch(function () {
                lista.innerHTML = '<p class="text-danger mb-0">Não foi possível carregar suas coleções.</p>';
            });
    }

    function alternar(cdColecao, caixa) {
        const url = config.alternarUrl.replace('__CD__', cdColecao);

        fetch(url, { method: 'POST', headers: cabecalho() })
            .then(function (r) {
                if (!r.ok) throw new Error('falhou');
                return r.json();
            })
            .then(function (dados) {
                caixa.checked = dados.contem;
                erro.textContent = dados.message;
            })
            .catch(function () {
                // Devolve a caixa ao estado de antes: deixá-la marcada mentiria
                // sobre o que está salvo.
                caixa.checked = !caixa.checked;
                erro.textContent = 'Não foi possível salvar. Tente de novo.';
            });
    }

    function criar() {
        const nome = campoNovo.value.trim();
        if (!nome) return;

        erro.textContent = '';

        fetch(config.criarUrl, {
            method: 'POST',
            headers: cabecalho(),
            body: JSON.stringify({ nm_colecao: nome, cd_bebida: config.cdBebida }),
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, dados: d }; }); })
            .then(function (resposta) {
                if (!resposta.ok) {
                    erro.textContent = resposta.dados.message || 'Não foi possível criar a coleção.';
                    return;
                }
                campoNovo.value = '';
                carregar();
            })
            .catch(function () {
                erro.textContent = 'Não foi possível criar a coleção.';
            });
    }

    document.getElementById('colecaoCriarBtn').addEventListener('click', criar);
    modal.addEventListener('show.bs.modal', carregar);
})();
