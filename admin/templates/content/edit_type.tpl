<div style="margin-bottom:1rem;">
    <a href="{{ROUTER.generate('content-admin-index')}}" class="button small secondary">← Retour aux types de contenus</a>
</div>

<h2>{% if isNew %}Nouveau type de contenu{% else %}Configurer : {{typeData.label}}{% endif %}</h2>

<form method="post" action="{{ROUTER.generate('content-admin-type-save')}}" id="evo-type-form">
    <input type="hidden" name="token" value="{{token}}" />
    <input type="hidden" name="_csrf" value="{{csrfToken}}" />
    <input type="hidden" name="fields_json" id="fields_json" value="{{fieldsJson|escape}}" />

    <section class="content" style="margin-bottom:1rem;">
        <header><h3>Paramètres généraux</h3></header>
        <p>
            <label for="type_id">Identifiant technique (slug unique)</label><br>
            <input type="text" name="type_id" id="type_id" value="{{typeData.id}}" {% if !isNew %}readonly{% endif %} required pattern="[a-z0-9_\-]+" placeholder="ex: association" />
        </p>
        <p>
            <label for="label">Libellé affiché</label><br>
            <input type="text" name="label" id="label" value="{{typeData.label}}" required placeholder="ex: Association" />
        </p>
        <p>
            <label for="description">Description</label><br>
            <textarea name="description" id="description" rows="2">{{typeData.description}}</textarea>
        </p>
        <p>
            <label for="icon">Icône FontAwesome</label><br>
            <input type="text" name="icon" id="icon" value="{{typeData.icon}}" />
        </p>
        <p>
            <input type="checkbox" name="cfg_rest_enabled" id="cfg_rest_enabled" value="1" {% if typeData.config.rest_enabled %}checked{% endif %} />
            <label for="cfg_rest_enabled">Exposer ce type dans l'API REST (<code>/api/v1/content/{{typeData.id}}</code>)</label><br>
            <input type="checkbox" name="cfg_translatable" id="cfg_translatable" value="1" {% if typeData.config.translatable %}checked{% endif %} />
            <label for="cfg_translatable">Activer la gestion multilingue</label><br>
            <input type="checkbox" name="cfg_workflow" id="cfg_workflow" value="1" {% if typeData.config.workflow %}checked{% endif %} />
            <label for="cfg_workflow">Activer le workflow éditorial</label><br>
            <input type="checkbox" name="cfg_revisions" id="cfg_revisions" value="1" {% if typeData.config.revisions %}checked{% endif %} />
            <label for="cfg_revisions">Activer l'historique des révisions</label>
        </p>
        <p>
            <label for="api_exposed_fields">Liste blanche API (champs racine autorisés séparés par virgule, vide = schéma complet autorisé)</label><br>
            <input type="text" name="api_exposed_fields" id="api_exposed_fields" value="{{apiExposedFieldsCsv}}" placeholder="ex: id, slug, title, status, fields, locale" />
        </p>
    </section>

    <section class="content" style="margin-bottom:1rem;">
        <header><h3>Permissions RBAC/ABAC</h3></header>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem;">
            <p><label>Lecture (view)</label><input type="text" name="perm_view" value="{{typeData.permissions.view}}" /></p>
            <p><label>Création (create)</label><input type="text" name="perm_create" value="{{typeData.permissions.create}}" /></p>
            <p><label>Édition (edit)</label><input type="text" name="perm_edit" value="{{typeData.permissions.edit}}" /></p>
            <p><label>Suppression (delete)</label><input type="text" name="perm_delete" value="{{typeData.permissions.delete}}" /></p>
            <p><label>Publication (publish)</label><input type="text" name="perm_publish" value="{{typeData.permissions.publish}}" /></p>
        </div>
    </section>

    <section class="content" style="margin-bottom:1rem;">
        <header style="display:flex;justify-content:space-between;align-items:center;">
            <h3>Champs personnalisés</h3>
            <button type="button" class="button small primary" id="evo-add-field-btn">+ Ajouter un champ</button>
        </header>
        <div id="evo-fields-builder"></div>
    </section>

    <p><button type="submit" class="button success">Enregistrer le type de contenu</button></p>
</form>

<script>
(function () {
    const input = document.getElementById('fields_json');
    const container = document.getElementById('evo-fields-builder');
    const addBtn = document.getElementById('evo-add-field-btn');
    const fieldTypes = {{supportedFieldTypes|json_encode|raw}};
    let fields = [];
    try {
        fields = JSON.parse(input.value || '[]');
        if (!Array.isArray(fields)) fields = [];
    } catch (e) { fields = []; }

    function sync() {
        fields = fields.map((f, idx) => Object.assign({}, f, { order: idx }));
        input.value = JSON.stringify(fields);
    }

    function render() {
        container.innerHTML = '';
        if (fields.length === 0) {
            container.innerHTML = '<p><small>Aucun champ défini.</small></p>';
            sync();
            return;
        }
        fields.forEach((f, idx) => {
            const box = document.createElement('div');
            box.style.cssText = 'border:1px solid #d1d5db;border-radius:6px;padding:.75rem;margin-bottom:.75rem;background:#fff;';
            box.innerHTML = `
                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;">
                    <strong>Champ #${idx + 1}</strong>
                    <div class="evo-f-actions"></div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.5rem;">
                    <label>Nom clé <input type="text" data-k="name" value="${(f.name||'').replace(/"/g,'&quot;')}" required /></label>
                    <label>Libellé <input type="text" data-k="label" value="${(f.label||'').replace(/"/g,'&quot;')}" /></label>
                    <label>Type <select data-k="type">${fieldTypes.map(t => `<option value="${t}" ${f.type===t?'selected':''}>${t}</option>`).join('')}</select></label>
                    <label>Options (select, virgules) <input type="text" data-k="options" value="${Array.isArray(f.options)?f.options.join(', '):''}" /></label>
                    <label>Perm. lecture <input type="text" data-k="readPermission" value="${f.readPermission||''}" placeholder="ex: public" /></label>
                    <label>Perm. écriture <input type="text" data-k="writePermission" value="${f.writePermission||''}" placeholder="ex: content.edit" /></label>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:.5rem;">
                    <label><input type="checkbox" data-k="required" ${f.required?'checked':''}/> Obligatoire</label>
                    <label><input type="checkbox" data-k="translatable" ${f.translatable?'checked':''}/> Traduisible</label>
                    <label><input type="checkbox" data-k="api_readable" ${f.api_readable!==false?'checked':''}/> API Lecture</label>
                    <label><input type="checkbox" data-k="api_writable" ${f.api_writable!==false?'checked':''}/> API Écriture</label>
                </div>`;
            const act = box.querySelector('.evo-f-actions');
            const mk = (txt, fn) => { const b = document.createElement('button'); b.type='button'; b.className='button small secondary'; b.textContent=txt; b.onclick=fn; return b; };
            if (idx > 0) act.appendChild(mk('↑', () => { [fields[idx-1], fields[idx]] = [fields[idx], fields[idx-1]]; render(); }));
            if (idx < fields.length - 1) act.appendChild(mk('↓', () => { [fields[idx+1], fields[idx]] = [fields[idx], fields[idx+1]]; render(); }));
            act.appendChild(mk('Supprimer', () => { fields.splice(idx, 1); render(); }));

            box.querySelectorAll('[data-k]').forEach(el => {
                el.addEventListener('change', () => {
                    const k = el.getAttribute('data-k');
                    if (el.type === 'checkbox') {
                        f[k] = el.checked;
                    } else if (k === 'options') {
                        f[k] = el.value.split(',').map(s => s.trim()).filter(Boolean);
                    } else {
                        f[k] = el.value.trim();
                    }
                    sync();
                });
            });
            container.appendChild(box);
        });
        sync();
    }

    addBtn.onclick = () => {
        fields.push({ name: 'champ_' + (fields.length + 1), label: 'Nouveau champ', type: 'text', required: false, translatable: false, api_readable: true, api_writable: true, options: [] });
        render();
    };
    render();
})();
</script>
