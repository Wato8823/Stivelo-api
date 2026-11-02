# Stive Clothing — Guide de développement de l'API

Ce document décrit l'API livrée et **chaque étape de développement**, dans
l'ordre où le dépôt a été construit. Chaque étape correspond à un commit de la
branche et se termine par des critères de recette vérifiables.

Le cadrage fonctionnel de référence est le cahier des charges du projet
(boutique de vêtements personnalisables, commande transmise par WhatsApp,
devise FCFA, architecture Angular + Laravel + PostgreSQL).

---

## 0. Vue d'ensemble

| Élément | Choix retenu |
| --- | --- |
| Framework API | Laravel 12 (PHP 8.2+) |
| Base de données | PostgreSQL 16 |
| Authentification admin | Laravel Sanctum (jetons Bearer, 8 h) |
| Frontend cible | Angular (consomme `/api/v1`) |
| Contrat d'interface | OpenAPI 3.1 — [`docs/openapi.yaml`](openapi.yaml) |
| Canal de commande MVP | WhatsApp Click-to-Chat (aucun envoi automatique) |
| Devise | FCFA (XAF), montants en entiers |
| Tests | PHPUnit 11 + `RefreshDatabase` sur PostgreSQL |
| Style | Laravel Pint |

### Cartographie de l'API

| Méthode | Route | Accès | Rôle |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | public | état du service |
| GET | `/api/v1/shop` | public | numéro WhatsApp, devise, tailles, bornes de mesures, statuts |
| GET | `/api/v1/categories` | public | familles publiées + nombre de modèles |
| GET | `/api/v1/products` | public | catalogue filtrable et paginé |
| GET | `/api/v1/products/{slug}` | public | fiche complète (galerie, variantes, mesures, lien WhatsApp) |
| POST | `/api/v1/orders` | public, `throttle:orders` | crée la demande, renvoie référence + lien WhatsApp |
| POST | `/api/v1/admin/login` | public, `throttle:login` | jeton Sanctum |
| GET | `/api/v1/admin/me` | jeton | profil, rôle et droits |
| POST | `/api/v1/admin/logout` | jeton | révoque le jeton courant |
| GET | `/api/v1/admin/stats` | jeton | tableau de bord |
| GET | `/api/v1/admin/orders` | jeton | suivi des demandes (filtres statut, recherche, dates) |
| GET | `/api/v1/admin/orders/{id}` | jeton | dossier complet + journal |
| PATCH | `/api/v1/admin/orders/{id}` | admin, conseiller | changement d'état contrôlé |
| GET | `/api/v1/admin/products` | jeton | catalogue administrable (brouillons, archivés) |
| POST | `/api/v1/admin/products` | admin | création d'un modèle |
| PUT/PATCH | `/api/v1/admin/products/{id}` | admin | mise à jour |
| DELETE | `/api/v1/admin/products/{id}` | admin | archivage logique |
| POST | `/api/v1/admin/products/{id}/media` | admin | téléversement d'une photo |
| DELETE | `/api/v1/admin/products/{id}/media/{media}` | admin | suppression d'une photo |
| GET/POST | `/api/v1/admin/categories` | admin | liste / création |
| PUT/DELETE | `/api/v1/admin/categories/{id}` | admin | mise à jour / suppression |

---

## Étape 1 — Cadrage technique et socle du projet

**Commit** : `chore(bootstrap): squelette Laravel 12 + PostgreSQL, routing /api/v1 et configuration métier`

### Objectifs

- Partir du squelette officiel Laravel 12 (aucune dette de version).
- Isoler toute la configuration métier hors du code.
- Préfixer l'API par `/api/v1` dès le premier jour (versionnage).

### Réalisations

- `composer.json` : Laravel 12, Sanctum 4, PHPUnit 11, Pint ; scripts `test`,
  `lint`, `format`.
- `bootstrap/app.php` : `apiPrefix: 'api/v1'`, routes API séparées.
- `config/catalog.php` : numéro WhatsApp, devise, statuts et transitions,
  bornes des mesures (20–250 cm), tailles standard (S → XXL), limites de débit,
  contraintes médias. **Aucune de ces valeurs n'est codée en dur ailleurs.**
- `config/sanctum.php` : expiration des jetons pilotée par `SANCTUM_EXPIRATION`
  (480 min par défaut).
- `.env.example` : connexion PostgreSQL, WhatsApp, devise, disque médias, S3.
- `docker-compose.yml` : PostgreSQL 16 prêt à l'emploi.
- `phpunit.xml` : environnement de test (PostgreSQL dédié, cache array, clé fixe).
- `GET /api/v1/health` : première route, utile aux sondes de disponibilité.

### Commandes

```bash
composer install
cp .env.example .env && php artisan key:generate
docker compose up -d db
```

### Critères de recette

- `php artisan route:list` liste les routes sous `api/v1`.
- `GET /api/v1/health` répond `{"status":"ok"}`.
- Aucune valeur métier n'apparaît en dur dans `app/`.

---

## Étape 2 — Modèle de données PostgreSQL

**Commit** : `feat(database): schéma PostgreSQL (catalogue, options, mesures, commandes, journal) et énumérations métier`

### Objectifs

- Traduire le modèle de données du cahier des charges en migrations versionnées.
- Poser les contraintes d'intégrité et les index dès l'origine.
- Représenter les états métier par des énumérations PHP typées.

### Tables créées

| Table | Rôle | Points clés |
| --- | --- | --- |
| `users` | comptes internes | `role` (admin/advisor/atelier), `active_at` |
| `categories` | 7 familles | `slug` unique, `sort_order`, `is_active` |
| `products` | modèles | `base_price` entier FCFA, `status`, `deleted_at` (archivage) |
| `product_media` | photos | `disk` + `storage_key` (S3 en production), `alt` obligatoire |
| `product_options` | couleurs / tissus / tailles | unicité `(product_id, type, value)`, `price_delta` |
| `product_measurement_fields` | guide des mesures | `key`, `label`, `is_required`, bornes `min_cm`/`max_cm` |
| `orders` | demandes | `reference` unique, consentement, `total_estimate`, IP hachée |
| `order_items` | lignes | `product_snapshot` JSONB figé, `line_total` |
| `measurements` | mensurations | une valeur par mesure et par ligne |
| `order_events` | journal | ancien/nouvel état, auteur, note |
| `personal_access_tokens` | jetons Sanctum | fourni par le paquet |

### Énumérations

`App\Enums\UserRole`, `OrderStatus` (avec `allowedTransitions()`),
`ProductOptionType`, `ProductStatus`, `OrderChannel`.

### Critères de recette

- `php artisan migrate:fresh` s'exécute sans erreur sur PostgreSQL.
- Les index couvrent les filtres réels : `(status, created_at)`,
  `(category_id, status)`, `(product_id, type, is_active)`.
- Aucun montant monétaire en flottant.

---

## Étape 3 — Modèles Eloquent, usines et catalogue de démonstration

**Commit** : `feat(models): modèles Eloquent, usines de données, catalogue de démonstration et visuels`

### Objectifs

- Exposer des modèles typés (casts enum, dates, JSONB) et des scopes réutilisables.
- Disposer de données de démonstration réalistes pour Angular.
- Générer des visuels sans dépendance externe.

### Réalisations

- Modèles : `User`, `Category`, `Product`, `ProductMedia`, `ProductOption`,
  `ProductMeasurementField`, `Order`, `OrderItem`, `Measurement`, `OrderEvent`.
- Scopes : `published()`, `active()`, `ordered()`, `search()`,
  `priceBetween()`, `withStatus()`.
- `Order::newReference()` : `STV-2026-00A31`, alphabet sans caractères ambigus.
- Usines pour toutes les entités (tests reproductibles).
- `CategorySeeder` : les 7 familles du cahier des charges.
- `CatalogSeeder` : 12 modèles, couleurs/tissus/tailles, 4 profils de mesures
  (costume, chemise, pantalon, ensemble, tenue), 8 visuels.
- `AdminUserSeeder` : comptes de démonstration (admin, conseiller, atelier).
- `DemoOrderSeeder` : 3 demandes (neuve, en confection, livrée) avec mesures
  et journal.
- `tools/generate_demo_media.py` : génère les PNG de `public/demo/products`
  (bibliothèque standard uniquement, reproductible).

### Commandes

```bash
php artisan migrate:fresh --seed
python3 tools/generate_demo_media.py   # regénère les visuels de démonstration
```

### Critères de recette

- `php artisan db:seed` est idempotent (relançable sans doublon).
- Le catalogue contient 7 catégories et 12 modèles publiés.
- Comptes : `admin@stive-clothing.test`, `conseiller@…`, `atelier@…` (mot de passe
  `password`, à changer immédiatement).

---

## Étape 4 — Authentification, rôles et erreurs normalisées

**Commit** : `feat(auth): authentification Sanctum, rôles, policies, format d'erreur unique et limitation de débit`

### Objectifs

- Protéger l'administration sans complexifier l'API.
- Accorder le minimum de droits par rôle.
- Renvoyer **un seul format d'erreur**, exploitable directement par Angular.

### Réalisations

- `POST /admin/login` : jeton Sanctum nommé (`device_name`), expiration 8 h,
  refus des comptes désactivés (`account_disabled`).
- Middleware : `AssignRequestId` (en-tête `X-Request-Id`, contexte de logs),
  `ForceJsonResponse`, `EnsureUserHasRole` (`role:admin,advisor`).
- Policies : `OrderPolicy` (consulter / changer un statut / lire les mesures /
  supprimer), `ProductPolicy`, `CategoryPolicy`.
- `ApiErrorRenderer` : rendu JSON uniforme
  `{ message, code, errors?, request_id }` pour 401/403/404/405/409/422/429/500 ;
  aucune trace interne exposée hors `APP_DEBUG`.
- Limitation de débit : `public` (120/min), `orders` (5/min), `login` (5/min),
  avec message français et en-tête `Retry-After`.
- Commande `php artisan stive:create-admin` pour créer un compte réel sans seeder.
- Mode strict Eloquent hors production (attributs non déclarés, N+1 si activé).

### Critères de recette

- Une requête sans jeton renvoie `401 unauthenticated`, jamais une page HTML.
- Un conseiller ne peut pas créer un modèle (`403 forbidden`).
- Toute erreur contient un `request_id` retrouvable dans `storage/logs`.

---

## Étape 5 — API publique du catalogue

**Commit** : `feat(catalog): API publique catégories et produits, filtres paginés, cache versionné et configuration boutique`

### Objectifs

- Servir efficacement le catalogue à Angular (liste + fiche).
- Ne jamais exposer de brouillon ni de modèle archivé.
- Rendre le cache invalide automatiquement à chaque écriture du catalogue.

### Réalisations

- `GET /categories` : catégories actives, ordonnées, avec `products_count`.
- `GET /products` : filtres `category`, `q`, `min_price`, `max_price`,
  `featured`, tri (`newest`, `price_asc`, `price_desc`, `name`), pagination
  plafonnée à 48 éléments.
- `GET /products/{slug}` : galerie, couleurs, tissus, tailles, guide des mesures,
  prix calculé, lien WhatsApp de prise de contact.
- `GET /shop` : configuration publique (numéro WhatsApp, devise, tailles,
  bornes de mesures, libellés des statuts).
- `CatalogCache` : cache par **version de clé** — `flush()` rend obsolètes toutes
  les entrées en une écriture (compatible cache `database`, sans tags).
- Ressources JSON dédiées (`ProductSummaryResource`, `ProductResource`, …) :
  le format de sortie est stable et documenté.

### Critères de recette

- `GET /products?category=costume-veste&min_price=80000` renvoie uniquement les
  modèles correspondants et `meta.total` cohérent.
- Une fiche non publiée renvoie `404 not_found`.
- Après modification d'un modèle en administration, la fiche publique est à jour
  immédiatement (cache invalidé).

---

## Étape 6 — Commandes, WhatsApp et administration

**Commit** : `feat(orders): création de demande (validation métier, prix serveur, message WhatsApp) et administration des commandes, produits et catégories`

### Objectifs

- Valider une demande de bout en bout, côté serveur.
- Figer l'historique (instantané + journal).
- Donner à l'équipe les outils de suivi et de gestion du catalogue.

### Réalisations

- `StoreOrderRequest` : validation en deux niveaux.
  1. forme : coordonnées, téléphone international, consentement obligatoire,
     1 à 5 articles, quantités, bornes de mesures ;
  2. métier : couleur/tissu/taille réellement actifs sur le modèle, sur-mesure
     autorisé, mesures obligatoires présentes et dans les bornes du modèle,
     clés de mesures inconnues rejetées.
- `OrderService` : transaction unique, **prix recalculé** (base + suppléments),
  instantané JSONB, mesures, référence unique, événement initial, IP hachée
  en HMAC (jamais stockée en clair), lien WhatsApp construit après commit.
- `OrderMessageBuilder` : message français reprenant modèle, coloris, tissu,
  taille, mensurations, total indicatif, référence et coordonnées.
- `POST /orders` → `201` avec `reference`, `status`, `whatsapp_url`.
- Administration des commandes : liste filtrée, dossier complet, changement
  d'état **contrôlé par transitions** (`409 invalid_status_transition`),
  journalisation avec auteur et note, mesures masquées si le rôle n'y a pas droit.
- Administration du catalogue : CRUD produits, options, guide des mesures,
  photos (MIME et poids contrôlés, texte alternatif obligatoire), catégories ;
  options retirées désactivées et non supprimées ; archivage logique.
- `GET /admin/stats` : volumes par statut, demandes du jour, chiffre d'affaires
  estimé, état du catalogue.

### Critères de recette

- Une demande sans consentement, sans mesure obligatoire ou avec une option
  inexistante est refusée (`422`) avec un champ d'erreur précis
  (`items.0.measurements.chest_cm`, `items.0.color`, …).
- Le prix envoyé par le client est ignoré ; le total est recalculé.
- `pending_confirmation → delivered` est refusé (`409`) ; le parcours complet
  étape par étape est accepté et journalisé.
- Le lien WhatsApp contient la référence et toutes les mensurations ; aucun
  message n'est envoyé par le serveur.
- Un atelier peut consulter un dossier mais pas en changer l'état.

---

## Étape 7 — Tests et non-régression

**Commit** : `test(api): couverture du catalogue, des commandes, de l'administration et des règles métier`

### Réalisations

- `tests/Feature/Public/CatalogTest.php` : catégories, filtres, pagination,
  fiche produit, 404, configuration boutique, santé.
- `tests/Feature/Public/OrderCreationTest.php` : création valide, recalcul du
  prix, sur-mesure, mesures manquantes/hors bornes, options invalides,
  consentement, téléphone, modèle non publié, limitation de débit.
- `tests/Feature/Admin/AuthTest.php` : connexion, identifiants invalides,
  compte désactivé, format d'erreur, `me`, déconnexion, throttle.
- `tests/Feature/Admin/OrderManagementTest.php` : liste et filtres, dossier +
  mesures, transitions valides/interdites, rôle atelier, tableau de bord.
- `tests/Feature/Admin/CatalogueManagementTest.php` : création, droits,
  désactivation des options retirées, validations, catégories, médias,
  archivage.
- `tests/Unit/OrderStatusTest.php`, `tests/Unit/OrderMessageBuilderTest.php`.

### Commandes

```bash
php artisan test                 # suite complète
php artisan test --filter=Order  # sous-ensemble
vendor/bin/pint --test           # style
```

### Critères de recette

- Suite verte sur PostgreSQL (base `stive_clothing_test`).
- Aucun test ne dépend de l'ordre d'exécution (`RefreshDatabase`).
- Les tests couvrent les critères de recette du cahier des charges (§11).

---

## Étape 8 — Documentation, contrat et intégration continue

**Commit** : `docs(api): contrat OpenAPI, guide de développement, README et CI`

### Réalisations

- `docs/openapi.yaml` : contrat OpenAPI 3.1 complet (schémas, erreurs, exemples,
  description des transitions et des règles de validation).
- `docs/DEVELOPPEMENT.md` : ce guide.
- `README.md` : démarrage rapide, table des endpoints.
- `GET /docs/openapi.yaml` : le contrat est servi par l'application.
- CI GitHub Actions : PostgreSQL 16 en service, `composer validate`, Pint,
  `php artisan test`.

### Critères de recette

- Le contrat valide dans un éditeur OpenAPI (Swagger Editor / Redocly).
- La CI passe sur une PR vierge.
- Un développeur Angular peut intégrer l'API uniquement avec `openapi.yaml`.

---

## Mise en route locale

```bash
# 1. Dépendances
composer install

# 2. Configuration
cp .env.example .env
php artisan key:generate

# 3. Base de données
docker compose up -d db
php artisan migrate --seed

# 4. Médias (photos produits locales)
php artisan storage:link

# 5. Serveur
php artisan serve        # http://localhost:8000

# 6. Vérifications
curl http://localhost:8000/api/v1/health
curl http://localhost:8000/api/v1/products | head
```

Comptes de démonstration (`php artisan db:seed`) :

| Rôle | E-mail | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin@stive-clothing.test` | `password` |
| Conseiller | `conseiller@stive-clothing.test` | `password` |
| Atelier | `atelier@stive-clothing.test` | `password` |

> En production : ne pas lancer `DatabaseSeeder`. Créer les comptes avec
> `php artisan stive:create-admin direction@stive-clothing.cm --role=admin`.

### Exemple de parcours complet

```bash
# Connexion
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/admin/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@stive-clothing.test","password":"password"}' | jq -r .token)

# Suivi des demandes
curl -s http://localhost:8000/api/v1/admin/orders -H "Authorization: Bearer $TOKEN" | jq

# Validation d'une demande
curl -s -X PATCH http://localhost:8000/api/v1/admin/orders/1 \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"status":"confirmed","note":"Prix confirmé par téléphone."}' | jq
```

---

## Conventions de code

- **Français** pour les messages utilisateur, l'anglais pour le code.
- Un contrôleur par ressource ; la logique métier vit dans `app/Services`.
- Toute validation dans un `FormRequest` ; toute sortie dans une `Resource`.
- Les montants sont des entiers ; les dates sont stockées en UTC.
- Les mensurations ne sortent jamais sans contrôle de rôle.
- Les valeurs métier passent par `config/catalog.php` ou les énumérations.

---

## Sécurité et confidentialité (CDC §9)

- Validation serveur systématique + limitation de débit sur les endpoints
  publics sensibles.
- Jetons Sanctum expirants, révocables, liés à un appareil nommé.
- IP client jamais stockée en clair (HMAC avec `APP_KEY`).
- Mesures corporelles : accès restreint par policy, consentement explicite
  obligatoire, durée de conservation documentée (365 jours par défaut,
  `measurement_retention_days`).
- Erreurs sans détail interne en production ; corrélation par `X-Request-Id`.
- Médias : MIME et poids contrôlés, texte alternatif obligatoire.
- À prévoir côté exploitation : HTTPS obligatoire, secrets hors dépôt, CORS
  limité au domaine Angular, sauvegardes chiffrées et test de restauration.

---

## Feuille de route (hors MVP)

| Priorité | Sujet | Prérequis |
| --- | --- | --- |
| P2 | Notifications à la création et au changement d'état | file d'attente + canal e-mail/SMS |
| P2 | WhatsApp Business Cloud API (envoi assisté) | compte Meta Business validé |
| P2 | Paiement mobile money / carte | prestataire choisi, webhooks signés |
| P2 | Calcul de livraison et suivi transporteur | grille tarifaire, prestataire |
| P3 | Purge automatique des mesures après rétention | commande planifiée + journal |
| P3 | Statistiques produits (vues, conversions) | mesure d'audience consentie |

---

## Journal des commits

```bash
git log --oneline
# docs(api): contrat OpenAPI, guide de développement, README et CI
# test(api): couverture du catalogue, des commandes, de l'administration et des règles métier
# feat(orders): création de demande (validation métier, prix serveur, message WhatsApp) et administration des commandes, produits et catégories
# feat(catalog): API publique catégories et produits, filtres paginés, cache versionné et configuration boutique
# feat(auth): authentification Sanctum, rôles, policies, format d'erreur unique et limitation de débit
# feat(models): modèles Eloquent, usines de données, catalogue de démonstration et visuels
# feat(database): schéma PostgreSQL (catalogue, options, mesures, commandes, journal) et énumérations métier
# chore(bootstrap): squelette Laravel 12 + PostgreSQL, routing /api/v1 et configuration métier
```
