<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Ingrediente;
use Database\Seeders\BebidaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Depois de um migrate:fresh o catálogo só voltava chamando a OpenAI, o que
 * custa dinheiro a cada vez. Um seeder fixo resolve isso e ainda serve de
 * massa de teste.
 */
class BebidaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_popula_o_catalogo(): void
    {
        $this->seed(BebidaSeeder::class);

        $this->assertGreaterThanOrEqual(15, Bebida::count());
        $this->assertGreaterThan(0, Ingrediente::count());
        $this->assertGreaterThan(0, BebidaIngrediente::count());
    }

    public function test_toda_bebida_tem_ingredientes_e_preparo(): void
    {
        $this->seed(BebidaSeeder::class);

        foreach (Bebida::all() as $bebida) {
            $this->assertNotEmpty($bebida->ds_preparo, "{$bebida->nm_bebida} sem preparo");
            $this->assertGreaterThan(
                0,
                BebidaIngrediente::where('cd_bebida', $bebida->cd_bebida)->count(),
                "{$bebida->nm_bebida} sem ingredientes"
            );
        }
    }

    /**
     * Seeder que só funciona em banco vazio é armadilha para quem rodar sem
     * pensar.
     */
    public function test_rodar_duas_vezes_nao_duplica(): void
    {
        $this->seed(BebidaSeeder::class);
        $bebidas = Bebida::count();
        $ingredientes = Ingrediente::count();
        $vinculos = BebidaIngrediente::count();

        $this->seed(BebidaSeeder::class);

        $this->assertSame($bebidas, Bebida::count());
        $this->assertSame($ingredientes, Ingrediente::count());
        $this->assertSame($vinculos, BebidaIngrediente::count());
    }

    /**
     * O seeder escreve ingrediente pelo Ingrediente::normalizar(), que é o
     * caminho único do projeto. Gravar direto recriaria as duplicatas que a
     * migração de fusão já teve de limpar uma vez.
     */
    public function test_ingredientes_ficam_normalizados(): void
    {
        $this->seed(BebidaSeeder::class);

        $nomes = Ingrediente::pluck('nm_ingrediente')->all();
        $canonicos = array_map(
            fn ($nome) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($nome))),
            $nomes
        );

        $this->assertSame(
            count($canonicos),
            count(array_unique($canonicos)),
            'há ingredientes equivalentes gravados como linhas diferentes'
        );
    }

    public function test_massa_cobre_os_dois_tipos(): void
    {
        $this->seed(BebidaSeeder::class);

        $this->assertGreaterThan(0, Bebida::where('id_tipo', TipoBebida::Alcoolica)->count());
        $this->assertGreaterThanOrEqual(5, Bebida::where('id_tipo', TipoBebida::NaoAlcoolica)->count());
    }

    public function test_seeder_padrao_inclui_o_catalogo(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(15, Bebida::count());
    }
}
