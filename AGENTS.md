# RÈGLES DE GOUVERNANCE, D'ARCHITECTURE ET DE SYNCHRONISATION (ÉCOSYSTÈME 299ko / DÉCLIC / VIVRE-BOURGES)

Ce document constitue le référentiel absolu et permanent pour l'agent Antigravity et les développeurs intervenant sur l'écosystème.

---

## 1. CARTOGRAPHIE DES ENVIRONNEMENTS & RÔLES

### 1.1 Déclic Local (`declic18.test`)
- **Chemin local** : `/home/max/Déclic`
- **Dépôt Git** : `git@github-nemstudio:NemStudio18/299koEVO.git` (branche `main`)
- **Rôle** : **Laboratoire de référence et Source de Vérité du Noyau CMS (Core)**.
- Toute évolution du CMS (administration, routage, templates système, marketplace client, gestion des utilisateurs, etc.) est développée, validée et testée en local ici.

### 1.2 Site Démo / Vitrine Officielle (`299ko.flexcb.fr`)
- **Serveur** : `192.168.1.178` via SSH `maximeblanc`
- **Chemin serveur** : `/var/www/my_webapp__19/www/`
- **Utilisateur système** : `my_webapp__19:www-data`
- **Rôle** : **Vitrine officielle de 299ko EVO**.
- **RÈGLE CRITIQUE** : Ce site doit TOUJOURS afficher la dernière version patchée et fonctionnelle du Core, exactement identique à Déclic local. Tout patch appliqué sur Déclic doit être déployé sur cette instance.

### 1.3 Portail Vivre-Bourges (`vivre-bourges.fr`)
- **Serveur** : `192.168.1.178` via SSH `maximeblanc`
- **Chemin serveur** : `/var/www/my_webapp__13/www/`
- **Utilisateur système** : `my_webapp__13:www-data`
- **Rôle** : **Site portail de production**.
- Fonctionne sur le Core 299ko + Thème exclusif `VivreBourges` + Plugins VIP (`LocalFeed`, `SearchMaps`, etc.) associés au jeton de licence `VB-PRO-2026`.

### 1.4 Hub API Marketplace (`299ko-api.flexcb.fr`)
- **Serveur** : `192.168.1.178` via SSH `maximeblanc`
- **Chemin serveur** : `/var/www/my_webapp__9/www/`
- **Utilisateur système** : `my_webapp__9:www-data`
- **Rôle** : **Serveur de distribution des extensions (Plugins, Thèmes)**.
- Lit les dépôts GitHub (publics et privés), génère les packages ZIP, calcule les empreintes SHA1, gère les tokens VIP et sert le catalogue API.

---

## 2. LES DÉPÔTS GITHUB

| Dépôt GitHub | Dossier local | Rôle / Contenu |
| :--- | :--- | :--- |
| `NemStudio18/299koEVO` | `/home/max/Déclic` | Core CMS 299ko / Déclic (Admin, Core, Templates) |
| `NemStudio18/Plugins_299koEVO` | `/home/max/Plugins_299koEVO` | Plugins publics (ex: LiveChat, Blog, Contact...) |
| `NemStudio18/299koEVO_Exclusive` | `/home/max/299koEVO_Exclusive` | Plugins et Thèmes VIP (LocalFeed, VivreBourges...) |
| `NemStudio18/Skins_299koEVO` | `/home/max/Skins_299koEVO` | Thèmes et skins publics |

---

## 3. PROTOCOLE DE SYNCHRONISATION (WORKFLOW STRICT)

### Protocole A : Évolution ou Correctif du Core CMS
1. **Édition & Test local** sur `/home/max/Déclic` (`declic18.test`).
2. **Commit & Push Git** :
   ```bash
   git -C /home/max/Déclic add -A
   git -C /home/max/Déclic commit -m "feat/fix: description précise"
   git -C /home/max/Déclic push origin main
   ```
3. **Déploiement sur 299ko.flexcb.fr (`my_webapp__19`)** :
   - Synchroniser les fichiers modifiés (`admin/`, `common/`, etc.).
   - Fixer les droits : `sudo chown -R my_webapp__19:www-data ...`.
4. **Déploiement sur vivre-bourges.fr (`my_webapp__13`)** :
   - Si le patch impacte le fonctionnement général du Core.
   - Fixer les droits : `sudo chown -R my_webapp__13:www-data ...`.

### Protocole B : Évolution ou Correctif d'une Extension (Plugin ou Thème)
1. **Édition dans le bon dépôt** (`Plugins_299koEVO` ou `299koEVO_Exclusive`).
2. **Incrémenter la version** dans `infos.json` (ex: `1.1.0` -> `1.1.1`).
3. **Commit & Push Git** sur le dépôt distant.
4. **Purge du cache du Hub API (`my_webapp__9`)** :
   ```bash
   ssh maximeblanc@192.168.1.178 "sudo rm -f /var/www/my_webapp__9/www/data/state.json /var/www/my_webapp__9/www/data/cache/*.json"
   ```
5. **Vérification de la mise à jour** :
   - Vérifier via API que la nouvelle version est exposée.
   - Vérifier que l'admin marketplace de `my_webapp__19` et `my_webapp__13` propose la mise à jour et qu'elle s'applique sans encombre.

---

## 4. RÈGLES DE CODAGE & DE SÉCURITÉ

1. **Variables JavaScript** : Toujours déclarer et calculer les structures de données (ex: `counts`) AVANT toute utilisation conditionnelle. Valider systématiquement par `node -c <fichier.js>`.
2. **Cohérence de la Casse (Case Sensitivity)** :
   - Les slugs de plugins doivent être rigoureusement identiques entre `infos.json`, le nom du dossier et le nom du zip (CamelCase ou minuscule, sans divergence).
   - Le moteur `MarketPlaceManager.php` conserve sa protection d'extraction insensible à la casse pour prévenir les doublons résiduels.
3. **Thème Sombre (Dark Mode)** :
   - Tout composant visuel doit comporter ses règles spécifiques sous `:root[data-theme="dark"]` et `html[data-theme="dark"]`.
   - Interdiction de laisser des fonds clairs hardcodés (`#fff`, `#f1f5f9`) sans équivalent sombre contrasté.
4. **Permissions Unix** :
   - Tout fichier déployé sur le serveur distant doit appartenir à l'utilisateur de l'application correspondante (`my_webapp__X:www-data`) avec permissions `644` (fichiers) ou `755` (dossiers).

---

## 5. CHECKLIST DE CLÔTURE D'UNE INTERVENTION

Avant de confirmer à l'utilisateur qu'une action est terminée :
- [ ] Le statut `git status` des dépôts concernés est propre (`clean`), validé et pushé sur GitHub.
- [ ] Les fichiers sur `299ko.flexcb.fr` et/ou `vivre-bourges.fr` sont synchronisés.
- [ ] Les droits d'accès et d'exécution serveur sont conformes.
- [ ] Les pages web ou points de terminaison concernés ont été testés (aucun 500, aucun crash JS dans la console).
