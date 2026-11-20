# Feuille de Route / Roadmap — 299koEVO

[🇫🇷 Français](#-feuille-de-route-2026---2027) • [🇬🇧 English](#-roadmap-2026---2027)

---

## 🇫🇷 Feuille de Route (2026 - 2027)

Cette feuille de route présente les grandes orientations fonctionnelles et techniques prévues pour l'écosystème 299koEVO.

### ✅ Réalisé récemment (v0.3.x)
- [x] **Moteur multi-stockage agnostique** : Support natif fichiers JSON, SQLite et MySQL/MariaDB avec bascule fluide.
- [x] **Antispam souverain (`299nobot`)** : Challenge cryptographique HMAC-SHA256, PoW adaptative dans le navigateur, zéro reCAPTCHA ni pistage tiers.
- [x] **Marketplace Hub intégré** : Téléchargement, installation et mise à jour des plugins et thèmes en 1 clic.
- [x] **Thème sombre universel** : Intégration complète du Dark Mode dans l'administration et les thèmes officiels.
- [x] **Plateforme Petites Annonces C2C** : Formulaire de dépôt public, modération administrative et espace membre.
- [x] **E-commerce & Passerelle de paiement** : Tunnel de commande complet avec intégration Stripe Checkout et suivi colis.

---

### 🎯 En cours & Prochaines étapes

#### 1. Expérience Utilisateur & Hub Marketplace (Court terme)
- [ ] **Présentation visuelle du Marketplace** : Captures d'écran et fiches détaillées des extensions directement dans le catalogue.
- [ ] **Vérification automatique des dépendances** : Validation stricte des plugins requis avant l'activation d'un thème ou d'une extension.
- [ ] **Gestionnaire de sauvegardes 1-clic** : Exportation et restauration complète de l'instance (code + données) au format archive ZIP.

#### 2. Architecture & API (Moyen terme)
- [ ] **API Headless REST v1** : Points de terminaison sécurisés pour consommer les contenus 299koEVO depuis des applications mobiles ou front-ends modernes.
- [ ] **Système de Webhooks** : Déclencheurs d'événements (nouvel article, nouvelle commande, nouveau message) pour s'interfacer avec des outils d'automatisation.
- [ ] **PWA (Progressive Web App) intégrée** : Mode hors-ligne, manifeste prêt à l'emploi et notifications push Web standards.

#### 3. Écosystème & Communauté (Long terme)
- [ ] **SDK & Générateur de plugins CLI** : Outil pour échafauder rapidement un nouveau plugin conforme aux standards.
- [ ] **Annuaire communautaire de thèmes** : Documentation étendue sur le moteur de templates pour les designers web.

---

## 🇬🇧 Roadmap (2026 - 2027)

### ✅ Recently Completed (v0.3.x)
- [x] **Agnostic Multi-Storage Engine**: JSON flat-file, SQLite, and MySQL/MariaDB support.
- [x] **Privacy-First Antispam (`299nobot`)**: HMAC-SHA256 token signing + browser Proof-of-Work without Google reCAPTCHA.
- [x] **Built-in Marketplace Hub**: One-click install and update system for plugins and themes.
- [x] **Universal Dark Mode**: Seamless theme switching in both frontend and admin panel.
- [x] **C2C Classified Ads Platform**: Public submission, admin moderation, and seller dashboard.
- [x] **E-Commerce & Payment Gateway**: Checkout flow with Stripe integration and parcel tracking.

### 🎯 Upcoming Milestones
- [ ] **Marketplace Enhancements**: Extension screenshots and rich preview cards.
- [ ] **One-Click Backup & Restore**: Full instance snapshot export/import.
- [ ] **Headless REST API v1**: JSON endpoints for mobile apps and decoupled frontends.
- [ ] **Webhooks System**: Event triggers for modern web automation.
- [ ] **Plugin Developer CLI**: Rapid scaffolding tool for module authors.

