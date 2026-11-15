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
        <div>
            <label for="icon"><strong>Icône FontAwesome</strong></label><br>
            <div style="display:flex;align-items:center;gap:.75rem;margin-top:.25rem;flex-wrap:wrap;">
                <div id="icon-preview" style="width:42px;height:42px;border-radius:8px;background:#f8fafc;border:2px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#1f62a9;flex-shrink:0;">
                    <i class="{{typeData.icon}}"></i>
                </div>
                <input type="text" name="icon" id="icon" value="{{typeData.icon}}" style="max-width:300px;" placeholder="ex: fa-solid fa-layer-group" />
                <button type="button" class="button secondary small" id="toggle-icon-picker"><i class="fa-solid fa-icons"></i> Choisir une icône...</button>
            </div>

            <div id="icon-picker-box" style="display:none;margin-top:.75rem;padding:1rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 6px 12px -2px rgba(0,0,0,0.08);max-width:720px;">
                <div style="display:flex;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap;align-items:center;">
                    <input type="search" id="icon-search" placeholder="Rechercher une icône (ex: livre, projet, carte, tag, contact...)" style="flex:1;min-width:200px;padding:.4rem .6rem;border-radius:6px;border:1px solid #cbd5e1;" />
                    <button type="button" class="button small icon-cat-btn primary" data-cat="all">Toutes</button>
                    <button type="button" class="button small icon-cat-btn secondary" data-cat="content">Contenu</button>
                    <button type="button" class="button small icon-cat-btn secondary" data-cat="commerce">Business</button>
                    <button type="button" class="button small icon-cat-btn secondary" data-cat="org">Organisation</button>
                    <button type="button" class="button small icon-cat-btn secondary" data-cat="social">Social</button>
                    <button type="button" class="button small icon-cat-btn secondary" data-cat="tools">Outils</button>
                </div>
                <div id="icon-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(82px,1fr));gap:.5rem;max-height:260px;overflow-y:auto;padding:.25rem;border:1px solid #f1f5f9;border-radius:6px;background:#f8fafc;">
                </div>
            </div>
        </div>
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
    const fieldTypes = {{supportedFieldTypesJson|raw}};
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

    // FontAwesome Icon Picker
    const iconInput = document.getElementById('icon');
    const iconPreview = document.getElementById('icon-preview');
    const togglePickerBtn = document.getElementById('toggle-icon-picker');
    const pickerBox = document.getElementById('icon-picker-box');
    const searchInput = document.getElementById('icon-search');
    const grid = document.getElementById('icon-grid');
    const catBtns = document.querySelectorAll('.icon-cat-btn');

    const iconsList = [
        // Contenu & Médias
        { cls: 'fa-solid fa-layer-group', name: 'Calques', cat: 'content', tags: 'layer group collection pile' },
        { cls: 'fa-solid fa-cubes', name: 'Cubes', cat: 'content', tags: 'cubes modules types bloc' },
        { cls: 'fa-solid fa-cube', name: 'Cube', cat: 'content', tags: 'cube module element' },
        { cls: 'fa-solid fa-book', name: 'Livre', cat: 'content', tags: 'livre book catalogue guide' },
        { cls: 'fa-solid fa-book-open', name: 'Livre ouvert', cat: 'content', tags: 'book open manuel lecture' },
        { cls: 'fa-solid fa-bookmark', name: 'Signet', cat: 'content', tags: 'bookmark favori marque-page' },
        { cls: 'fa-solid fa-newspaper', name: 'Actualités', cat: 'content', tags: 'newspaper journal news article presse blog' },
        { cls: 'fa-solid fa-file-lines', name: 'Page/Doc', cat: 'content', tags: 'file document page texte fiche' },
        { cls: 'fa-solid fa-pen-nib', name: 'Plume', cat: 'content', tags: 'pen plume redaction ecriture post' },
        { cls: 'fa-solid fa-feather-pointed', name: 'Feather', cat: 'content', tags: 'feather plume blog ecrivain' },
        { cls: 'fa-solid fa-quote-left', name: 'Citation', cat: 'content', tags: 'quote citation temoignage avis' },
        { cls: 'fa-solid fa-image', name: 'Photo', cat: 'content', tags: 'image photo visuel image' },
        { cls: 'fa-solid fa-images', name: 'Galerie', cat: 'content', tags: 'images galerie photos album' },
        { cls: 'fa-solid fa-camera', name: 'Appareil', cat: 'content', tags: 'camera photo shooting' },
        { cls: 'fa-solid fa-video', name: 'Vidéo', cat: 'content', tags: 'video clip film media' },
        { cls: 'fa-solid fa-film', name: 'Film', cat: 'content', tags: 'film cinema cinema streaming' },
        { cls: 'fa-solid fa-music', name: 'Musique', cat: 'content', tags: 'music audio chanson album' },
        { cls: 'fa-solid fa-headphones', name: 'Audio', cat: 'content', tags: 'headphones podcast ecoute' },
        { cls: 'fa-solid fa-podcast', name: 'Podcast', cat: 'content', tags: 'podcast emission radio micro' },

        // Business & E-commerce
        { cls: 'fa-solid fa-briefcase', name: 'Projets', cat: 'commerce', tags: 'briefcase projet portfolio travail client entreprise' },
        { cls: 'fa-solid fa-building', name: 'Entreprise', cat: 'commerce', tags: 'building societe batiment agence societe' },
        { cls: 'fa-solid fa-shop', name: 'Boutique', cat: 'commerce', tags: 'shop magasin store commerce e-commerce' },
        { cls: 'fa-solid fa-cart-shopping', name: 'Panier', cat: 'commerce', tags: 'cart panier vente produit' },
        { cls: 'fa-solid fa-bag-shopping', name: 'Sac', cat: 'commerce', tags: 'bag boutique mode shopping' },
        { cls: 'fa-solid fa-basket-shopping', name: 'Pannier bio', cat: 'commerce', tags: 'basket marche epicerie cagette' },
        { cls: 'fa-solid fa-tag', name: 'Étiquette', cat: 'commerce', tags: 'tag promo tarif prix reference' },
        { cls: 'fa-solid fa-tags', name: 'Tags', cat: 'commerce', tags: 'tags categories mots cles' },
        { cls: 'fa-solid fa-credit-card', name: 'Carte', cat: 'commerce', tags: 'credit card bancaire reglement' },
        { cls: 'fa-solid fa-receipt', name: 'Facture', cat: 'commerce', tags: 'receipt note facture recu ticket' },
        { cls: 'fa-solid fa-coins', name: 'Tarif', cat: 'commerce', tags: 'coins monnaie prix finance devise' },
        { cls: 'fa-solid fa-boxes-stacked', name: 'Stock', cat: 'commerce', tags: 'boxes stock inventaire logistique' },
        { cls: 'fa-solid fa-chart-line', name: 'Statistiques', cat: 'commerce', tags: 'chart stats performance rentabilite' },
        { cls: 'fa-solid fa-chart-pie', name: 'Diagramme', cat: 'commerce', tags: 'chart pie repartition parts' },
        { cls: 'fa-solid fa-handshake', name: 'Partenaire', cat: 'commerce', tags: 'handshake contrat accord partenariat' },

        // Organisation & Dates
        { cls: 'fa-solid fa-calendar', name: 'Calendrier', cat: 'org', tags: 'calendar date agenda evenement' },
        { cls: 'fa-solid fa-calendar-days', name: 'Événements', cat: 'org', tags: 'calendar planning festif dates' },
        { cls: 'fa-solid fa-calendar-check', name: 'RDV', cat: 'org', tags: 'calendar check reservation confirmation' },
        { cls: 'fa-solid fa-clock', name: 'Horaires', cat: 'org', tags: 'clock heure temps duree planning' },
        { cls: 'fa-solid fa-list-check', name: 'Tâches', cat: 'org', tags: 'list check to-do checklist controle' },
        { cls: 'fa-solid fa-table-list', name: 'Tableau', cat: 'org', tags: 'table list grille repertoire' },
        { cls: 'fa-solid fa-folder', name: 'Dossier', cat: 'org', tags: 'folder repertoire archives' },
        { cls: 'fa-solid fa-folder-open', name: 'Dossier ouv.', cat: 'org', tags: 'folder open fichiers' },
        { cls: 'fa-solid fa-bell', name: 'Alerte', cat: 'org', tags: 'bell notification rappel alerte' },
        { cls: 'fa-solid fa-location-dot', name: 'Lieu', cat: 'org', tags: 'location pin adresse carte map ville' },
        { cls: 'fa-solid fa-map', name: 'Carte', cat: 'org', tags: 'map plan geographie itineraire' },
        { cls: 'fa-solid fa-compass', name: 'Boussole', cat: 'org', tags: 'compass orientation guide decouvrir' },

        // Social & Personnes
        { cls: 'fa-solid fa-user', name: 'Membre', cat: 'social', tags: 'user profil membre personne compte' },
        { cls: 'fa-solid fa-users', name: 'Adhérents', cat: 'social', tags: 'users equipe adhesion communaute groupe' },
        { cls: 'fa-solid fa-user-group', name: 'Équipe', cat: 'social', tags: 'team groupe collectif intervenants' },
        { cls: 'fa-solid fa-address-card', name: 'Fiche', cat: 'social', tags: 'card contact annuaire carte' },
        { cls: 'fa-solid fa-comments', name: 'Forum', cat: 'social', tags: 'comments message chat avis debats' },
        { cls: 'fa-solid fa-comment-dots', name: 'Réaction', cat: 'social', tags: 'comment dialogue reponse avis' },
        { cls: 'fa-solid fa-envelope', name: 'Contact', cat: 'social', tags: 'envelope mail message email' },
        { cls: 'fa-solid fa-heart', name: 'Coup de cœur', cat: 'social', tags: 'heart favori like amour don' },
        { cls: 'fa-solid fa-star', name: 'Étoile', cat: 'social', tags: 'star avis note vedette premium' },
        { cls: 'fa-solid fa-award', name: 'Récompense', cat: 'social', tags: 'award medaille diplome prix' },
        { cls: 'fa-solid fa-trophy', name: 'Trophée', cat: 'social', tags: 'trophy concours tournoi palmares' },

        // Outils & Tech
        { cls: 'fa-solid fa-gear', name: 'Option', cat: 'tools', tags: 'gear reglage configuration mecanique' },
        { cls: 'fa-solid fa-gears', name: 'Système', cat: 'tools', tags: 'gears engrenage process automatisation' },
        { cls: 'fa-solid fa-wrench', name: 'Outil', cat: 'tools', tags: 'wrench cle maintenance support sav' },
        { cls: 'fa-solid fa-screwdriver-wrench', name: 'Services', cat: 'tools', tags: 'tools bricolage artisan intervention' },
        { cls: 'fa-solid fa-code', name: 'Code', cat: 'tools', tags: 'code developpeur integration balise' },
        { cls: 'fa-solid fa-database', name: 'Données', cat: 'tools', tags: 'database base sql stockage export' },
        { cls: 'fa-solid fa-server', name: 'Serveur', cat: 'tools', tags: 'server hosting machine infra' },
        { cls: 'fa-solid fa-globe', name: 'Internet', cat: 'tools', tags: 'globe web site monde reseau' },
        { cls: 'fa-solid fa-link', name: 'Lien', cat: 'tools', tags: 'link url lien hypertexte externe' },
        { cls: 'fa-solid fa-paperclip', name: 'Pièce jointe', cat: 'tools', tags: 'paperclip trombone attache fichier' },
        { cls: 'fa-solid fa-lock', name: 'Sécurisé', cat: 'tools', tags: 'lock prive cadenas restreint' },
        { cls: 'fa-solid fa-shield-halved', name: 'Sécurité', cat: 'tools', tags: 'shield protection bouclier confiance' },
        { cls: 'fa-solid fa-cloud', name: 'Nuage', cat: 'tools', tags: 'cloud drive sync hebergement' },
        { cls: 'fa-solid fa-lightbulb', name: 'Idée', cat: 'tools', tags: 'lightbulb ampoule astuce conseil innovation' }
    ];

    let currentCat = 'all';
    let currentQuery = '';

    function updatePreview() {
        const val = (iconInput.value || '').trim();
        iconPreview.innerHTML = val ? `<i class="${val}"></i>` : '<i class="fa-solid fa-layer-group"></i>';
    }

    function renderIcons() {
        grid.innerHTML = '';
        const q = currentQuery.toLowerCase();
        const filtered = iconsList.filter(item => {
            if (currentCat !== 'all' && item.cat !== currentCat) return false;
            if (q) {
                const haystack = (item.cls + ' ' + item.name + ' ' + item.tags).toLowerCase();
                return haystack.includes(q);
            }
            return true;
        });

        if (filtered.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:1rem;color:#64748b;font-size:.85rem;">Aucune icône trouvée. Essayez un autre mot-clé.</div>';
            return;
        }

        const currentVal = (iconInput.value || '').trim();
        filtered.forEach(item => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const isSelected = currentVal === item.cls;
            btn.style.cssText = `display:flex;flex-direction:column;align-items:center;justify-content:center;padding:.45rem .25rem;border-radius:6px;border:1px solid ${isSelected ? '#1f62a9' : '#e2e8f0'};background:${isSelected ? '#eff6ff' : '#ffffff'};cursor:pointer;transition:all .15s ease;`;
            btn.title = item.name + ' (' + item.cls + ')';
            btn.innerHTML = `<i class="${item.cls}" style="font-size:1.25rem;margin-bottom:.25rem;color:${isSelected ? '#1f62a9' : '#334155'};"></i><span style="font-size:10px;line-height:1.1;color:#64748b;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70px;">${item.name}</span>`;
            btn.onmouseenter = () => { if (!isSelected) btn.style.borderColor = '#94a3b8'; };
            btn.onmouseleave = () => { if (!isSelected) btn.style.borderColor = '#e2e8f0'; };
            btn.onclick = () => {
                iconInput.value = item.cls;
                updatePreview();
                renderIcons();
            };
            grid.appendChild(btn);
        });
    }

    if (togglePickerBtn && pickerBox) {
        togglePickerBtn.onclick = () => {
            const isHidden = pickerBox.style.display === 'none';
            pickerBox.style.display = isHidden ? 'block' : 'none';
            if (isHidden) {
                renderIcons();
                if (searchInput) searchInput.focus();
            }
        };
    }

    if (searchInput) {
        searchInput.oninput = () => {
            currentQuery = searchInput.value.trim();
            renderIcons();
        };
    }

    catBtns.forEach(b => {
        b.onclick = () => {
            catBtns.forEach(other => {
                other.classList.remove('primary');
                other.classList.add('secondary');
            });
            b.classList.remove('secondary');
            b.classList.add('primary');
            currentCat = b.getAttribute('data-cat') || 'all';
            renderIcons();
        };
    });

    if (iconInput) {
        iconInput.addEventListener('input', () => {
            updatePreview();
            renderIcons();
        });
    }
})();
</script>
