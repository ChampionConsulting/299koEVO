<div style="margin-bottom:1rem;">
    <a href="{{ROUTER.generate('content-admin-entries', ['type' => typeDef.id])}}" class="button small secondary">← Retour à {{typeDef.label}}</a>
</div>

<h2>{% if entry %}Modifier #{{entry.getId()}} : {{entry.getTitle()}}{% else %}Nouvelle entrée : {{typeDef.label}}{% endif %}</h2>

<form method="post" action="{{ROUTER.generate('content-admin-entry-save', ['type' => typeDef.id])}}" id="evo-entry-form">
    <input type="hidden" name="token" value="{{token}}" />
    <input type="hidden" name="_csrf" value="{{csrfToken}}" />
    <input type="hidden" name="id" value="{% if entry %}{{entry.getId()}}{% endif %}" />
    <input type="hidden" name="blocks_json" id="blocks_json" value="{{blocksJson|escape}}" />

    <section class="content" style="margin-bottom:1rem;">
        <header><h3>Informations principales</h3></header>
        <p><label>Titre</label><br><input type="text" name="title" value="{% if entry %}{{entry.getTitle()}}{% endif %}" /></p>
        <p><label>Slug URL</label><br><input type="text" name="slug" value="{% if entry %}{{entry.getSlug()}}{% endif %}" placeholder="Généré automatiquement si vide" /></p>
        <p>
            <label>Statut éditorial</label><br>
            <select name="status" id="status">
                {% for stKey, stLabel in availableStatuses %}
                    <option value="{{stKey}}" {% if stKey == currentStatus %}selected{% endif %}>{{stLabel}}</option>
                {% endfor %}
            </select>
        </p>
        <p>
            <label>Langue</label><br>
            <select name="locale">
                {% for loc in enabledLocales %}
                    <option value="{{loc}}" {% if loc == currentLocale %}selected{% endif %}>{{loc|upper}}</option>
                {% endfor %}
            </select>
        </p>
        <p><label>Groupe de traduction</label><br><input type="text" name="translation_group" value="{{translationGroup}}" placeholder="Identifiant de groupe optionnel" /></p>
    </section>

    <section class="content" style="margin-bottom:1rem;">
        <header><h3>Champs du type « {{typeDef.label}} »</h3></header>
        {{fieldsHtml|raw}}
    </section>

    <section class="content" style="margin-bottom:1rem;">
        <header style="display:flex;justify-content:space-between;align-items:center;">
            <h3>Constructeur visuel de blocs</h3>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <select id="evo-add-block-type"></select>
                <button type="button" class="button small primary" id="evo-add-block-btn">+ Ajouter un bloc</button>
            </div>
        </header>
        <div id="evo-blocks-list" class="evo-blocks-editor"></div>
    </section>

    <p><label>Note de révision</label><br><input type="text" name="revision_summary" placeholder="Résumé de la modification" /></p>
    <p><button type="submit" class="button success">Enregistrer</button></p>
</form>

{% if entry && revisions %}
<section class="content" style="margin-top:1.5rem;">
    <header><h3>Historique des révisions ({{revisions|length}})</h3></header>
    <table>
        <thead><tr><th>Version</th><th>Statut</th><th>Résumé</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
            {% for rev in revisions %}
            <tr>
                <td>v{{rev.version}}</td>
                <td>{{rev.status}}</td>
                <td>{{rev.summary}}</td>
                <td>{{rev.created_at}}</td>
                <td>
                    <form method="post" action="{{ROUTER.generate('content-admin-entry-restore-revision', ['type' => typeDef.id])}}" style="display:inline;">
                        <input type="hidden" name="token" value="{{token}}" />
                        <input type="hidden" name="_csrf" value="{{csrfToken}}" />
                        <input type="hidden" name="entry_id" value="{{entry.getId()}}" />
                        <input type="hidden" name="revision_id" value="{{rev.id}}" />
                        <button type="submit" class="button small secondary">Restaurer</button>
                    </form>
                </td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</section>
{% endif %}

<script>
(function () {
    const blocksInput = document.getElementById('blocks_json');
    const blocksList = document.getElementById('evo-blocks-list');
    const typeSelect = document.getElementById('evo-add-block-type');
    const addBtn = document.getElementById('evo-add-block-btn');
    if (!blocksInput || !blocksList || !typeSelect || !addBtn) return;

    let blockDefs = {};
    try { blockDefs = JSON.parse('{{blockDefsJson|raw}}' || '{}'); } catch (e) {}
    let blocks = [];
    try { blocks = JSON.parse(blocksInput.value || '[]'); if (!Array.isArray(blocks)) blocks = []; } catch (e) { blocks = []; }

    Object.keys(blockDefs).forEach(t => {
        const opt = document.createElement('option');
        opt.value = t;
        opt.textContent = blockDefs[t].label || t;
        typeSelect.appendChild(opt);
    });

    function sync() {
        blocks = blocks.map((b, i) => Object.assign({}, b, { order: i }));
        blocksInput.value = JSON.stringify(blocks);
    }

    function mkInput(labelTxt, val, onChg, isArea) {
        const wrap = document.createElement('p');
        wrap.style.margin = '.35rem 0';
        const lbl = document.createElement('label');
        lbl.textContent = labelTxt;
        lbl.style.fontWeight = '600';
        const el = document.createElement(isArea ? 'textarea' : 'input');
        if (!isArea) el.type = 'text';
        else el.rows = 3;
        el.style.width = '100%';
        el.value = val ?? '';
        el.oninput = () => onChg(el.value);
        wrap.appendChild(lbl);
        wrap.appendChild(document.createElement('br'));
        wrap.appendChild(el);
        return wrap;
    }

    function renderTypedFields(blk, body) {
        const d = blk.data = blk.data || {};
        if (blk.type === 'text') {
            body.appendChild(mkInput('Titre / Sous-titre', d.heading || '', v => { d.heading = v; sync(); }));
            body.appendChild(mkInput('Contenu HTML / Texte', d.content || '', v => { d.content = v; sync(); }, true));
        } else if (blk.type === 'image') {
            body.appendChild(mkInput('URL ou chemin image (src)', d.src || '', v => { d.src = v; sync(); }));
            body.appendChild(mkInput('Texte alternatif (alt)', d.alt || '', v => { d.alt = v; sync(); }));
            body.appendChild(mkInput('Légende', d.caption || '', v => { d.caption = v; sync(); }));
            body.appendChild(mkInput('Lien cible (optionnel)', d.link || '', v => { d.link = v; sync(); }));
        } else if (blk.type === 'video') {
            body.appendChild(mkInput('URL Vidéo (YouTube / Vimeo / MP4)', d.url || '', v => { d.url = v; sync(); }));
            body.appendChild(mkInput('Titre', d.title || '', v => { d.title = v; sync(); }));
        } else if (blk.type === 'button') {
            body.appendChild(mkInput('Libellé du bouton', d.label || '', v => { d.label = v; sync(); }));
            body.appendChild(mkInput('URL de destination', d.url || '', v => { d.url = v; sync(); }));
            body.appendChild(mkInput('Variante CSS (primary, secondary...)', d.variant || 'primary', v => { d.variant = v; sync(); }));
        } else if (blk.type === 'quote') {
            body.appendChild(mkInput('Citation', d.text || '', v => { d.text = v; sync(); }, true));
            body.appendChild(mkInput('Auteur', d.author || '', v => { d.author = v; sync(); }));
            body.appendChild(mkInput('Source', d.source || '', v => { d.source = v; sync(); }));
        } else if (blk.type === 'list') {
            body.appendChild(mkInput('Style (unordered ou ordered)', d.style || 'unordered', v => { d.style = v; sync(); }));
            body.appendChild(mkInput('Éléments (un par ligne)', Array.isArray(d.items) ? d.items.join('\n') : '', v => { d.items = v.split('\n').map(s => s.trim()).filter(Boolean); sync(); }, true));
        } else if (blk.type === 'plugin_content') {
            body.appendChild(mkInput('Slug du plugin cible (blog, agenda, docs...)', d.plugin || '', v => { d.plugin = v; sync(); }));
            body.appendChild(mkInput('Mode (latest, list, single)', d.mode || 'latest', v => { d.mode = v; sync(); }));
            body.appendChild(mkInput('Limite d’éléments', String(d.limit ?? 5), v => { d.limit = parseInt(v, 10) || 5; sync(); }));
        } else {
            const area = document.createElement('textarea');
            area.rows = 4;
            area.style.width = '100%';
            area.style.fontFamily = 'monospace';
            area.value = JSON.stringify(d, null, 2);
            area.onchange = () => {
                try { blk.data = JSON.parse(area.value); area.style.borderColor = '#d1d5db'; sync(); }
                catch (e) { area.style.borderColor = '#dc2626'; }
            };
            body.appendChild(area);
        }
    }

    function renderBlocks() {
        blocksList.innerHTML = '';
        if (blocks.length === 0) {
            blocksList.innerHTML = '<p><small>Aucun bloc ajouté.</small></p>';
            sync();
            return;
        }
        blocks.forEach((blk, idx) => {
            const card = document.createElement('div');
            card.style.cssText = 'border:1px solid #d1d5db;border-radius:6px;padding:.75rem;margin-bottom:.75rem;background:#fff;';
            const head = document.createElement('div');
            head.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;';
            const def = blockDefs[blk.type] || { label: blk.type };
            const title = document.createElement('strong');
            title.textContent = '#' + (idx + 1) + ' — ' + def.label;
            const btns = document.createElement('div');
            btns.style.cssText = 'display:flex;gap:.25rem;';
            const mkBtn = (txt, fn) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'button small secondary'; b.textContent = txt; b.onclick = fn; return b; };
            if (idx > 0) btns.appendChild(mkBtn('↑', () => { [blocks[idx-1], blocks[idx]] = [blocks[idx], blocks[idx-1]]; sync(); renderBlocks(); }));
            if (idx < blocks.length - 1) btns.appendChild(mkBtn('↓', () => { [blocks[idx+1], blocks[idx]] = [blocks[idx], blocks[idx+1]]; sync(); renderBlocks(); }));
            btns.appendChild(mkBtn('Supprimer', () => { blocks.splice(idx, 1); sync(); renderBlocks(); }));
            head.appendChild(title);
            head.appendChild(btns);
            card.appendChild(head);
            const body = document.createElement('div');
            renderTypedFields(blk, body);
            card.appendChild(body);
            blocksList.appendChild(card);
        });
        sync();
    }

    addBtn.onclick = () => {
        const t = typeSelect.value;
        if (!t) return;
        const def = blockDefs[t] || {};
        blocks.push({ id: 'blk_' + Math.random().toString(36).slice(2, 10), type: t, order: blocks.length, data: JSON.parse(JSON.stringify(def.defaultData || {})), settings: {} });
        sync();
        renderBlocks();
    };
    renderBlocks();
})();
</script>
