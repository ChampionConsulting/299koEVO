<section class="overflow-auto adm-backup-section">
    <header class="adm-section-header">
        <div class="adm-header-title">
            <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
            <h2>{{Lang.configmanager-backup-nav-title}}</h2>
        </div>
        <p class="adm-header-desc">
            Créez des snapshots de sécurité de votre site ou restaurez en un clic une version précédente en cas d'anomalie ou après une mise à jour.
        </p>
    </header>

    <div class="adm-backup-actions-bar">
        <div class="adm-backup-create-group">
            <span class="adm-action-label"><i class="fa-solid fa-plus"></i> Nouvelle sauvegarde :</span>
            <a class="button is-primary" href="{ ROUTER.generate('configmanager-create-backup', ['token' => token]) }?scope=all" title="Sauvegarde complète (fichiers + données)">
                <i class="fa-solid fa-cubes"></i> Sauvegarde Complète
            </a>
            <a class="button" href="{ ROUTER.generate('configmanager-create-backup', ['token' => token]) }?scope=data" title="Sauvegarde uniquement le dossier data/">
                <i class="fa-solid fa-database"></i> Données (data/)
            </a>
            <a class="button" href="{ ROUTER.generate('configmanager-create-backup', ['token' => token]) }?scope=code" title="Sauvegarde uniquement le code CMS et extensions">
                <i class="fa-solid fa-code"></i> Code CMS
            </a>
        </div>
    </div>

    <table class="adm-table adm-backup-table">
        <thead>
            <tr>
                <th>{{Lang.configmanager-backup-date}}</th>
                <th>Type / Portée</th>
                <th>{{Lang.configmanager-backup-size}}</th>
                <th>Fichier</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            {% FOR backup IN backups %}
            <tr id="post{{backup.timestamp}}">
                <td>
                    <strong>{{Util.getDateHour(backup.date)}}</strong>
                </td>
                <td>
                    {% IF backup.type == 'data' %}
                        <span class="adm-badge adm-badge-data"><i class="fa-solid fa-database"></i> Données</span>
                    {% ELSEIF backup.type == 'code' %}
                        <span class="adm-badge adm-badge-code"><i class="fa-solid fa-code"></i> Code</span>
                    {% ELSEIF backup.type == 'pre-update' %}
                        <span class="adm-badge adm-badge-safety" title="Instantané généré automatiquement avant mise à jour"><i class="fa-solid fa-shield-halved"></i> Safety Rollback</span>
                    {% ELSE %}
                        <span class="adm-badge adm-badge-full"><i class="fa-solid fa-cubes"></i> Complète</span>
                    {% ENDIF %}
                </td>
                <td>{{backup.filesize}}</td>
                <td><code class="adm-backup-filename">{{backup.basename}}</code></td>
                <td style="text-align: right;" class="adm-backup-action-btns">
                    <button type="button" class="button is-restore" onclick="ConfigManagerRestoreBackup('{{backup.basename}}', '{{backup.date}}')" title="Restaurer cette version en 1 clic">
                        <i class="fa-solid fa-rotate-left"></i> Restaurer
                    </button>
                    <a title="{{Lang.configmanager-backup-download}}" class="button" href="{{backup.url}}">
                        <i class="fa-solid fa-download"></i>
                    </a>
                    <button type="button" title="{{Lang.configmanager-backup-delete}}" onclick="ConfigManagerDeleteBackup('{{backup.basename}}')" class="button alert">
                        <i class="fa-regular fa-trash-can"></i>
                    </button>
                </td>
            </tr>
            {% ENDFOR %}
            {% IF emptyBackups %}
            <tr>
                <td colspan="5" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted, #64748b);">
                    <i class="fa-solid fa-box-open" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.5;"></i>
                    {{Lang.configmanager-backup-no-backup}}
                </td>
            </tr>
            {% ENDIF %}
        </tbody>
    </table>
</section>

<!-- Modal / Formulaire caché pour restauration sécurisée -->
<form id="restoreForm" method="POST" action="{ ROUTER.generate('configmanager-restore-backup', ['token' => token]) }" style="display:none;">
    <input type="hidden" name="token" value="{{token}}" />
    <input type="hidden" name="file" id="restoreFile" value="" />
</form>

<style>
.adm-backup-section {
    padding: 1.25rem 0;
}
.adm-header-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.25rem;
    color: var(--text, #1e293b);
}
.adm-header-title i {
    color: var(--primary, #2563eb);
}
.adm-header-desc {
    color: var(--text-muted, #64748b);
    margin: 0.35rem 0 1.25rem 0;
    font-size: 0.92rem;
}
.adm-backup-actions-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding: 1rem;
    background: var(--surface-alt, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
}
.adm-backup-create-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}
.adm-action-label {
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--text, #334155);
    margin-right: 4px;
}
.adm-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 4px;
}
.adm-badge-full {
    background: rgba(37, 99, 235, 0.12);
    color: #2563eb;
    border: 1px solid rgba(37, 99, 235, 0.25);
}
.adm-badge-data {
    background: rgba(124, 58, 237, 0.12);
    color: #7c3aed;
    border: 1px solid rgba(124, 58, 237, 0.25);
}
.adm-badge-code {
    background: rgba(14, 165, 233, 0.12);
    color: #0284c7;
    border: 1px solid rgba(14, 165, 233, 0.25);
}
.adm-badge-safety {
    background: rgba(234, 88, 12, 0.12);
    color: #c2410c;
    border: 1px solid rgba(234, 88, 12, 0.25);
}
.adm-backup-filename {
    font-size: 0.82rem;
    background: var(--surface-alt, #f1f5f9);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--text-muted, #475569);
}
.adm-backup-action-btns {
    white-space: nowrap;
}
.adm-backup-action-btns .button {
    margin-left: 4px;
}
.button.is-restore {
    background: #059669;
    color: #ffffff;
    border: 1px solid #047857;
}
.button.is-restore:hover {
    background: #047857;
}
:root[data-theme="dark"] .adm-backup-actions-bar,
html[data-theme="dark"] .adm-backup-actions-bar {
    background: #1e293b;
    border-color: #334155;
}
:root[data-theme="dark"] .adm-backup-filename,
html[data-theme="dark"] .adm-backup-filename {
    background: #0f172a;
    color: #cbd5e1;
}
:root[data-theme="dark"] .adm-badge-full,
html[data-theme="dark"] .adm-badge-full {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
    border-color: rgba(59, 130, 246, 0.4);
}
:root[data-theme="dark"] .adm-badge-data,
html[data-theme="dark"] .adm-badge-data {
    background: rgba(168, 85, 247, 0.2);
    color: #d8b4fe;
    border-color: rgba(168, 85, 247, 0.4);
}
:root[data-theme="dark"] .adm-badge-safety,
html[data-theme="dark"] .adm-badge-safety {
    background: rgba(249, 115, 22, 0.2);
    color: #fdba74;
    border-color: rgba(249, 115, 22, 0.4);
}
</style>

<script>
function ConfigManagerRestoreBackup(file, date) {
    let msg = "⚠️ RESTAURATION DU SITE EN 1 CLIC ⚠️\n\n" +
              "Vous êtes sur le point de restaurer la sauvegarde du " + date + " (" + file + ").\n\n" +
              "Cette opération va écraser les fichiers actuels par ceux de la sauvegarde.\n\n" +
              "Souhaitez-vous continuer ?";
    if (confirm(msg)) {
        document.getElementById('restoreFile').value = file;
        document.getElementById('restoreForm').submit();
    }
}

async function ConfigManagerDeleteBackup(fileOrTimestamp) {
    if (confirm('{{Lang.confirm.deleteItem}}') === true) {
        let url = '{ ROUTER.generate("configmanager-delete-backup") }';
        let data = {
            timestamp: fileOrTimestamp,
            file: fileOrTimestamp,
            token: '{{token}}'
        };
        let response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        if (response.status === 204) {
            let row = document.querySelector('tr[id*="' + fileOrTimestamp + '"]') ||
                      document.getElementById('post' + fileOrTimestamp);
            if (row) {
                row.remove();
            }
            if (typeof Toastify === 'function') {
                Toastify({
                    text: "{{Lang.core-item-deleted}}",
                    className: "success"        
                }).showToast();
            } else {
                alert("{{Lang.core-item-deleted}}");
            }
        } else {
            if (typeof Toastify === 'function') {
                Toastify({
                    text: "{{Lang.core-item-not-deleted}}",
                    className: "error"        
                }).showToast();
            } else {
                alert("{{Lang.core-item-not-deleted}}");
            }
        }                
    }
}
</script>