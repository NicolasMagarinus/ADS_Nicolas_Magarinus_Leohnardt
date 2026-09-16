/*
 * Tela de envio de receita: o select de ingredientes com busca no servidor.
 *
 * Duas armadilhas moram nestas seis linhas, e as duas falham em silêncio —
 * o build passa, a página carrega, e o campo de ingrediente simplesmente
 * continua um <select> comum.
 *
 * A primeira é a ordem: o select2 é um plugin jQuery e procura o jQuery ao
 * ser avaliado, então jquery-global.js precisa ter rodado antes. Como
 * declarações de import são içadas, isso não se resolve escrevendo a
 * atribuição no meio deste arquivo — daí o módulo separado.
 *
 * A segunda é que, pelo caminho CommonJS (o que o Vite usa), o select2 não
 * se instala sozinho: exporta uma factory `function (root, jQuery)` que
 * precisa ser chamada. Um `import 'select2'` sem a chamada devolve a função
 * e nunca registra $.fn.select2.
 */
import $ from './jquery-global.js';
import instalarSelect2 from 'select2';

import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';

instalarSelect2(window, $);
