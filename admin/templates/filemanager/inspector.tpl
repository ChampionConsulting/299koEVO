<section class="module-card">
    <header class="fm-header-bar" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--evo-border, #e2e8f0);">
        <div>
            <h2 style="margin: 0; font-size: 1.4rem; color: var(--evo-text, #1e293b); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-broom-ball" style="color: var(--evo-accent, #3b82f6);"></i>
                Audit &amp; Nettoyage de la Médiathèque
            </h2>
            <p style="margin: 0.25rem 0 0; font-size: 0.9rem; color: var(--evo-text-muted, #64748b);">
                Détection automatique des fichiers orphelins, conversion WebP par lot et libération d'espace disque.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{backUrl}}" class="button secondary small">
                <i class="fa-solid fa-arrow-left"></i> Retour aux dossiers
            </a>
            <a href="?refresh=1" class="button primary small">
                <i class="fa-solid fa-rotate"></i> Relancer l'audit
            </a>
        </div>
    </header>

    <!-- KPI STATS CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="stat-card" style="background: var(--evo-surface-alt, #f8fafc); border: 1px solid var(--evo-border, #e2e8f0); border-radius: var(--evo-radius, 8px); padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--evo-text-muted, #64748b); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-hard-drive"></i> Stockage Global
            </div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--evo-text, #1e293b);">
                {{stats.total_size_formatted}}
            </div>
            <div style="font-size: 0.85rem; color: var(--evo-text-muted, #64748b); margin-top: 0.25rem;">
                {{stats.total_files}} fichier(s) scanné(s) dans <code>/data/upload/</code>
            </div>
        </div>

        <div class="stat-card" style="background: var(--evo-surface-alt, #f8fafc); border: 1px solid {% if stats.orphan_files_count > 0 %}#f87171{% else %}var(--evo-border, #e2e8f0){% endif %}; border-radius: var(--evo-radius, 8px); padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: {% if stats.orphan_files_count > 0 %}#ef4444{% else %}var(--evo-text-muted, #64748b){% endif %}; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> Fichiers Orphelins
            </div>
            <div style="font-size: 1.6rem; font-weight: 700; color: {% if stats.orphan_files_count > 0 %}#ef4444{% else %}#10b981{% endif %};">
                {{stats.orphan_files_count}} <span style="font-size: 1rem; font-weight: 500;">({{stats.orphan_size_formatted}})</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--evo-text-muted, #64748b); margin-top: 0.25rem;">
                Non référencés dans les articles, pages ou plugins
            </div>
        </div>

        <div class="stat-card" style="background: var(--evo-surface-alt, #f8fafc); border: 1px solid var(--evo-border, #e2e8f0); border-radius: var(--evo-radius, 8px); padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--evo-accent, #3b82f6); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-bolt"></i> Éligibles WebP
            </div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--evo-text, #1e293b);">
                {{stats.non_webp_count}} <span style="font-size: 1rem; font-weight: 500; color: #10b981;">(-{{stats.estimated_webp_savings_formatted}})</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--evo-text-muted, #64748b); margin-top: 0.25rem;">
                Gain moyen estimé après compression sans perte
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 2px solid var(--evo-border, #e2e8f0);">
        <button type="button" class="tab-btn active" onclick="switchMediaTab('tab-orphans', this)" style="padding: 0.6rem 1.2rem; font-weight: 600; border: none; background: none; border-bottom: 2px solid var(--evo-accent, #3b82f6); cursor: pointer; color: var(--evo-text, #1e293b);">
            <i class="fa-solid fa-trash-can"></i> Fichiers orphelins ({{stats.orphan_files_count}})
        </button>
        <button type="button" class="tab-btn" onclick="switchMediaTab('tab-webp', this)" style="padding: 0.6rem 1.2rem; font-weight: 600; border: none; background: none; cursor: pointer; color: var(--evo-text-muted, #64748b);">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Optimisation WebP ({{stats.non_webp_count}})
        </button>
        <button type="button" class="tab-btn" onclick="switchMediaTab('tab-used', this)" style="padding: 0.6rem 1.2rem; font-weight: 600; border: none; background: none; cursor: pointer; color: var(--evo-text-muted, #64748b);">
            <i class="fa-solid fa-circle-check"></i> Médias utilisés ({{stats.used_files_count}})
        </button>
    </div>

    <!-- TAB 1: ORPHANS -->
    <div id="tab-orphans" class="media-tab-content">
        {% if stats.orphan_files_count == 0 %}
            <div style="text-align: center; padding: 3rem 1rem; background: var(--evo-surface-alt, #f8fafc); border-radius: var(--evo-radius, 8px); border: 1px dashed #10b981;">
                <i class="fa-solid fa-circle-check" style="font-size: 2.5rem; color: #10b981; margin-bottom: 0.75rem;"></i>
                <h3 style="margin: 0; color: var(--evo-text, #1e293b);">Aucun fichier orphelin détecté</h3>
                <p style="margin: 0.5rem 0 0; color: var(--evo-text-muted, #64748b); font-size: 0.95rem;">
                    Tous les fichiers présents dans votre dossier <code>/data/upload/</code> sont référencés et utilisés.
                </p>
            </div>
        {% else %}
            <form method="post" action="{{cleanUrl}}" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement les fichiers orphelins sélectionnés ?');">
                <input type="hidden" name="_csrf" value="{{_csrfToken}}">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <label style="cursor: pointer; font-size: 0.9rem; font-weight: 600; color: var(--evo-text, #1e293b);">
                            <input type="checkbox" onchange="toggleAllCheckboxes(this, 'orphan-item-cb')"> Tout sélectionner
                        </label>
                    </div>
                    <div>
                        <button type="submit" class="button alert small">
                            <i class="fa-solid fa-trash-can"></i> Supprimer les orphelins sélectionnés
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 480px; overflow-y: auto; border: 1px solid var(--evo-border, #e2e8f0); border-radius: var(--evo-radius, 8px);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead style="background: var(--evo-surface-alt, #f8fafc); position: sticky; top: 0; z-index: 2;">
                            <tr style="border-bottom: 1px solid var(--evo-border, #e2e8f0);">
                                <th style="padding: 0.75rem 1rem; width: 40px;"></th>
                                <th style="padding: 0.75rem 1rem;">Fichier</th>
                                <th style="padding: 0.75rem 1rem;">Emplacement relatif</th>
                                <th style="padding: 0.75rem 1rem; width: 100px;">Taille</th>
                                <th style="padding: 0.75rem 1rem; width: 140px;">Dernière modif.</th>
                            </tr>
                        </thead>
                        <tbody>
                            {% for file in orphans %}
                            <tr style="border-bottom: 1px solid var(--evo-border, #e2e8f0);">
                                <td style="padding: 0.6rem 1rem; text-align: center;">
                                    <input type="checkbox" name="orphans[]" value="{{file.rel_path}}" class="orphan-item-cb" checked>
                                </td>
                                <td style="padding: 0.6rem 1rem;">
                                    <strong>{{file.filename}}</strong>
                                    {% if file.is_image %}
                                        <a href="/{{file.web_path}}" target="_blank" style="margin-left: 0.5rem; color: var(--evo-accent, #3b82f6);" title="Prévisualiser">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    {% endif %}
                                </td>
                                <td style="padding: 0.6rem 1rem; color: var(--evo-text-muted, #64748b); font-family: monospace; font-size: 0.85rem;">
                                    {{file.rel_path}}
                                </td>
                                <td style="padding: 0.6rem 1rem; font-weight: 600;">
                                    {{file.size_formatted}}
                                </td>
                                <td style="padding: 0.6rem 1rem; color: var(--evo-text-muted, #64748b);">
                                    {{file.date_formatted}}
                                </td>
                            </tr>
                            {% endfor %}
                        </tbody>
                    </table>
                </div>
            </form>
        {% endif %}
    </div>

    <!-- TAB 2: WEBP OPTIMIZATION -->
    <div id="tab-webp" class="media-tab-content" style="display: none;">
        {% if stats.non_webp_count == 0 %}
            <div style="text-align: center; padding: 3rem 1rem; background: var(--evo-surface-alt, #f8fafc); border-radius: var(--evo-radius, 8px); border: 1px dashed #10b981;">
                <i class="fa-solid fa-bolt" style="font-size: 2.5rem; color: #10b981; margin-bottom: 0.75rem;"></i>
                <h3 style="margin: 0; color: var(--evo-text, #1e293b);">Toutes vos images sont déjà au format WebP !</h3>
                <p style="margin: 0.5rem 0 0; color: var(--evo-text-muted, #64748b); font-size: 0.95rem;">
                    Votre médiathèque bénéficie de performances optimales et d'un poids de chargement minimal.
                </p>
            </div>
        {% else %}
            <form method="post" action="{{convertUrl}}" onsubmit="return confirm('Lancer la conversion WebP pour les images sélectionnées ?');">
                <input type="hidden" name="_csrf" value="{{_csrfToken}}">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                        <label style="cursor: pointer; font-size: 0.9rem; font-weight: 600; color: var(--evo-text, #1e293b);">
                            <input type="checkbox" onchange="toggleAllCheckboxes(this, 'webp-item-cb')"> Tout sélectionner
                        </label>
                        <label style="cursor: pointer; font-size: 0.9rem; color: var(--evo-text-muted, #64748b);">
                            <input type="checkbox" name="delete_original" value="1"> Supprimer l'original après conversion
                        </label>
                    </div>
                    <div>
                        <button type="submit" class="button primary small">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Convertir en WebP (Gain estimé : {{stats.estimated_webp_savings_formatted}})
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 480px; overflow-y: auto; border: 1px solid var(--evo-border, #e2e8f0); border-radius: var(--evo-radius, 8px);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead style="background: var(--evo-surface-alt, #f8fafc); position: sticky; top: 0; z-index: 2;">
                            <tr style="border-bottom: 1px solid var(--evo-border, #e2e8f0);">
                                <th style="padding: 0.75rem 1rem; width: 40px;"></th>
                                <th style="padding: 0.75rem 1rem;">Fichier</th>
                                <th style="padding: 0.75rem 1rem;">Format actuel</th>
                                <th style="padding: 0.75rem 1rem; width: 110px;">Poids actuel</th>
                                <th style="padding: 0.75rem 1rem; width: 130px;">Gain estimé</th>
                            </tr>
                        </thead>
                        <tbody>
                            {% for img in nonWebp %}
                            <tr style="border-bottom: 1px solid var(--evo-border, #e2e8f0);">
                                <td style="padding: 0.6rem 1rem; text-align: center;">
                                    <input type="checkbox" name="images[]" value="{{img.rel_path}}" class="webp-item-cb" checked>
                                </td>
                                <td style="padding: 0.6rem 1rem;">
                                    <strong>{{img.filename}}</strong>
                                    <div style="font-size: 0.8rem; color: var(--evo-text-muted, #64748b); font-family: monospace;">{{img.rel_path}}</div>
                                </td>
                                <td style="padding: 0.6rem 1rem;">
                                    <span style="text-transform: uppercase; font-weight: 700; font-size: 0.8rem; padding: 2px 6px; border-radius: 4px; background: var(--evo-border, #e2e8f0);">{{img.ext}}</span>
                                </td>
                                <td style="padding: 0.6rem 1rem; font-weight: 600;">
                                    {{img.size_formatted}}
                                </td>
                                <td style="padding: 0.6rem 1rem; color: #10b981; font-weight: 700;">
                                    -{{img.estimated_savings_formatted}}
                                </td>
                            </tr>
                            {% endfor %}
                        </tbody>
                    </table>
                </div>
            </form>
        {% endif %}
    </div>

    <!-- TAB 3: USED MEDIA -->
    <div id="tab-used" class="media-tab-content" style="display: none;">
        <div class="table-responsive" style="max-height: 480px; overflow-y: auto; border: 1px solid var(--evo-border, #e2e8f0); border-radius: var(--evo-radius, 8px);">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead style="background: var(--evo-surface-alt, #f8fafc); position: sticky; top: 0; z-index: 2;">
                    <tr style="border-bottom: 1px solid var(--evo-border, #e2e8f0);">
                        <th style="padding: 0.75rem 1rem;">Média</th>
                        <th style="padding: 0.75rem 1rem;">Taille</th>
                        <th style="padding: 0.75rem 1rem;">Utilisé par (Entités &amp; Contenus)</th>
                    </tr>
                </thead>
                <tbody>
                    {% for item in used %}
                    <tr style="border-bottom: 1px solid var(--evo-border, #e2e8f0);">
                        <td style="padding: 0.6rem 1rem;">
                            <strong>{{item.filename}}</strong>
                            <div style="font-size: 0.8rem; color: var(--evo-text-muted, #64748b); font-family: monospace;">{{item.rel_path}}</div>
                        </td>
                        <td style="padding: 0.6rem 1rem; font-weight: 600;">
                            {{item.size_formatted}}
                        </td>
                        <td style="padding: 0.6rem 1rem;">
                            {% for usage in item.usages %}
                                <span style="display: inline-block; background: var(--evo-surface, #e2e8f0); border: 1px solid var(--evo-border, #cbd5e1); border-radius: 4px; padding: 2px 8px; font-size: 0.82rem; margin: 2px 4px 2px 0;">
                                    <i class="fa-solid fa-link" style="color: var(--evo-accent, #3b82f6); margin-right: 4px;"></i> {{usage}}
                                </span>
                            {% endfor %}
                        </td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function switchMediaTab(tabId, btn) {
    document.querySelectorAll('.media-tab-content').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(function(el) {
        el.style.borderBottom = 'none';
        el.style.color = 'var(--evo-text-muted, #64748b)';
    });
    var target = document.getElementById(tabId);
    if (target) {
        target.style.display = 'block';
    }
    btn.style.borderBottom = '2px solid var(--evo-accent, #3b82f6)';
    btn.style.color = 'var(--evo-text, #1e293b)';
}

function toggleAllCheckboxes(master, className) {
    document.querySelectorAll('.' + className).forEach(function(cb) {
        cb.checked = master.checked;
    });
}
</script>
