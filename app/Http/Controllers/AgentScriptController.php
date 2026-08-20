<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class AgentScriptController extends Controller
{
    public function install(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $script = File::get(base_path('agent/linux/install.sh'));
        $script = str_replace(
            [
                '__PINGWISE_URL__',
                '__HEARTBEAT_URL__',
                '__HEARTBEAT_SCRIPT_URL__',
            ],
            [
                $baseUrl,
                $baseUrl.'/api/v1/servers/heartbeat',
                $baseUrl.'/agent/heartbeat.sh',
            ],
            $script,
        );

        return $this->shellScript($script, 'install.sh');
    }

    public function heartbeat(): Response
    {
        $script = File::get(base_path('agent/linux/heartbeat.sh'));

        return $this->shellScript($script, 'heartbeat.sh');
    }

    protected function shellScript(string $script, string $filename): Response
    {
        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
