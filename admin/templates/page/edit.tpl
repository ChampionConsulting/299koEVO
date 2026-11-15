<form method="post" action="{ ROUTER.generate("page-admin-save") }" enctype="multipart/form-data" id="page-edit-form">
    <input type="hidden" name="_csrf" value="{{_csrfToken}}" />
    <input type="hidden" name="target" value="{{pageItem.getTarget()}}" />
    <input type="hidden" name="id" value="{{pageItem.getId()}}" />
    {{extraHiddenFields}}

    <div class="adm-dash-head page-edit-head">
        <div>
            <h2>
                <i class="fa-solid fa-file-pen"></i>
                {% if pageItem.getId() %}{{pageItem.getName()}}{% else %}{{Lang.page.add-page}}{% endif %}
            </h2>
        </div>
        <div class="admin-actions-bar page-edit-actions">
            <a class="button" href="{ ROUTER.generate("page-admin-home") }">
                <i class="fa-solid fa-arrow-left"></i> Retour aux pages
            </a>
            <button type="submit" class="button primary">
                <i class="fa-solid fa-save"></i> {{Lang.submit}}
            </button>
        </div>
    </div>

    <div class="tabs-container page-edit-tabs" data-tab-key="page-edit">
        <ul class="tabs-header">
            <li data-tab="content" class="default-tab">
                <i class="fa-solid fa-file-lines"></i> {{Lang.page.content-heading}}
            </li>
            <li data-tab="settings">
                <i class="fa-solid fa-gear"></i> {{Lang.core-settings}}
            </li>
            <li data-tab="seo">
                <i class="fa-solid fa-magnifying-glass"></i> {{Lang.page.seo-heading}}
            </li>
            <li data-tab="blocks">
                <i class="fa-solid fa-cubes"></i> Blocs &amp; Champs
            </li>
            <li data-tab="revisions">
                <i class="fa-solid fa-clock-rotate-left"></i> Révisions &amp; Langue
            </li>
            <li data-tab="access">
                <i class="fa-solid fa-flask"></i> Conditions d'accès <span class="adm-badge-exp">Expérimental</span>
            </li>
        </ul>

        <ul class="tabs">
            <!-- Tab 1: Contenu -->
            <li class="tab">
                <section id="section-content" class="content">
                    <header><h3>{{Lang.page.content-heading}}</h3></header>
                    <p>
                        <label for="name">{{Lang.page.name-label}}</label><br>
                        <input type="text" name="name" id="name" value="{{pageItem.getName()}}" required="required" />
                    </p>
                    <div id="row-page-details">
                        <p>
                            <label for="mainTitle">{{Lang.page.page-title}}</label><br>
                            <input type="text" name="mainTitle" id="mainTitle" value="{{pageItem.getMainTitle()}}" />
                        </p>
                        <p>
                            <label for="file">{{Lang.page.include-file}}</label><br>
                            <select name="file" id="file">
                                <option value="">--</option>
                                {% for file in page.listTemplates() %}
                                    <option {% if file == pageItem.getFile() %}selected{% endif %} value="{{file}}">{{file}}</option>
                                {% endfor %}
                            </select>
                        </p>
                        <p>
                            {{contentEditor}}
                        </p>
                    </div>
                </section>
            </li>

            <!-- Tab 2: Paramètres & Médias -->
            <li class="tab">
                <section id="section-settings" class="content">
                    <header><h3>{{Lang.core-settings}}</h3></header>
                    <p>
                        <label for="status">Statut éditorial (Workflow)</label><br>
                        <select name="status" id="status">
                            {% for stKey, stLabel in workflowStatuses %}
                                <option value="{{stKey}}" {% if currentStatus == stKey %}selected{% endif %}>{{stLabel}}</option>
                            {% endfor %}
                        </select>
                        <input type="hidden" name="isHidden" id="isHiddenMirror" value="{% if currentStatus == 'published' %}0{% else %}1{% endif %}" />
                    </p>
                    <p id="row-homepage">
                        <input {% if pageItem.getIsHomepage() %}checked{% endif %} type="checkbox" name="isHomepage" id="isHomepage" />
                        <label for="isHomepage">{{Lang.page.homepage}}</label>
                    </p>
                    {% if not pageItem.getId() %}
                    <p class="page-edit-auto-menu">
                        <input type="checkbox" name="addToMainMenu" id="addToMainMenu" value="1" checked />
                        <label for="addToMainMenu"><strong>Ajouter automatiquement un lien dans le menu principal</strong></label><br>
                        <small>Vous pourrez ensuite le déplacer ou le retirer depuis <a href="/admin/menus">Menus</a>.</small>
                    </p>
                    {% endif %}

                    <p>
                        <label for="legalType">Type de page légale (Préréglage)</label><br>
                        <select name="legalType" id="legalType">
                            <option value="none" {% if pageItem.getLegalType() == 'none' %}selected{% endif %}>-- Aucun --</option>
                            <option value="cgu" {% if pageItem.getLegalType() == 'cgu' %}selected{% endif %}>CGU (Conditions Générales d'Utilisation)</option>
                            <option value="cgv" {% if pageItem.getLegalType() == 'cgv' %}selected{% endif %}>CGV (Conditions Générales de Vente)</option>
                            <option value="privacy" {% if pageItem.getLegalType() == 'privacy' %}selected{% endif %}>Politique de Confidentialité</option>
                        </select>
                        <button type="button" id="applyPreset" class="button small page-edit-preset-btn">Appliquer le préréglage</button>
                    </p>

                    <p>
                        <label for="_password">{{Lang.page.restrict-access-password}}</label><br>
                        <input type="password" name="_password" id="_password" value="" autocomplete="new-password" />
                    </p>
                    {% if pageItem.getPassword() != "" %}
                        <p>
                            <input type="checkbox" name="resetPassword" id="resetPassword" />
                            <label for="resetPassword">{{Lang.page.remove-password-restriction}}</label>
                        </p>
                    {% endif %}
                </section>

                {% for section in extraSections %}
                    {{section}}
                {% endfor %}
            </li>

            <!-- Tab 3: Référencement (SEO) -->
            <li class="tab">
                <section id="section-seo" class="content">
                    <header><h3>{{Lang.page.seo-heading}}</h3></header>
                    <p>
                        <input {% if pageItem.getNoIndex() %}checked{% endif %} type="checkbox" name="noIndex" id="noIndex"/>
                        <label for="noIndex">{{Lang.page.no-index-checkbox}}</label>
                    </p>
                    <p>
                        <label for="metaTitleTag">{{Lang.page.meta-title}}</label><br>
                        <input type="text" name="metaTitleTag" id="metaTitleTag" value="{{pageItem.getMetaTitleTag()}}" />
                    </p>
                    <p>
                        <label for="metaDescriptionTag">{{Lang.page.meta-description}}</label><br>
                        <input type="text" name="metaDescriptionTag" id="metaDescriptionTag" value="{{pageItem.getMetaDescriptionTag()}}" />
                    </p>
                </section>
            </li>

            <!-- Tab 3b: Composition de blocs & Champs personnalisés -->
            <li class="tab">
                <section id="section-blocks" class="content">
                    <header><h3>Compositeur de blocs</h3></header>
                    <p><small>Les blocs structurés s'ajoutent après le contenu principal de la page sans casser l'éditeur historique.</small></p>
                    <input type="hidden" name="blocks_json" id="blocks_json" value="{{blocksJson|escape}}" />
                    <div style="display:flex;gap:.5rem;margin-bottom:.75rem;align-items:center;">
                        <select id="evo-add-block-type"></select>
                        <button type="button" class="button small primary" id="evo-add-block-btn">+ Ajouter un bloc</button>
                    </div>
                    <div id="evo-blocks-list" class="evo-blocks-editor"></div>
                </section>
                {% if hasCustomFields %}
                <section id="section-custom-fields" class="content">
                    <header><h3>Champs personnalisés de la page</h3></header>
                    {{customFieldsHtml|raw}}
                </section>
                {% endif %}
            </li>

            <!-- Tab 3c: Révisions & Multilingue -->
            <li class="tab">
                <section id="section-multilingual" class="content">
                    <header><h3>Langue &amp; Groupe de traduction</h3></header>
                    <p>
                        <label for="page_locale">Langue de la page</label><br>
                        <select name="locale" id="page_locale">
                            <option value="">-- Par défaut --</option>
                            {% for loc in enabledLocales %}
                                <option value="{{loc}}" {% if pageItem.getLocale() == loc %}selected{% endif %}>{{loc}}</option>
                            {% endfor %}
                        </select>
                    </p>
                    <p>
                        <label for="translation_group">Identifiant du groupe de traduction</label><br>
                        <input type="text" name="translation_group" id="translation_group" value="{{pageItem.getTranslationGroup()}}" placeholder="Généré automatiquement si vide" />
                    </p>
                </section>
                <section id="section-revisions" class="content">
                    <header><h3>Historique des révisions</h3></header>
                    {% if pageRevisions %}
                        <table class="table">
                            <thead>
                                <tr><th>Version</th><th>Date</th><th>Auteur</th><th>Statut</th><th>Résumé</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                {% for rev in pageRevisions %}
                                    <tr>
                                        <td>v{{rev.version}}</td>
                                        <td>{{rev.created_at}}</td>
                                        <td>{{rev.author_name}}</td>
                                        <td>{{rev.status}}</td>
                                        <td>{{rev.summary}}</td>
                                        <td>
                                            <button type="submit"
                                                formaction="{ ROUTER.generate("page-admin-restore-revision") }"
                                                name="revision_id"
                                                value="{{rev.id}}"
                                                class="button small warning"
                                                onclick="document.getElementById('evo-restore-page-id').disabled=false;return confirm('Restaurer la révision v{{rev.version}} ?');">
                                                Restaurer
                                            </button>
                                        </td>
                                    </tr>
                                {% endfor %}
                            </tbody>
                        </table>
                        <input type="hidden" name="page_id" id="evo-restore-page-id" value="{{pageItem.getId()}}" disabled />
                    {% else %}
                        <p>Aucune révision enregistrée pour cette page.</p>
                    {% endif %}
                </section>
            </li>

            <!-- Tab 4: Conditions d'accès -->
            <li class="tab">
                <section id="section-access" class="content">
                    <header>
                        <h3>
                            Conditions d'accès à la page
                            <span class="adm-badge-exp">Fonctionnalité expérimentale</span>
                        </h3>
                    </header>
                    <p class="page-edit-exp-note">
                        <i class="fa-solid fa-flask" aria-hidden="true"></i>
                        <strong>Fonctionnalité expérimentale :</strong> l'éditeur de conditions et la politique d'accès dynamique sont en cours de test. Vérifiez bien l'accès avec le bouton <em>Test Policy</em> ; en cas de doute, utilisez plutôt le statut brouillon ou le mot de passe dans l'onglet <em>Paramètres</em>.
                    </p>
                    <div id="conditions-container">
                        <input type="hidden" name="conditions" id="conditions" value="{{pageItem.getConditions()|json_encode}}" />
                        <div id="conditions-list" class="page-conditions-list"></div>
                        <div class="page-conditions-actions">
                            <button type="button" class="button small secondary" id="add-condition-btn">+ Ajouter une condition</button>
                            <button type="button" class="button small warning" id="test-conditions-btn">Test Policy (Dry-run)</button>
                        </div>
                        <div id="test-result" class="page-test-result"></div>
                    </div>
                </section>
            </li>
        </ul>
    </div>

    <div class="page-edit-footer">
        <a class="button" href="{ ROUTER.generate("page-admin-home") }">
            <i class="fa-solid fa-arrow-left"></i> Retour
        </a>
        <button type="submit" class="button primary">
            <i class="fa-solid fa-save"></i> {{Lang.submit}}
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('page-edit-form');
    const nameInput = document.getElementById('name');
    const contentTabHeader = document.querySelector('.page-edit-tabs > .tabs-header > li[data-tab="content"]');

    if (form && nameInput && contentTabHeader) {
        form.addEventListener('invalid', function(e) {
            if (e.target === nameInput) {
                contentTabHeader.click();
                setTimeout(function() { nameInput.focus(); }, 20);
            }
        }, true);
    }

    const applyPresetBtn = document.getElementById('applyPreset');
    if (applyPresetBtn) {
        applyPresetBtn.addEventListener('click', function() {
            const type = document.getElementById('legalType').value;
            if (type === 'none') {
                alert('Veuillez sélectionner un type de page légale.');
                return;
            }
            if (!confirm('Cela remplacera le titre et le contenu actuel. Continuer ?')) {
                return;
            }
            const presets = {
                'cgu': {
                    'name': 'CGU',
                    'mainTitle': 'Conditions Générales d\'Utilisation',
                    'content': '<h2>1. Objet</h2><p>Les présentes CGU ont pour objet de définir les modalités de mise à disposition du site...</p>'
                },
                'cgv': {
                    'name': 'CGV',
                    'mainTitle': 'Conditions Générales de Vente',
                    'content': '<h2>1. Objet</h2><p>Les présentes CGV définissent les conditions applicables aux ventes conclues entre...</p>'
                },
                'privacy': {
                    'name': 'Confidentialité',
                    'mainTitle': 'Politique de Confidentialité',
                    'content': '<h2>Collecte des données</h2><p>Nous collectons les données suivantes : Nom, Prénom, Email...</p>'
                }
            };
            if (presets[type]) {
                document.getElementById('name').value = presets[type].name;
                document.getElementById('mainTitle').value = presets[type].mainTitle;
                const ed = (typeof tinymce !== 'undefined') ? (tinymce.get('pageContent') || tinymce.activeEditor) : null;
                if (ed) {
                    ed.setContent(presets[type].content);
                }
                const contentArea = document.querySelector('textarea[name="content"]');
                if (contentArea) {
                    contentArea.value = presets[type].content;
                }
                if (contentTabHeader) {
                    contentTabHeader.click();
                }
            }
        });
    }
});

(function () {
    const conditionsInput = document.getElementById('conditions');
    const listContainer = document.getElementById('conditions-list');
    const addBtn = document.getElementById('add-condition-btn');
    const testBtn = document.getElementById('test-conditions-btn');
    const testResult = document.getElementById('test-result');
    if (!conditionsInput || !listContainer || !addBtn || !testBtn || !testResult) return;

    let providers = {};
    let conditions = [];
    try {
        conditions = JSON.parse(conditionsInput.value || '[]');
    } catch (e) {
        conditions = [];
    }

    async function fetchMetadata() {
        try {
            const response = await fetch('{ ROUTER.generate("page-admin-list-providers-ajax") }');
            providers = await response.json();
            render();
        } catch (e) {
            console.error('Failed to fetch providers metadata', e);
        }
    }

    function render() {
        listContainer.innerHTML = '';
        if (conditions.length === 0) {
            listContainer.innerHTML = '<div class="page-conditions-empty">Aucune condition (Page accessible publiquement)</div>';
        }

        conditions.forEach((cond, index) => {
            const provider = providers[cond.type] || { label: cond.type, fields: {} };
            const row = document.createElement('div');
            row.className = 'page-cond-row';

            const leftCol = document.createElement('div');
            leftCol.className = 'page-cond-type-col';
            const typeSelect = document.createElement('select');
            Object.keys(providers).forEach(pid => {
                const opt = document.createElement('option');
                opt.value = pid;
                opt.textContent = providers[pid].label;
                if (cond.type === pid) opt.selected = true;
                typeSelect.appendChild(opt);
            });
            typeSelect.onchange = (e) => {
                conditions[index] = { type: e.target.value };
                render();
            };
            leftCol.appendChild(typeSelect);

            const midCol = document.createElement('div');
            midCol.className = 'page-cond-fields-col';

            if (provider.fields) {
                Object.keys(provider.fields).forEach(fname => {
                    const field = provider.fields[fname];
                    const fieldWrapper = document.createElement('div');
                    fieldWrapper.className = 'page-cond-field';

                    const label = document.createElement('label');
                    label.textContent = field.label;
                    fieldWrapper.appendChild(label);

                    let input;
                    if (field.type === 'select') {
                        input = document.createElement('select');
                        const fillOptions = (opts) => {
                            const currentVal = cond[fname];
                            input.innerHTML = '<option value="">-- Sélectionner --</option>';
                            Object.keys(opts).forEach(okey => {
                                const opt = document.createElement('option');
                                opt.value = okey;
                                opt.textContent = opts[okey];
                                if (currentVal === okey) opt.selected = true;
                                input.appendChild(opt);
                            });
                        };
                        fillOptions(field.options || {});
                        if (field.dependsOn && cond[field.dependsOn]) {
                            const depVal = cond[field.dependsOn];
                            fetch('{ ROUTER.generate("page-admin-list-providers-ajax") }?table=' + encodeURIComponent(depVal))
                                .then(r => r.json())
                                .then(data => {
                                    const currentMeta = data[cond.type];
                                    if (currentMeta && currentMeta.fields[fname] && currentMeta.fields[fname].options) {
                                        fillOptions(currentMeta.fields[fname].options);
                                    }
                                })
                                .catch(() => {});
                        }
                    } else if (field.type === 'boolean') {
                        input = document.createElement('select');
                        input.innerHTML = '<option value="1">Oui</option><option value="0">Non</option>';
                        input.value = (cond[fname] === true || cond[fname] === "1") ? "1" : "0";
                    } else {
                        input = document.createElement('input');
                        input.type = 'text';
                        input.value = cond[fname] || '';
                        input.placeholder = field.placeholder || '';
                    }

                    input.onchange = (e) => {
                        let val = e.target.value;
                        if (field.type === 'boolean') val = (val === "1");
                        updateCondition(index, fname, val);
                        if (field.dynamic_reload) render();
                    };
                    fieldWrapper.appendChild(input);
                    midCol.appendChild(fieldWrapper);
                });
            }

            const rightCol = document.createElement('div');
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'page-cond-remove';
            removeBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
            removeBtn.onclick = () => removeCondition(index);
            rightCol.appendChild(removeBtn);

            row.appendChild(leftCol);
            row.appendChild(midCol);
            row.appendChild(rightCol);
            listContainer.appendChild(row);
        });

        conditionsInput.value = JSON.stringify(conditions);
    }

    function updateCondition(index, key, value) {
        conditions[index][key] = value;
        conditionsInput.value = JSON.stringify(conditions);
    }

    function removeCondition(index) {
        conditions.splice(index, 1);
        render();
    }

    addBtn.onclick = () => {
        const firstProvider = Object.keys(providers)[0] || 'auth';
        conditions.push({ type: firstProvider });
        render();
    };

    testBtn.onclick = async () => {
        testResult.className = 'page-test-result is-visible';
        testResult.textContent = 'Évaluation en cours...';
        try {
            const formData = new FormData();
            formData.append('conditions', JSON.stringify(conditions));
            formData.append('_csrf', '{{_csrfToken}}');
            const response = await fetch('{ ROUTER.generate("page-admin-explain-policy-ajax") }', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.allowed) {
                testResult.className = 'page-test-result is-visible is-allowed';
                testResult.innerHTML = `<strong>Accès Autorisé</strong> pour votre rôle actuel (<code>${data.user_role}</code>).`;
            } else {
                testResult.className = 'page-test-result is-visible is-denied';
                testResult.innerHTML = `<strong>Accès Refusé</strong> pour votre rôle actuel (<code>${data.user_role}</code>).`;
            }
        } catch (e) {
            testResult.className = 'page-test-result is-visible is-error';
            testResult.textContent = "Erreur lors du test.";
        }
    };

    fetchMetadata();
})();

(function () {
    const statusSel = document.getElementById('status');
    const hiddenMirror = document.getElementById('isHiddenMirror');
    if (statusSel && hiddenMirror) {
        statusSel.addEventListener('change', function () {
            hiddenMirror.value = statusSel.value === 'published' ? '0' : '1';
        });
    }

    const blocksInput = document.getElementById('blocks_json');
    const blocksList = document.getElementById('evo-blocks-list');
    const typeSelect = document.getElementById('evo-add-block-type');
    const addBtn = document.getElementById('evo-add-block-btn');
    if (!blocksInput || !blocksList || !typeSelect || !addBtn) return;

    let blockDefs = {};
    try {
        blockDefs = JSON.parse('{{blockDefsJson|raw}}' || '{}');
    } catch (e) {
        blockDefs = {};
    }

    let blocks = [];
    try {
        blocks = JSON.parse(blocksInput.value || '[]');
        if (!Array.isArray(blocks)) blocks = [];
    } catch (e) {
        blocks = [];
    }

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

    function renderBlocks() {
        blocksList.innerHTML = '';
        if (blocks.length === 0) {
            blocksList.innerHTML = '<p class="evo-blocks-empty">Aucun bloc ajouté pour l\'instant.</p>';
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
            title.textContent = '#' + (idx + 1) + ' — ' + def.label + ' (' + blk.type + ')';
            const btns = document.createElement('div');
            btns.style.cssText = 'display:flex;gap:.25rem;';

            const mkBtn = (txt, fn) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'button small secondary';
                b.textContent = txt;
                b.onclick = fn;
                return b;
            };
            if (idx > 0) btns.appendChild(mkBtn('↑', () => { [blocks[idx-1], blocks[idx]] = [blocks[idx], blocks[idx-1]]; sync(); renderBlocks(); }));
            if (idx < blocks.length - 1) btns.appendChild(mkBtn('↓', () => { [blocks[idx+1], blocks[idx]] = [blocks[idx], blocks[idx+1]]; sync(); renderBlocks(); }));
            btns.appendChild(mkBtn('Supprimer', () => { blocks.splice(idx, 1); sync(); renderBlocks(); }));

            head.appendChild(title);
            head.appendChild(btns);
            card.appendChild(head);

            const area = document.createElement('textarea');
            area.rows = 4;
            area.style.width = '100%';
            area.style.fontFamily = 'monospace';
            area.value = JSON.stringify(blk.data || {}, null, 2);
            area.onchange = () => {
                try {
                    blk.data = JSON.parse(area.value);
                    area.style.borderColor = '#d1d5db';
                    sync();
                } catch (e) {
                    area.style.borderColor = '#dc2626';
                }
            };
            card.appendChild(area);
            blocksList.appendChild(card);
        });
        sync();
    }

    addBtn.onclick = () => {
        const t = typeSelect.value;
        if (!t) return;
        const def = blockDefs[t] || {};
        blocks.push({
            id: 'blk_' + Math.random().toString(36).slice(2, 10),
            type: t,
            order: blocks.length,
            data: JSON.parse(JSON.stringify(def.defaultData || {})),
            settings: {}
        });
        sync();
        renderBlocks();
    };

    renderBlocks();
})();
</script>
