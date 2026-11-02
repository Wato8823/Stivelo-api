# Installation locale — API Stive Clothing (MySQL)

Guide pas à pas pour installer les outils, récupérer le code depuis Git et
faire tourner l'API **Laravel 12 + MySQL 8** sur un poste de développement.

Durée : ~15 minutes la première fois.

---

## 1. Outils requis

| Outil | Version | Rôle | Vérification |
| --- | --- | --- | --- |
| Git | ≥ 2.40 | récupérer le code | `git --version` |
| PHP | **8.3** (8.2 min.) | exécuter Laravel | `php -v` |
| Composer | 2.x | dépendances PHP | `composer --version` |
| MySQL | **8.0+** (ou MariaDB 10.6+) | base de données | `mysql --version` |
| Docker Desktop | — *(optionnel)* | MySQL en une commande | `docker --version` |
| Node.js | 20/22 *(optionnel)* | futur front Angular | `node -v` |

Extensions PHP obligatoires : `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`,
`zip`, `curl` — plus `gd` (téléversement et tests d'images).

Contrôle rapide :

```bash
php -m | grep -E "pdo_mysql|mbstring|openssl|fileinfo|zip|gd"
```

Les six lignes doivent apparaître. S'il en manque une, voir § 2 (activation dans
`php.ini`).

---

## 2. Installer les outils par système

### Windows — option A (recommandée) : Laragon

1. Télécharger **Laragon Full** : <https://laragon.org/download> (fournit PHP 8.3,
   Composer, Git et un terminal).
2. Menu Laragon → **PHP → Extensions** : cocher `pdo_mysql`, `mysqli`, `gd`,
   `mbstring`, `fileinfo`, `zip`, `openssl`.
3. Onglet **Database** de Laragon → **MySQL** → *Start* (pas besoin d'installer
   MySQL à part, Laragon l'embarque).
4. Ouvrir le terminal Laragon (bouton *Terminal*) pour les commandes suivantes.

### Windows — option B : PHP natif + MySQL

1. PHP : <https://windows.php.net/download> (thread-safe, x64) → dézipper dans
   `C:\php`.
2. Dans `C:\php\php.ini` (copier `php.ini-development` en `php.ini`), décommenter :

   ```ini
   extension=pdo_mysql
   extension=mysqli
   extension=mbstring
   extension=openssl
   extension=fileinfo
   extension=zip
   extension=gd
   extension_dir = "C:\php\ext"
   ```

3. Ajouter `C:\php` et le dossier de Composer au `PATH` (Paramètres → Variables
   d'environnement), puis rouvrir le terminal.
4. MySQL : <https://dev.mysql.com/downloads/installer> → *Server only* ou
   *Full*. Noter le mot de passe `root`.
5. Composer : <https://getcomposer.org/download> → *Composer-Setup.exe*.

### Windows — option C : WSL2 + Ubuntu

```powershell
wsl --install
```

Puis dans le terminal **Ubuntu** :

```bash
sudo apt update
sudo apt install -y git unzip curl php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-gd php8.3-curl php8.3-zip mysql-server
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo service mysql start
```

### macOS

```bash
brew install php@8.3 composer mysql git
brew services start mysql
```

MAMP/XAMPP fonctionnent aussi : pointer PHP vers leurs binaires et renseigner
`DB_SOCKET` dans `.env` (§ 4).

### Linux (Debian/Ubuntu)

```bash
sudo apt update
sudo apt install -y git unzip curl php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-gd php8.3-curl php8.3-zip mysql-server
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo service mysql start
```

### Alternative Docker (tous OS)

Si Docker Desktop est installé, **inutile d'installer MySQL** : le fichier
`docker-compose.yml` du dépôt fournit MySQL 8.4 avec les bons identifiants et
crée automatiquement la base de test.

```bash
docker compose up -d db
```

---

## 3. Créer la base de données

### Avec Docker (recommandé)

Rien à faire : les bases `stive_clothing` et `stive_clothing_test` sont créées
au premier démarrage (voir `docker/mysql/init/`).

### Avec un MySQL installé sur la machine

```bash
mysql -u root -p <<'SQL'
CREATE DATABASE stive_clothing      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE stive_clothing_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stive'@'%' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON stive_clothing.*      TO 'stive'@'%';
GRANT ALL PRIVILEGES ON stive_clothing_test.* TO 'stive'@'%';
FLUSH PRIVILEGES;
SQL
```

> Sous XAMPP/MAMP, l'utilisateur `root` sans mot de passe suffit : adapter
> `DB_USERNAME`/`DB_PASSWORD` dans `.env`.

---

## 4. Récupérer le code depuis Git

```bash
git clone https://github.com/Wato8823/Sttivelo-api.git
cd Sttivelo-api
git checkout main
git log --oneline -3
```

Pour suivre une branche en cours de revue (ex. portage MySQL) :

```bash
git fetch origin
git checkout arena/f002b2d3-sttivelo-api
```

---

## 5. Configurer et démarrer

```bash
composer install
cp .env.example .env          # Windows cmd/PowerShell : copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve             # http://localhost:8000
```

Le bloc base de données du `.env` :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stive_clothing
DB_USERNAME=stive
DB_PASSWORD=secret
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
# DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
```

Après toute modification du `.env` : `php artisan config:clear`.

### Séparer les identifiants de test (recommandé)

Les tests tournent sur une base dédiée mais **avec les identifiants de votre
`.env`**. Pour utiliser un utilisateur MySQL réservé aux tests :

```bash
cp .env.testing.example .env.testing   # puis adapter DB_USERNAME / DB_PASSWORD
```

`.env.testing` est prioritaire sur `.env` lorsque `APP_ENV=testing` et n'est
jamais versionné.

### Données de démonstration créées par `--seed`

- 7 catégories, 12 modèles (couleurs, tissus, tailles, guide des mesures) ;
- 3 demandes illustrant le suivi (neuve, en confection, livrée) ;
- 3 comptes internes :

| Rôle | E-mail | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin@stive-clothing.test` | `password` |
| Conseiller | `conseiller@stive-clothing.test` | `password` |
| Atelier | `atelier@stive-clothing.test` | `password` |

---

## 6. Vérifier que l'API répond

```bash
# santé
curl http://localhost:8000/api/v1/health

# catalogue
curl http://localhost:8000/api/v1/products

# fiche produit
curl http://localhost:8000/api/v1/products/costume-signature-deux-pieces

# connexion administrateur
curl -X POST http://localhost:8000/api/v1/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@stive-clothing.test","password":"password"}'
```

Avec le jeton renvoyé :

```bash
TOKEN=LE_JETON_RECU

curl http://localhost:8000/api/v1/admin/orders -H "Authorization: Bearer $TOKEN"

curl -X PATCH http://localhost:8000/api/v1/admin/orders/1 \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"status":"confirmed","note":"Prix confirmé par téléphone."}'
```

---

## 7. Lancer les tests

```bash
php artisan test
```

### Comment les tests choisissent leur base

| Paramètre | Valeur |
| --- | --- |
| Nom de la base | **imposé par `phpunit.xml`** : `stive_clothing_test` |
| Hôte, port, utilisateur, mot de passe | **repris de votre `.env`** (ou de `.env.testing` s'il existe) |
| Base de développement | jamais touchée |

Seul le nom de la base est forcé, et une garde dans `tests/TestCase.php`
(`setUpTraits`, donc avant toute migration) refuse de démarrer si le nom ne se
termine pas par `_test` : `migrate:fresh` ne peut pas effacer vos données
réelles.

### Créer la base de test

Sans client `mysql` installé (Windows, Laragon, XAMPP), utilisez l'outil fourni :

```bash
php tools/create-database.php stive_clothing_test stive
```

Le premier argument est la base, le second l'utilisateur à qui en accorder
l'accès (facultatif). La connexion administrateur se règle par variables
d'environnement :

```bash
DB_ADMIN_PASSWORD=monmotdepasse php tools/create-database.php stive_clothing_test stive
```

Avec le client `mysql` :

```bash
mysql -u root -p -e "CREATE DATABASE stive_clothing_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON stive_clothing_test.* TO 'votre_utilisateur'@'localhost'; FLUSH PRIVILEGES;"
```

Avec Docker, elle est créée automatiquement au premier démarrage
(`docker/mysql/init/01-create-test-database.sql`).

### Vérifier l'environnement avant de lancer les tests

```bash
php artisan stive:check-setup
```

La commande contrôle, dans l'ordre : version de PHP, extensions (`pdo_mysql`,
`mbstring`, `openssl`…), `APP_KEY`, droits d'écriture (`storage/`,
`bootstrap/cache/`), connexion à la base de développement, puis existence de la
base de test. Chaque échec est accompagné de la commande exacte à exécuter —
y compris les instructions `CREATE DATABASE` adaptées à votre utilisateur.

Résultat attendu quand tout est en place :

```
[OK] Version de PHP           8.3.x (8.2 minimum)
[OK] Extension pdo_mysql      chargée
[OK] Connexion (mysql)        stive_clothing accessible
[OK] Base « stive_clothing_test »   accessible avec l'utilisateur « root »
Environnement prêt — 0 avertissement(s).
```

Enfin, la suite elle-même :

```bash
php artisan test                       # suite complète
php artisan test --filter=OrderCreation # sous-ensemble
```

## 8. Dépannage

| Message | Cause / solution |
| --- | --- |
| `could not find driver` | extension `pdo_mysql` désactivée → § 2, puis rouvrir le terminal |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL arrêté (`sudo service mysql start`, Laragon → Start, `docker compose up -d db`) |
| `Access denied for user '…'` au démarrage | identifiants de `.env` incorrects → corriger `DB_USERNAME`/`DB_PASSWORD`, puis `php artisan config:clear` |
| `Access denied` **uniquement** pendant `php artisan test` | l'utilisateur de test n'a pas accès à `stive_clothing_test` → § 7 (création + `GRANT`), ou renseigner `.env.testing` |
| `Unknown database 'stive_clothing'` | `php artisan migrate` lancé avant la création de la base (§ 3) |
| `Unknown database 'stive_clothing_test'` | base de test absente → § 7 ou `php artisan stive:check-setup` |
| `Specified key was too long` | base créée sans `utf8mb4` → recréer avec `CHARACTER SET utf8mb4` |
| `php`/`composer` non reconnu | `PATH` incomplet → rouvrir le terminal après modification |
| `419`/`500` au premier lancement | `php artisan key:generate` oublié ou `.env` absent |
| modifications invisibles | `php artisan optimize:clear` |
| port 3306 déjà pris | arrêter l'autre MySQL ou changer `DB_PORT` (et le port du conteneur) |

---

## 9. Suite de la lecture

1. `README.md` — démarrage rapide et tableau des endpoints.
2. `docs/DEVELOPPEMENT.md` — les étapes de développement, objectifs et critères de recette.
3. `docs/openapi.yaml` — contrat à importer dans Postman, Insomnia ou Swagger Editor.
