<section class="module-card">
    <header style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-shield-halved" style="color:var(--ag-accent, #38bdf8);"></i>
            <span>PluginInspector : Audit Global de Sécurité</span>
        </div>
        <div>
            <a class="button small" href="{{backUrl}}"><i class="fa-solid fa-arrow-left"></i> Retour aux extensions</a>
        </div>
    </header>

    <div style="padding:16px;">
        <!-- Résumé de l'audit -->
        <div class="module-grid" style="margin-bottom:20px;">
            <div class="stat-card">
                <span class="stat-value">{{summary.total}}</span>
                <span class="stat-label">Extensions analysées</span>
            </div>
            <div class="stat-card" style="border-top:3px solid #10b981;">
                <span class="stat-value" style="color:#10b981;">{{summary.passed}}</span>
                <span class="stat-label">Conformes (100%)</span>
            </div>
            <div class="stat-card" style="border-top:3px solid #f59e0b;">
                <span class="stat-value" style="color:#f59e0b;">{{summary.warning}}</span>
                <span class="stat-label">Avertissements</span>
            </div>
            <div class="stat-card" style="border-top:3px solid #ef4444;">
                <span class="stat-value" style="color:#ef4444;">{{summary.dangerous}}</span>
                <span class="stat-label">À risque / Critiques</span>
            </div>
            <div class="stat-card">
                <span class="stat-value">{{summary.average_score}}%</span>
                <span class="stat-label">Score moyen</span>
            </div>
        </div>

        <!-- Tableau des résultats -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Extension</th>
                        <th>Type</th>
                        <th>Score & Santé</th>
                        <th>Statut</th>
                        <th>Fichiers / LoC</th>
                        <th>Anomalies</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {% for slug, rep in reports %}
                        <tr>
                            <td>
                                <strong>{{slug}}</strong>
                                {% if rep.metadata.version %}
                                    <span style="font-size:0.78rem;color:var(--ag-text-muted, #64748b);margin-left:4px;">v{{rep.metadata.version}}</span>
                                {% endif %}
                                {% if rep.metadata.description %}
                                    <div class="small" style="color:var(--ag-text-muted, #64748b);max-width:350px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        {{rep.metadata.description}}
                                    </div>
                                {% endif %}
                            </td>
                            <td><span style="font-size:0.8rem;text-transform:uppercase;">{{rep.type}}</span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="width:50px;background:var(--ag-border, #e2e8f0);height:6px;border-radius:3px;overflow:hidden;">
                                        <div style="width:{{rep.score}}%;height:100%;{% if rep.status == 'passed' %}background:#10b981;{% elseif rep.status == 'warning' %}background:#f59e0b;{% else %}background:#ef4444;{% endif %}"></div>
                                    </div>
                                    <strong style="font-size:0.88rem;{% if rep.status == 'passed' %}color:#10b981;{% elseif rep.status == 'warning' %}color:#f59e0b;{% else %}color:#ef4444;{% endif %}">
                                        {{rep.score}}%
                                    </strong>
                                </div>
                            </td>
                            <td>
                                {% if rep.status == 'passed' %}
                                    <span class="badge success" style="font-size:0.75rem;padding:3px 8px;border-radius:12px;background:#10b981;color:#fff;">Conforme</span>
                                {% elseif rep.status == 'warning' %}
                                    <span class="badge warning" style="font-size:0.75rem;padding:3px 8px;border-radius:12px;background:#f59e0b;color:#fff;">Avis</span>
                                {% else %}
                                    <span class="badge alert" style="font-size:0.75rem;padding:3px 8px;border-radius:12px;background:#ef4444;color:#fff;">À risque</span>
                                {% endif %}
                            </td>
                            <td style="font-size:0.85rem;">
                                {{rep.files_stats.total_files}} fich. / {{rep.files_stats.total_lines}} lig.
                            </td>
                            <td>
                                {% if rep.issues.critical|length > 0 %}
                                    <span style="color:#ef4444;font-weight:700;font-size:0.82rem;"><i class="fa-solid fa-triangle-exclamation"></i> {{rep.issues.critical|length}} critique(s)</span>
                                {% elseif rep.issues.warning|length > 0 %}
                                    <span style="color:#f59e0b;font-size:0.82rem;"><i class="fa-solid fa-circle-exclamation"></i> {{rep.issues.warning|length}} avis</span>
                                {% else %}
                                    <span style="color:#10b981;font-size:0.82rem;"><i class="fa-solid fa-check"></i> 0</span>
                                {% endif %}
                            </td>
                            <td style="text-align:right;">
                                <a class="button small" href="/admin/pluginsmanager/inspect/{{slug}}" style="padding:4px 8px;font-size:0.8rem;">
                                    <i class="fa-solid fa-magnifying-glass"></i> Détails
                                </a>
                            </td>
                        </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>
</section>
