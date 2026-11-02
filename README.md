# Stive Clothing — API

API REST Laravel 12 + PostgreSQL de la boutique **Stive Clothing**
(vêtements personnalisables, commande transmise par WhatsApp).

> Documentation complète : [`docs/DEVELOPPEMENT.md`](docs/DEVELOPPEMENT.md) ·
> Contrat d'interface : [`docs/openapi.yaml`](docs/openapi.yaml)

```bash
composer install
cp .env.example .env && php artisan key:generate
docker compose up -d db
php artisan migrate --seed
php artisan serve
```

| Endpoint | Description |
| --- | --- |
| `GET /api/v1/health` | État du service |
| `GET /api/v1/categories` | Catégories publiées |
| `GET /api/v1/products` | Catalogue filtrable et paginé |
| `GET /api/v1/products/{slug}` | Fiche produit, variantes et guide des mesures |
| `POST /api/v1/orders` | Création d'une demande (retourne la référence + lien WhatsApp) |
| `POST /api/v1/admin/login` | Connexion de l'administration (Sanctum) |
| `GET /api/v1/admin/orders` | Suivi des demandes (rôle requis) |
