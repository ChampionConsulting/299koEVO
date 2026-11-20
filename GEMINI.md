# RÈGLES DE GOUVERNANCE, D'ARCHITECTURE ET DE SYNCHRONISATION (ÉCOSYSTÈME 299ko / DÉCLIC / VIVRE-BOURGES)

Ce document constitue le référentiel absolu et permanent pour l'agent Antigravity et les développeurs intervenant sur l'écosystème.

---

## 1. CARTOGRAPHIE DES ENVIRONNEMENTS & CHAÎNE DE VÉRITÉ

La cascade des sources de vérité fonctionne selon le schéma strict suivant :
```
[Déclic Local (declic18.test)]  ──(Commit & Push)──>  [GitHub (origin/main)]
        │ (Labo de dev & tests en live)                     │
        │ (Validation visuelle utilisateur)                 ▼
        │                                        [Hub API (299ko-api)]
        │                                           (Zips, Hashes, API)
        │                                                   │
        ▼                                                   ▼
[Tests fonctionnels immédiats]                 [299ko.flexcb.fr & Vivre-Bourges]
                                              (Mises à jour via Marketplace)
```

### 1.1 Déclic Local (`declic18.test`)
- **Chemin local** : `/home/max/Déclic`
- **Dépôt Git** : `git@github-nemstudio:NemStudio18/299koEVO.git` (branche `main`)
- **Rôle** : **Laboratoire de référence et Source de Vérité pour GitHub**.
- **RÈGLE FONDAMENTALE** : **Toute modification (Core, Plugin ou Thème) doit d'abord être testée et visualisée en direct sur Déclic local**. L'utilisateur doit pouvoir constater et valider le rendu en live avant tout déploiement extérieur.

### 1.2 GitHub (`NemStudio18/*`)
- **Rôle** : **Source de Vérité unique pour les serveurs distants et la production**.
- Aucune machine distante ne reçoit de code qui n'ait pas été préalablement validé et pushé sur GitHub.

### 1.3 Hub API Marketplace (`299ko-api.flexcb.fr` - `my_webapp__9`)
- **Rôle** : **Serveur d'emballage et de distribution des extensions**.
- Il lit les dépôts GitHub publics et exclusifs, construit les archives ZIP, calcule les empreintes SHA1 et sert le catalogue API filtré par token.

### 1.4 Site Démo / Vitrine Officielle (`299ko.flexcb.fr` - `my_webapp__19`)
- **Rôle** : **Vitrine officielle de 299ko EVO**.
- Affiche la version publique officielle du CMS.

### 1.5 Portail Vivre-Bourges (`vivre-bourges.fr` - `my_webapp__13`)
- **Rôle** : **Site portail de production**.
- Fait tourner le Core 299ko + Thème exclusif `VivreBourges` + Plugins VIP (`LocalFeed`, `SearchMaps`, etc.) associés au jeton `VB-PRO-2026`.

---

## 2. LES DÉPÔTS GITHUB

| Dépôt GitHub | Dossier local | Rôle / Contenu |
| :--- | :--- | :--- |
| `NemStudio18/299koEVO` | `/home/max/Déclic` | Core CMS 299ko / Déclic (Admin, Core, Templates) |
| `NemStudio18/Plugins_299koEVO` | `/home/max/Plugins_299koEVO` | Plugins publics (ex: LiveChat, Blog, Contact...) |
| `NemStudio18/299koEVO_Exclusive` | `/home/max/299koEVO_Exclusive` | Plugins et Thèmes VIP (LocalFeed, VivreBourges...) |
| `NemStudio18/Skins_299koEVO` | `/home/max/Skins_299koEVO` | Thèmes et skins publics |

---

## 3. PROTOCOLE STRICT DE DÉPLOIEMENT

### 3.1 Pour les Extensions (Plugins & Thèmes)
**INTERDICTION de déployer des extensions sur les serveurs distants par simple copie de fichiers locaux (scp/rsync).**
Pour garantir que la chaîne de distribution publique fonctionne sans faille :

1. **Développement & Test local** : Modification et validation en direct sur `declic18.test`.
2. **Incrément de version** : Mettre à jour `version` dans `infos.json` (ou `param/infos.json`).
3. **Commit & Push GitHub** : Pousser sur le dépôt dédié (`Plugins_299koEVO` ou `299koEVO_Exclusive`).
4. **Purge du Hub API (`my_webapp__9`)** : Forcer la régénération des ZIP et des hashes :
   ```bash
   ssh maximeblanc@192.168.1.178 "sudo rm -f /var/www/my_webapp__9/www/data/state.json /var/www/my_webapp__9/www/data/cache/*.json"
   ```
5. **Application sur les serveurs via le Marketplace** :
   Déclencher l'installation ou la mise à jour via le moteur officiel du Marketplace (soit depuis l'interface d'administration, soit en ligne de commande via l'API interne `installRessource()`).
   *Exemple CLI sur le serveur :*
   ```bash
   sudo php -r 'define("ROOT", "/var/www/my_webapp__19/www/"); define("DS", DIRECTORY_SEPARATOR); chdir(ROOT); require_once "common/common.php"; $mp = \Core\Core::getInstance()->extensions()->marketplace(); $mp->installRessource($mp->getPlugin("NomDuPlugin"));'
   ```
   *Bénéfice : Cela certifie que le package téléchargé par les serveurs est le ZIP officiel distribué par le Hub, éliminant tout écart de version ou d'arborescence.*

### 3.2 Pour le Cœur CMS (Core 299koEVO)
1. **Édition & Test en direct** sur `/home/max/Déclic` (`declic18.test`).
2. **Commit & Push** sur `NemStudio18/299koEVO:main`.
3. **Synchronisation** sur `299ko.flexcb.fr` (`my_webapp__19`) et `vivre-bourges.fr` (`my_webapp__13`).
4. **Rétablissement des droits** : `sudo chown -R my_webapp__XX:www-data ...` et `chmod 644/755`.
5. **Alignement du patch** : Mise à jour de l'empreinte dans `data/core/settings/core_patch.json` pour que toutes les instances affichent le même identifiant de commit.

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
- [ ] Le code a été visualisé et testé en direct sur `declic18.test`.
- [ ] Le statut `git status` des dépôts concernés est propre (`clean`), validé et pushé sur GitHub.
- [ ] Les extensions sur `299ko.flexcb.fr` et `vivre-bourges.fr` ont été mises à jour via le Marketplace.
- [ ] Les droits d'accès et d'exécution serveur sont conformes.
- [ ] Les pages web ou points de terminaison concernés ont été testés (aucun 500, aucun crash JS dans la console).
