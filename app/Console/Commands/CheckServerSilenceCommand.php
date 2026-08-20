<?php

namespace App\Console\Commands;

use App\Services\Servers\ServerHeartbeatService;
use Illuminate\Console\Command;

class CheckServerSilenceCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pingwise:servers:check-silence';

    /**
     * @var string
     */
    protected $description = 'Отметить серверы без heartbeat как молчащие';

    public function handle(ServerHeartbeatService $heartbeats): int
    {
        $this->info('Проверка тишины серверов...');

        $marked = $heartbeats->checkSilence();

        $this->info("Отмечено молчащих: {$marked}");

        return Command::SUCCESS;
    }
}
