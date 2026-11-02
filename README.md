# Stive Clothing — API

API REST **Laravel 12 + MySQL 8** de la boutique **Stive Clothing** :
catalogue de vêtements personnalisables, prise de mesures sur mesure,
demande de commande transmise par WhatsApp et administration sécurisée.

- 📘 **Guide de développement (chaque étape)** : [`docs/DEVELOPPEMENT.md`](docs/DEVELOPPEMENT.md)
- 🛠️ **Installation locale (outils + Git)** : [`docs/INSTALLATION.md`](docs/INSTALLATION.md)
- 📄 **Contrat OpenAPI 3.1** : [`docs/openapi.yaml`](docs/openapi.yaml) — aussi servi par `GET /docs/openapi.yaml`

## Démarrage rapide

```bash
composer install
cp .env.example .env && php artisan key:generate
docker compose up -d db            # MySQL 8.4 (+ base de test)
php artisan migrate --seed
php artisan storage:link
php tools/create-database.php stive_clothing_test stive   # base des tests (une fois)
php artisan stive:check-setup      # vérifie PHP, .env, base de dev et base de test
php artisan serve                  # http://localhost:8000
```

```bash
curl http://localhost:8000/api/v1/health
curl http://localhost:8000/api/v1/products
php artisan test                   # utilise stive_clothing_test, identifiants de .env
```

Comptes de démonstration : `admin@stive-clothing.test`, `conseiller@stive-clothing.test`,
`atelier@stive-clothing.test` — mot de passe `password` (démonstration uniquement).

## Endpoints principaux

| Méthode | Route | Accès |
| --- | --- | --- |
| GET | `/api/v1/health` | public |
| GET | `/api/v1/shop` | public — numéro WhatsApp, devise, tailles, statuts |
| GET | `/api/v1/categories` | public |
| GET | `/api/v1/products` | public — filtres `category`, `q`, `min_price`, `max_price`, `featured`, tri, pagination |
| GET | `/api/v1/products/{slug}` | public — galerie, variantes, guide des mesures |
| POST | `/api/v1/orders` | public — crée la demande, renvoie la référence + lien WhatsApp |
| POST | `/api/v1/admin/login` | public — jeton Sanctum |
| GET | `/api/v1/admin/me` · `/logout` | jeton |
| GET | `/api/v1/admin/stats` | jeton |
| GET | `/api/v1/admin/orders` · `/{id}` | jeton |
| PATCH | `/api/v1/admin/orders/{id}` | admin, conseiller |
| CRUD | `/api/v1/admin/products` · `/categories` · `/media` | admin |

## Principes

- **Prix recalculé côté serveur**, jamais accepté du client ; montants en entiers FCFA.
- **Instantané** des options et du prix dans chaque ligne de commande.
- **WhatsApp Click-to-Chat** : le serveur prépare le message, le client l'envoie.
- **Mensurations** : consentement obligatoire, accès restreint par rôle, IP hachée.
- **Erreurs** au format unique `{ message, code, errors?, request_id }`.
- **Limitation de débit** : 5 demandes/min et 5 connexions/min par IP.

## Licence

Propriétaire — usage réservé à Stive Clothing.
