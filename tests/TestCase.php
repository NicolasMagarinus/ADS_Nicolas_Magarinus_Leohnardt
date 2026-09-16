<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A suíte roda sem os assets buildados.
     *
     * 28 arquivos de Feature renderizam view, e toda view passa pelo layout,
     * que carrega o @vite. Sem public/build — que é gitignored, e portanto não
     * existe em clone novo nem em CI — cada um deles morreria com
     * ViteManifestNotFoundException, e o defeito real que o teste procurava
     * ficaria enterrado sob centenas de falhas idênticas.
     *
     * O que se perde: nenhum teste confere o HTML das tags de asset. O que
     * cobre esse buraco é o AssetsJsTest, que verifica o contrato pelo lado dos
     * fontes (entries, globais expostos, caminho declarado na view).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
