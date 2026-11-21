<section class="module-card">
    <header style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-shield-halved" style="color:var(--ag-accent, #38bdf8);"></i>
            <span>PluginInspector : {{report.slug}}</span>
        </div>
        <div style="display:flex;gap:8px;">
            <a class="button small" href="{{backUrl}}"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a>
            <a class="button small" href="{{inspectAllUrl}}"><i class="fa-solid fa-list-check"></i> Audit global</a>
        </div>
    </header>

    <div style="padding:16px;">
        <!-- Score & Statut Header -->
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;background:var(--ag-surface-card, #f8fafc);border:1px solid var(--ag-border, #e2e8f0);padding:16px 20px;border-radius:12px;margin-bottom:20px;">
            <div style="text-align:center;min-width:110px;">
                <div style="font-size:2.2rem;font-weight:800;line-height:1;{% if report.status == 'passed' %}color:#10b981;{% elseif report.status == 'warning' %}color:#f59e0b;{% else %}color:#ef4444;{% endif %}">
                    {{report.score}}%
                </div>
                <div style="font-size:0.75rem;text-transform:uppercase;font-weight:700;letter-spacing:0.05em;margin-top:4px;color:var(--ag-text-muted, #64748b);">
                    Score de santé
                </div>
            </div>

            <div style="flex:1;min-width:220px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span style="font-weight:700;font-size:1.1rem;">Statut global :</span>
                    {% if report.status == 'passed' %}
                        <span class="badge success" style="font-size:0.85rem;padding:4px 10px;border-radius:20px;background:#10b981;color:#fff;"><i class="fa-solid fa-check"></i> Conforme</span>
                    {% elseif report.status == 'warning' %}
                        <span class="badge warning" style="font-size:0.85rem;padding:4px 10px;border-radius:20px;background:#f59e0b;color:#fff;"><i class="fa-solid fa-triangle-exclamation"></i> Avertissement</span>
                    {% else %}
                        <span class="badge alert" style="font-size:0.85rem;padding:4px 10px;border-radius:20px;background:#ef4444;color:#fff;"><i class="fa-solid fa-skull-crossbones"></i> Non Conforme / À risque</span>
                    {% endif %}
                </div>
                <div style="font-size:0.88rem;color:var(--ag-text-muted, #64748b);">
                    {% if report.status == 'passed' %}
                        Aucune vulnérabilité ni anomalie critique détectée. L'extension respecte les standards du Core 299ko.
                    {% elseif report.status == 'warning' %}
                        Quelques anomalies ou bonnes pratiques non respectées. L'extension peut fonctionner mais mérite révision.
                    {% else %}
                        Fonctions d'exécution de code ou appels système proscrits détectés. Vérification manuelle requise avant usage en production.
                    {% endif %}
                </div>
            </div>

            <div style="display:flex;gap:12px;text-align:center;font-size:0.82rem;">
                <div style="padding:6px 12px;border-radius:8px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);">
                    <div style="font-weight:700;font-size:1.1rem;color:#ef4444;">{{report.issues.critical|length}}</div>
                    <div style="color:#ef4444;">Critiques</div>
                </div>
                <div style="padding:6px 12px;border-radius:8px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);">
                    <div style="font-weight:700;font-size:1.1rem;color:#f59e0b;">{{report.issues.warning|length}}</div>
                    <div style="color:#f59e0b;">Avis</div>
                </div>
                <div style="padding:6px 12px;border-radius:8px;background:rgba(56,189,248,0.1);border:1px solid rgba(56,189,248,0.2);">
                    <div style="font-weight:700;font-size:1.1rem;color:#0284c7;">{{report.files_stats.total_files}}</div>
                    <div style="color:#0284c7;">Fichiers</div>
                </div>
            </div>
        </div>

        <!-- Grille Statistiques & Métadonnées -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-bottom:20px;">
            <div style="background:var(--ag-surface-card, #f8fafc);border:1px solid var(--ag-border, #e2e8f0);padding:14px;border-radius:10px;">
                <h4 style="margin:0 0 10px 0;font-size:0.95rem;display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-code"></i> Volumétrie du Code</h4>
                <ul style="list-style:none;margin:0;padding:0;font-size:0.88rem;line-height:1.7;">
                    <li><strong>Total fichiers :</strong> {{report.files_stats.total_files}} ({{report.files_stats.size_formatted}})</li>
                    <li><strong>Lignes de code :</strong> {{report.files_stats.total_lines}}</li>
                    <li><strong>Fichiers PHP :</strong> {{report.files_stats.php_files}}</li>
                    <li><strong>Templates (.tpl) :</strong> {{report.files_stats.tpl_files}}</li>
                    <li><strong>Styles & Scripts :</strong> {{report.files_stats.css_files}} CSS / {{report.files_stats.js_files}} JS</li>
                </ul>
            </div>

            <div style="background:var(--ag-surface-card, #f8fafc);border:1px solid var(--ag-border, #e2e8f0);padding:14px;border-radius:10px;">
                <h4 style="margin:0 0 10px 0;font-size:0.95rem;display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-circle-info"></i> Métadonnées (infos.json)</h4>
                <ul style="list-style:none;margin:0;padding:0;font-size:0.88rem;line-height:1.7;">
                    <li><strong>Nom :</strong> {{report.metadata.name}} (v{{report.metadata.version}})</li>
                    <li><strong>Auteur :</strong> {{report.metadata.author}}</li>
                    <li><strong>Description :</strong> {{report.metadata.description}}</li>
                    {% if report.metadata.minCoreVersion %}
                        <li><strong>Core Requis :</strong> v{{report.metadata.minCoreVersion}}</li>
                    {% endif %}
                </ul>
            </div>

            <div style="background:var(--ag-surface-card, #f8fafc);border:1px solid var(--ag-border, #e2e8f0);padding:14px;border-radius:10px;">
                <h4 style="margin:0 0 10px 0;font-size:0.95rem;display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plug"></i> Événements & Hooks</h4>
                <div style="font-size:0.88rem;line-height:1.7;">
                    <div><strong>Hooks enregistrés ({{report.hooks.registered|length}}) :</strong></div>
                    <div style="font-size:0.82rem;color:var(--ag-text-muted, #64748b);margin-bottom:6px;">
                        {% if report.hooks.registered|length > 0 %}
                            {% for h in report.hooks.registered %}
                                <code style="margin-right:4px;">{{h}}</code>
                            {% endfor %}
                        {% else %}
                            <em>Aucun hook addHook() détecté</em>
                        {% endif %}
                    </div>
                    <div><strong>Hooks appelés ({{report.hooks.called|length}}) :</strong></div>
                    <div style="font-size:0.82rem;color:var(--ag-text-muted, #64748b);">
                        {% if report.hooks.called|length > 0 %}
                            {% for h in report.hooks.called %}
                                <code style="margin-right:4px;">{{h}}</code>
                            {% endfor %}
                        {% else %}
                            <em>Aucun hook callHook() détecté</em>
                        {% endif %}
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des Problèmes Détectés -->
        {% if report.issues.critical|length > 0 %}
            <div style="background:rgba(239,68,68,0.08);border:1px solid #ef4444;border-radius:10px;padding:14px;margin-bottom:16px;">
                <h4 style="color:#ef4444;margin:0 0 10px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Anomalies Critiques / Risques Sécurité ({{report.issues.critical|length}})
                </h4>
                <ul style="margin:0;padding-left:20px;font-size:0.9rem;color:#b91c1c;line-height:1.7;">
                    {% for issue in report.issues.critical %}
                        <li>{{issue}}</li>
                    {% endfor %}
                </ul>
            </div>
        {% endif %}

        {% if report.issues.warning|length > 0 %}
            <div style="background:rgba(245,158,11,0.08);border:1px solid #f59e0b;border-radius:10px;padding:14px;margin-bottom:16px;">
                <h4 style="color:#d97706;margin:0 0 10px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-circle-exclamation"></i> Avertissements & Recommandations ({{report.issues.warning|length}})
                </h4>
                <ul style="margin:0;padding-left:20px;font-size:0.9rem;color:#b45309;line-height:1.7;">
                    {% for issue in report.issues.warning %}
                        <li>{{issue}}</li>
                    {% endfor %}
                </ul>
            </div>
        {% endif %}

        {% if report.issues.critical|length == 0 and report.issues.warning|length == 0 %}
            <div style="background:rgba(16,185,129,0.08);border:1px solid #10b981;border-radius:10px;padding:14px;color:#047857;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-circle-check" style="font-size:1.3rem;"></i>
                <div><strong>Code 100% conforme :</strong> Aucun problème de sécurité, aucune fonction proscrite, et structure parfaitement conforme aux standards du CMS.</div>
            </div>
        {% endif %}
    </div>
</section>
