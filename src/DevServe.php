<?php

namespace Marill\DevServe;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DevServe
{
    /**
     * L'application.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Chemin des logs.
     *
     * @var string
     */
    protected $logsPath;

    /**
     * Crée une nouvelle instance du manager.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->logsPath = storage_path('logs/dev-serve');

        // Créer le répertoire de logs s'il n'existe pas
        if (! File::exists($this->logsPath)) {
            File::makeDirectory($this->logsPath, 0755, true);
        }
    }

    /**
     * Obtient le chemin des logs.
     *
     * @return string
     */
    public function getLogsPath()
    {
        return $this->logsPath;
    }

    /**
     * Démarre un processus en arrière-plan.
     *
     * @param  array|string  $command
     * @param  string  $logFile
     * @return \Symfony\Component\Process\Process
     */
    public function startProcess($command, $logFile = null)
    {
        if (is_string($command)) {
            $command = explode(' ', $command);
        }

        $process = new Process($command, null, null, null, null);
        $process->setTimeout(null);

        if ($logFile) {
            $logHandle = fopen($logFile, 'a+');

            $process->start(function ($type, $buffer) use ($logHandle) {
                $outputPrefix = $type === Process::ERR ? 'ERROR' : 'OUT';
                $line = '['.date('Y-m-d H:i:s')."] [{$outputPrefix}] {$buffer}";
                fwrite($logHandle, $line);
            });
        } else {
            $process->start();
        }

        return $process;
    }

    /**
     * Obtient la configuration des serveurs.
     *
     * @return array
     */
    public function getServersConfig()
    {
        return config('dev-serve.servers', []);
    }
}
