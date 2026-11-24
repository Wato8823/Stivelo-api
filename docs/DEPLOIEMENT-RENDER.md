# Déployer l'API Stive Clothing sur Render (offre gratuite)

Guide pas à pas pour mettre l'API en ligne **gratuitement** sur
[Render](https://render.com), avec le frontend hébergé sur **Cloudflare Pages**
et les photos produits sur **Cloudflare R2** (10 Go gratuits, compatible S3).

> ⚠️ **Ce que l'offre gratuite implique** (à connaître avant de commencer) :
>
> | Ressource | Limite |
> | --- | --- |
> | Web service | 750 h/mois, **mise en veille après 15 min sans trafic** (réveil ≈ 1 min) |
> | Système de fichiers | **Éphémère** : les fichiers téléversés sont perdus à chaque redémarrage → les photos vont sur R2 |
> | Postgres gratuit | 1 Go — **expire 30 jours après création**, supprimé 14 jours plus tard. Exportez vos données ou passez au plan payant (dès 7 $/mois) |
> | Shell / one-off | **Non disponible en gratuit** → l'admin est créé au démarrage via `CREATE_ADMIN_*` |
> | Bande passante | 5 Go/mois |
>
> Pour une vraie boutique en production, le passage au Starter (~7 $/mois)
> supprime presque toutes ces limites — l'application n'a rien à changer.

---

## 1. Préparer Cloudflare R2 (stockage des photos)

1. Compte Cloudflare → menu **R2 Object Storage** → *Create bucket* → nom : `stive-media`.
2. Dans le bucket → onglet **Settings** → **Public access** → *Allow Access* sur le
   domaine `r2.dev` (ou mieux : votre propre sous-domaine `media.stiveclothing.cm`).
   Notez l'URL publique, ex. `https://pub-abc123.r2.dev`.
3. R2 → **Manage R2 API Tokens** → *Create API token* → permission **Object Read & Write**,
   restreint au bucket `stive-media`. Notez :
   - `Access Key ID`
   - `Secret Access Key`
   - l'**Account ID** (visible dans l'URL du dashboard ou la sidebar R2) :
     l'endpoint S3 est `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`.

## 2. Déployer sur Render (Blueprint)

Le dépôt contient un Blueprint `render.yaml` à la racine : il décrit le web service
Docker **et** la base Postgres gratuite.

1. Poussez la branche sur GitHub (déjà le cas).
2. [https://dashboard.render.com](https://dashboard.render.com) → **New → Blueprint**.
3. Autorisez l'accès au dépôt `Wato8823/Stivelo-api`, choisissez la branche
   (`main` une fois la PR fusionnée, ou la branche de travail pour tester).
4. Render détecte `render.yaml` et propose : `stive-clothing-api` (web, Docker, gratuit)
   et `stive-postgres` (Postgres gratuit). Acceptez.
5. Renseignez les variables marquées « sync: false » :

   | Variable | Valeur |
   | --- | --- |
   | `CORS_ALLOWED_ORIGINS` | URL publique du frontend, ex. `https://stive-clothing.pages.dev` |
   | `SANCTUM_STATEFUL_DOMAINS` | le même domaine **sans** `https://` |
   | `STIVE_WHATSAPP_NUMBER` | numéro réel au format international, sans `+` ni espaces (ex. `2376XXXXXXXX`) |
   | `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | clés R2 (étape 1) |
   | `AWS_BUCKET` | `stive-media` |
   | `AWS_ENDPOINT` | `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` |
   | `AWS_URL` | URL publique du bucket, ex. `https://pub-abc123.r2.dev` |
   | `CREATE_ADMIN_EMAIL` | e-mail du compte admin réel, ex. `direction@stive-clothing.cm` |
   | `CREATE_ADMIN_PASSWORD` | mot de passe **fort** (≥ 10 caractères) |

6. **Apply** → le build Docker démarre (installation Composer, migrations,
   seed du catalogue, création du compte admin, démarrage du serveur).
7. Vérifiez :

   ```bash
   curl https://stive-clothing-api.onrender.com/api/v1/health
   # {"status":"ok","database":"ok", ...}
   ```

8. **Important, juste après le premier démarrage** :
   - retirez `CREATE_ADMIN_PASSWORD` des variables (le compte existe déjà) ;
   - passez `SEED_CATALOG` à `"false"` (le seed est idempotent, mais inutile
     à chaque réveil).
9. Le domaine `https://stive-clothing-api.onrender.com` est utilisé tout du long ;
   vous pouvez ensuite associer un sous-domaine (`api.stiveclothing.cm`) dans
   **Settings → Custom Domains** (gratuit, TLS automatique).

> Pas de Blueprint ? **New → Web Service** → *Docker* → mêmes variables
> d'environnement (copier celles de `render.yaml`), plus un **New → PostgreSQL**
> (plan Free) ; liez ensuite `DB_URL` à la chaîne de connexion **interne** de la base.

## 3. Brancher le frontend (Cloudflare Pages)

Dans les variables d'environnement du projet Pages :

```bash
# Angular (exemple)
API_BASE_URL=https://stive-clothing-api.onrender.com/api/v1
```

Points de vigilance :

- Le frontend est sur une **autre origine** → c'est CORS qui décide.
  `CORS_ALLOWED_ORIGINS` doit contenir l'URL exacte du site Pages
  (ex. `https://stive-clothing.pages.dev`). Pour les previews de branches
  (`https://<hash>.stive-clothing.pages.dev`), ajoutez l'URL de chaque preview
  ou passez à un domaine personnalisé unique.
- L'authentification admin utilise un **jeton Bearer** (`POST /api/v1/admin/login`
  → `Authorization: Bearer stv_...`) : pas de cookie → pas de souci de SameSite.
- Le frontend ne doit jamais appeler `localhost` ou la base directement :
  uniquement l'URL publique de l'API.

## 4. Limites à surveiller & passage en payant

- **Mise en veille** : le premier visiteur après 15 min d'inactivité attend ~1 min.
  Un ping régulier (ex. Cloudflare Worker cron / uptime monitor) peut garder
  l'instance éveillée, au prix du quota 750 h/mois.
- **Expiration Postgres (30 jours)** : notez la date de création de la base ;
  exportez avec `pg_dump` (depuis un poste avec psql — Render ne donne pas de
  shell en gratuit) ou passez au plan payant avant l'échéance.
- **Uploads** : tout ce qui est écrit hors R2 (logs, cache, sessions) est perdu
  au redémarrage — c'est sans impact, la base et les photos vivent dans
  Postgres et R2.
- Passage en production réelle : instance **Starter** (~7 $/mois) + **Postgres
  Starter** (~7 $/mois) — l'application n'a rien à changer.

## 5. Variables d'environnement — récapitulatif

Voir `render.yaml` (Blueprint) ou `.env.production.example` (modèle complet).
Les plus importantes : `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`
(generateValue dans le Blueprint), `DB_URL` (injectée par Render),
`CORS_ALLOWED_ORIGINS`, `PRODUCT_MEDIA_DISK=s3` + variables R2,
`STIVE_WHATSAPP_NUMBER`.

## 6. CI/CD

- `.github/workflows/tests.yml` exécute les tests sur PostgreSQL 16 à chaque
  push/PR — le même moteur qu'en production.
- Render redéploie automatiquement à chaque push sur la branche déployée
  (`autoDeploy: true` dans `render.yaml`).
