# Gouvernance & Pérennité / Project Governance & Longevity

[🇫🇷 Français](#-gouvernance-du-projet-299koevo-français) • [🇬🇧 English](#-project-governance--longevity-english)

---

## 🇫🇷 Gouvernance du Projet 299koEVO (Français)

### 1. Philosophie & Indépendance

**299koEVO** est un projet logiciel libre et indépendant. Il n'est pas financé par une grande entreprise commerciale ni lié à des intérêts publicitaires.  
Notre boussole repose sur trois principes non négociables :
1. **Légèreté** : Ne jamais imposer une infrastructure disproportionnée ou des dépendances lourdes.
2. **Liberté** : Laisser l'utilisateur maître de ses contenus, de ses choix d'hébergement et de ses données.
3. **Indépendance** : Garantir l'autonomie du web personnel et associatif sans dépendance aux plateformes fermées.

---

### 2. Rôles et Responsabilités

| Rôle | Responsabilités |
| :--- | :--- |
| **Mainteneur Principal** (*Lead Maintainer*) | Responsable de la cohérence architecturale, de la revue finale des Pull Requests, du déploiement des correctifs de sécurité et de la publication des nouvelles versions (*Releases*). |
| **Contributeurs Réguliers** (*Core Contributors*) | Développeurs, concepteurs de thèmes et rédacteurs de documentation qui participent activement aux revues, maintiennent des modules officiels et guident les nouveaux arrivants. |
| **Communauté & Utilisateurs** | Utilisateurs qui installent le CMS, partagent leurs retours, signalent des anomalies, proposent des idées d'extensions ou aident d'autres utilisateurs sur les forums de discussion. |

---

### 3. Prise de Décision Technique

Les décisions techniques se prennent de façon transparente et pragmatique :
- **Idées & Discussions ouvertes** : Toute proposition d'évolution majeure est d'abord débattue publiquement sur [GitHub Discussions](https://github.com/NemStudio18/299koEVO/discussions).
- **Principe de frugalité** : Avant d'ajouter une fonctionnalité dans le cœur, nous nous posons toujours la question : *« Cela peut-il être réalisé sous forme de plugin ? »*. Si oui, cela doit rester une extension pour préserver la légèreté du noyau CMS.
- **Sécurité et stabilité** : Aucun compromis n'est fait sur la sécurité (CSRF, sanitization, antispam souverain) ou sur la rétrocompatibilité des formats de stockage (JSON/SQLite).

---

### 4. Continuité & Pérennité du Projet

L'une des plus grandes craintes autour des projets indépendants est le risque d'abandon si le créateur d'origine vient à manquer de temps. 299koEVO est conçu dès le premier jour pour survivre à ses auteurs :

1. **Licence Libre GPLv3 intégrale** : Le code appartient au bien commun. Nul ne peut privatiser 299koEVO.
2. **Architecture standardisée et sans magie** : Pas de framework monolithique obscur, pas de transpilation opaque. Du PHP moderne, lisible et documenté que n'importe quel développeur PHP peut auditer, réparer et faire évoluer.
3. **Plan de transmission communautaire** : En cas d'indisponibilité prolongée du mainteneur principal, les accès de gestion GitHub et les dépôts de l'organisation NemStudio18 peuvent être transmis aux contributeurs réguliers les plus actifs pour assurer la publication des correctifs de sécurité et la maintenance du catalogue API.

---

### 5. Comment devenir contributeur régulier ?

L'accès au statut de contributeur régulier se fait naturellement par l'implication :
- Contribuer régulièrement à l'amélioration du cœur ou des plugins officiels.
- Aider activement les membres dans l'espace d'entraide [GitHub Discussions](https://github.com/NemStudio18/299koEVO/discussions).
- Maintenir une extension officielle ou contribuer à la documentation.

---

## 🇬🇧 Project Governance & Longevity (English)

### 1. Philosophy & Independence

**299koEVO** is a free and independent open-source project. It is not backed by a commercial venture or advertising network.  
Our decision-making is guided by three core values:
1. **Lightweight**: Never impose disproportionate infrastructure or heavy dependencies.
2. **Freedom**: Give users full control over their content, hosting, and data.
3. **Independence**: Empower personal and non-profit websites without locking them into proprietary ecosystems.

---

### 2. Roles & Community Structure

| Role | Responsibilities |
| :--- | :--- |
| **Lead Maintainer** | Guides architectural design, conducts final PR reviews, releases official security updates, and packages new releases. |
| **Core Contributors** | Developers, theme designers, and technical writers who review code, maintain official plugins, and mentor newcomers. |
| **Community Members** | Users who deploy 299koEVO, showcase their websites, provide feedback, report bugs, and help fellow users in GitHub Discussions. |

---

### 3. Technical Decision-Making

We believe in open and rational decision-making:
- **Open RFCs and Discussions**: Major architectural changes are proposed and evaluated openly in [GitHub Discussions](https://github.com/NemStudio18/299koEVO/discussions).
- **Modularity First**: Whenever a new feature is considered, we evaluate whether it belongs in Core or in a Plugin. If it can be a plugin, it stays a plugin to keep the core lean and fast.
- **Zero Compromise on Security & Privacy**: Strict CSRF tokens, sovereign antispam, and data sovereignty always take precedence.

---

### 4. Project Longevity & Continuity

An independent project must not rely solely on a single individual. 299koEVO is built to last:
1. **GPLv3 Licensing**: The codebase is public domain and protected by copyleft. It can never be closed or privatized.
2. **Standards-based Code**: Clean, modern PHP without framework lock-in or obscure build pipelines. Any PHP developer can read, audit, and patch the CMS.
3. **Community Succession Plan**: In the event of prolonged unavailability of the lead maintainer, governance rights and official repository management can transition to the most active core contributors.
