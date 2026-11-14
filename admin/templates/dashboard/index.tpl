<div class="adm-dash-wrap">
    <!-- Hero Header Dynamique -->
    <section class="adm-dash-hero">
        <div class="adm-dash-hero-content">
            <span class="adm-dash-kicker"><i class="fa-solid fa-circle-check"></i> Centre de contrôle • {{siteName|default('Déclic')}}</span>
            <h1 class="adm-dash-title">{{greeting}}, {{adminName}} !</h1>
            <p class="adm-dash-sub">{{formattedDate}} • Vue d'ensemble du site et raccourcis d'administration.</p>
        </div>
        <div class="adm-dash-hero-actions">
            <a href="{{siteUrl}}" target="_blank" class="adm-btn-site-preview" title="Ouvrir le site public dans un nouvel onglet">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>Voir le site</span>
            </a>
            <div class="adm-dash-quick">
                {% for ql in quickLinks %}
                    <a href="{{ql.url}}" class="adm-quick-btn">
                        <i class="{{ql.icon}}"></i>
                        <span>{{ql.label}}</span>
                    </a>
                {% endfor %}
            </div>
        </div>
    </section>

    <!-- Grille de KPIs Temps Réel -->
    <div class="adm-kpi-grid">
        <a href="/admin/page" class="adm-kpi-card">
            <div class="kpi-icon kpi-blue"><i class="fa-regular fa-file-lines"></i></div>
            <div class="kpi-body">
                <span class="kpi-label">Pages CMS</span>
                <strong class="kpi-val">{{stats.publishedPages|default(0)}}</strong>
                <span class="kpi-sub">{{stats.draftPages|default(0)}} brouillon(s)</span>
            </div>
        </a>

        <a href="/admin/lightstats" class="adm-kpi-card">
            <div class="kpi-icon kpi-green"><i class="fa-solid fa-chart-line"></i></div>
            <div class="kpi-body">
                <span class="kpi-label">Visiteurs Aujourd'hui</span>
                <strong class="kpi-val">{{trafficStats.todayVisits|default(0)}}</strong>
                <span class="kpi-sub">{{trafficStats.todayPageviews|default(0)}} page(s) vue(s)</span>
            </div>
        </a>

        <a href="/admin/users" class="adm-kpi-card">
            <div class="kpi-icon kpi-indigo"><i class="fa-solid fa-users"></i></div>
            <div class="kpi-body">
                <span class="kpi-label">Utilisateurs</span>
                <strong class="kpi-val">{{stats.usersCount|default(0)}}</strong>
                <span class="kpi-sub">Comptes enregistrés</span>
            </div>
        </a>

        <a href="/admin/pluginsmanager" class="adm-kpi-card">
            <div class="kpi-icon kpi-purple"><i class="fa-solid fa-puzzle-piece"></i></div>
            <div class="kpi-body">
                <span class="kpi-label">Plugins & Extensions</span>
                <strong class="kpi-val">{{stats.activePlugins|default(0)}} / {{stats.totalPlugins|default(0)}}</strong>
                <span class="kpi-sub">Modules installés</span>
            </div>
        </a>

        {% for card in pluginCards %}
        <a href="{{card.url}}" class="adm-kpi-card">
            <div class="kpi-icon kpi-{{card.accent|default('blue')}}"><i class="{{card.icon}}"></i></div>
            <div class="kpi-body">
                <span class="kpi-label">{{card.title}}</span>
                <strong class="kpi-val">{{card.value}}</strong>
                <span class="kpi-sub">{{card.sub}}</span>
            </div>
        </a>
        {% endfor %}
    </div>

    <!-- Disposition Double Colonne Activités & Santé Système -->
    <div class="adm-dash-columns">
        <!-- Colonne Principale (Gauche) : Activités Récentes -->
        <div class="adm-dash-main-col">
            <!-- Dernières Pages -->
            <div class="adm-card-panel">
                <div class="adm-card-header">
                    <div class="adm-card-header-title">
                        <i class="fa-regular fa-file-lines"></i>
                        <h3>Dernières pages éditées</h3>
                    </div>
                    <a href="/admin/page" class="adm-card-header-link">Voir toutes les pages →</a>
                </div>
                <div class="adm-card-body">
                    {% if recentPages %}
                    <table class="adm-table-simple">
                        <thead>
                            <tr>
                                <th>Titre de la page</th>
                                <th>Statut</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {% for p in recentPages %}
                            <tr>
                                <td>
                                    <div class="adm-page-name-cell">
                                        <i class="fa-regular fa-file-code"></i>
                                        <span class="adm-page-title">{{p.name}}</span>
                                        {% if p.isHome %}
                                            <span class="adm-badge adm-badge-gold">Accueil</span>
                                        {% endif %}
                                    </div>
                                </td>
                                <td>
                                    {% if p.isHidden %}
                                        <span class="adm-badge adm-badge-muted">Brouillon</span>
                                    {% else %}
                                        <span class="adm-badge adm-badge-green">Publiée</span>
                                    {% endif %}
                                </td>
                                <td style="text-align:right;">
                                    <a href="/admin/page/edit/{{p.id}}" class="adm-table-btn" title="Modifier cette page">
                                        <i class="fa-solid fa-pen-to-square"></i> Modifier
                                    </a>
                                </td>
                            </tr>
                            {% endfor %}
                        </tbody>
                    </table>
                    {% else %}
                    <p class="adm-empty-state">Aucune page CMS trouvée.</p>
                    {% endif %}
                </div>
            </div>

            <!-- Derniers Messages Contact (si présents) -->
            {% if recentMessages %}
            <div class="adm-card-panel">
                <div class="adm-card-header">
                    <div class="adm-card-header-title">
                        <i class="fa-regular fa-envelope"></i>
                        <h3>Derniers messages reçus</h3>
                    </div>
                    <a href="/admin/contact" class="adm-card-header-link">Messagerie complète →</a>
                </div>
                <div class="adm-card-body">
                    <div class="adm-msg-list">
                        {% for msg in recentMessages %}
                        <a href="/admin/contact" class="adm-msg-item">
                            <div class="adm-msg-avatar">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div class="adm-msg-details">
                                <div class="adm-msg-head">
                                    <strong class="adm-msg-sender">{{msg.name}}</strong>
                                    <span class="adm-msg-date">{{msg.date}}</span>
                                </div>
                                <div class="adm-msg-subject">{{msg.subject}}</div>
                            </div>
                        </a>
                        {% endfor %}
                    </div>
                </div>
            </div>
            {% endif %}

            <!-- Derniers Utilisateurs Inscrits -->
            {% if recentUsers %}
            <div class="adm-card-panel">
                <div class="adm-card-header">
                    <div class="adm-card-header-title">
                        <i class="fa-solid fa-user-plus"></i>
                        <h3>Derniers utilisateurs inscrits</h3>
                    </div>
                    <a href="/admin/users" class="adm-card-header-link">Gérer les comptes →</a>
                </div>
                <div class="adm-card-body">
                    <div class="adm-users-grid">
                        {% for u in recentUsers %}
                        <div class="adm-user-mini-card">
                            <div class="adm-user-avatar">
                                <i class="fa-solid fa-circle-user"></i>
                            </div>
                            <div class="adm-user-info">
                                <strong>{{u.username}}</strong>
                                <span>{{u.email}}</span>
                                <small class="adm-badge adm-badge-indigo">{{u.role}}</small>
                            </div>
                        </div>
                        {% endfor %}
                    </div>
                </div>
            </div>
            {% endif %}
        </div>

        <!-- Colonne Latérale (Droite) : Météo, Système & Raccourcis -->
        <div class="adm-dash-side-col">
            <!-- Widget Météo Temps Réel (si disponible) -->
            {% if weatherInfo %}
            <div class="adm-card-panel adm-weather-card">
                <div class="adm-card-header">
                    <div class="adm-card-header-title">
                        <i class="fa-solid fa-cloud-sun"></i>
                        <h3>Météo Locale en Direct</h3>
                    </div>
                    <span class="adm-weather-city">{{weatherInfo.city}}</span>
                </div>
                <div class="adm-weather-body">
                    <div class="adm-weather-main">
                        <span class="adm-weather-temp">{{weatherInfo.temp}}°C</span>
                        <div class="adm-weather-meta">
                            <span><i class="fa-solid fa-droplet"></i> {{weatherInfo.humidity}}% humidité</span>
                            <span><i class="fa-solid fa-wind"></i> {{weatherInfo.wind}} km/h vent</span>
                        </div>
                    </div>
                </div>
            </div>
            {% endif %}

            <!-- Carte Santé & Sécurité Système -->
            <div class="adm-card-panel">
                <div class="adm-card-header">
                    <div class="adm-card-header-title">
                        <i class="fa-solid fa-shield-halved"></i>
                        <h3>Santé du Système</h3>
                    </div>
                </div>
                <div class="adm-card-body">
                    <ul class="adm-health-list">
                        <li class="adm-health-item">
                            <span class="adm-health-label"><i class="fa-solid fa-lock" style="color:var(--evo-success);"></i> Sécurité SSL / HTTPS</span>
                            {% if systemHealth.https %}
                                <span class="adm-badge adm-badge-green"><i class="fa-solid fa-check"></i> Actif</span>
                            {% else %}
                                <span class="adm-badge adm-badge-muted">Local / HTTP</span>
                            {% endif %}
                        </li>
                        <li class="adm-health-item">
                            <span class="adm-health-label"><i class="fa-solid fa-database"></i> Moteur Stockage</span>
                            <strong>{{storageDriver}}</strong>
                        </li>
                        <li class="adm-health-item">
                            <span class="adm-health-label"><i class="fa-solid fa-palette"></i> Thème Actif</span>
                            <strong>{{systemHealth.activeTheme}}</strong>
                        </li>
                        <li class="adm-health-item">
                            <span class="adm-health-label"><i class="fa-solid fa-hard-drive"></i> Cache Système</span>
                            <strong>{{systemHealth.cacheSizeMb}} Mo</strong>
                        </li>
                        <li class="adm-health-item">
                            <span class="adm-health-label"><i class="fa-solid fa-user-check"></i> Inscriptions publiques</span>
                            {% if systemHealth.registration %}
                                <span class="adm-badge adm-badge-green">Ouvertes</span>
                            {% else %}
                                <span class="adm-badge adm-badge-muted">Fermées</span>
                            {% endif %}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Raccourcis d'Administration Utiles -->
            <div class="adm-card-panel">
                <div class="adm-card-header">
                    <div class="adm-card-header-title">
                        <i class="fa-solid fa-bolt"></i>
                        <h3>Actions Rapides</h3>
                    </div>
                </div>
                <div class="adm-card-body adm-side-actions">
                    <a href="/admin/page/new" class="adm-action-row">
                        <i class="fa-solid fa-file-circle-plus"></i>
                        <div>
                            <strong>Créer une page</strong>
                            <small>Rédiger et publier un nouveau contenu</small>
                        </div>
                    </a>
                    <a href="/admin/menus" class="adm-action-row">
                        <i class="fa-solid fa-bars-staggered"></i>
                        <div>
                            <strong>Organiser les menus</strong>
                            <small>Personnaliser l'arborescence du site</small>
                        </div>
                    </a>
                    <a href="/admin/filemanager" class="adm-action-row">
                        <i class="fa-solid fa-images"></i>
                        <div>
                            <strong>Médiathèque</strong>
                            <small>Gérer photos, logos et documents</small>
                        </div>
                    </a>
                    <a href="/admin/configmanager" class="adm-action-row">
                        <i class="fa-solid fa-sliders"></i>
                        <div>
                            <strong>Paramètres généraux</strong>
                            <small>SEO, titre, logo et maintenance</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {% if extraWidgets %}
    <div class="adm-dash-widgets">
        {% for w in extraWidgets %}
            <div class="adm-dash-widget-box">{{w}}</div>
        {% endfor %}
    </div>
    {% endif %}

    <!-- Barre Inférieure d'Empreinte Système -->
    <div class="adm-dash-meta-bar">
        <span><i class="fa-solid fa-code-branch"></i> 299koEVO v{{cmsVersion}}</span>
        <span><i class="fa-brands fa-php"></i> PHP {{phpVersion}}</span>
        <span><i class="fa-solid fa-server"></i> {{storageDriver}}</span>
        <span><i class="fa-solid fa-shield"></i> Architecture Modulaire & Layered CSS</span>
    </div>
</div>
