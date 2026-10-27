# Contrats API SUMA — Backend Laravel

Contrats relevés dans le backend Laravel du dépôt. Les chemins ci-dessous sont relatifs au préfixe `/api`.
Ce document décrit l'implémentation observée ; il ne remplace pas les contrôles d'autorisation côté serveur.

## Conventions communes

- Routes privées : `Authorization: Bearer <token>` et compte actif (`auth:sanctum`, `active`), sauf indication contraire.
- Les erreurs de validation API sont généralement `422` :

```json
{
  "message": "Validation failed.",
  "errors": {
    "field_name": ["The field_name field is required."]
  }
}
```

- Réponses d'authentification : `401` si non authentifié.
- Autorisation refusée : `403`.
- Ressource inconnue ou enfant ne correspondant pas au parent d'URL : `404`.
- Les dates sont validées avec le validateur Laravel `date`, sans format strict unique. Envoyer de préférence les dates-heures au format ISO 8601 avec fuseau, par exemple `2026-10-25T09:00:00+00:00`. Les réponses de dates-heures suivent la sérialisation JSON de Laravel/Carbon.
- Sauf précision, les listes renvoient une propriété `data`. Les actions de création ou mise à jour renvoient souvent aussi un `message`.

## Portée des lectures et des rôles

Les filtres transmis par le client ne peuvent pas élargir le périmètre imposé par le serveur.

| Lecture | Administrateur | Responsable de département | Étudiant | Encadreur | Juré |
|---|---|---|---|---|---|
| Liste et fiche des mémoires | Tous les mémoires | Mémoires de son département | Ses propres mémoires | Mémoires qu'il encadre | Non autorisé |
| Calendrier authentifié des soutenances | Tous les dossiers | Dossiers de son département | Ses dossiers | Dossiers qu'il encadre | Dossiers auxquels il est affecté |
| Rapport PDF d'une soutenance | Tous les dossiers | Dossiers de son département | Participant au dossier | Encadreur affecté | Juré affecté |
| Profils d'encadreurs | Tous les profils | Profils de son département | Encadreur de son dossier | Son propre profil | Encadreurs des dossiers auxquels il est affecté |

Le calendrier public est distinct : il ne montre que les soutenances planifiées et explicitement publiées, sans identité des étudiants ou encadreurs, résultat ni document. L'inscription publique crée uniquement un compte étudiant ; `role_id` est refusé. Les comptes étudiants et jurés peuvent être importés par CSV (administrateur), et les comptes d'encadreur sont créés par la gestion des profils d'encadreur. Les opérations sous `/admin`, dont la suppression d'année académique, exigent le rôle administrateur.

## 1. Sujets de mémoire

Routes déclarées dans `routes/api.php`, contrôleur `ThesisSubjectController`.

| Méthode et chemin | Rôles et action |
|---|---|
| `GET /thesis-subjects` | Administrateur, responsable de département, encadreur ou étudiant ; résultats bornés par rôle. |
| `POST /thesis-subjects` | Étudiant : créer sa proposition en brouillon. |
| `PUT /thesis-subjects/{thesisSubject}` | Étudiant propriétaire : modifier son brouillon ou sujet rejeté. |
| `POST /thesis-subjects/{thesisSubject}/submit` | Étudiant propriétaire : soumettre le sujet. Corps vide. |
| `POST /thesis-subjects/published` | Administrateur ou responsable du département fourni : publier un sujet. |
| `POST /thesis-subjects/{thesisSubject}/select` | Étudiant du même département : sélectionner un sujet publié non attribué. Corps vide. |
| `POST /thesis-subjects/{thesisSubject}/review` | Administrateur ou responsable du département du sujet : examiner/décider. |
| `GET /thesis-subjects/{thesisSubject}` | Administrateur, étudiant propriétaire, responsable du département ; encadreur s'il encadre le mémoire lié. |

### Liste

Query parameters :

- `status` : `draft`, `submitted`, `under_review`, `accepted`, `rejected`, `published`.
- `department_id` : identifiant de département existant ; le filtre est appliqué au rôle administrateur.
- `per_page` : entier de 1 à 100, défaut 15.

Réponse :

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

### Créer ou modifier une proposition

`POST /thesis-subjects` et `PUT /thesis-subjects/{thesisSubject}` utilisent les champs suivants :

```json
{
  "title": "Étude de la qualité de l’eau",
  "problem_statement": "Description du problème",
  "objectives": "Objectifs de l’étude",
  "keywords": ["eau", "qualité"],
  "additional_information": "Informations complémentaires"
}
```

- `title` : requis à la création, chaîne de 255 caractères maximum.
- `problem_statement`, `objectives`, `additional_information` : facultatifs, chaînes de 10 000 caractères maximum.
- `keywords` : tableau facultatif de 20 éléments maximum ; chaque élément est une chaîne de 100 caractères maximum.
- Le `PUT` accepte une mise à jour partielle.

Création renvoie `201`, mise à jour `200`. Réponse :

```json
{
  "message": "Subject proposal created successfully.",
  "data": {
    "id": 3,
    "title": "Étude de la qualité de l’eau",
    "problem_statement": "Description du problème",
    "objectives": "Objectifs de l’étude",
    "keywords": ["eau", "qualité"],
    "additional_information": "Informations complémentaires",
    "status": "draft",
    "is_published": false,
    "published_at": null,
    "decision_reason": null,
    "created_at": "2026-10-24T19:00:00.000000Z",
    "updated_at": "2026-10-24T19:00:00.000000Z"
  }
}
```

Les relations `department`, `student`, `reviewer` et `history` sont incluses lorsqu'elles sont chargées par le point de terminaison.

### Publication départementale

`POST /thesis-subjects/published` :

```json
{
  "department_id": 3,
  "title": "Sujet proposé par le département",
  "problem_statement": "Description facultative",
  "objectives": "Objectifs facultatifs",
  "keywords": ["recherche"],
  "additional_information": null
}
```

`department_id` est requis et doit exister. Le responsable de département ne peut publier que pour son propre département ; l'administrateur peut publier pour le département indiqué. Réponse `201`.

### Soumission, sélection et décision

- Soumission : `POST /thesis-subjects/{id}/submit`, corps vide. Seuls les statuts `draft` ou `rejected` peuvent être soumis ; sinon `422`.
- Sélection : `POST /thesis-subjects/{id}/select`, corps vide. Le sujet publié doit être libre et correspondre au département de l'étudiant ; sinon `403` ou `422`.
- Décision : `POST /thesis-subjects/{id}/review` :

```json
{
  "status": "rejected",
  "remarks": "Veuillez préciser la méthodologie."
}
```

`status` doit être `under_review`, `accepted` ou `rejected`. `remarks` est requis pour un rejet, facultatif autrement ; 5 000 caractères maximum. Seuls les sujets `submitted` ou `under_review` peuvent être examinés, sinon `422`.

Réponse de ces actions : `200`, avec `message` et `data` (ressource du sujet mise à jour).

## 2. Pré-soutenances

Contrôleur : `PreDefenseSessionController`.

| Méthode et chemin | Rôles et action |
|---|---|
| `GET /pre-defense-sessions` | Administrateur, responsable de département, encadreur, étudiant ; résultats limités par rôle. |
| `POST /pre-defense-sessions` | Administrateur, responsable de département ou encadreur : créer une session. |
| `GET /pre-defense-sessions/{session}` | Administrateur, responsable assigné, responsable du département concerné, étudiant ayant un passage ou encadreur d'un mémoire de la session. |
| `PUT /pre-defense-sessions/{session}` | Administrateur, responsable assigné ou responsable du département : modifier une session planifiée. |
| `POST /pre-defense-sessions/{session}/status` | Mêmes gestionnaires : clôturer en `completed` ou `cancelled`. |
| `POST /pre-defense-sessions/{session}/passages` | Mêmes gestionnaires : ajouter un passage. |
| `PUT /pre-defense-sessions/{session}/passages/{passage}` | Mêmes gestionnaires : modifier un passage appartenant à cette session. |

La ressource filtrée pour un étudiant ne montre que ses propres passages et l'historique de ces passages (avec les événements de session).

### Filtres de liste

- `department_id` : département existant ; appliqué au rôle administrateur.
- `status` : `scheduled`, `completed`, `cancelled`.
- `per_page` : 1 à 100, défaut 15.

La réponse est paginée avec `data` et `meta` (`current_page`, `last_page`, `per_page`, `total`).

### Création de session

```json
{
  "department_id": 3,
  "responsible_user_id": 12,
  "location": "Salle A",
  "scheduled_at": "2026-10-25T09:00:00+00:00",
  "duration_minutes": 240
}
```

- `department_id`, `responsible_user_id`, `location`, `scheduled_at` sont requis.
- La durée est facultative, défaut 240 minutes, bornes 1 à 480.
- Le responsable doit être actif et être administrateur, responsable du département concerné ou encadreur rattaché à ce département.
- `scheduled_at` est validé comme date ; le fuseau horaire explicite est recommandé.
- Réponse `201` : `message` et `data` avec id, département, responsable, lieu, horaire, `ends_at`, durée, statut, clôture, passages, historique et timestamps (relations selon chargement).

### Mise à jour de session

`PUT /pre-defense-sessions/{session}` accepte un ou plusieurs champs :

```json
{
  "responsible_user_id": 12,
  "location": "Salle B",
  "scheduled_at": "2026-10-25T10:00:00+00:00",
  "duration_minutes": 180
}
```

Les champs suivent les mêmes contraintes que la création, mais sont facultatifs. La session doit être `scheduled`. Les passages déjà planifiés doivent rester compris dans le nouvel intervalle. Réponse `200`.

### Passages

Ajout (`POST .../{session}/passages`) :

```json
{
  "thesis_id": 42,
  "position": 1,
  "scheduled_at": "2026-10-25T09:15:00+00:00",
  "duration_minutes": 15,
  "observations": "Observations",
  "recommendations": "Recommandations"
}
```

- `thesis_id` : mémoire existant et du même département que la session.
- `position` : requis, entier ≥1 et unique par session.
- `scheduled_at` : requis, date devant tenir dans la session.
- `duration_minutes` : facultatif, défaut 15 minutes, 1 à 240.
- `observations`, `recommendations` : facultatifs, 10 000 caractères maximum.

Mise à jour (`PUT .../{session}/passages/{passage}`) : champs partiels `position`, `scheduled_at`, `duration_minutes`, `observations`, `recommendations`, `outcome`. `outcome` vaut `pending`, `ready` ou `needs_revision`. Le mémoire d'un passage ne peut pas être remplacé par cette route.

Les conflits de lieu, d'étudiant, avec une soutenance officielle, les passages hors créneau ou les positions répétées provoquent `422`. Le `passage` qui ne correspond pas à la session donne `404`.

### Clôture

```json
{"status": "completed"}
```

Le statut doit être `completed` ou `cancelled`. Une session ne peut être clôturée que depuis `scheduled`; pour `completed`, tous les passages doivent avoir un résultat non `pending`. Réponse `200`.

## 3. Réunions d'encadrement

Contrôleur : `SupervisionMeetingController`.

| Méthode et chemin | Rôles et action |
|---|---|
| `GET /supervision-meetings` | Participants uniquement : étudiant et encadreur associés à la réunion. |
| `POST /theses/{thesis}/supervision-meetings` | Étudiant propriétaire ou encadreur assigné au mémoire. |
| `GET /supervision-meetings/{meeting}` | Participants uniquement. |
| `PUT /supervision-meetings/{meeting}` | Participant, si la réunion est planifiée. |
| `POST /supervision-meetings/{meeting}/actions` | Participant d'une réunion non annulée ; création d'action assignée à un participant. |
| `PUT /supervision-meetings/{meeting}/actions/{action}` | Participant ; la personne affectée à l'action doit effectuer la mise à jour de son statut. |

Un administrateur non participant n'obtient pas de droit supplémentaire par le seul rôle administrateur.

### Filtres

- `thesis_id` : mémoire existant.
- `status` : `scheduled`, `completed`, `cancelled`.
- `per_page` : 1 à 100, défaut 15.

Réponse paginée avec `data` et `meta`.

### Création de réunion

```json
{
  "scheduled_at": "2026-10-25T09:00:00+00:00",
  "duration_minutes": 60,
  "mode": "in_person",
  "location": "Bureau 2",
  "agenda": "Ordre du jour",
  "minutes": "Compte rendu",
  "difficulties": "Difficultés rencontrées",
  "decisions": "Décisions"
}
```

- `scheduled_at` et `mode` sont requis.
- `duration_minutes` facultatif, défaut 60, bornes 1 à 480.
- `mode` est une chaîne jusqu'à 50 caractères ; bien que le modèle fournisse `in_person` et `online`, la requête ne restreint pas la valeur à ces choix.
- `location` : chaîne facultative, 255 caractères maximum.
- `agenda` : 5 000 caractères maximum ; `minutes`, `difficulties`, `decisions` : 10 000 caractères maximum.
- Un encadreur doit être affecté au mémoire. Les participants sont établis côté serveur.

La réunion ne peut pas chevaucher une autre réunion d'un participant. Réponse `201`, avec `message` et `data` : `id`, `thesis_id`, horaires, durée, mode, lieu, textes, statut, participants, actions, historique et timestamps.

### Modification et actions

`PUT /supervision-meetings/{meeting}` accepte partiellement les champs de création ainsi que `status` (`scheduled`, `completed`, `cancelled`). Terminer met `completed_at` à l'heure courante ; remettre à `scheduled` l'efface.

Création d'action :

```json
{
  "assigned_to": 12,
  "description": "Envoyer le plan révisé",
  "due_at": "2026-10-30"
}
```

`assigned_to` et `description` sont requis ; l'assigné doit être un participant. `due_at` est facultatif, validé comme date et casté en date (jour).

Mise à jour d'action : `status` requis (`pending`, `in_progress`, `completed`), avec `assigned_to`, `description`, `due_at` facultatifs. Réponse réunion/action : `200` pour mise à jour ; `201` pour création. Un enfant d'action ne correspondant pas à la réunion donne `404`. Les affectations invalides et conflits horaires donnent `422`.

## 4. Corrections liées aux versions

Contrôleur : `ThesisCorrectionController`.

| Méthode et chemin | Rôles et action |
|---|---|
| `GET /theses/{thesis}/corrections` | Toute personne autorisée à consulter le mémoire. |
| `POST /theses/{thesis}/corrections` | Encadreur assigné, responsable du département concerné ou administrateur : créer une correction. |
| `POST /theses/{thesis}/corrections/{correction}/status` | Étudiant propriétaire : `corrected`. Encadreur/chef de département/administrateur autorisé à créer une correction : `to_correct` ou `validated`. |

Création :

```json
{
  "thesis_version_id": 17,
  "page_number": 4,
  "section": "Méthodologie",
  "anchor": "Paragraphe 2",
  "remark": "Préciser la méthode d’échantillonnage.",
  "priority": "high"
}
```

- `thesis_version_id` : requis ; doit référencer une version de ce mémoire précis.
- `remark` : requis, 5 000 caractères maximum.
- `page_number` : facultatif, entier ≥1.
- `section`, `anchor` : chaînes facultatives, 255 caractères maximum.
- `priority` : facultatif : `low`, `medium`, `high`.

Réponse `201`, `message` et `data` avec id, `thesis_id`, version, page, section, ancre, remarque, priorité, statut, auteur, résolveur, résolution, historique et timestamps.

Mise à jour de statut :

```json
{
  "status": "corrected",
  "remarks": "Correction effectuée dans la version suivante."
}
```

`status` et `remarks` sont requis ; remarques jusqu'à 5 000 caractères. Transitions : `to_correct → corrected → validated` ou retour `corrected → to_correct`. Toute autre transition provoque `422`. Un identifiant de correction qui n'appartient pas au mémoire de l'URL donne `404`.

## 5. Calendrier public

Route publique (aucun token) : `GET /defense-calendar`.

Paramètres :

- `from` et `to` : requis, dates parseables ; `to` doit être égal ou postérieur à `from`.
- `department_id` : facultatif, département existant.
- `room_id` : facultatif, salle existante.

Exemple :

```http
GET /api/defense-calendar?from=2026-10-24T00:00:00Z&to=2026-10-31T23:59:59Z&department_id=3
```

Réponse :

```json
{
  "data": [
    {
      "id": 8,
      "scheduled_at": "2026-10-25T09:00:00.000000Z",
      "ends_at": "2026-10-25T10:00:00.000000Z",
      "thesis": {
        "title": "Étude de la qualité de l’eau",
        "department": "Sciences",
        "program": "Master"
      },
      "room": {
        "name": "Salle A",
        "building": "Bâtiment 1"
      }
    }
  ]
}
```

Seules les soutenances au statut `scheduled` et explicitement marquées publiques sont retournées. La ressource n'expose pas les identités étudiant/encadreur, les résultats ni les documents. La liste n'est pas paginée. Validation invalide : `422`.

## 6. Imports CSV

Routes sous `/admin/imports/{type}` ; type limité par la route à `students` ou `jury-members`. Rôle requis : administrateur, compte actif et authentifié.

| Méthode et chemin | Résultat |
|---|---|
| `GET /admin/imports/{type}/template` | Télécharge un gabarit `text/csv; charset=UTF-8`. |
| `POST /admin/imports/{type}/preview` | Prévisualise et valide un fichier multipart `file`. |
| `POST /admin/imports/{type}/confirm` | Confirme l'import avec le même fichier et son empreinte. |

`type` invalide : route non trouvée (`404`). `file` est requis, types MIME/extensions CSV ou TXT, limite **5 Mo**. Maximum **5 000 lignes de données** (hors lignes vides). Les en-têtes doivent correspondre exactement au gabarit, dans le même ordre :

- `students` : `first_name,last_name,email,phone,student_number,department_code,program_code,academic_year_label,enrollment_date`
- `jury-members` : `first_name,last_name,email,phone,department_code,specialization`

Le contenu CSV est séparé par virgules et les champs peuvent être entre guillemets doubles. L'import ne prend pas de colonne rôle : le rôle est défini par le type.

Exemple de ligne étudiants :

```csv
first_name,last_name,email,phone,student_number,department_code,program_code,academic_year_label,enrollment_date
Ada,Lovelace,ada@example.test,5550100,S-001,SCI,SCI-1,2026-2027,2026-09-01
```

Prévisualisation `200` :

```json
{
  "data": {
    "type": "students",
    "preview_hash": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
    "total_rows": 1,
    "valid_rows": 1,
    "duplicate_rows": 0,
    "invalid_rows": 0,
    "rows": [
      {
        "line": 2,
        "valid": true,
        "duplicate": false,
        "errors": [],
        "data": {
          "first_name": "Ada",
          "last_name": "Lovelace",
          "email": "ada@example.test",
          "phone": "5550100",
          "student_number": "S-001",
          "department_code": "SCI",
          "program_code": "SCI-1",
          "academic_year_label": "2026-2027",
          "enrollment_date": "2026-09-01"
        }
      }
    ]
  }
}
```

Le rapport contient les données de lignes CSV ; il peut donc contenir des informations personnelles. L'accès est réservé à l'administrateur.

Confirmation : le POST multipart doit inclure `file` **et** `preview_hash` (empreinte SHA-256 de 64 caractères hexadécimaux fournie par l'aperçu). Un fichier modifié depuis l'aperçu est rejeté en `422`, erreur `preview_hash`.

Succès `200` :

```json
{
  "message": "CSV import completed. Duplicate rows were skipped.",
  "data": {
    "created_count": 1,
    "duplicate_count": 0,
    "report": {
      "type": "students",
      "preview_hash": "...",
      "total_rows": 1,
      "valid_rows": 1,
      "duplicate_rows": 0,
      "invalid_rows": 0,
      "rows": []
    }
  }
}
```

Doublons d'email (et de numéro étudiant pour un import étudiant) : lignes signalées puis ignorées. Toute autre ligne invalide empêche l'import entier et renvoie `422` avec le rapport dans `data`. En-têtes incorrects, fichier vide, taille/lignes trop élevées ou validation invalide : `422`. Non-administrateur : `403`. Les mots de passe aléatoires attribués ne sont pas renvoyés.

## 7. Profil utilisateur

Route privée : `POST /auth/profile`. Accepte `multipart/form-data` (recommandé avec avatar) ou un formulaire sans avatar. Tous les champs sont facultatifs :

| Champ | Validation |
|---|---|
| `first_name` | Chaîne, maximum 100 caractères. |
| `last_name` | Chaîne, maximum 100 caractères. |
| `email` | Email valide, maximum 255 caractères, unique hors utilisateur courant. |
| `phone` | Chaîne nullable, maximum 30 caractères. |
| `avatar` | Image, maximum **2 048 Ko (2 Mo)**. |

Exemple multipart : `first_name=Ada`, `email=ada@example.test`, `avatar=<fichier image>`.

Réponse `200` :

```json
{
  "message": "Profile updated successfully.",
  "data": {
    "id": 12,
    "first_name": "Ada",
    "last_name": "Lovelace",
    "full_name": "Ada Lovelace",
    "email": "ada@example.test",
    "phone": null,
    "avatar": "avatars/nom-du-fichier",
    "email_verified_at": null,
    "is_active": true,
    "last_login_at": "2026-10-24T19:00:00.000000Z",
    "role": {},
    "created_at": "2026-10-24T19:00:00.000000Z",
    "updated_at": "2026-10-24T19:00:00.000000Z"
  }
}
```

`avatar` est un chemin du disque `public`, pas une URL complète. Les champs rôle et état du compte ne sont pas acceptés par cette requête ; la modification concerne l'utilisateur authentifié. Validation invalide, email déjà utilisé ou avatar dépassant la limite : `422`.

## Sources d'implémentation

- Routes : `routes/api.php`
- Contrôleurs : `app/Http/Controllers/Api/Thesis/ThesisSubjectController.php`, `app/Http/Controllers/Api/Defense/PreDefenseSessionController.php`, `app/Http/Controllers/Api/Supervision/SupervisionMeetingController.php`, `app/Http/Controllers/Api/Thesis/ThesisCorrectionController.php`, `app/Http/Controllers/Api/Defense/PublicDefenseCalendarController.php`, `app/Http/Controllers/Api/Admin/CsvImportController.php`, `app/Http/Controllers/Api/Auth/AuthController.php`
- Form Requests correspondantes : `app/Http/Requests/Thesis`, `app/Http/Requests/PreDefense`, `app/Http/Requests/Supervision`, `app/Http/Requests/Defense/PublicDefenseCalendarRequest.php`, `app/Http/Requests/Admin`, `app/Http/Requests/Auth/UpdateProfileRequest.php`
- Ressources JSON : `app/Http/Resources`
