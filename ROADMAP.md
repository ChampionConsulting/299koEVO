# Feuille de Route / Roadmap — 299koEVO

[🇫🇷 Français](#-feuille-de-route-2026---2027) • [🇬🇧 English](#-roadmap-2026---2027)

---

## 🇫🇷 Feuille de Route (2026 - 2027)

Cette feuille de route présente les grandes orientations fonctionnelles et techniques prévues pour l'écosystème 299koEVO.

### ✅ Réalisé récemment (v0.3.x)
- [x] **Moteur multi-stockage agnostique** : Support natif fichiers JSON, SQLite et MySQL/MariaDB avec bascule fluide.
- [x] **Antispam souverain (`299nobot`)** : Challenge cryptographique HMAC-SHA256, PoW adaptative dans le navigateur, zéro reCAPTCHA ni pistage tiers.
- [x] **Sécurisation CSRF intégrale** : Enregistrement de `{{SHOW.tokenField}}` et vérification stricte `Csrf::validate()` sur l'ensemble des formulaires et contrôleurs POST de l'écosystème (publics et exclusifs).
- [x] **Marketplace Hub intégré** : Téléchargement, installation et mise à jour des plugins et thèmes en 1 clic.
- [x] **Thème sombre universel** : Intégration complète du Dark Mode dans l'administration et les thèmes officiels.
- [x] **Plateforme Petites Annonces C2C** : Formulaire de dépôt public, modération administrative et espace membre.
- [x] **E-commerce & Passerelle de paiement** : Tunnel de commande complet avec intégration Stripe Checkout et suivi colis.

---

### 🎯 Prochaines étapes & Évolutions de l'Architecture

#### 1. Consolidation du Cœur & Abstraction de Données
- [ ] **Évolution de `JsonActiveRecord` (Haute priorité)** :
  - Support des transactions et verrous d'écriture concurrents sur JSON plat.
  - Système de migrations versionnées (`001_init.php`) avec capacités de rollback.
  - Harmonisation complète pour migrer les 10 plugins SQL direct vers l'ORM agnostique.
- [ ] **Centre de Gestion des Hooks & Événements (`EventCenter` / `HookManager`)** :
  - Catalogue des hooks disponibles avec documentation des contrats d'entrée/sortie.
  - Inspecteur visuel d'administration des écouteurs et priorité d'exécution.
- [ ] **Inspecteur de Permissions & Politiques d'Accès (`AccessPolicyEvaluator`)** :
  - Matrice visuelle interactive dans l'admin pour tester les règles ABAC selon le profil utilisateur.

#### 2. Nouveaux Modules & Outils Développeurs
- [ ] **`PluginInspector`** : Analyseur statique d'extensions (détection des dépendances circulaires, validation CSRF, hooks utilisés).
- [ ] **`BackupManager` & `SiteExporter`** : Exportation 1-clic et restauration complète (code, données, médias) en archive ZIP sécurisée pour déménager de serveur en toute indépendance.
- [ ] **`ApiManager`** : Gestionnaire de jetons d'API personnels, limitation de débit (*rate-limiting*) et documentation OpenAPI.
- [ ] **`Workflow`** : Orchestrateur d'actions automatiques (ex: publication d'article -> notification push -> flux RSS -> partage SocialFeed).
- [ ] **`TranslationManager`** : Interface de traduction comparative multilingue détectant automatiquement les chaînes manquantes.
- [ ] **`PluginSandbox`** : Environnement de test isolé pour tester un plugin sans toucher à l'instance réelle.

---

## 🇬🇧 Roadmap (2026 - 2027)

### ✅ Recently Completed (v0.3.x)
- [x] **Agnostic Multi-Storage Engine**: JSON flat-file, SQLite, and MySQL/MariaDB support.
- [x] **Privacy-First Antispam (`299nobot`)**: HMAC-SHA256 token signing + browser Proof-of-Work without Google reCAPTCHA.
- [x] **Ecosystem-Wide CSRF Enforcement**: Automatic `{{SHOW.tokenField}}` form embedding and `Csrf::validate()` checks across all POST handlers.
- [x] **Built-in Marketplace Hub**: One-click install and update system for plugins and themes.
- [x] **Universal Dark Mode**: Seamless theme switching in both frontend and admin panel.
- [x] **C2C Classified Ads Platform**: Public submission, admin moderation, and seller dashboard.
- [x] **E-Commerce & Payment Gateway**: Checkout flow with Stripe integration and parcel tracking.

### 🎯 Upcoming Milestones
- [ ] **`JsonActiveRecord` Enhancements**: Concurrency locking, versioned migration schema, and complete ORM alignment for legacy SQL modules.
- [ ] **`EventCenter` & Hook Registry**: Hook catalog, contracts documentation, and visual interaction inspector.
- [ ] **`PluginInspector`**: Static code audit tool for extensions (dependencies, circular references, security compliance).
- [ ] **`BackupManager` & `SiteExporter`**: 1-click snapshot export and seamless migration between servers.
- [ ] **`ApiManager`**: Developer API token management, rate-limiting, and auto-generated OpenAPI endpoints.
- [ ] **`Workflow` Engine**: Event-driven automation across plugins without tight coupling.
- [ ] **`TranslationManager`**: Visual localization tool for multi-language dictionary sync.
