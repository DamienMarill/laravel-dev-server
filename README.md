# Laravel Dev Serve

[![Latest Version on Packagist](https://img.shields.io/packagist/v/marill/laravel-dev-serve.svg)](https://packagist.org/packages/marill/laravel-dev-serve)
[![Total Downloads](https://img.shields.io/packagist/dt/marill/laravel-dev-serve.svg)](https://packagist.org/packages/marill/laravel-dev-serve)
[![License](https://img.shields.io/packagist/l/marill/laravel-dev-serve.svg)](https://packagist.org/packages/marill/laravel-dev-serve)

**Laravel Dev Serve** est un outil pour démarrer et gérer tous vos serveurs de développement Laravel en parallèle, avec une interface conviviale pour surveiller leur état et leurs logs.

## 🚀 Fonctionnalités

- ✅ **Démarrage parallèle** de tous vos serveurs de développement
- ✅ **Surveillance automatique** et redémarrage en cas de plantage
- ✅ **Logs séparés** pour chaque service
- ✅ **Interface colorée** pour une meilleure lisibilité
- ✅ **Personnalisation complète** des serveurs via un fichier de configuration
- ✅ **Compatibilité CI/CD** pour les environnements d'intégration continue

## 📋 Installation

```bash
composer require marill/laravel-dev-serve --dev
```

Publiez la configuration (optionnel):

```bash
php artisan vendor:publish --tag=dev-serve-config
```

## 🔧 Utilisation

### Démarrer tous les serveurs

```bash
php artisan dev:serve
```

Ce qui lancera en parallèle:
- Le serveur Laravel (php artisan serve)
- Le serveur de développement frontend (npm run dev ou Vite)
- Le worker de queue
- Le scheduler

### Options disponibles

```bash
# Spécifier les ports
php artisan dev:serve --port=8000 --npm-port=3000

# Utiliser Vite au lieu de npm run dev
php artisan dev:serve --vite

# Désactiver certains serveurs
php artisan dev:serve --no-laravel --no-queue

# Utiliser une configuration personnalisée
php artisan dev:serve --config=config/dev-serve.php
```

### Consulter les logs

```bash
# Afficher les logs de tous les serveurs
php artisan dev:logs

# Suivre les logs en temps réel
php artisan dev:logs --follow

# Afficher les logs d'un service spécifique
php artisan dev:logs laravel --follow

# Afficher les dernières N lignes
php artisan dev:logs --lines=100

# Lister tous les services disponibles
php artisan dev:logs --list

# Effacer les logs
php artisan dev:logs --clear
php artisan dev:logs laravel --clear
```

## ⚙️ Configuration

Voici un exemple de configuration complète:

```php
// config/dev-serve.php
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
            'enabled' => true,
            'name' => 'Task Scheduler',
            'command' => 'php artisan schedule:work',
            'color' => 'magenta',
            'autoRestart' => true,
        ],
        // Ajoutez vos serveurs personnalisés ici
    ],
    'polling_interval' => 0.5, // En secondes
];
```

## 🧪 Tests

```bash
composer test
```

## 🔄 Changelog

Consultez le [CHANGELOG](CHANGELOG.md) pour les informations sur les versions récentes.

## ⚖️ Licence

Ce package est distribué sous la licence MIT. Voir [LICENSE.md](LICENSE.md) pour plus de détails.

## 🙏 Crédits

- [Damien Marill](https://marill.dev)
- [Claude Sonnet 3.7](https://claude.ai/)
