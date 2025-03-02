<?php

// config for Marill/DevServe
return [
    'servers' => [
        'laravel' => [
            'enabled' => true,
            'name' => 'Laravel Server',
            'command' => 'php artisan serve --host=0.0.0.0 --port=8000',
            'color' => 'green',
            'autoRestart' => true,
        ],
        'vite' => [
            'enabled' => true,
            'name' => 'Vite Dev Server',
            'command' => 'npm run dev',
            'color' => 'blue',
            'autoRestart' => false,
        ],
        'queue' => [
            'enabled' => true,
            'name' => 'Queue Worker',
            'command' => 'php artisan queue:work --tries=3 --timeout=90',
            'color' => 'yellow',
            'autoRestart' => true,
        ],
        'scheduler' => [
            'enabled' => false,
            'name' => 'Task Scheduler',
            'command' => 'php artisan schedule:work',
            'color' => 'magenta',
            'autoRestart' => true,
        ],
        // Exemple de serveur API supplémentaire
        'api' => [
            'enabled' => false, // Désactivé par défaut
            'name' => 'API Server',
            'command' => 'php artisan serve --port=8001',
            'color' => 'cyan',
            'autoRestart' => true,
        ],
        // Exemple d'un serveur WebSockets
        'websockets' => [
            'enabled' => false, // Désactivé par défaut
            'name' => 'WebSockets',
            'command' => 'php artisan websockets:serve',
            'color' => 'magenta',
            'autoRestart' => true,
        ],
        'reverb' => [
            'enabled' => false,
            'name' => 'Reverb',
            'command' => 'php artisan reverb:serve',
            'color' => 'magenta',
            'autoRestart' => true,
        ],
        // Exemple de commande personnalisée
        'custom' => [
            'enabled' => false, // Désactivé par défaut
            'name' => 'Service Personnalisé',
            'command' => 'echo "Service démarré" && sleep 60', // Exemple simple
            'color' => 'white',
            'autoRestart' => false,
        ],
    ],
    'polling_interval' => 0.5, // En secondes
];
