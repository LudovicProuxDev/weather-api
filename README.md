# Weather API ☀️

Application web de météo en français. Elle affiche les prévisions **heure par heure** du modèle **Météo-France**, fournies par [Open-Meteo](https://open-meteo.com/), pour n'importe quelle ville. Chaque utilisateur connecté peut enregistrer ses villes et retrouver la météo du jour sur sa page d'accueil.

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Stack technique](#stack-technique)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Lancer l'application](#lancer-lapplication)
- [Comptes de démonstration](#comptes-de-démonstration)
- [Configuration](#configuration)
- [Tests et qualité](#tests-et-qualité)
- [Docker](#docker)
- [Architecture](#architecture)
- [Sécurité](#sécurité)
- [Dépannage](#dépannage)

## Fonctionnalités

- **Recherche de ville** par son nom, avec l'API de géocodage d'Open-Meteo. Les résultats indiquent le département, la région et le pays.
- **Prévisions sur 4 jours, heure par heure** : température, température ressentie, humidité, point de rosée, précipitations, pluie et neige.
- **Mes localisations** : chaque utilisateur enregistre jusqu'à 20 villes.
- **Page d'accueil** avec la météo du jour de chaque ville enregistrée (température actuelle, minimum, maximum, précipitations). Les prévisions sont chargées en différé, pour que la page s'affiche immédiatement.
- **Comptes utilisateurs** : inscription, vérification de l'adresse e-mail, réinitialisation du mot de passe, double authentification (TOTP) et clés d'accès (passkeys).
- **Interface en français**, avec un mode clair et un mode sombre.

## Stack technique

| Couche           | Technologie                                                        |
| ---------------- | ------------------------------------------------------------------ |
| Back-end         | PHP 8.5, Laravel 13, Laravel Fortify                               |
| Front-end        | React 19, Inertia.js v3, TypeScript, Tailwind CSS 4, Vite+         |
| Routes typées    | Laravel Wayfinder                                                  |
| Base de données  | PostgreSQL 18                                                      |
| Données météo    | Open-Meteo (modèle `meteofrance_seamless`) et son API de géocodage |
| Serveur (Docker) | FrankenPHP (Caddy)                                                 |
| Qualité          | PHPUnit, PHPStan, Laravel Pint, Vite+ (lint et formatage)          |

## Prérequis

- **PHP 8.5** et **Composer 2**. Sous Linux ou WSL, le plus simple est [php.new](https://php.new).
- **Node.js 22 ou plus récent** (24 LTS recommandé), avec **npm 11 ou plus récent** (fourni avec Node.js 24).
- **Docker** et **Docker Compose**, pour PostgreSQL et l'image de production.

## Installation

```bash
git clone https://github.com/LudovicProuxDev/weather-api.git
cd weather-api

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Choisissez ensuite un mot de passe pour la base de données et renseignez-le dans `.env` (voir [Configuration](#configuration)) :

```bash
openssl rand -hex 24   # copier le résultat dans DB_PASSWORD
```

Démarrez PostgreSQL, puis créez les tables :

```bash
docker compose up -d db
php artisan migrate
```

> Au premier démarrage, le conteneur PostgreSQL crée aussi la base `weather_api_testing`, utilisée par les tests.

## Lancer l'application

```bash
docker compose up -d db   # si PostgreSQL n'est pas déjà démarré
composer run dev
```

L'application est accessible sur **http://localhost:8000**. La commande `composer run dev` lance en parallèle le serveur Laravel, Vite (avec rechargement à chaud), le worker de file d'attente et l'affichage des logs.

## Comptes de démonstration

Les comptes de démonstration sont décrits dans un fichier **local et confidentiel**, `.env.demo-users`. Il est ignoré par git et par Docker. Chaque compte y est défini sur ce modèle :

```dotenv
DEMO_USERS_COUNT=1

DEMO_USER_1_FIRSTNAME="Camille"
DEMO_USER_1_LASTNAME="Dubois"
DEMO_USER_1_EMAIL="camille.dubois@weather-api.test"
DEMO_USER_1_PASSWORD="un-mot-de-passe-robuste"
DEMO_USER_1_CITIES="2988507,2996944"   # identifiants Open-Meteo (ici Paris et Lyon)
```

Pour créer les comptes, ou les mettre à jour après une modification du fichier :

```bash
php artisan db:seed --class=DemoUserSeeder
```

Les comptes sont créés avec une adresse e-mail déjà vérifiée et les villes indiquées dans « Mes localisations ». Le seeder refuse de s'exécuter en production.

> ⚠️ Ne renommez pas ce fichier en `.env.local` : quand `APP_ENV=local`, Laravel chargerait ce fichier **à la place** de `.env`.

## Configuration

Les principales variables de `.env` :

| Variable                         | Rôle                                                        | Valeur par défaut      |
| -------------------------------- | ----------------------------------------------------------- | ---------------------- |
| `APP_NAME`                       | Nom affiché de l'application                                | `"Weather API"`        |
| `APP_LOCALE`                     | Langue de l'application                                     | `fr`                   |
| `DB_HOST`                        | Hôte PostgreSQL (voir [Dépannage](#dépannage) sous WSL)     | `127.0.0.1`            |
| `DB_DATABASE` / `DB_USERNAME`    | Base et utilisateur PostgreSQL                              | `weather_api`          |
| `DB_PASSWORD`                    | Mot de passe PostgreSQL, **à définir obligatoirement**      | _(vide)_               |
| `SESSION_SECURE_COOKIE`          | Cookies réservés au HTTPS (à mettre à `true` en production) | `false`                |
| `OPEN_METEO_MODEL`               | Modèle de prévision Open-Meteo                              | `meteofrance_seamless` |
| `OPEN_METEO_FORECAST_DAYS`       | Nombre de jours de prévision                                | `4`                    |
| `OPEN_METEO_CACHE_TTL`           | Durée du cache des prévisions, en secondes                  | `1800`                 |
| `OPEN_METEO_GEOCODING_CACHE_TTL` | Durée du cache des recherches de villes, en secondes        | `86400`                |

Open-Meteo ne demande **aucune clé d'API**. Les autres réglages d'Open-Meteo sont dans `config/services.php`.

## Tests et qualité

```bash
php artisan test                # suite PHPUnit (sur la base weather_api_testing)
vendor/bin/pint                 # formatage du code PHP
vendor/bin/phpstan analyse --memory-limit=1G
npm run check                   # lint et formatage du front
npm run types:check             # vérification TypeScript
composer ci:check               # tout ce qui précède, comme en CI
```

Les appels à Open-Meteo sont simulés dans les tests (`Http::fake`) : aucune requête réseau réelle n'est faite.

La **CI GitHub Actions** (`.github/workflows/tests.yml`) lance `composer ci:check` à chaque push sur `main` et à chaque pull request. Elle utilise un conteneur PostgreSQL éphémère, sans mot de passe dans le dépôt.

## Docker

La stack `compose.yaml` comprend quatre services :

| Service     | Rôle                                                                                     |
| ----------- | ---------------------------------------------------------------------------------------- |
| `app`       | Application servie par FrankenPHP sur le port **8080** (migrations lancées au démarrage) |
| `queue`     | Worker de la file d'attente                                                              |
| `scheduler` | Tâches planifiées                                                                        |
| `db`        | PostgreSQL 18, accessible uniquement depuis la machine (`127.0.0.1:5432`)                |

```bash
docker compose up -d --build   # construire et démarrer
docker compose logs -f         # suivre les logs
docker compose down            # arrêter (les données sont conservées)
docker compose down -v         # arrêter et supprimer les données
```

L'application est alors accessible sur **http://localhost:8080**. Le port se change avec `APP_PORT`. `APP_KEY` et `DB_PASSWORD` sont lus dans `.env` : Compose refuse de démarrer s'ils manquent.

L'image `Dockerfile` est construite en plusieurs étapes (dépendances Composer, compilation du front, image finale). Dans l'image finale, PHP 8.5 est configuré pour la production avec OPcache, l'application tourne avec un utilisateur non-root, et les sessions sont chiffrées.

## Architecture

```
app/
├── Actions/Weather/
│   ├── FetchHourlyForecast.php    # Prévisions Open-Meteo, regroupées par jour, en cache
│   └── GeocodeCities.php          # Recherche de villes et lecture d'une ville par son ID
├── Concerns/CallsOpenMeteo.php    # Client HTTP commun (timeouts, nouvelles tentatives)
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php        # Page d'accueil (prévisions différées)
│   │   ├── WeatherForecastController.php  # Page Météo et recherche de ville
│   │   └── SavedLocationController.php    # Ajout et retrait de localisations
│   ├── Middleware/SecurityHeaders.php     # En-têtes de sécurité HTTP
│   └── Requests/                          # Validation des entrées
├── Models/        # User, SavedLocation
└── Policies/      # SavedLocationPolicy
database/seeders/DemoUserSeeder.php        # Comptes de démonstration
lang/fr/, lang/fr.json                     # Traductions françaises
resources/js/
├── pages/         # dashboard.tsx, weather.tsx, welcome.tsx, auth/, settings/
├── components/    # Composants d'interface
└── lib/weather.ts # Formatage commun (dates, températures, couleurs)
```

### Routes principales

| Méthode  | URL                       | Description                                                                                                    |
| -------- | ------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `GET`    | `/`                       | Page de présentation publique                                                                                  |
| `GET`    | `/dashboard`              | Accueil : météo du jour de mes villes                                                                          |
| `GET`    | `/weather`                | Page Météo. Paramètres : `?search=…` (recherche), `?city=<id>` (ville Open-Meteo) ou `?latitude=…&longitude=…` |
| `POST`   | `/weather/locations`      | Enregistrer une ville (`city_id`)                                                                              |
| `DELETE` | `/weather/locations/{id}` | Retirer une ville enregistrée                                                                                  |
| —        | `/settings/*`             | Profil, sécurité (mot de passe, 2FA, clés d'accès), apparence                                                  |

Toutes les routes, sauf `/`, demandent d'être connecté avec une adresse e-mail vérifiée. Pour la liste complète : `php artisan route:list`.

## Sécurité

- **Authentification** avec Fortify : limitation des tentatives de connexion, double authentification TOTP, clés d'accès, vérification de l'adresse e-mail et confirmation du mot de passe pour les pages sensibles.
- **Autorisation** : un utilisateur ne peut retirer que ses propres localisations. Une tentative sur la localisation d'un autre renvoie une `404`, pour ne pas révéler qu'elle existe.
- **Données non falsifiables** : pour enregistrer une ville, le client n'envoie que son identifiant. Le nom et les coordonnées sont récupérés côté serveur auprès d'Open-Meteo.
- **Limites de requêtes** : 60 requêtes par minute sur les pages Météo et Accueil, 20 par minute pour l'enregistrement de villes. Le cache limite aussi la consommation du quota Open-Meteo.
- **En-têtes HTTP** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` et `Cross-Origin-Opener-Policy`, plus `Strict-Transport-Security` en HTTPS.
- **Secrets** : aucun secret dans le dépôt. `.env` et `.env.demo-users` sont ignorés par git et par Docker. Les dépendances sont vérifiées avec `composer audit` et `npm audit`.

## Dépannage

**Connexions à PostgreSQL coupées sous WSL** (`SQLSTATE[08006] … server closed the connection unexpectedly`). Sous WSL, le proxy de ports de Docker coupe une partie des connexions à `127.0.0.1:5432`. Le conteneur `db` a une adresse fixe sur le réseau Docker : indiquez-la dans `.env` pour s'en passer.

```dotenv
DB_HOST=172.30.0.10
```

Cette adresse n'est pas joignable depuis Docker Desktop (macOS ou Windows sans WSL) : dans ce cas, gardez `127.0.0.1`.

**Page blanche ou ressources introuvables** (`ERR_CONNECTION_REFUSED` sur le port 5173). Le serveur Vite s'est arrêté ou bloqué. Relancez `composer run dev`, ou compilez le front avec `npm run build` puis supprimez `public/hot`.

**`npm install` échoue avec « Invalid comparator »**. La version de npm est trop ancienne : passez à Node.js 24 LTS (npm 11).
