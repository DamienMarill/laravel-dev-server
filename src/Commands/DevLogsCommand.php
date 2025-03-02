<?php

namespace Marill\DevServe\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DevLogsCommand extends Command
{
    /**
     * Nom et signature de la commande.
     *
     * @var string
     */
    protected $signature = 'dev:logs
                            {service? : Service spécifique à surveiller (toutes par défaut)}
                            {--F|follow : Suivre les logs en temps réel (comme tail -f)}
                            {--L|lines=50 : Nombre de lignes à afficher}
                            {--clear : Effacer les logs avant affichage}
                            {--list : Lister tous les services disponibles}';

    /**
     * Description de la commande.
     *
     * @var string
     */
    protected $description = 'Affiche les logs des serveurs de développement';

    /**
     * Chemin des logs.
     */
    protected string $logsPath;

    /**
     * Indique si la commande a été interrompue.
     */
    protected bool $interrupted = false;

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
        $this->logsPath = storage_path('logs/dev-serve');
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Exécution de la commande.
     */
    public function handle()
    {
        // Vérifier si le répertoire de logs existe
        if (! File::exists($this->logsPath)) {
            $this->error("Le dossier des logs n'existe pas. Exécutez d'abord la commande dev:serve.");

            return Command::FAILURE;
        }

        // Lister les services disponibles si demandé
        if ($this->option('list')) {
            return $this->listServices();
        }

        // Récupérer le service demandé
        $service = $this->argument('service');

        // Effacer les logs si demandé
        if ($this->option('clear')) {
            return $this->clearLogs($service);
        }

        // Afficher les logs
        return $this->showLogs($service);
    }

    /**
     * Liste tous les services disponibles.
     */
    protected function listServices(): int
    {
        $logFiles = File::glob($this->logsPath.'/*.log');

        if (empty($logFiles)) {
            $this->error("Aucun fichier de log trouvé. Exécutez d'abord la commande dev:serve.");

            return Command::FAILURE;
        }

        $this->info('Services disponibles:');

        foreach ($logFiles as $file) {
            $serviceName = pathinfo($file, PATHINFO_FILENAME);
            // Vérifier si le fichier contient des données
            $size = File::size($file);
            $status = $size > 0 ? '<fg=green>actif</>' : '<fg=yellow>vide</>';
            $formattedSize = $this->formatBytes($size);

            $this->line(" • <options=bold>$serviceName</> - $status - $formattedSize");
        }

        $this->line('');
        $this->info('Pour consulter les logs d\'un service spécifique:');
        $this->line('  php artisan dev:logs nom_du_service --follow');
        $this->line('');

        return Command::SUCCESS;
    }

    /**
     * Efface les logs du service spécifié.
     */
    protected function clearLogs(?string $service): int
    {
        if ($service) {
            $logFile = $this->logsPath.'/'.$service.'.log';

            if (! File::exists($logFile)) {
                $this->error("Le service '$service' n'existe pas.");

                return Command::FAILURE;
            }

            // Effacer le contenu du fichier
            File::put($logFile, '');
            $this->info("Les logs du service '$service' ont été effacés.");
        } else {
            // Demander confirmation avant d'effacer tous les logs
            if (! $this->confirm('Voulez-vous vraiment effacer TOUS les logs de TOUS les services?', false)) {
                return Command::SUCCESS;
            }

            $logFiles = File::glob($this->logsPath.'/*.log');

            foreach ($logFiles as $file) {
                File::put($file, '');
                $this->line(' • Logs effacés: '.basename($file));
            }

            $this->info('Tous les logs ont été effacés.');
        }

        return Command::SUCCESS;
    }

    /**
     * Affiche les logs du service spécifié.
     */
    protected function showLogs(?string $service): int
    {
        // Capturer les interruptions (Ctrl+C) si possible
        if (! $this->isWindows && function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                $this->interrupted = true;
            });
        } else {
            // Message d'avertissement sur Windows uniquement si le mode follow est activé
            if ($this->option('follow')) {
                $this->warn('Mode "follow" limité sur Windows. Utilisez Ctrl+C pour arrêter.');
            }
        }

        $follow = $this->option('follow');
        $lines = $this->option('lines');

        if ($service) {
            $logFile = $this->logsPath.'/'.$service.'.log';

            if (! File::exists($logFile)) {
                $this->error("Le service '$service' n'existe pas.");

                return Command::FAILURE;
            }

            $this->displayLogs($logFile, $follow, $lines);
        } else {
            // Afficher tous les logs
            $logFiles = File::glob($this->logsPath.'/*.log');

            if (empty($logFiles)) {
                $this->error("Aucun fichier de log trouvé. Exécutez d'abord la commande dev:serve.");

                return Command::FAILURE;
            }

            // Utiliser la commande tail avec grep pour combiner les logs
            $this->displayCombinedLogs($logFiles, $follow, $lines);
        }

        return Command::SUCCESS;
    }

    /**
     * Vérifie si la commande 'tail' est disponible.
     */
    protected function isTailAvailable(): bool
    {
        // Sur Windows, vérifier spécifiquement si tail est disponible (ex: Git Bash, WSL)
        if ($this->isWindows) {
            $process = new Process(['where', 'tail']);
            $process->run();

            return $process->isSuccessful();
        }

        // Sur Unix, tail est généralement disponible
        return true;
    }

    /**
     * Lit les dernières lignes d'un fichier en PHP pur.
     */
    protected function readLastLinesPhp(string $filename, int $lines): array
    {
        $file = new \SplFileObject($filename, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $result = [];
        $offset = max(0, $lastLine - $lines);

        $file->seek($offset);

        while (! $file->eof()) {
            $result[] = $file->fgets();
        }

        return $result;
    }

    /**
     * Affiche les logs d'un fichier spécifique.
     */
    protected function displayLogs(string $logFile, bool $follow, int $lines): void
    {
        $serviceName = pathinfo($logFile, PATHINFO_FILENAME);

        $this->info("Affichage des logs pour le service: $serviceName".($follow ? ' (CTRL+C pour arrêter)' : ''));
        $this->line('');

        if (File::size($logFile) === 0) {
            $this->warn('Le fichier de log est vide.');

            return;
        }

        // Si tail n'est pas disponible ou que nous sommes sur Windows sans tail,
        // utiliser une implémentation PHP pure pour au moins afficher les lignes
        $tailAvailable = $this->isTailAvailable();

        if (! $tailAvailable) {
            $this->warn("Commande 'tail' non disponible. Utilisation d'une méthode alternative.");

            if ($follow) {
                $this->warn("Le mode 'follow' n'est pas pris en charge avec la méthode alternative.");
            }

            // Lire les dernières lignes avec PHP
            $lastLines = $this->readLastLinesPhp($logFile, $lines);
            $this->output->write($this->colorizeOutput(implode('', $lastLines)));

            return;
        }

        if ($follow) {
            // Utiliser tail -f pour suivre les logs en temps réel
            $process = new Process(['tail', '-f', '-n', (string) $lines, $logFile]);
            $process->setTimeout(null);
            $process->start();

            while ($process->isRunning() && ! $this->interrupted) {
                $output = $process->getIncrementalOutput();
                if (! empty($output)) {
                    $this->output->write($this->colorizeOutput($output));
                }

                $errorOutput = $process->getIncrementalErrorOutput();
                if (! empty($errorOutput)) {
                    $this->output->write("<fg=red>$errorOutput</>");
                }

                usleep(100000); // 0.1 secondes
            }

            // Arrêter le processus si nécessaire
            if ($process->isRunning()) {
                $process->stop();
            }
        } else {
            // Afficher les N dernières lignes sans suivre
            $process = new Process(['tail', '-n', (string) $lines, $logFile]);
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                $this->output->write($this->colorizeOutput($output));
            } else {
                $this->error('Erreur lors de la lecture du fichier: '.$process->getErrorOutput());
            }
        }
    }

    /**
     * Affiche les logs combinés de plusieurs fichiers.
     */
    protected function displayCombinedLogs(array $logFiles, bool $follow, int $lines): void
    {
        $this->info('Affichage des logs pour tous les services'.($follow ? ' (CTRL+C pour arrêter)' : ''));
        $this->line('');

        // Vérifier si tail est disponible
        if (! $this->isTailAvailable()) {
            $this->warn("Commande 'tail' non disponible. Affichage des logs individuellement.");

            // Afficher les logs de chaque fichier séparément
            foreach ($logFiles as $file) {
                $serviceName = pathinfo($file, PATHINFO_FILENAME);
                $this->line("\n<fg=cyan>=== $serviceName ===</>");

                // Lire les dernières lignes avec PHP
                $lastLines = $this->readLastLinesPhp($file, $lines);
                $this->output->write($this->colorizeOutput(implode('', $lastLines)));
            }

            return;
        }

        // Construire la commande pour combiner les logs
        $command = ['tail'];

        if ($follow) {
            $command[] = '-f';
        }

        $command[] = '-n';
        $command[] = (string) $lines;

        // Ajouter tous les fichiers de log
        $command = array_merge($command, $logFiles);

        // Exécuter la commande
        $process = new Process($command);
        $process->setTimeout(null);
        $process->start();

        while ($process->isRunning() && ! $this->interrupted) {
            $output = $process->getIncrementalOutput();
            if (! empty($output)) {
                $this->output->write($this->colorizeOutput($output));
            }

            $errorOutput = $process->getIncrementalErrorOutput();
            if (! empty($errorOutput)) {
                $this->output->write("<fg=red>$errorOutput</>");
            }

            usleep(100000); // 0.1 secondes
        }

        // Arrêter le processus si nécessaire
        if ($process->isRunning()) {
            $process->stop();
        }
    }

    /**
     * Ajoute des couleurs aux logs en fonction de leur contenu.
     */
    protected function colorizeOutput(string $output): string
    {
        $lines = explode("\n", $output);
        $colorized = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                $colorized[] = $line;

                continue;
            }

            // Colorer les erreurs
            if (strpos($line, '[ERROR]') !== false) {
                $line = "<fg=red>$line</>";
            } // Colorer les avertissements
            elseif (strpos($line, 'warning') !== false || strpos($line, 'Warning') !== false) {
                $line = "<fg=yellow>$line</>";
            } // Colorer les informations de démarrage/initialisation
            elseif (strpos($line, 'Server running') !== false || strpos($line, 'started') !== false) {
                $line = "<fg=green>$line</>";
            } // Colorer les noms des services
            elseif (preg_match('/\[(Laravel Server|Vite|Queue Worker|Task Scheduler|WebSockets|API Server)\]/', $line, $matches)) {
                $service = $matches[1];
                $color = match ($service) {
                    'Laravel Server' => 'green',
                    'Vite' => 'blue',
                    'Queue Worker' => 'yellow',
                    'Task Scheduler' => 'magenta',
                    'WebSockets' => 'magenta',
                    'API Server' => 'cyan',
                    default => 'white',
                };
                $line = preg_replace('/\[('.preg_quote($service).')\]/', "[<fg=$color>$1</>]", $line);
            }

            $colorized[] = $line;
        }

        return implode("\n", $colorized);
    }

    /**
     * Formate les octets en unités lisibles.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
