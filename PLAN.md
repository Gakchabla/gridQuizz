# Plan — App de quiz "grille de chiffres"

Stack : **Symfony (API Platform) + PostgreSQL + Vue.js**, exécuté en local via **Docker Compose**.

## Hypothèses

- Un seul écran pilote le jeu (pas de temps réel, pas de websockets) : l'état de la partie vit dans le front Vue.
- Les sessions/thèmes/questions sont persistés en base pour être réutilisés/rejoués. Le flag "répondu" par question est aussi persisté (permet de reprendre une partie en cours).
- API Platform pour le CRUD (Session/Thème/Question) + un contrôleur custom pour l'action "reset" et pour marquer une question répondue.

## Structure du repo

```
gridquizz/
├── docker-compose.yml
├── api/      → projet Symfony (API Platform)
└── front/    → projet Vue (Vite)
```

## Services Docker Compose

- **`database`** : `postgres:16-alpine`, volume nommé, port 5432 exposé.
- **`php`** : Symfony/API Platform sur FrankenPHP (socle officiel du skeleton `api-platform/api-platform`, inclut HTTPS local + PHP 8.3+ + Caddy) — mount de `api/`, port 8000/443, `depends_on: database`.
- **`front`** : `node:20-alpine`, `npm run dev` (Vite) sur `front/`, port 5173, hot-reload monté en volume.

`docker compose up` lance tout : API sur `https://localhost` (ou `:8000`), front Vite sur `:5173`, Postgres sur `:5432`.

## Modèle de données (Doctrine / PostgreSQL)

```
Session
- id, name, isShuffled (bool), revealDuration (int, secondes), createdAt

Theme
- id, session (FK), name, color (varchar, hex)

Question
- id, session (FK), theme (FK), number (int, unique par session, 1..N),
  questionText, answerText, isAnswered (bool, default false)
```

`number` généré automatiquement à la création (position d'ajout), pas saisi à la main → garantit 1..N sans trou.

## Backend Symfony

- `composer require api symfony/orm-pack` (API Platform + Doctrine), driver `pdo_pgsql`.
- Entités `Session`, `Theme`, `Question` exposées en API Platform (GET/POST/PUT/DELETE).
- Endpoint custom `POST /api/sessions/{id}/reset` : repasse `isAnswered = false` sur toutes les questions de la session.
- Endpoint custom (ou PATCH API Platform) pour marquer `isAnswered = true` quand une question est cliquée.
- CORS (`nelmio/cors-bundle`) pour autoriser le front Vue en dev.

## Frontend Vue.js

Vite + Vue 3 + Pinia + Vue Router.

**Vues**
- `SessionListView` : liste/CRUD des sessions.
- `SessionEditView` : gestion des thèmes (nom + couleur) et questions (texte, réponse, thème) d'une session, + paramètres (ordre aléatoire ou non, durée d'affichage).
- `GameView` : écran de jeu.

**Store `game` (Pinia)**
- Charge les questions de la session, calcule l'ordre de la grille (shuffle Fisher-Yates si `isShuffled`, sinon tri par `number`).
- Timer : chiffres colorés (couleur du thème) pendant `revealDuration`s puis passage au noir (`setTimeout`).
- État par question : `answered` (API), `open` (modale, local).

**Composants**
- `GridNumber.vue` : un chiffre, couleur dynamique, désactivé si `answered`.
- `QuestionModal.vue` : affiche la question, bouton "Afficher la réponse" (toggle local), bouton "Fermer".
- `ResetButton.vue` : appelle `POST /reset`, remet toute la grille à zéro.

## Déroulé de jeu

1. Chargement des questions de la session → génération de l'ordre de grille selon le paramètre.
2. Affichage grille colorée → après `revealDuration`s, tout passe en noir (chiffres restent cliquables).
3. Clic sur un chiffre non répondu → appel API pour marquer `isAnswered = true` (verrouille immédiatement) → ouverture modale (réponse masquée).
4. Bouton "Afficher la réponse" → toggle local.
5. Bouton "Fermer" → ferme la modale, chiffre reste non cliquable.
6. Bouton "Reset" → appelle l'API reset, tous les chiffres redeviennent cliquables.

## Notes d'implémentation (étape 0)

- PHP local : le squelette Symfony généré (8.1.x) nécessite PHP ≥ 8.4 → image `php:8.4-cli` dans `api/Dockerfile`.
- Port front : `5173` était déjà occupé sur la machine par un autre process → mappé sur `5174:5173` dans `docker-compose.yml`. Accès : `http://localhost:5174`.
- Credential helper Docker Desktop (`docker-credential-desktop.exe`) non trouvé sur le PATH du shell Git Bash → nécessaire de l'ajouter au PATH pour builder l'image (`C:\Program Files\Docker\Docker\resources\bin`).
- `DATABASE_URL` dans `api/.env` pointe sur le service Docker `database` (nom réseau interne), pas `127.0.0.1` — l'API ne tourne qu'en conteneur.

## Notes d'implémentation (étape 2)

- Le endpoint `reset` (`POST /api/sessions/{id}/reset`) est implémenté en **State Processor** API Platform (`App\State\ResetSessionProcessor`, interface `ProcessorInterface`), pas en contrôleur Symfony classique : un contrôleur brut retourne directement une `Response`, ce qui casse le pipeline de sérialisation JSON-LD d'API Platform (erreur 500 "must return a Response object"). Le processor délègue l'écriture en base au `PersistProcessor` Doctrine natif après avoir remis les questions à `isAnswered = false`.
- `PATCH /api/questions/{id}` (`Content-Type: application/merge-patch+json`) suffit pour marquer une question répondue — pas besoin d'endpoint custom, c'est l'opération CRUD standard d'API Platform.

## Notes d'implémentation (étape 3)

- Le bind mount Windows → conteneur Docker ne déclenche pas les événements de fichiers natifs (inotify) attendus par Vite : ses changements n'étaient pas détectés (contenu servi périmé). Fix : `server.watch.usePolling: true` dans `front/vite.config.js`.
- Réponse collection API Platform (JSON-LD) : les items sont dans la clé `member` (pas `hydra:member`).

## Notes d'implémentation (étape 4)

- **Piège Symfony/API Platform sur les propriétés booléennes "isX"** : `Session::$isShuffled` (getter `isShuffled()`, setter `setIsShuffled()`) et `Question::$isAnswered` généraient DEUX termes JSON-LD distincts (`isShuffled` et `shuffled`) car le nom de propriété PHP brut et le nom dérivé du getter/setter ne coïncidaient pas. Résultat : la création (`POST`, format `application/ld+json`) ignorait silencieusement la valeur envoyée sous la clé `shuffled`, alors que `PATCH` (`application/merge-patch+json`, décodage JSON classique sans résolution de termes JSON-LD) fonctionnait correctement. Fix : renommer la propriété PHP elle-même (`$isShuffled` → `$shuffled`, `$isAnswered` → `$answered`) et le setter (`setShuffled()`, `setAnswered()`) pour qu'il n'y ait plus qu'un seul nom cohérent partout. Migration de renommage de colonnes générée et jouée.
- **Cache de métadonnées API Platform** : après ce genre de changement de nom de propriété, `php bin/console cache:clear` est nécessaire — contrairement au conteneur DI, le cache de métadonnées de ressources (contexte JSON-LD, listes de propriétés) n'est pas invalidé automatiquement par un simple changement de fichier PHP en dev.
- Le front envoie systématiquement `shuffled` (jamais `isShuffled`) pour la création et l'édition de session.
- **Lenteur extrême des appels API** (plusieurs secondes à plus d'une minute par requête) : causée par le bind mount Windows → conteneur, qui rend les accès fichiers très lents ; Opcache revalide le timestamp de chaque fichier de `vendor/` (des milliers de fichiers) à chaque requête en dev. Fix : `vendor/` et `var/` sont sortis du bind mount et servis depuis des volumes Docker natifs (`php_vendor`, `php_var`), peuplés au build de l'image via `composer install`. Résultat : ~0,5s par requête au lieu de dizaines de secondes. ⚠️ Conséquence : après un `composer require`/`composer update`, il faut `docker compose build php && docker compose up -d php` pour que le volume soit repeuplé (un simple montage bind ne suffit plus pour `vendor/`).

## Notes d'implémentation (revue post-étape 6)

- Le `number` d'une question **n'est plus persisté en base** : il a été retiré de l'entité `Question` (colonne + contrainte unique supprimées via migration, `QuestionNumberListener` supprimé). L'association chiffre↔question est désormais **recalculée aléatoirement à chaque chargement de `GameView`** (donc à chaque début de partie), côté front uniquement, en mémoire.
- Le paramètre de session `shuffled` ne contrôle plus que la **disposition visuelle de la grille** (ordre de lecture 1..N vs positions mélangées), indépendamment de l'association chiffre↔question ci-dessus.
- **Révision** : l'association chiffre↔question est en fait repassée côté backend (`Question::$number`, nullable, plus de contrainte unique) pour permettre la reprise exacte d'une partie en cours. Elle n'est plus assignée à la création (pas de listener), mais par `ResetSessionProcessor` — qui gère donc à la fois "reset" (déverrouille) et "(re)démarrage de partie" (réassigne des numéros aléatoires 1..N). `GameView` déclenche cet appel automatiquement si les questions n'ont pas encore de `number` (première partie), sinon reprend les numéros existants sans re-révéler les couleurs. Boutons "↺ Nouvelle partie" (liste des sessions + écran d'édition, visibles seulement si une partie est en cours = au moins un `number` non nul) appellent explicitement ce reset puis lancent `GameView`.

## Joueurs, tour par tour et score (post-plan initial)

- Nouvelle entité `Player` (id, session, thème "propriétaire", nom, score) gérée sur l'écran d'édition.
- `Session::currentPlayer` (nullable, `onDelete: SET NULL`) indique à qui c'est le tour de choisir une question.
- `ResetSessionProcessor` remet aussi les scores à 0 et redonne la main au premier joueur (par id) au (re)démarrage d'une partie.
- Nouvelle opération `POST /api/questions/{id}/resolve` (body `{"correct": bool}`), traitée par `ResolveQuestionProcessor` : marque la question répondue, attribue les points au joueur dont c'était le tour (1 point si le thème de la question correspond à son thème, 2 points sinon), puis fait avancer le tour au joueur suivant (ordre par id, boucle). `Question::$correct` est une propriété **transitoire** (non `#[ORM\Column]`) utilisée uniquement comme entrée de cette opération.
- Front : `GameView` affiche le joueur dont c'est le tour + un tableau des scores ; la modale question propose désormais "✓ Bonne réponse" / "✗ Mauvaise réponse" (remplace le bouton "Fermer" — juger une question est ce qui referme la modale).

## Catégorie bonus et équilibrage (post-plan initial)

- `Theme::bonus` (bool) marque la catégorie "bonus" : pas de joueur propriétaire, pas de contrainte de couleur de joueur.
- `Player::theme` est **unique en base** (`JoinColumn(unique: true)`) : un thème ne peut avoir qu'un seul joueur. Le formulaire front ne propose que les thèmes normaux pas encore pris ; côté API une tentative de doublon renvoie une 500 (contrainte Postgres) — acceptable ici car le vrai garde-fou est le formulaire.
- **Révision** : thème et joueur sont désormais créés/supprimés **ensemble** en un seul formulaire ("Thèmes & joueurs") — plus de section "Joueurs" séparée. Le nom du joueur est obligatoire sauf si la case "Catégorie bonus" est cochée. Le nombre de questions de la catégorie bonus suit `nombre de thèmes normaux` (= nombre de combos thème/joueur), incrémenté de 1 à chaque création d'un thème normal (avec son joueur). Les thèmes sont toujours affichés avec le bonus en dernier (`orderedThemes`).
- Règle d'équilibrage (juste indicative/bloquante côté front, rien de forcé en base) :
  - Un joueur par thème normal (1:1).
  - Tous les thèmes normaux ont le même nombre de questions Q (≥ 1).
  - La catégorie bonus a exactement `nombre de joueurs` questions.
  - Résultat : chaque joueur répond à Q+1 questions au total, quel que soit l'ordre de sélection ("vol" de questions inclus), car le nombre total de questions divise exactement par le nombre de joueurs dans le tour par tour.
- Écran d'édition : section "État de la partie" qui liste ce qui manque, et désactive "▶ Jouer" / "↺ Nouvelle partie" tant que ce n'est pas respecté.
- ⚠️ Cette vérification n'existe que sur l'écran d'édition — le lien "Jouer" de la liste des sessions (écran principal) n'est pas encore gardé par cette règle (demanderait de charger joueurs/thèmes/questions pour chaque session dans la liste).

## Édition des questions par thème (post-plan initial)

- Le formulaire unique "Ajouter une question" (avec sélection de thème) a été retiré, remplacé par :
  - Une section par thème listant ses questions avec deux champs texte éditables **en ligne** (question/réponse).
  - Un bouton **unique** "+ Ajouter une question (à chaque thème normal)" qui crée une question vide dans **tous les thèmes normaux en même temps** (une "manche") — garantit par construction que les thèmes normaux restent à égalité.
  - À la création d'un nouveau thème normal, ses questions vides sont **auto-créées** pour rattraper le nombre de questions des thèmes existants ; à la création d'un thème bonus, autant que de joueurs actuels.
  - À l'ajout d'un joueur, **une question vide est auto-créée dans la catégorie bonus** (pour suivre `nombre de joueurs`).
- Les éditions de texte des questions sont **locales** (pas sauvegardées à chaque frappe) : un bouton **"✓ Valider les questions"** (désactivé tant que `readiness.ready` est faux) envoie tous les textes en une fois (`PATCH` en parallèle).
- Garde anti-perte : navigation interne (`onBeforeRouteLeave`) et fermeture d'onglet (`beforeunload`) demandent confirmation s'il y a des modifications de questions non validées.

## Palette de couleurs pour les thèmes (post-plan initial)

- Remplacement du `<input type="color">` par un jeu de **10 pastilles fixes** (`THEME_COLORS` dans `SessionEditView.vue`). Une couleur déjà utilisée par un autre thème de la session est grisée et non cliquable.
- Couleurs choisies et validées avec le skill `dataviz` (`scripts/validate_palette.js`, mode dark, surface `#161d34` = `--surface` de l'app, `--pairs all`) : bande de luminosité OKLCH, plancher de chroma et contraste ≥3:1 passent toutes les 10. La séparation daltonisme/vision normale ne passe pas le seuil idéal sur les 2 paires les plus proches — c'est une limite connue au-delà de ~8 couleurs catégorielles simultanées. Mitigation déjà en place : le nom du thème est toujours affiché à côté de la pastille (badges partout dans l'app), donc l'identification ne repose jamais sur la couleur seule.
- **Révision "plus flash"** : chroma poussée vers la limite du gamut sRGB pour chaque teinte (recherche binaire de la chroma max par teinte + facteur ~0.9-0.95).
- **Révision "rééquilibrage"** (moins de bleu-vert, un vrai jaune) : seulement 2 teintes froides (teal + bleu, au lieu de 3), jaune ajouté en poussant volontairement sa luminosité au-dessus de la bande validée (sinon un jaune saturé se lit comme olive/moutarde). Palette finale : `#da1a69 #e84415 #da7e1a #e2b936 #569f18 #03816b #318eb5 #8050eb #b31ce4 #e51eb9`.
- **Thème bonus automatique** : `ensureBonusTheme()` en crée un dès le chargement de l'écran d'édition s'il n'en existe pas (nom "Bonus", première couleur libre de la palette), avec un nombre de questions qui suit dynamiquement le nombre de thèmes normaux (= joueurs) comme avant. Un bouton "+ Ajouter une question ici" par thème (normal ou bonus) permet d'en ajouter une manuellement en plus, en complément du bouton "manche" qui ajoute une question à tous les thèmes normaux en même temps.
- **Nouveau thème normal** : reçoit `DEFAULT_NORMAL_QUESTION_COUNT = 5` questions vides s'il n'y a pas encore d'autre thème normal pour donner la cible (au lieu de 0 avant).
- **Couleur "rainbow"** : le thème bonus auto-créé (`ensureBonusTheme()`) reçoit `color: 'rainbow'` (tient dans la colonne `varchar(7)` sans migration — 7 caractères). C'est une valeur spéciale reconnue côté front : partout où une couleur de thème s'affiche (`ColorDot.vue`, `GridNumber.vue`), `color === 'rainbow'` applique la classe `.is-rainbow` (dégradé animé en CSS) au lieu d'un `background-color` uni. Composant `ColorDot.vue` créé pour factoriser cette logique (utilisée 6 fois dans 3 fichiers).

## Étapes de réalisation

```
0. [FAIT] Scaffolding Docker + Symfony (API Platform) + Vue (Vite) + docker-compose.yml
   → vérif : `docker compose up` lance les 3 services sans erreur
1. [FAIT] Entités Doctrine (Session/Theme/Question) + migrations
   → vérif : CRUD fonctionnel via /api (Swagger UI API Platform)
2. [FAIT] Endpoints reset + "answer"
   → vérif : test manuel via Swagger UI
3. [FAIT] Front : routing + appel API (liste sessions)
   → vérif : la liste des sessions créées en base s'affiche
4. [FAIT] Écran d'édition de session (thèmes + questions + params)
   → vérif : création d'une session complète avec plusieurs thèmes/questions colorés
5. [FAIT] Écran de jeu : grille + timer couleur→noir + génération ordre
   → vérif : la grille suit le paramètre de session et le minuteur fonctionne
6. [FAIT] Clic chiffre → modale question/réponse + verrouillage + reset
   → vérif : parcours complet jouable de bout en bout sur une vraie session
```
