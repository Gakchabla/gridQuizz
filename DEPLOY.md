# Plan de déploiement — hébergement en ligne

Statut : pas encore fait, à reprendre plus tard.

## Choix retenu : Clever Cloud

Hébergeur français, support natif Symfony (déploiement par `git push`, pas besoin de
conteneuriser la prod), base PostgreSQL managée, hébergement statique pour le front Vue.
Évite d'avoir à remplacer le serveur PHP intégré (`php -S`, pratique en dev mais pas fait
pour la prod) par quelque chose de plus robuste — Clever Cloud s'en charge lui-même.

Alternatives envisagées si besoin de changer d'avis plus tard :
- **Railway** / **Render** : équivalents à Clever Cloud, support Docker direct (réutiliserait
  le `docker-compose.yml` existant), bons pour un déploiement rapide multi-services.
- **VPS (Hetzner, OVH)** : plus de contrôle, moins cher à long terme, mais il faut gérer
  soi-même les mises à jour, la sécurité et le HTTPS — et remplacer `php -S` par
  FrankenPHP ou nginx+php-fpm avant de mettre en prod.

## Étapes

Les étapes 1 à 4 se font sur l'interface web de Clever Cloud (compte, carte bancaire) —
ne peuvent pas être faites depuis Claude Code.

1. Créer un compte sur [clever-cloud.com](https://clever-cloud.com) (gratuit pour démarrer,
   facturation à l'usage ensuite).
2. Créer une **app PHP** pour `api/` — Clever Cloud détecte Symfony automatiquement.
3. Ajouter un **add-on PostgreSQL** — la variable `DATABASE_URL` est injectée
   automatiquement dans l'app PHP par Clever Cloud (pas besoin de la configurer à la main).
4. Créer une **app statique** pour `front/` (le build Vue), ou la faire servir directement
   par Clever Cloud.
5. Pousser le code sur le remote git fourni par Clever Cloud (`git push clever main`),
   comme à l'époque de Heroku.

## Préparation côté code (à faire avec Claude Code, pas encore fait)

- Fichiers de config Clever Cloud (`clevercloud/php.json` pour `api/`, config équivalente
  pour `front/`).
- Variables d'environnement de prod (`APP_ENV=prod`, `APP_SECRET` généré, `CORS_ALLOW_ORIGIN`
  mis à jour pour pointer vers le futur domaine du front au lieu de `localhost`).
- Vérifier que les migrations Doctrine tournent bien au déploiement (Clever Cloud permet de
  lancer une commande de build/déploiement, ex. `php bin/console doctrine:migrations:migrate --no-interaction`).
- `front/.env` (ou équivalent de prod) : `VITE_API_URL` pointant vers l'URL de l'app API
  Clever Cloud au lieu de `http://localhost:8000`.
