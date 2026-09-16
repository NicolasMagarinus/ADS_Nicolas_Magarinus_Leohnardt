/*
 * Põe o jQuery no window, e nada mais.
 *
 * Este arquivo existe por causa da ordem de avaliação dos módulos. O select2
 * é um plugin jQuery: ao ser avaliado, procura o jQuery para se registrar
 * nele. Escrito num módulo só —
 *
 *     import $ from 'jquery';
 *     window.jQuery = $;
 *     import 'select2';
 *
 * — não funciona: declarações de import são içadas e avaliadas antes de
 * qualquer instrução do corpo, então o select2 rodaria com o window ainda
 * limpo. Separando, quem importa este módulo antes do select2 garante que a
 * atribuição já aconteceu, porque o grafo é avaliado em profundidade e na
 * ordem dos imports.
 *
 * O $ também precisa ficar global porque as 87 linhas de JavaScript inline da
 * view de envio de receita não são módulo e o usam direto.
 */
import $ from 'jquery';

window.$ = window.jQuery = $;

export default $;
