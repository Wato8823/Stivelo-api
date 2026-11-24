#!/usr/bin/env bash
# Démarrage du conteneur sur Render (offre gratuite).
# - config:cache : calculée à l'exécution (les variables d'environnement
#   Render ne sont disponibles qu'au démarrage, pas au build de l'image) ;
# - migrations : appliquées à chaque déploiement (idempotentes) ;
# - catalogue  : seedé si SEED_CATALOG=true (idempotent — updateOrCreate) ;
# - admin      : créé/mis à jour si CREATE_ADMIN_EMAIL + CREATE_ADMIN_PASSWORD
#   sont définis (le shell n'est pas disponible sur l'offre gratuite).
set -e

cd "$(dirname "$0")/.."

php artisan config:cache
php artisan migrate --force

php artisan storage:link 2>/dev/null || true

if [ "${SEED_CATALOG:-false}" = "true" ]; then
    echo "→ SEED_CATALOG=true : mise à jour du catalogue de démonstration (idempotent)…"
    php artisan db:seed --force
fi

if [ -n "${CREATE_ADMIN_EMAIL:-}" ] && [ -n "${CREATE_ADMIN_PASSWORD:-}" ]; then
    echo "→ Création/mise à jour du compte d'administration ${CREATE_ADMIN_EMAIL}…"
    php artisan stive:create-admin "${CREATE_ADMIN_EMAIL}" \
        --password="${CREATE_ADMIN_PASSWORD}" \
        --role="${CREATE_ADMIN_ROLE:-admin}"
    echo "→ Pensez à retirer CREATE_ADMIN_PASSWORD des variables d'environnement une fois le compte créé."
fi

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"
