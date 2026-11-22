<section class="content">
    <div id="content_title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h2><i class="fa-solid fa-network-wired"></i> Orchestrateur de Workflows (No-Code)</h2>
            <p style="margin: 4px 0 0 0; color: var(--cb-text-muted, #64748b); font-size: 0.95rem;">
                Automatisez les passerelles entre vos plugins et services tiers (Webhooks, e-mails, alertes) sans une ligne de code.
            </p>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="{{ createUrl }}" class="button primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-plus"></i> Créer un Workflow
            </a>
        </div>
    </div>

    <!-- Onglets Règles / Historique -->
    <div class="tabs-container">
        <ul class="tabs-header">
            <li class="default-tab">
                <i class="fa-solid fa-sliders"></i> Règles Actives ({{ workflows|length }})
            </li>
            <li>
                <i class="fa-solid fa-clock-rotate-left"></i> Journal d'Exécution ({{ history|length }})
            </li>
        </ul>

        <ul class="tabs">
            <!-- TAB 1 : Règles actives -->
            <li class="tab default-tab">
                <div class="workflow-section">
                    {% if workflows|length > 0 %}
                        <div class="table-responsive">
                            <table class="workflow-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px; text-align: center;">État</th>
                                        <th>Nom du Workflow</th>
                                        <th>Événement Déclencheur</th>
                                        <th>Actions</th>
                                        <th style="text-align: center;">Mode</th>
                                        <th style="text-align: center;">Exécutions</th>
                                        <th style="text-align: right;">Dernier Lancement</th>
                                        <th style="text-align: right;">Options</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {% for wf in workflows %}
                                        <tr>
                                            <td style="text-align: center;">
                                                <form method="post" action="{{ ROUTER.generate('workflow-admin-toggle', {'id': wf.id}) }}" style="margin: 0; display: inline;">
                                                    {{ SHOW.tokenField }}
                                                    <button type="submit" class="wf-status-btn {% if wf.is_enabled %}is-enabled{% else %}is-disabled{% endif %}" title="{% if wf.is_enabled %}Actif (Cliquer pour désactiver){% else %}En pause (Cliquer pour activer){% endif %}">
                                                        <i class="fa-solid {% if wf.is_enabled %}fa-toggle-on{% else %}fa-toggle-off{% endif %}"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <strong style="font-size: 0.95rem; color: var(--cb-text, #0f172a);">{{ wf.name }}</strong>
                                                {% if wf.description %}
                                                    <br><small style="color: var(--cb-text-muted, #64748b);">{{ wf.description }}</small>
                                                {% endif %}
                                            </td>
                                            <td>
                                                <span class="badge badge-trigger">
                                                    <i class="fa-solid fa-bolt"></i> {{ wf.trigger }}
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                    {% for act in wf.actions %}
                                                        <span class="badge badge-action">
                                                            {% if act.type == 'webhook' %}
                                                                <i class="fa-solid fa-paper-plane"></i> Webhook
                                                            {% elseif act.type == 'email' %}
                                                                <i class="fa-solid fa-envelope"></i> E-mail
                                                            {% elseif act.type == 'log' %}
                                                                <i class="fa-solid fa-file-lines"></i> Log
                                                            {% else %}
                                                                {{ act.type }}
                                                            {% endif %}
                                                        </span>
                                                    {% endfor %}
                                                </div>
                                            </td>
                                            <td style="text-align: center;">
                                                {% if wf.is_async %}
                                                    <span class="badge badge-async" title="Exécution asynchrone via TaskScheduler">
                                                        <i class="fa-solid fa-hourglass-start"></i> Différé (Async)
                                                    </span>
                                                {% else %}
                                                    <span class="badge badge-sync" title="Exécution immédiate bloquante">
                                                        <i class="fa-solid fa-forward"></i> Direct
                                                    </span>
                                                {% endif %}
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="font-weight: 700; color: var(--cb-text, #0f172a);">{{ wf.runs_count|default(0) }}</span>
                                                {% if wf.runs_count > 0 %}
                                                    <br><small style="color: #10b981; font-weight: 600;">{{ wf.success_count|default(0) }} ok</small>
                                                    {% if wf.failure_count > 0 %}
                                                        / <small style="color: #ef4444; font-weight: 600;">{{ wf.failure_count }} err</small>
                                                    {% endif %}
                                                {% endif %}
                                            </td>
                                            <td style="text-align: right; font-size: 0.82rem; color: var(--cb-text-muted, #64748b); white-space: nowrap;">
                                                {{ wf.last_run|default('Jamais') }}
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <a href="{{ ROUTER.generate('workflow-admin-edit', {'id': wf.id}) }}" class="button outline" style="padding: 4px 8px; font-size: 0.82rem;" title="Modifier">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <form method="post" action="{{ ROUTER.generate('workflow-admin-delete', {'id': wf.id}) }}" style="display: inline-block; margin: 0;" onsubmit="return confirm('Confirmez-vous la suppression définitive de ce workflow ?');">
                                                    {{ SHOW.tokenField }}
                                                    <button type="submit" class="button outline" style="padding: 4px 8px; font-size: 0.82rem; color: #dc2626; border-color: #dc2626;" title="Supprimer">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                        </div>
                    {% else %}
                        <div class="workflow-empty-state">
                            <i class="fa-solid fa-diagram-project" style="font-size: 3rem; color: var(--cb-border, #cbd5e1); margin-bottom: 1rem;"></i>
                            <h3>Aucun workflow configuré</h3>
                            <p style="color: var(--cb-text-muted, #64748b); max-width: 460px; margin: 0 auto 1.5rem auto;">
                                Créez votre première règle d'automatisation pour relier un événement (nouvel article, message de contact, inscription) à un webhook ou un e-mail.
                            </p>
                            <a href="{{ createUrl }}" class="button primary">
                                <i class="fa-solid fa-plus"></i> Créer mon premier Workflow
                            </a>
                        </div>
                    {% endif %}
                </div>
            </li>

            <!-- TAB 2 : Journal d'exécution -->
            <li class="tab">
                <div class="workflow-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <h3 style="margin: 0; font-size: 1.15rem;"><i class="fa-solid fa-list-check"></i> Dernières Exécutions</h3>
                        {% if history|length > 0 %}
                            <form method="post" action="{{ clearHistoryUrl }}" style="margin: 0;" onsubmit="return confirm('Vider l\'historique des exécutions ?');">
                                {{ SHOW.tokenField }}
                                <button type="submit" class="button outline" style="font-size: 0.82rem; color: #dc2626; border-color: #dc2626;">
                                    <i class="fa-solid fa-trash"></i> Vider l'historique
                                </button>
                            </form>
                        {% endif %}
                    </div>

                    {% if history|length > 0 %}
                        <div class="table-responsive">
                            <table class="workflow-table">
                                <thead>
                                    <tr>
                                        <th>Date & Heure</th>
                                        <th>Workflow</th>
                                        <th>Événement</th>
                                        <th style="text-align: center;">Statut</th>
                                        <th style="text-align: right;">Durée</th>
                                        <th>Détails des Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {% for log in history %}
                                        <tr>
                                            <td style="font-size: 0.82rem; white-space: nowrap; color: var(--cb-text-muted, #64748b);">
                                                {{ log.timestamp }}
                                            </td>
                                            <td>
                                                <strong>{{ log.workflow_name }}</strong>
                                            </td>
                                            <td>
                                                <code style="font-size: 0.82rem;">{{ log.trigger }}</code>
                                            </td>
                                            <td style="text-align: center;">
                                                {% if log.success %}
                                                    <span class="badge badge-success"><i class="fa-solid fa-check"></i> Succès</span>
                                                {% else %}
                                                    <span class="badge badge-error"><i class="fa-solid fa-triangle-exclamation"></i> Échec</span>
                                                {% endif %}
                                            </td>
                                            <td style="text-align: right; font-size: 0.86rem; font-weight: 600;">
                                                {{ log.duration_ms }} ms
                                            </td>
                                            <td style="font-size: 0.82rem;">
                                                {% for act in log.actions %}
                                                    <div style="margin-bottom: 2px;">
                                                        <strong>{{ act.type|upper }}</strong> :
                                                        {% if act.success %}
                                                            <span style="color: #10b981;">OK</span>
                                                            {% if act.response.http_code %} (HTTP {{ act.response.http_code }}){% endif %}
                                                        {% else %}
                                                            <span style="color: #ef4444;">{{ act.error }}</span>
                                                        {% endif %}
                                                    </div>
                                                {% endfor %}
                                            </td>
                                        </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                        </div>
                    {% else %}
                        <div class="msg info">
                            <i class="fa-solid fa-circle-info"></i> Aucun historique d'exécution pour le moment.
                        </div>
                    {% endif %}
                </div>
            </li>
        </ul>
    </div>
</section>

<style>
.workflow-section { margin-top: 1rem; }
.workflow-empty-state { text-align: center; padding: 3rem 1.5rem; background: var(--cb-card-bg, #fff); border: 1px dashed var(--cb-border, #cbd5e1); border-radius: 12px; }
.workflow-empty-state h3 { margin: 0 0 0.5rem 0; font-size: 1.2rem; color: var(--cb-text, #0f172a); }

/* Table */
.table-responsive { overflow-x: auto; border-radius: 8px; border: 1px solid var(--cb-border, #e2e8f0); }
.workflow-table { width: 100%; border-collapse: collapse; text-align: left; }
.workflow-table th { background: var(--cb-bg-alt, #f8fafc); padding: 10px 14px; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--cb-text-muted, #64748b); border-bottom: 1px solid var(--cb-border, #e2e8f0); }
.workflow-table td { padding: 12px 14px; border-bottom: 1px solid var(--cb-border, #e2e8f0); font-size: 0.9rem; vertical-align: middle; }
.workflow-table tr:last-child td { border-bottom: 0; }
.workflow-table tbody tr:hover { background: var(--cb-hover, rgba(0,0,0,0.015)); }

/* Status Toggle Button */
.wf-status-btn { background: none; border: none; font-size: 1.4rem; cursor: pointer; padding: 0; line-height: 1; transition: transform 0.15s ease; }
.wf-status-btn:hover { transform: scale(1.1); }
.wf-status-btn.is-enabled { color: #10b981; }
.wf-status-btn.is-disabled { color: #94a3b8; }

/* Badges */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; line-height: 1.4; }
.badge-trigger { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.badge-action { background: rgba(16, 185, 129, 0.12); color: #047857; }
.badge-async { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.badge-sync { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }
.badge-success { background: rgba(16, 185, 129, 0.15); color: #047857; }
.badge-error { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }

/* Dark Mode */
:root[data-theme="dark"] .workflow-empty-state,
html[data-theme="dark"] .workflow-empty-state { background: #1e293b; border-color: #334155; }
:root[data-theme="dark"] .workflow-table th,
html[data-theme="dark"] .workflow-table th { background: #0f172a; border-color: #334155; color: #94a3b8; }
:root[data-theme="dark"] .workflow-table td,
html[data-theme="dark"] .workflow-table td { border-color: #334155; color: #cbd5e1; }
:root[data-theme="dark"] .table-responsive,
html[data-theme="dark"] .table-responsive { border-color: #334155; }
</style>

