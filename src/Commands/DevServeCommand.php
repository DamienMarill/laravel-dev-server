<?php

namespace Marill\DevServe\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DevServeCommand extends Command
{
    /**
     * Nom et signature de la commande.
     *
     * @var string
     */
    protected $signature = 'dev:serve
                            {--P|port=8000 : Port de démarrage pour le serveur Laravel}
                            {--N|npm-port=3000 : Port de démarrage pour le serveur npm}
                            {--vite : Démarrer Vite au lieu de npm run dev}
                            {--no-laravel : Ne pas démarrer le serveur Laravel}
                            {--no-npm : Ne pas démarrer npm}
                            {--no-queue : Ne pas démarrer le worker de queue}
                            {--no-scheduler : Ne pas démarrer le scheduler}
                            {--config= : Chemin vers un fichier de config personnalisé}';

    /**
     * Description de la commande.
     *
     * @var string
     */
    protected $description = 'Démarre tous les serveurs de développement en parallèle';

    /**
     * Collection des processus en cours d'exécution.
     */
    protected Collection $processes;

    /**
     * Collection des fichiers temporaires pour les logs.
     */
    protected Collection $logFiles;

    /**
     * Indique si la commande a été interrompue.
     */
    protected bool $interrupted = false;

    /**
     * Police utilisée pour l'affichage des titres.
     */
    protected string $asciiFont = 'small';

    /**
     * Indique si la commande s'exécute dans un environnement CI.
     */
    protected bool $isCI = false;

    /**
     * Chemin des logs.
     */
    protected string $logsPath;

    /**
     * Indique si nous sommes sur Windows.
     */
    protected bool $isWindows;

    /**
     * Initialisation de la commande.
     */
    public function __construct()
    {
        parent::__construct();
        $this->processes = collect();
        $this->logFiles = collect();
        $this->logsPath = storage_path('logs/dev-serve');
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Vérifier si nous sommes dans un environnement CI
        $this->isCI = env('CI', false);

        // Créer le répertoire de logs s'il n'existe pas
        if (!File::exists($this->logsPath)) {
            File::makeDirectory($this->logsPath, 0755, true);
        }
    }

    /**
     * Exécution de la commande.
     */
    public function handle()
    {
        // Capturer les interruptions (Ctrl+C) si possible
        if (!$this->isWindows && function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'handleInterrupt']);
            pcntl_signal(SIGTERM, [$this, 'handleInterrupt']);
        } else {
            $this->warn('Signal handling not available on this platform (Windows). Use Ctrl+C to stop, but server cleanup may not be complete.');
        }

        // Charger la configuration
        $config = $this->loadConfig();

        // Afficher le titre
        $this->displayTitle();

        // Démarrer les serveurs
        $this->startServers($config);

        // Surveiller les processus et les logs
        $this->monitorProcesses();

        // Nettoyer à la fin
        $this->cleanup();

        return Command::SUCCESS;
    }

    /**
     * Charge la configuration par défaut ou depuis un fichier.
     */
    /**
     * Charge la configuration par défaut ou depuis un fichier.
     */
    protected function loadConfig(): array
    {
        // Option 1: Config spécifiée via l'option --config
        $configPath = $this->option('config');
        if ($configPath && File::exists($configPath)) {
            $customConfig = include $configPath;
            $this->info("Configuration chargée depuis : {$configPath}");
            return $customConfig;
        }

        // Option 2: Config publiée standard (dev-serve.php)
        if (config()->has('dev-serve')) {
            $this->info("Configuration chargée depuis config/dev-serve.php");
            return config('dev-serve');
        }

        // Option 3: Config par défaut intégrée
        $this->info("Utilisation de la configuration par défaut");

        return [
            'servers' => [
                'laravel' => [
                    'enabled' => !$this->option('no-laravel'),
                    'name' => 'Laravel Server',
                    'command' => 'php artisan serve --port=' . $this->option('port'),
                    'color' => 'green',
                    'autoRestart' => true,
                ],
                'npm' => [
                    'enabled' => !$this->option('no-npm'),
                    'name' => $this->option('vite') ? 'Vite' : 'NPM',
                    'command' => $this->option('vite') ? 'npm run dev' : ('npm run dev -- --port=' . $this->option('npm-port')),
                    'color' => 'blue',
                    'autoRestart' => false,
                ],
                'queue' => [
                    'enabled' => !$this->option('no-queue'),
                    'name' => 'Queue Worker',
                    'command' => 'php artisan queue:work --tries=3 --timeout=90',
                    'color' => 'yellow',
                    'autoRestart' => true,
                ],
                'scheduler' => [
                    'enabled' => !$this->option('no-scheduler'),
                    'name' => 'Task Scheduler',
                    'command' => 'php artisan schedule:work',
                    'color' => 'magenta',
                    'autoRestart' => true,
                ],
            ],
            'polling_interval' => 0.5, // En secondes
        ];
    }

    /**
     * Affiche le titre de la commande.
     */
    protected function displayTitle(): void
    {
        if ($this->isCI) {
            $this->info('Dev Serve - Démarrage des serveurs de développement');
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('<fg=cyan>╔═════════════════════════════════════════════╗</>');
        $this->output->writeln('<fg=cyan>║                                             ║</>');
        $this->output->writeln('<fg=cyan>║</>     <fg=bright-magenta>DEV SERVE - ENVIRONNEMENT LOCAL</>     <fg=cyan>║</>');
        $this->output->writeln('<fg=cyan>║                                             ║</>');
        $this->output->writeln('<fg=cyan>╚═════════════════════════════════════════════╝</>');
        $this->output->writeln('');
        $this->info('Démarrage des serveurs... Appuyez sur Ctrl+C pour arrêter');
        $this->output->writeln('');
    }

    /**
     * Démarre tous les serveurs configurés.
     */
    protected function startServers(array $config): void
    {
        foreach ($config['servers'] as $key => $server) {
            if (!$server['enabled']) {
                $this->line("<fg=gray>• {$server['name']} : désactivé</>");
                continue;
            }

            $this->line("<fg={$server['color']}>• Démarrage de {$server['name']}...</>");

            // Créer un fichier de log pour ce serveur
            $logFile = $this->logsPath . '/' . $key . '.log';
            $this->logFiles->put($key, $logFile);

            // Démarrer le processus
            $process = $this->startProcess($key, $server, $logFile);
            $this->processes->put($key, [
                'process' => $process,
                'config' => $server,
            ]);
        }

        // Petit délai pour laisser les processus démarrer
        sleep(1);
    }

    /**
     * Démarre un processus unique.
     */
    protected function startProcess(string $key, array $server, string $logFile): Process
    {
        // Préparer la commande
        $command = $server['command'];
        if (is_string($command)) {
            $command = explode(' ', $command);
        }

        // Créer un nouveau processus
        $process = new Process($command, null, null, null, null);
        $process->setTimeout(null);

        // Fichier de log
        $logHandle = fopen($logFile, 'a+');

        // Démarrer le processus
        $process->start(function ($type, $buffer) use ($key, $server, $logHandle) {
            $outputPrefix = $type === Process::ERR ? 'ERROR' : 'OUT';
            $line = "[" . date('Y-m-d H:i:s') . "] [{$server['name']}] [{$outputPrefix}] {$buffer}";
            fwrite($logHandle, $line);
        });

        $this->line("<fg={$server['color']}>  → {$server['name']} démarré | PID: {$process->getPid()} | Log: " . basename($logFile) . "</>");

        return $process;
    }

    /**
     * Surveillance des processus et gestion des logs.
     */
    protected function monitorProcesses(): void
    {
        $this->output->writeln('');
        $this->info('Tous les serveurs sont démarrés. Appuyez sur Ctrl+C pour arrêter.');
        $this->output->writeln('');
        $this->line('┌─' . str_repeat('─', 60) . '┐');
        $this->line('│ <fg=bright-yellow>STATUS SERVEURS</>                                             │');
        $this->line('├─' . str_repeat('─', 60) . '┤');

        $maxNameLength = $this->processes->max(fn($data) => strlen($data['config']['name'])) + 2;

        foreach ($this->processes as $key => $data) {
            $name = str_pad($data['config']['name'], $maxNameLength);
            $color = $data['config']['color'];
            $process = $data['process'];

            if ($process->isRunning()) {
                $status = "<fg=green>● ACTIF</>";
            } else {
                $exitCode = $process->getExitCode();
                $status = $exitCode === 0
                    ? "<fg=yellow>○ TERMINÉ</>"
                    : "<fg=red>✕ ÉCHEC ({$exitCode})</>";
            }

            $pid = $process->isRunning() ? $process->getPid() : 'N/A';
            $this->line("│ <fg={$color}>{$name}</> {$status} | PID: {$pid} | Log: " . basename($this->logFiles->get($key)) . " │");
        }

        $this->line('└─' . str_repeat('─', 60) . '┘');

        $this->output->writeln('');
        $this->line('Pour consulter les logs:');
        $this->line('  <fg=cyan>• Tous: </><fg=white>tail -f ' . $this->logsPath . '/*.log</>');

        foreach ($this->processes as $key => $data) {
            $color = $data['config']['color'];
            $this->line("  <fg={$color}>• {$data['config']['name']}: </><fg=white>tail -f " . $this->logFiles->get($key) . "</>");
        }

        $this->output->writeln('');

        // Boucle de surveillance des processus
        while (!$this->interrupted) {
            // Vérifier les processus
            foreach ($this->processes as $key => $data) {
                $process = $data['process'];
                $config = $data['config'];

                // Si le processus s'est terminé et qu'il doit redémarrer automatiquement
                if (!$process->isRunning() && $config['autoRestart'] && !$this->interrupted) {
                    $this->line("<fg={$config['color']}>• {$config['name']} s'est arrêté. Redémarrage...</>");

                    // Redémarrer le processus
                    $process = $this->startProcess($key, $config, $this->logFiles->get($key));
                    $this->processes[$key]['process'] = $process;
                }
            }

            // Attendre un peu avant la prochaine vérification
            usleep(500000); // 0.5 secondes
        }
    }

    /**
     * Gère l'interruption de la commande (CTRL+C).
     */
    public function handleInterrupt(): void
    {
        $this->interrupted = true;
        $this->line('');
        $this->info('Arrêt des serveurs en cours...');

        // Arrêter tous les processus
        foreach ($this->processes as $key => $data) {
            $process = $data['process'];
            $config = $data['config'];

            if ($process->isRunning()) {
                $this->line("<fg={$config['color']}>• Arrêt de {$config['name']}...</>");
                // Sur Windows, on ne peut pas utiliser SIGINT, donc on utilise juste stop()
                if ($this->isWindows) {
                    $process->stop();
                } else {
                    $process->stop(3, SIGINT); // Envoyer un SIGINT (Ctrl+C)
                }
            }
        }

        $this->cleanup();

        // Ne pas appeler exit(0) si nous ne sommes pas dans un handler de signal
        // car cela interromprait le flux normal de l'exécution
        if (!$this->isWindows && function_exists('pcntl_async_signals')) {
            exit(0);
        }
    }

    /**
     * Nettoyage des ressources.
     */
    protected function cleanup(): void
    {
        // Fermer les fichiers de logs
        foreach ($this->logFiles as $logFile) {
            if (is_resource($logFile)) {
                fclose($logFile);
            }
        }
    }
}
