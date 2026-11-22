<section class="module-card">
    <header>
        <i class="fa-solid fa-database"></i> {{Lang.configmanager-storage-title|default('Gestion du Stockage Agnostique')}}
    </header>

    <div class="storage-intro">
        <p>Gérez ici la flexibilité de vos données et vos infrastructures de bases de données.</p>
    </div>

    <div class="tabs-container" data-tab-key="storage">
        <ul class="tabs-header">
            <li class="default-tab" data-tab="connections">
                <i class="fa-solid fa-server"></i> Connexions SQL Disponibles
            </li>
            <li data-tab="components">
                <i class="fa-solid fa-layer-group"></i> Gestion par Composant
            </li>
            <li data-tab="info">
                <i class="fa-solid fa-circle-info"></i> Informations
            </li>
            <li data-tab="audit">
                <i class="fa-solid fa-stethoscope"></i> Intégrité & Audit
                {% if auditData.score < 90 %}
                    <span class="badge badge-warning" style="margin-left: 6px; font-size: 0.75rem;">{{ auditData.score }}/100</span>
                {% else %}
                    <span class="badge badge-success" style="margin-left: 6px; font-size: 0.75rem;">100/100</span>
                {% endif %}
            </li>
        </ul>

        <ul class="tabs">
            <!-- TAB 1 : Connexions SQL -->
            <li class="tab">
                <div class="storage-section">
                    <h3><i class="fa-solid fa-server"></i> Connexions SQL Disponibles</h3>
                    <p class="section-desc">Configurez vos accès aux bases de données externes (MySQL) ou locales (SQLite).</p>

        <div class="connection-tabs">
            <div class="tab-btn active" data-target="default">
                Défaut ({% if dbConfig.driver %}{{dbConfig.driver}}{% else %}Non configuré{% endif %})
                {% if connectionStatuses.default %}
                    <span class="status-dot {{ connectionStatuses.default.success ? 'status-online' : 'status-offline' }}" title="{{ connectionStatuses.default.message }}"></span>
                {% endif %}
            </div>
            {% for name, db in databases %}
            <div class="tab-btn" data-target="{{name|replace(':', '-')}}">
                {{name}} ({{db.driver}})
                {% if connectionStatuses[name] %}
                    <span class="status-dot {{ connectionStatuses[name].success ? 'status-online' : 'status-offline' }}" title="{{ connectionStatuses[name].message }}"></span>
                {% endif %}
            </div>
            {% endfor %}
            <div class="tab-btn btn-add-conn" data-target="add"><i class="fa-solid fa-plus"></i> Ajouter</div>
        </div>

        <!-- Panel Default -->
        <div id="panel-default" class="connection-panel active">
            {% if connectionStatuses.default and not connectionStatuses.default.success %}
            <div class="msg error" style="margin-bottom: 1rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> <strong>Erreur de connexion :</strong> {{ connectionStatuses.default.message }}
            </div>
            {% elseif connectionStatuses.default %}
            <div class="msg success" style="margin-bottom: 1rem;">
                <i class="fa-solid fa-circle-check"></i> <strong>Connexion établie avec succès.</strong>
            </div>
            {% endif %}
            <form method="post" action="{{saveDatabaseUrl}}">
                {{SHOW.tokenField}}
                <input type="hidden" name="db_name" value="default">
                <div class="db-config-grid">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Driver</label>
                            <select name="database[driver]" class="db-driver-selector">
                                <option value="mysql" {% if dbConfig.driver == 'mysql' %}selected{% endif %}>MySQL</option>
                                <option value="sqlite" {% if dbConfig.driver == 'sqlite' %}selected{% endif %}>SQLite</option>
                                <option value="pgsql" {% if dbConfig.driver == 'pgsql' %}selected{% endif %}>PostgreSQL</option>
                            </select>
                        </div>
                        <div class="form-group db-fields-mysql" style="{% if dbConfig.driver != 'mysql' %}display:none{% endif %}">
                            <label>Hôte</label>
                            <input type="text" name="database[host]" value="{{dbConfig.host|default('localhost')}}">
                        </div>
                    </div>
                    <div class="db-fields-mysql" style="{% if dbConfig.driver != 'mysql' %}display:none{% endif %}">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Base de données</label>
                                <input type="text" name="database[dbname]" value="{{dbConfig.dbname}}">
                            </div>
                            <div class="form-group">
                                <label>Utilisateur</label>
                                <input type="text" name="database[user]" value="{{dbConfig.user}}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="password" name="database[password]" placeholder="Laisser vide pour ne pas modifier">
                        </div>
                    </div>
                    <div class="db-fields-sqlite" style="{% if dbConfig.driver != 'sqlite' %}display:none{% endif %}">
                        <div class="form-group">
                            <label>Chemin du fichier (.sqlite)</label>
                            <input type="text" name="database[path]" value="{{dbConfig.path}}" placeholder="ex: data/storage/custom.sqlite">
                        </div>
                    </div>
                    <button type="submit" class="button primary">Mettre à jour la connexion par défaut</button>
                </div>
            </form>
        </div>

        <!-- Panel Add -->
        <div id="panel-add" class="connection-panel">
            <form method="post" action="{{saveDatabaseUrl}}">
                {{SHOW.tokenField}}
                <div class="db-config-grid">
                    <div class="form-group">
                        <label>Identifiant unique de la connexion (ex: <strong>secondary</strong>)</label>
                        <input type="text" name="db_name" placeholder="Nom interne sans espaces" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Driver</label>
                            <select name="database[driver]" class="db-driver-selector">
                                <option value="mysql">MySQL</option>
                                <option value="pgsql">PostgreSQL</option>
                            </select>
                        </div>
                        <div class="form-group db-fields-mysql">
                            <label>Hôte</label>
                            <input type="text" name="database[host]" value="localhost">
                        </div>
                    </div>
                    <div class="db-fields-mysql">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Base de données</label>
                                <input type="text" name="database[dbname]">
                            </div>
                            <div class="form-group">
                                <label>Utilisateur</label>
                                <input type="text" name="database[user]">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="password" name="database[password]">
                        </div>
                    </div>
                    <!-- SQLite est géré automatiquement par plugin, pas besoin de le créer manuellement ici -->
                    <button type="submit" class="button success">Ajouter cette connexion SQL</button>
                </div>
            </form>
        </div>

        {% for name, db in databases %}
        <div id="panel-{{name|replace(':', '-')}}" class="connection-panel">
            {% if connectionStatuses[name] and not connectionStatuses[name].success %}
            <div class="msg error" style="margin-bottom: 1rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> <strong>Erreur de connexion ({{name}}) :</strong> {{ connectionStatuses[name].message }}
            </div>
            {% elseif connectionStatuses[name] %}
            <div class="msg success" style="margin-bottom: 1rem;">
                <i class="fa-solid fa-circle-check"></i> <strong>Connexion '{{name}}' établie avec succès.</strong>
            </div>
            {% endif %}
            <form method="post" action="{{saveDatabaseUrl}}">
                {{SHOW.tokenField}}
                <input type="hidden" name="db_name" value="{{name}}">
                <div class="db-config-grid">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Driver</label>
                            <select name="database[driver]" class="db-driver-selector">
                                <option value="mysql" {% if db.driver == 'mysql' %}selected{% endif %}>MySQL</option>
                                <option value="sqlite" {% if db.driver == 'sqlite' %}selected{% endif %}>SQLite</option>
                                <option value="pgsql" {% if db.driver == 'pgsql' %}selected{% endif %}>PostgreSQL</option>
                            </select>
                        </div>
                        <div class="form-group db-fields-mysql" style="{% if db.driver != 'mysql' %}display:none{% endif %}">
                            <label>Hôte</label>
                            <input type="text" name="database[host]" value="{{db.host|default('localhost')}}">
                        </div>
                    </div>
                    <div class="db-fields-mysql" style="{% if db.driver != 'mysql' %}display:none{% endif %}">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Base de données</label>
                                <input type="text" name="database[dbname]" value="{{db.dbname}}">
                            </div>
                            <div class="form-group">
                                <label>Utilisateur</label>
                                <input type="text" name="database[user]" value="{{db.user}}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="password" name="database[password]" placeholder="Laisser vide pour ne pas modifier">
                        </div>
                    </div>
                    <div class="db-fields-sqlite" style="{% if db.driver != 'sqlite' %}display:none{% endif %}">
                        <div class="form-group">
                            <label>Chemin du fichier (.sqlite)</label>
                            <input type="text" name="database[path]" value="{{db.path}}">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="button primary">Mettre à jour {{name}}</button>
                        <button type="submit" name="delete_conn" value="1" class="button outline danger" onclick="return confirm('Supprimer cette connexion ?')">Supprimer</button>
                    </div>
                </div>
            </form>
        </div>
        {% endfor %}
                </div>
            </li>

            <!-- TAB 2 : Gestion par Composant -->
            <li class="tab">
                <div class="storage-section">
                    <h3><i class="fa-solid fa-layer-group"></i> Gestion par Composant</h3>
                    <p class="section-desc">Définissez le type de stockage et la base de données cible pour chaque module.</p>

        <form method="post" action="{{saveUrl}}">
            {{SHOW.tokenField}}
            
            <div class="table-container">
                <table class="storage-table">
                    <thead>
                        <tr>
                            <th>Composant</th>
                            <th>État Actuel</th>
                            <th>Cible de Stockage</th>
                            <th>Actions de Migration</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for key, model in models %}
                        {% set current = storageConfig[key]|default(storageConfig.default)|default('json') %}
                        <tr data-model="{{key}}">
                            <td>
                                <div class="model-info">
                                    <span class="model-label">{{model.label}}</span>
                                    <code class="model-class">{{model.class}}</code>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-{{current == 'json' ? 'json' : 'sql'}}" data-connection="{{current}}">
                                    {{current|upper}}
                                </span>
                            </td>
                            <td>
                                <select name="storage[{{key}}]" class="storage-selector">
                                    {% if model.supports_json %}
                                    <optgroup label="Fichiers">
                                        <option value="json" {% if current == 'json' %}selected{% endif %}>JSON (local)</option>
                                    </optgroup>
                                    {% endif %}

                                    {% if model.valid_connections %}
                                    <optgroup label="Bases de Données">
                                        {% for connId, connLabel in model.valid_connections %}
                                            <option value="{{connId}}" {% if current == connId %}selected{% endif %}>{{connLabel}}</option>
                                        {% endfor %}
                                    </optgroup>
                                    {% endif %}
                                </select>
                            </td>
                            <td class="migration-cell">
                                <button type="button" class="button outline btn-migrate" style="display:none">
                                    <i class="fa-solid fa-arrows-rotate"></i> Migrer
                                </button>
                                <div class="migration-status"></div>
                            </td>
                        </tr>
                        {% endfor %}
                    </tbody>
                </table>
            </div>

            <div class="config-actions">
                <button type="submit" class="button success large">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les affectations de stockage
                </button>
            </div>
        </form>
    </div>

    <div class="storage-help">
        <h3><i class="fa-solid fa-circle-info"></i> Guide Multi-Driver</h3>
        <div class="help-grid">
            <div class="help-item">
                <strong>Granularité</strong>
                <p>Vous pouvez faire cohabiter MySQL pour vos utilisateurs et SQLite pour vos sondages.</p>
            </div>
            <div class="help-item">
                <strong>Connexions</strong>
                <p>Créez d'abord vos connexions SQL, puis assignez-les aux composants souhaités.</p>
            </div>
        </div>
        <p class="help-warning"><strong>Attention :</strong> Assurez-vous que le driver SQL cible est bien configuré (testez la connexion) avant de migrer des données sensibles.</p>
                </div>
            </li>

            <!-- TAB 3 : Informations -->
            <li class="tab">
                <div class="storage-section" style="margin-bottom: 1.5rem;">
                    <h3><i class="fa-solid fa-circle-info"></i> Comment fonctionne le Stockage</h3>
                    <p class="section-desc">Le CMS peut stocker ses entités (Utilisateurs, Pages, Menus, Galerie, Redirections SEO…) soit dans des <strong>fichiers JSON locaux</strong> (<code>data/</code>), soit dans une <strong>base SQL</strong> (<strong>SQLite</strong>, <strong>MySQL</strong> ou <strong>PostgreSQL</strong>) configurée dans <code>data/env.json</code>.</p>

                    <div class="help-grid" style="margin-bottom: 1.5rem;">
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-database"></i> 1. Connexions SQL</h4>
                            <p>La connexion <strong>Défaut</strong> est la base principale du site. Vous pouvez aussi déclarer des connexions secondaires nommées si vous voulez isoler un module sur une autre base.</p>
                        </div>
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-layer-group"></i> 2. Affectation par composant</h4>
                            <p>Chaque composant possède sa propre cible : <code>JSON (local)</code> ou <code>Défaut (sql)</code>. C'est ici que vous choisissez quel composant lit/écrit dans JSON ou dans votre base SQL.</p>
                        </div>
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-arrows-rotate"></i> 3. Migration sécurisée</h4>
                            <p>Lorsque vous changez la cible d'un composant dans le tableau, un bouton <strong>Migrer</strong> apparaît pour copier les enregistrements existants vers la nouvelle cible avant d'enregistrer.</p>
                        </div>
                    </div>

                    <h3><i class="fa-solid fa-book"></i> Exemples concrets de paramétrage</h3>
                    <div class="help-grid">
                        <div class="db-config-grid">
                            <strong style="display: block; margin-bottom: 6px; color: #0f172a;">Exemple A — Site standard autonome (Recommandé)</strong>
                            <ul style="margin: 0 0 0 18px; line-height: 1.6; font-size: 0.9rem;">
                                <li><strong>Connexion Défaut :</strong> Driver <code>SQLite</code>, chemin <code>/home/.../data/database.sqlite</code> (aucun serveur MySQL externe nécessaire).</li>
                                <li><strong>Composants principaux :</strong> <code>User</code>, <code>PageItem</code>, <code>MenuItem</code>, <code>ContactMessage</code>, <code>GalerieItem</code>, <code>Redirect</code> sur <code>Défaut (SQL)</code>.</li>
                                <li><strong>Stockage par défaut :</strong> <code>JSON</code> pour les petits réglages et caches légers.</li>
                            </ul>
                        </div>
                        <div class="db-config-grid">
                            <strong style="display: block; margin-bottom: 6px; color: #0f172a;">Exemple B — Site à fort trafic (MySQL dédié)</strong>
                            <ul style="margin: 0 0 0 18px; line-height: 1.6; font-size: 0.9rem;">
                                <li><strong>Connexion Défaut :</strong> Driver <code>MySQL</code> (<code>localhost</code>, base dédiée, utilisateur &amp; mot de passe).</li>
                                <li><strong>Procédure :</strong> 1) Enregistrer la connexion MySQL et vérifier le voyant vert, 2) Cliquer sur <em>Migrer</em> pour chaque composant souhaité dans l'onglet <em>Gestion par Composant</em>, 3) Enregistrer les affectations.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </li>

            <!-- TAB 4 : Intégrité & Audit (DataInspector) -->
            <li class="tab">
                <div class="storage-section">
                    <h3><i class="fa-solid fa-stethoscope"></i> DataInspector : Audit d'intégrité & Réparation</h3>
                    <p class="section-desc">Vérification de la santé des fichiers de données, des références croisées et détection des données orphelines.</p>

                    <!-- Score Card -->
                    <div class="db-config-grid" style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div class="audit-score-circle {% if auditData.score >= 90 %}score-healthy{% elseif auditData.score >= 60 %}score-warning{% else %}score-critical{% endif %}">
                                <span class="score-num">{{ auditData.score }}</span>
                                <span class="score-unit">/100</span>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 4px 0; font-size: 1.1rem;">
                                    {% if auditData.status == 'healthy' %}
                                        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Données saines et intègres
                                    {% elseif auditData.status == 'warning' %}
                                        <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i> Anomalies mineures détectées
                                    {% else %}
                                        <i class="fa-solid fa-circle-xmark" style="color: #ef4444;"></i> Anomalies critiques requérant réparation
                                    {% endif %}
                                </h4>
                                <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
                                    Dernière analyse : {{ auditData.stats.scanned_at|default('À l\'instant') }}
                                </p>
                            </div>
                        </div>

                        <form method="post" action="{{ repairUrl }}" style="margin: 0;">
                            {{ SHOW.tokenField }}
                            <button type="submit" class="button {% if auditData.score < 100 %}primary{% else %}secondary{% endif %}" style="display: inline-flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-wrench"></i> Exécuter la réparation automatique
                            </button>
                        </form>
                    </div>

                    <!-- Métriques détaillées -->
                    <div class="help-grid" style="margin-bottom: 1.5rem;">
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-file-code"></i> Fichiers JSON</h4>
                            <p><strong>{{ auditData.stats.json_files_valid }} / {{ auditData.stats.json_files_checked }}</strong> fichiers valides et lisibles.</p>
                        </div>
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-fingerprint"></i> Clés & Identifiants</h4>
                            <p><strong>{{ auditData.stats.duplicate_ids_found|default(0) }}</strong> doublon(s) d'identifiant.</p>
                        </div>
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-link"></i> Navigation & Menus</h4>
                            <p><strong>{{ auditData.stats.orphan_menu_items }}</strong> lien(s) rompu(s) détecté(s).</p>
                        </div>
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-folder-tree"></i> Catégories du Blog</h4>
                            <p><strong>{{ auditData.stats.orphan_category_refs }}</strong> référence(s) orpheline(s).</p>
                        </div>
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-comments"></i> Commentaires</h4>
                            <p><strong>{{ auditData.stats.orphan_comments }}</strong> commentaire(s) orphelin(s).</p>
                        </div>
                        <div class="storage-help">
                            <h4><i class="fa-solid fa-arrows-rotate"></i> Synchro JSON ⇄ SQL</h4>
                            <p>
                                {% if auditData.stats.sql_integrity == 'connected' %}
                                    {% if auditData.stats.cross_storage_synced %}
                                        <span style="color: #10b981; font-weight: bold;"><i class="fa-solid fa-check"></i> 100% synchronisé</span>
                                    {% else %}
                                        <span style="color: #f59e0b; font-weight: bold;"><i class="fa-solid fa-triangle-exclamation"></i> Écarts détectés</span>
                                    {% endif %}
                                {% else %}
                                    <span style="color: #64748b;">Moteur SQL inactif</span>
                                {% endif %}
                            </p>
                        </div>
                    </div>

                    <!-- Comparateur Croisé JSON vs SQL -->
                    {% if auditData.stats.cross_storage_diffs|length > 0 %}
                        <div class="db-config-grid" style="margin-bottom: 1.5rem; padding: 1rem; border: 1px solid var(--cb-border, #e2e8f0); border-radius: 8px; background: var(--cb-bg-alt, #f8fafc);">
                            <h4 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-scale-balanced" style="color: #6366f1;"></i> Comparateur d'intégrité croisée (Flat-File JSON vs Base SQL)
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                {% for modelName, diff in auditData.stats.cross_storage_diffs %}
                                    <div style="background: var(--cb-card-bg, #fff); padding: 0.75rem; border-radius: 6px; border-left: 3px solid {% if diff.synced %}#10b981{% else %}#f59e0b{% endif %}; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                        <div style="font-weight: 600; font-size: 0.9rem; margin-bottom: 4px;">{{ modelName }}</div>
                                        <div style="font-size: 0.82rem; color: var(--cb-text-muted, #64748b);">
                                            JSON: <strong>{{ diff.json }}</strong> | SQL: <strong>{{ diff.sql }}</strong>
                                        </div>
                                        <div style="font-size: 0.78rem; margin-top: 4px; font-weight: bold; color: {% if diff.synced %}#10b981{% else %}#f59e0b{% endif %};">
                                            {% if diff.synced %}<i class="fa-solid fa-check"></i> Parfaitement synchronisé{% else %}<i class="fa-solid fa-triangle-exclamation"></i> Désynchronisé (écart de {{ diff.json - diff.sql }}){% endif %}
                                        </div>
                                    </div>
                                {% endfor %}
                            </div>
                        </div>
                    {% endif %}

                    <!-- Liste des anomalies -->
                    {% if auditData.issues.critical|length > 0 or auditData.issues.warning|length > 0 or auditData.issues.info|length > 0 %}
                        <div class="issues-container" style="margin-top: 1.5rem;">
                            <h4 style="margin-bottom: 0.75rem;"><i class="fa-solid fa-triangle-exclamation"></i> Détail des anomalies</h4>
                            
                            {% if auditData.issues.critical|length > 0 %}
                                <div class="msg error" style="margin-bottom: 1rem;">
                                    <strong>Anomalies critiques :</strong>
                                    <ul style="margin: 0.5rem 0 0 1.25rem;">
                                        {% for item in auditData.issues.critical %}
                                            <li>{{ item.message|default(item) }}</li>
                                        {% endfor %}
                                    </ul>
                                </div>
                            {% endif %}

                            {% if auditData.issues.warning|length > 0 %}
                                <div class="msg warning" style="margin-bottom: 1rem;">
                                    <strong>Avertissements :</strong>
                                    <ul style="margin: 0.5rem 0 0 1.25rem;">
                                        {% for item in auditData.issues.warning %}
                                            <li>{{ item.message|default(item) }}</li>
                                        {% endfor %}
                                    </ul>
                                </div>
                            {% endif %}

                            {% if auditData.issues.info|length > 0 %}
                                <div class="msg info" style="margin-bottom: 1rem;">
                                    <strong>Informations :</strong>
                                    <ul style="margin: 0.5rem 0 0 1.25rem;">
                                        {% for item in auditData.issues.info %}
                                            <li>{{ item.message|default(item) }}</li>
                                        {% endfor %}
                                    </ul>
                                </div>
                            {% endif %}
                        </div>
                    {% else %}
                        <div class="msg success">
                            <i class="fa-solid fa-shield-check"></i> <strong>Parfait !</strong> Aucun fichier corrompu ni référence orpheline. L'intégrité globale de votre base et de vos fichiers plats est optimale.
                        </div>
                    {% endif %}
                </div>
            </li>
        </ul>
    </div>
</section>

<style>
.storage-section { margin-bottom: 2.5rem; }
.storage-section h3 { margin-bottom: 0.5rem; font-size: 1.25em; display: flex; align-items: center; gap: 10px; }
.section-desc { color: #666; margin-bottom: 1.5rem; font-size: 0.95em; }
.section-divider { border: 0; border-top: 1px solid #eee; margin: 2rem 0; }

/* Connections Manager */
.connection-tabs { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 1rem; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
.tab-btn { padding: 7px 14px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; border-radius: 8px; cursor: pointer; font-size: 0.88rem; font-weight: 600; }
.tab-btn.active { background: var(--primary-color, #1f62a9); color: #ffffff; border-color: var(--primary-color, #1f62a9); }
.tab-btn:hover:not(.active) { background: #f1f5f9; color: #0f172a; }
.btn-add-conn { background: #ecfdf5; color: #15803d; border-color: #bbf7d0; }

.connection-panel { display: none; }
.connection-panel.active { display: block; animation: fadeIn 0.25s ease; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

/* DB Config Form */
.db-config-grid { background: #f8fafc; padding: 1.5rem; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 820px; }
.form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
.form-group { flex: 1; display: flex; flex-direction: column; gap: 5px; margin-bottom: 1rem; }
.form-group label { font-weight: 600; font-size: 0.85rem; color: #334155; }
.form-group input, .form-group select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.92rem; }

/* Model Table */
.badge { padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
.badge-json { background: #475569; color: white; }
.badge-sql { background: var(--primary-color, #1f62a9); color: white; }

.storage-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.storage-table th { background: #f8fafc; padding: 12px; font-size: 0.85rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
.storage-table td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }

.button.outline { background: transparent; border: 1px solid var(--primary-color, #1f62a9); color: var(--primary-color, #1f62a9); font-size: 0.82rem; }
.button.outline:hover { background: var(--primary-color, #1f62a9); color: white; }

.migration-status { font-size: 0.8em; margin-top: 5px; font-weight: 500; }
.migration-status.success { color: #16a34a; }
.migration-status.error { color: #dc2626; }

/* Audit DataInspector */
.audit-score-circle { width: 68px; height: 68px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
.score-healthy { background: #dcfce7; color: #15803d; border: 2px solid #86efac; }
.score-warning { background: #fef3c7; color: #b45309; border: 2px solid #fcd34d; }
.score-critical { background: #fee2e2; color: #b91c1c; border: 2px solid #fca5a5; }
.score-num { font-size: 1.4rem; line-height: 1; }
.score-unit { font-size: 0.65rem; opacity: 0.8; }
.badge-warning { background: #f59e0b; color: #ffffff; }
.badge-success { background: #10b981; color: #ffffff; }

/* Dark Mode */
:root[data-theme="dark"] .db-config-grid,
html[data-theme="dark"] .db-config-grid { background: #1e293b; border-color: #334155; }
:root[data-theme="dark"] .tab-btn,
html[data-theme="dark"] .tab-btn { background: #1e293b; color: #cbd5e1; border-color: #334155; }
:root[data-theme="dark"] .tab-btn:hover:not(.active),
html[data-theme="dark"] .tab-btn:hover:not(.active) { background: #334155; color: #ffffff; }
:root[data-theme="dark"] .storage-table th,
html[data-theme="dark"] .storage-table th { background: #1e293b; border-color: #334155; color: #cbd5e1; }
:root[data-theme="dark"] .storage-table td,
html[data-theme="dark"] .storage-table td { border-color: #334155; }
:root[data-theme="dark"] .score-healthy,
html[data-theme="dark"] .score-healthy { background: #064e3b; color: #6ee7b7; border-color: #059669; }
:root[data-theme="dark"] .score-warning,
html[data-theme="dark"] .score-warning { background: #78350f; color: #fde68a; border-color: #d97706; }
:root[data-theme="dark"] .score-critical,
html[data-theme="dark"] .score-critical { background: #7f1d1d; color: #fca5a5; border-color: #dc2626; }
:root[data-theme="dark"] .storage-help,
html[data-theme="dark"] .storage-help { background: #1e293b; border-color: #334155; color: #cbd5e1; }
:root[data-theme="dark"] .storage-help h4,
html[data-theme="dark"] .storage-help h4 { color: #f1f5f9; }

.storage-help { background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid var(--success-color, #16a34a); border-radius: 12px; padding: 1.5rem; }
.help-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
.help-item strong { color: #15803d; }

.status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-left: 5px; }
.status-online { background-color: #16a34a; box-shadow: 0 0 5px rgba(22, 163, 74, 0.5); }
.status-offline { background-color: #dc2626; box-shadow: 0 0 5px rgba(220, 38, 38, 0.5); }

.migration-loading::after { content: "..."; animation: dots 1.5s infinite; }
@keyframes dots { 0% { content: "."; } 33% { content: ".."; } 66% { content: "..."; } }
</style>

<script>
// Tabs Management avec mémorisation de l'onglet actif
(function () {
    const STORAGE_KEY = 'adm_tab:' + window.location.pathname + ':storage_conn';
    const tabBtns = document.querySelectorAll('.connection-tabs .tab-btn');
    const panels = document.querySelectorAll('.connection-panel');

    function activateConnTab(target, persist) {
        const panel = document.getElementById('panel-' + target);
        if (!panel) return false;
        tabBtns.forEach(b => b.classList.toggle('active', b.dataset.target === target));
        panels.forEach(p => p.classList.remove('active'));
        panel.classList.add('active');
        if (persist) {
            try { sessionStorage.setItem(STORAGE_KEY, target); } catch (e) {}
        }
        return true;
    }

    try {
        const saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) activateConnTab(saved, false);
    } catch (e) {}

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.dataset.target || (this.classList.contains('btn-add-conn') ? 'add' : '');
            if (target) activateConnTab(target, true);
        });
    });
})();

// DB Driver Fields Toggle
document.querySelectorAll('.db-driver-selector').forEach(select => {
    select.addEventListener('change', function() {
        const panel = this.closest('.connection-panel');
        panel.querySelector('.db-fields-mysql').style.display = (this.value === 'mysql') ? 'block' : 'none';
        panel.querySelector('.db-fields-sqlite').style.display = (this.value === 'sqlite') ? 'block' : 'none';
    });
});

// Migration Sync
document.querySelectorAll('.storage-selector').forEach(select => {
    select.addEventListener('change', function() {
        const row = this.closest('tr');
        const btn = row.querySelector('.btn-migrate');
        const badge = row.querySelector('.badge');
        const currentId = badge.dataset.connection; // "json", "default", or custom conn name
        const targetId = this.value; // same
        
        const currentDriver = currentId === 'json' ? 'json' : 'sql';
        const targetDriver = (targetId === 'json') ? 'json' : 'sql';

        if (targetId === currentId) {
            btn.style.display = 'none';
        } else {
            btn.style.display = 'inline-block';
            btn.dataset.targetId = targetId;
            btn.dataset.sourceId = currentId;
            
            // On simplifie pour l'UI de migration (json <-> sql)
            // Mais le code PHP saura utiliser le bon nom de connexion
            const targetLabel = targetId.toUpperCase();
            btn.innerHTML = `<i class="fa-solid fa-arrows-rotate"></i> Vers ${targetLabel}`;
        }
    });
    // Init
    select.dispatchEvent(new Event('change'));
});

// Migration Async
document.querySelectorAll('.btn-migrate').forEach(button => {
    button.addEventListener('click', async function() {
        const row = this.closest('tr');
        const model = row.dataset.model;
        const targetId = this.dataset.targetId;
        const sourceId = this.dataset.sourceId;
        const statusDiv = row.querySelector('.migration-status');
        
        if (!confirm(`Migrer "${model}" de ${sourceId.toUpperCase()} vers ${targetId.toUpperCase()} ?`)) return;

        this.disabled = true;
        this.classList.add('migration-loading');
        statusDiv.textContent = 'En cours...';
        statusDiv.className = 'migration-status';

        try {
            const formData = new FormData();
            formData.append('model', model);
            formData.append('target', targetId === 'json' ? 'json' : 'sql');
            formData.append('target_connection', targetId === 'json' ? '' : targetId);
            formData.append('source', sourceId === 'json' ? 'json' : 'sql');
            formData.append('source_connection', sourceId === 'json' ? '' : sourceId);
            formData.append('token', '{{token}}');

            const response = await fetch('{{migrateUrl}}', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                statusDiv.textContent = `Terminé (${result.count} entrées)`;
                statusDiv.classList.add('success');
                // Fake refresh
                const badge = row.querySelector('.badge');
                badge.textContent = targetId.toUpperCase();
                badge.dataset.connection = targetId;
                badge.className = `badge badge-${targetId === 'json' ? 'json' : 'sql'}`;
                row.querySelector('.storage-selector').dispatchEvent(new Event('change'));
            } else {
                statusDiv.textContent = 'Erreur migration';
                statusDiv.classList.add('error');
            }
        } catch (e) {
            statusDiv.textContent = 'Erreur critique';
            statusDiv.classList.add('error');
        } finally {
            this.disabled = false;
            this.classList.remove('migration-loading');
        }
    });
});
</script>
