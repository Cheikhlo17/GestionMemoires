# SUMA — Gestion des mémoires et des soutenances

SUMA est le **backend Laravel** d'une application universitaire de gestion des sujets de mémoire, du suivi des étudiants et encadreurs, des soutenances et de l'archivage.

> **Important :** ce dépôt contient le backend Laravel et son API. Le frontend Angular est dans un autre projet et n'est pas inclus ici. L'adresse `/` affiche la page d'accueil Laravel ; elle n'est pas l'interface de gestion SUMA.

Ce guide explique l'installation locale, les rôles et un parcours complet de l'application. Les contrats détaillés (champs, exemples JSON, réponses et erreurs) des principaux domaines sont dans [`CONTRATS_API_SUMa.md`](./CONTRATS_API_SUMa.md).

## 1. Comment l'application fonctionne

Une interface (par exemple le frontend Angular, Postman ou une autre application) envoie des requêtes HTTP à l'API Laravel. L'API vérifie l'identité, le rôle, les données et les règles métier, puis lit ou modifie la base de données et renvoie du JSON.

```text
Frontend Angular / Postman / client HTTP
                    |
                    | requêtes HTTP /api/...
                    v
           Backend Laravel (ce dépôt)
          routes, contrôleurs, validations,
          autorisations, services, modèles
                    |
          +---------+-----------+
          |                     |
          v                     v
      Base MySQL          Fichiers privés/publics
```

Le backend expose notamment :

- l'inscription, la connexion, la gestion du profil et les jetons Sanctum ;
- les dossiers étudiants, départements, programmes et années universitaires ;
- les sujets proposés par les étudiants ou publiés par un département ;
- les mémoires, leurs versions, commentaires et corrections ;
- les réunions d'encadrement avec actions de suivi ;
- les pré-soutenances et les passages d'étudiants ;
- les soutenances officielles, le jury, les évaluations et le résultat final ;
- le calendrier public des soutenances, après publication explicite ;
- l'archivage privé ou public des mémoires éligibles ;
- les imports CSV administrateur pour étudiants et membres du jury.

Le code est organisé principalement sous `app/Http` (routes, contrôleurs, validations et ressources JSON), `app/Policies` (autorisations), `app/Services` (règles métier), `app/Models` (données Eloquent) et `database/migrations` (structure de base).

## 2. Prérequis

Installez les outils suivants sur votre ordinateur :

- **PHP 8.2 ou supérieur**, avec les extensions exigées par Laravel et les pilotes de base de données utilisés ;
- **Composer**, pour installer les dépendances PHP ;
- **MySQL**, si vous voulez utiliser le moteur configuré dans `.env.example` ;
- **Node.js et npm** seulement si vous voulez construire les ressources Vite/Blade de ce dépôt. Ils ne remplacent pas Angular et ne sont pas nécessaires pour tester les routes JSON.
- **Git** pour récupérer ou mettre à jour le code.

Vérifiez les outils dans PowerShell :

```powershell
php -v
composer --version
mysql --version
node --version
npm --version
```

La commande `node`/`npm` est facultative si vous n'utilisez que l'API.

## 3. Installation locale (Windows / PowerShell)

Ouvrez PowerShell dans le dossier du backend Laravel.

### Étape 1 — Installer les dépendances PHP

```powershell
composer install
```

### Étape 2 — Créer le fichier de configuration local

```powershell
Copy-Item .env.example .env
```

Le fichier `.env` contient la configuration privée de **votre machine**. Ne le publiez pas et ne le transmettez pas dans Git.

### Étape 3 — Préparer une base de développement

Créez une base MySQL locale vide, par exemple `gestionmemoire`, puis renseignez les valeurs correspondantes dans `.env` :

```dotenv
APP_NAME=SUMA
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:4200

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestionmemoire
DB_USERNAME=votre_utilisateur_mysql
DB_PASSWORD=votre_mot_de_passe_mysql
```

Utilisez les identifiants de votre installation MySQL. L'exemple n'exige pas que vous utilisiez `root` ni un mot de passe vide.

Générez ensuite la clé Laravel :

```powershell
php artisan key:generate
```

### Étape 4 — Vérifier et appliquer les migrations

Commencez par regarder l'état des migrations :

```powershell
php artisan migrate:status
```

Sur une **base locale de développement que vous avez vérifiée**, appliquez les migrations en attente :

```powershell
php artisan migrate
```

Cette commande crée ou met à jour les tables de l'application. Elle ne doit pas être lancée sur une base de production sans sauvegarde et procédure de déploiement validée. N'utilisez pas `migrate:fresh` ou `db:wipe` sur une base contenant des données à conserver.

Les migrations récentes comprennent les sujets, les corrections, les réunions, les soutenances publiques et les pré-soutenances. Les noms des index des migrations corrections/réunions ont été raccourcis pour respecter la limite MySQL ; récupérez la version courante du code avant de relancer une migration ayant échoué.

### Étape 5 — (Facultatif) Ajouter les données de démonstration

Sur une base **locale jetable uniquement**, vous pouvez ajouter les départements, programmes, rôles, années et comptes de démonstration :

```powershell
php artisan db:seed
```

Le seeder crée des comptes et données d'exemple, y compris des mots de passe définis dans le code. N'exécutez pas ce seeder en production. Consultez `database/seeders/UserSeeder.php` et `database/seeders/DemoAccountSeeder.php`, changez les mots de passe des comptes locaux après connexion et ne réutilisez jamais ces comptes en production.

Le seeder est prévu pour un schéma vide ou déjà compatible. Pour un premier démarrage, effectuez d'abord les migrations. Ne relancez pas les seeders sur une base utilisateur sans comprendre leurs `updateOrCreate` et les données qu'ils modifient.

### Étape 6 — Démarrer le serveur API

```powershell
php artisan serve
```

Par défaut, l'API est accessible à `http://127.0.0.1:8000/api`. La racine `http://127.0.0.1:8000/` affiche la page de bienvenue Laravel.

Pour exécuter les tests automatisés :

```powershell
php artisan test
```

La configuration PHPUnit du dépôt utilise SQLite en mémoire (`:memory:`) : les tests ne doivent donc pas utiliser les données MySQL locales.

### Étape 7 — (Facultatif) Construire les ressources Vite/Blade

Le JavaScript/CSS présent dans ce dépôt est le scaffolding Laravel/Vite, pas l'application Angular :

```powershell
npm install
npm run build
```

Pour développer les ressources Vite :

```powershell
npm run dev
```

L'installation et le démarrage du frontend Angular se font dans son propre dossier/projet, non documenté par ce dépôt.

## 4. Envoyer une première requête à l'API

Vous pouvez utiliser Postman, Insomnia, une future interface Angular ou PowerShell. Pour les routes JSON, envoyez généralement l'en-tête :

```http
Accept: application/json
Content-Type: application/json
```

### Connexion

Exemple PowerShell, avec des valeurs de compte **à remplacer** par un compte local valide :

```powershell
$baseUrl = "http://127.0.0.1:8000"
$loginBody = @{
    email = "votre-compte@example.test"
    password = "VotreMotDePasse"
} | ConvertTo-Json

$login = Invoke-RestMethod `
    -Method Post `
    -Uri "$baseUrl/api/auth/login" `
    -ContentType "application/json" `
    -Headers @{ Accept = "application/json" } `
    -Body $loginBody

$token = $login.data.token
```

`POST /api/auth/login` renvoie le jeton dans `data.token`. Les exemples suivants l'utilisent dans l'en-tête Bearer :

```powershell
$headers = @{
    Accept = "application/json"
    Authorization = "Bearer $token"
}

Invoke-RestMethod -Method Get -Uri "$baseUrl/api/auth/me" -Headers $headers
```

Les jetons sont personnels : ne les mettez pas dans le code source, une capture d'écran publique ou un dépôt Git.

### Inscription d'un étudiant

```powershell
$registerBody = @{
    first_name = "Awa"
    last_name = "Diop"
    email = "awa@example.test"
    password = "Remplacez-Moi-123!"
    password_confirmation = "Remplacez-Moi-123!"
} | ConvertTo-Json

Invoke-RestMethod `
    -Method Post `
    -Uri "$baseUrl/api/auth/register" `
    -ContentType "application/json" `
    -Headers @{ Accept = "application/json" } `
    -Body $registerBody
```

L'inscription publique crée toujours un compte avec le rôle `student`; le client ne peut pas choisir un rôle privilégié. La création de ce compte ne crée pas à elle seule le dossier académique étudiant (département, programme, année et numéro étudiant). L'administration doit créer/associer le profil étudiant, ou importer un dossier avec le processus CSV décrit plus bas.

## 5. Rôles et responsabilités

Les rôles enregistrés par le seeder sont :

| Rôle | Usage général |
|---|---|
| `administrator` | Paramètres et opérations d'administration, imports, gestion globale autorisée. |
| `student` | Proposer/sélectionner un sujet, créer et déposer son mémoire, consulter ses données, participer aux réunions et passages qui le concernent. |
| `supervisor` | Suivre les mémoires assignés, réunions, corrections et certaines pré-soutenances/soutenances liées. |
| `head-of-department` | Examiner les sujets de son département et administrer les parcours pédagogiques autorisés pour ce département. |
| `jury-member` | Consulter les soutenances auxquelles il est affecté et déposer son évaluation. |

Les autorisations sont appliquées côté serveur. Le fait de masquer un bouton dans une interface ne suffit pas à autoriser ou interdire une action. Selon l'action, l'accès peut aussi dépendre du département, de l'affectation au mémoire ou de la participation à une réunion/séance.

## 6. Parcours métier de bout en bout

Un parcours normal suit généralement l'ordre ci-dessous. Les rôles exacts autorisés sont également vérifiés par l'API ; ce résumé n'est pas un moyen de contourner ces vérifications.

### Étape A — Préparer les données institutionnelles

L'administration configure les départements, programmes et années universitaires, puis crée ou importe les profils étudiants, encadreurs et membres du jury.

Routes principales :

- `GET|POST /api/admin/departments`
- `GET|POST|PUT|DELETE /api/admin/programs`
- `GET|POST|PUT|DELETE /api/admin/academic-years`
- `GET|POST /api/students` et routes étudiant associées, selon les autorisations.
- `GET /api/lookups/departments`, `/api/lookups/programs`, `/api/lookups/academic-years`, `/api/lookups/supervisors`, `/api/lookups/jury-members`

Une création de mémoire dépend de relations cohérentes : étudiant, sujet accepté, département, programme et année universitaire doivent correspondre.

### Étape B — Créer et approuver un sujet

Un étudiant peut :

1. proposer un sujet via `POST /api/thesis-subjects` ;
2. soumettre sa proposition via `POST /api/thesis-subjects/{id}/submit` ;
3. consulter son statut via `GET /api/thesis-subjects/{id}`.

Un administrateur ou le responsable autorisé du département peut publier un sujet via `POST /api/thesis-subjects/published`. Un responsable de département peut examiner une proposition via `POST /api/thesis-subjects/{id}/review`. Un étudiant peut choisir un sujet départemental publié et non encore attribué via `POST /api/thesis-subjects/{id}/select`.

**Important :** un mémoire ne peut être créé/soumis que si un sujet accepté est attribué à cet étudiant.

### Étape C — Créer le mémoire et déposer une version

Créer un brouillon : `POST /api/theses`.

Exemple de corps (les identifiants doivent venir des données de votre base) :

```json
{
  "title": "Plateforme de suivi universitaire",
  "abstract": "Résumé facultatif du mémoire.",
  "department_id": 1,
  "program_id": 2,
  "academic_year_id": 1,
  "thesis_subject_id": 7
}
```

L'étudiant ne transmet pas `student_id` : le serveur le déduit du compte authentifié. Il faut téléverser au moins une version avant de soumettre le mémoire :

```http
POST /api/theses/{thesis}/versions
Content-Type: multipart/form-data
```

Form-data : `file` obligatoire, `notes` facultatif. Les types déclarés acceptés sont PDF, DOCX et ZIP, limite **20 Mo**. Pour l'archivage final, une version PDF est requise.

Téléverser un fichier ne soumet pas automatiquement le mémoire. Après le dépôt, utiliser `POST /api/theses/{thesis}/submit`. La soumission nécessite également le sujet accepté attribué.

### Étape D — Affecter un encadreur et suivre la révision

Un administrateur ou responsable de département autorisé peut affecter un encadreur :

```http
POST /api/theses/{thesis}/assign-supervisor
```

```json
{"supervisor_id": 4}
```

Le superviseur examine le mémoire puis peut utiliser `POST /api/theses/{thesis}/status` pour les transitions permises par le cycle de vie, par exemple `under_review`, `revision_required` ou `approved`. Les transitions invalides sont refusées.

Routes de consultation et d'échange :

- `GET /api/theses` : liste limitée au rôle/portée de l'utilisateur ;
- `GET /api/theses/{thesis}` : détail soumis à autorisation ;
- `GET|POST /api/theses/{thesis}/comments` : commentaires ;
- `GET|POST /api/theses/{thesis}/corrections` : corrections liées à une version précise ;
- `POST /api/theses/{thesis}/corrections/{correction}/status` : progression d'une correction ;
- `GET /api/theses/{thesis}/versions` : versions du mémoire ;
- `GET /api/theses/{thesis}/versions/{version}/download` : téléchargement autorisé.

### Étape E — Organiser les réunions d'encadrement

Un étudiant ou son encadreur peut planifier une réunion sur un mémoire auquel il participe :

```http
POST /api/theses/{thesis}/supervision-meetings
```

Le corps contient notamment `scheduled_at` (date/heure), `mode`, et éventuellement `duration_minutes`, `location` et `agenda`. Les dates ISO 8601 avec fuseau sont recommandées.

Les participants peuvent consulter la réunion et suivre des actions :

- `GET /api/supervision-meetings`
- `GET /api/supervision-meetings/{meeting}`
- `PUT /api/supervision-meetings/{meeting}`
- `POST /api/supervision-meetings/{meeting}/actions`
- `PUT /api/supervision-meetings/{meeting}/actions/{action}`

Les actions sont assignées à un participant ; les données de réunion ne sont pas une liste publique.

### Étape F — Organiser une pré-soutenance

Un administrateur, responsable de département ou encadreur autorisé crée une session :

```http
POST /api/pre-defense-sessions
```

Champs requis : `department_id`, `responsible_user_id`, `location`, `scheduled_at`. La durée est facultative (240 minutes par défaut). Ensuite, le responsable ajoute les passages ordonnés via `POST /api/pre-defense-sessions/{session}/passages`, avec le mémoire, l'heure, la position et éventuellement les observations/recommandations.

Les passages doivent tenir dans la session ; l'API contrôle les conflits de lieu, étudiant et soutenance officielle. Le responsable indique ensuite le résultat du passage avec `PUT /api/pre-defense-sessions/{session}/passages/{passage}` (`pending`, `ready`, `needs_revision`) et clôture la session via `POST /api/pre-defense-sessions/{session}/status`. Une session ne peut être complétée tant qu'un passage est encore `pending`.

### Étape G — Planifier la soutenance officielle

Un administrateur ou le responsable du département peut créer une soutenance :

```http
POST /api/defense-schedules
```

Corps de base :

```json
{
  "thesis_id": 12,
  "defense_room_id": 2,
  "scheduled_at": "2026-11-15T10:00:00+00:00",
  "duration_minutes": 90
}
```

La date doit être future ; durée facultative de 30 à 240 minutes. Le jury est ensuite affecté via `POST /api/defense-schedules/{schedule}/assign-jury`. Le corps `jury` contient trois membres avec les rôles `president`, `examiner`, `reporter`.

Les membres du jury affectés déposent leur évaluation via `POST /api/defense-schedules/{schedule}/evaluations`. L'administrateur ou le responsable du département enregistre le résultat final via `POST /api/defense-schedules/{schedule}/result`. Le verdict vaut `pass`, `pass_with_revisions` ou `fail`; la note facultative est comprise entre 0 et 20.

Routes utiles :

- `GET /api/defense-schedules/mine` : soutenances associées à l'utilisateur ;
- `GET /api/defense-schedules/calendar` : calendrier authentifié, avec accès limité ;
- `GET /api/defense-schedules/{schedule}` : détail selon autorisation ;
- `GET /api/defense-schedules/{schedule}/report` : rapport PDF soumis à autorisation.

### Étape H — Publier une soutenance dans le calendrier public

Une soutenance reste privée tant qu'un utilisateur autorisé ne l'a pas publiée :

```http
PUT /api/defense-schedules/{schedule}/publication
```

```json
{"is_public": true}
```

Le calendrier public `GET /api/defense-calendar` n'expose que les soutenances `scheduled` explicitement publiées. Les dates `from` et `to` sont obligatoires. La ressource publique exclut notamment les identités étudiant/encadreur, le résultat et les documents.

### Étape I — Archiver le mémoire

L'archivage est une action administrative distincte. Les conditions métier incluent l'approbation du mémoire, une version PDF, une soutenance terminée et un résultat final qui n'est pas un échec.

1. Vérifier les conditions avec `GET /api/admin/theses/{thesis}/archive-eligibility`.
2. Si le mémoire est éligible, créer l'archive avec `POST /api/admin/theses/{thesis}/archive`.
3. La visibilité doit être décidée explicitement : `private` par défaut ou `public` si sa publication est autorisée.
4. Une archive publique apparaît dans `GET /api/archives` et peut être consultée/téléchargée par les routes publiques d'archives. Une archive privée ne doit pas être rendue publique automatiquement.

## 7. Authentification, profil et erreurs fréquentes

### Routes d'authentification

- `POST /api/auth/register` : inscription étudiant publique ;
- `POST /api/auth/login` : connexion, limitée à cinq tentatives par minute ;
- `POST /api/auth/logout` : révoquer le jeton courant ;
- `GET /api/auth/me` : identité et rôle du compte ;
- `POST /api/auth/profile` : modifier le profil, y compris un avatar image de 2 Mo maximum ;
- `POST /api/auth/change-password` : changer le mot de passe ;
- `POST /api/auth/forgot-password` puis `POST /api/auth/reset-password` : récupération ;
- `POST /api/auth/resend-verification` : renvoyer un lien de vérification.

Un mot de passe d'inscription/changement doit respecter la politique de validation (au moins 8 caractères avec majuscule/minuscule, chiffre et symbole). Les notifications email dépendent de la configuration `MAIL_*` de `.env`.

### Format courant des erreurs API

Validation incorrecte (`422`) :

```json
{
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

Autres codes courants :

- `401` : jeton absent ou invalide ;
- `403` : rôle ou autorisation insuffisant ;
- `404` : identifiant inconnu ou enfant n'appartenant pas au parent demandé ;
- `409` : conflit de planning signalé par le service concerné ;
- `422` : données ou transition métier invalides.

Pour éviter les réponses HTML d'erreur, envoyez `Accept: application/json`.

## 8. Imports CSV administrateur

Les imports sont disponibles uniquement pour le rôle administrateur :

- `GET /api/admin/imports/students/template`
- `POST /api/admin/imports/students/preview`
- `POST /api/admin/imports/students/confirm`
- les trois routes équivalentes avec `jury-members`.

Le flux est : télécharger le gabarit → compléter le CSV → envoyer un multipart `file` à `preview` → examiner le rapport → réenvoyer le même fichier et la valeur `preview_hash` à `confirm`.

Règles importantes :

- fichier CSV/TXT de 5 Mo maximum, 5 000 lignes de données maximum ;
- noms et ordre des colonnes doivent correspondre exactement au gabarit ;
- les rôles sont déterminés par le type d'import, jamais par une colonne du fichier ;
- les doublons sont rapportés et ignorés ;
- les lignes invalides autres que les doublons empêchent tout l'import ;
- la confirmation rejette un fichier différent de celui prévisualisé ;
- les rapports incluent les données des lignes et sont réservés aux administrateurs ;
- les mots de passe aléatoires attribués aux comptes importés ne sont pas renvoyés par l'API ; configurez et testez le processus de récupération de mot de passe avant un usage réel.

Les colonnes CSV exactes et un exemple de réponse sont documentés dans [`CONTRATS_API_SUMa.md`](./CONTRATS_API_SUMa.md).

## 9. Résultats et mentions académiques

Les notes de soutenance utilisent une échelle de 0 à 20. Les seuils et libellés de mention ne sont pas inventés par l'application : ils sont fournis par l'établissement dans `ACADEMIC_GRADE_MENTIONS`.

La valeur par défaut est une liste JSON vide (`[]`), donc aucune mention n'est attribuée par défaut. Avant de renseigner cette variable, validez officiellement les bornes avec l'établissement. Chaque entrée doit avoir `min`, `max` et `label`; les intervalles doivent être dans l'échelle 0–20 et ne pas se chevaucher.

Exemple de **forme uniquement**, à remplacer par des seuils officiellement approuvés :

```dotenv
ACADEMIC_GRADE_MENTIONS=[{"min":0,"max":10,"label":"LIBELLE_A_VALIDER"},{"min":10.01,"max":20,"label":"AUTRE_LIBELLE_A_VALIDER"}]
```

Après une modification `.env` sur un serveur utilisant une configuration mise en cache, actualisez la configuration selon la procédure de déploiement Laravel.

## 10. Commandes utiles

```powershell
# Vérifier les routes réellement enregistrées
php artisan route:list --path=api

# Voir l'état des migrations sans rien appliquer
php artisan migrate:status

# Appliquer les migrations en attente (base cible à vérifier auparavant)
php artisan migrate

# Exécuter tous les tests
php artisan test

# Vérifier le style PHP sur un fichier précis
vendor\bin\pint --test app\Services\CsvImportService.php

# Lancer le serveur local
php artisan serve
```

Ne lancez pas les commandes de migration avant d'avoir confirmé la base utilisée dans `.env`. Les tests sont isolés sur SQLite en mémoire, tandis que les migrations de développement et de déploiement agissent sur la base configurée.

## 11. Points à connaître avant une mise en production

- Définir `APP_ENV=production`, `APP_DEBUG=false`, une clé secrète robuste et des identifiants MySQL dédiés et limités.
- Ne jamais déployer les comptes, mots de passe ou données de démonstration des seeders.
- Sauvegarder la base et les fichiers avant une mise à jour de schéma.
- Vérifier `php artisan migrate:status`, puis appliquer les migrations via une procédure contrôlée ; ne jamais utiliser `migrate:fresh` ou `db:wipe` en production.
- Configurer le courrier, la file d'attente, le stockage privé/public et les tâches nécessaires à l'environnement avant de dépendre de ces services.
- Confirmer les seuils de mention académique et les autorisations de publication des archives avec l'établissement.
- Tester les API avec les rôles réels et vérifier les autorisations côté serveur ; un guard frontend seul ne protège aucune donnée.
- Mettre en place le frontend Angular dans son dépôt séparé, configurer son URL d'API vers `/api` et définir la configuration CORS/Sanctum appropriée aux domaines réellement utilisés.

## 12. Documentation et références

- Contrats de routes, payloads et réponses : [`CONTRATS_API_SUMa.md`](./CONTRATS_API_SUMa.md)
- Routes API : `routes/api.php`
- Configuration d'exemple : `.env.example` (modèle uniquement ; les secrets vont dans `.env`)
- Migrations : `database/migrations`
- Seeders : `database/seeders`
- Tests : `tests/`
- Documentation officielle Laravel : <https://laravel.com/docs/12.x>
