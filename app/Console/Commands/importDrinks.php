<?php

namespace App\Console\Commands;

use App\Http\Controllers\IntegracaoCockailController;
use Illuminate\Console\Command;

class importDrinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-drinks ' .  '{acao}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importação dos drinks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $acao = $this->argument('acao');
        switch ($acao)
        {
            case "getDrinks":
                IntegracaoCockailController::obterBebidas();
            break;

            case "getIngredients":
                IntegracaoCockailController::obterIngredientes();
            break;

            case "getDrinkIngredient":
                IntegracaoCockailController::obterBebidaIngrediente();
            break;
        }
    }
}
