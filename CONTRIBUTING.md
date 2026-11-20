# Contribuer à 299koEVO / Contributing to 299koEVO

[🇫🇷 Guide en Français](#-guide-de-contribution-français) • [🇬🇧 English Guide](#-contribution-guide-english)

---

## 🇫🇷 Guide de contribution (Français)

Merci pour votre intérêt envers **299koEVO** ! Ce projet est un CMS libre, indépendant et respectueux de la vie privée. Toute contribution est la bienvenue : amélioration du cœur, création de plugins, confection de thèmes, traductions ou retours d'expérience.

### 🌟 Comment pouvez-vous aider ?

1. **Partager vos idées & créations** : Sur [GitHub Discussions](https://github.com/NemStudio18/299koEVO/discussions).
2. **Signaler un bug** : En ouvrant une [Issue GitHub](https://github.com/NemStudio18/299koEVO/issues) avec une description claire et un moyen de reproduire.
3. **Créer ou améliorer un plugin** : Sur le dépôt officiel des extensions [`NemStudio18/Plugins_299koEVO`](https://github.com/NemStudio18/Plugins_299koEVO).
4. **Créer des thèmes visuels** : Sur le dépôt dédié aux thèmes [`NemStudio18/Skins_299koEVO`](https://github.com/NemStudio18/Skins_299koEVO).
5. **Améliorer le code du cœur** : Proposez vos pull requests directement sur ce dépôt.

---

### 🛠️ Règles de développement & standards

Pour garantir la stabilité, la légèreté et la sécurité de l'écosystème :

1. **PHP 8.2+ strict** :
   - Typage strict des paramètres et retours de fonction.
   - Ne pas introduire de bibliothèques lourdes ou de frameworks tiers obligatoires : le CMS doit rester ultra-rapide et autonome.
2. **Prise en charge du Thème Sombre (Dark Mode)** :
   - Tout composant HTML/CSS doit supporter nativement le mode clair et le mode sombre via `:root[data-theme="dark"]`.
3. **Validation JavaScript** :
   - Validez toujours votre code JS (`node -c <fichier.js>`) pour éviter toute régression.
4. **Sécurité & Confidentialité** :
   - Validez systématiquement les jetons CSRF pour les requêtes POST modifiant l'état (`\Core\Security\Csrf::validate()`).
   - Assurez l'échappement HTML pour prévenir les injections XSS.
   - **Aucune dépendance externe de télémétrie ou de pistage tiers** (pas de polices Google Fonts obligatoires distantes, pas de scripts de suivi).
5. **Hygiène Git** :
   - Ne commitez jamais de fichiers locaux, de configurations temporaires ou de données de test (`data/`, `RAPPORT_*.md`, etc.).

---

### 🔀 Workflow de Pull Request

1. **Forkez** le dépôt et créez votre branche (`git checkout -b feature/ma-super-amelioration`).
2. **Testez** vos modifications sur votre serveur local.
3. **Commitez** vos modifications avec un message clair suivant la convention [Conventional Commits](https://www.conventionalcommits.org/) (ex: `feat(core): ...`, `fix(admin): ...`).
4. **Poussez** votre branche sur votre fork (`git push origin feature/ma-super-amelioration`).
5. **Ouvrez une Pull Request** avec un résumé clair de ce qui change et pourquoi.

---

## 🇬🇧 Contribution Guide (English)

Thank you for your interest in **299koEVO**! As an open-source, independent, and privacy-first CMS, every contribution helps make the web simpler and more autonomous.

### 🌟 Ways to Contribute

1. **Join the Community**: Share ideas and show off your websites on [GitHub Discussions](https://github.com/NemStudio18/299koEVO/discussions).
2. **Report Bugs**: Submit clear and reproducible bug reports via [GitHub Issues](https://github.com/NemStudio18/299koEVO/issues).
3. **Build Plugins**: Add or extend modules in the official [`NemStudio18/Plugins_299koEVO`](https://github.com/NemStudio18/Plugins_299koEVO) repository.
4. **Design Themes**: Contribute custom skins and templates in [`NemStudio18/Skins_299koEVO`](https://github.com/NemStudio18/Skins_299koEVO).
5. **Core Enhancements**: Submit pull requests directly to this repository.

### 🛠️ Development Guidelines

- **PHP 8.2+**: Clean, typed, and dependency-light code.
- **Dark Mode Support**: Every frontend and admin view must support `:root[data-theme="dark"]`.
- **Privacy & Security**: Always check CSRF tokens, sanitize inputs/outputs, and never inject third-party trackers or CDNs that violate user privacy.
- **Git Hygiene**: Keep commits focused and descriptive using conventional commit formats (`feat:`, `fix:`, `refactor:`).
