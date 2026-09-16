/*
 * Entry de JavaScript de toda página.
 *
 * Os três window.* abaixo não são preguiça: as 574 linhas de JavaScript que
 * ainda moram inline dentro das views não são módulos e continuam lendo estes
 * nomes do escopo global. Enquanto elas existirem, tirar qualquer um dos três
 * quebra uma tela — silenciosamente, e só a dela:
 *
 *   window.Drinkerito → busca ao vivo do cabeçalho (partials/header)
 *   window.Swal       → favoritos, cadastro_bebida/create, /index, bebida/show
 *   window.bootstrap  → perfil/index, que faz new bootstrap.Modal(...)
 *
 * Os testes de AssetsJsTest existem para não deixar isso ser removido por
 * engano. Ver docs/superpowers/specs/2026-09-16-migracao-vite-design.md
 */
import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';

// Define window.Drinkerito ao ser avaliado.
import './drinkerito.js';

window.bootstrap = bootstrap;
window.Swal = Swal;
