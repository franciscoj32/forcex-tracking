<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\Productivity\BoardService;
use Illuminate\Console\Command;

/**
 * Publica en la pizarra los cumpleanos de hoy y avisa al equipo.
 * No duplica si se corre varias veces. Con el modulo apagado no hace nada.
 */
class PostBirthdays extends Command
{
    protected $signature = 'forcex:cumpleanos';

    protected $description = 'Publica los cumpleanos del dia en la pizarra de oficina';

    public function handle(BoardService $board): int
    {
        if (! AppSetting::moduleEnabled('productividad')) {
            return self::SUCCESS;
        }

        $created = $board->postBirthdays();
        $this->info('Cumpleaños publicados hoy: '.$created->count().'.');

        return self::SUCCESS;
    }
}
