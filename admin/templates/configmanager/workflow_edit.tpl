<section class="content">
    <div id="content_title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h2>
                <i class="fa-solid fa-code-branch"></i> 
                {% if workflow.id %}Modifier le Workflow : {{ workflow.name }}{% else %}Nouveau Workflow No-Code{% endif %}
            </h2>
            <p style="margin: 4px 0 0 0; color: var(--cb-text-muted, #64748b); font-size: 0.95rem;">
                Définissez la règle : Déclencheur &rarr; Conditions &rarr; Actions automatisées.
            </p>
        </div>
        <div>
            <a href="{{ backUrl }}" class="button secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-arrow-left"></i> Retour aux Workflows
            </a>
        </div>
    </div>

    <form method="post" action="{{ saveUrl }}" id="workflowForm">
        {{ SHOW.tokenField }}
        <input type="hidden" name="id" value="{{ workflow.id }}" />
        <input type="hidden" name="conditions_json" id="conditionsJsonInput" value="" />
        <input type="hidden" name="actions_json" id="actionsJsonInput" value="" />

        <!-- BLOC 1 : Informations Générales & Déclencheur -->
        <div class="wf-card" style="margin-bottom: 1.5rem;">
            <h3 class="wf-card-title"><i class="fa-solid fa-bolt" style="color: #6366f1;"></i> 1. Déclencheur (Trigger)</h3>
            <p class="wf-card-desc">L'événement du CMS qui active cette règle.</p>

            <div class="form-row" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;">
                <div class="form-group" style="flex: 2; min-width: 260px;">
                    <label for="wf_name"><strong>Nom du Workflow</strong> <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="wf_name" name="name" required value="{{ workflow.name }}" placeholder="Ex: Notification Discord nouvel article" style="width: 100%; padding: 8px 12px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 8px;" />
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="wf_trigger"><strong>Événement Déclencheur</strong> <span style="color: #ef4444;">*</span></label>
                    <select id="wf_trigger" name="trigger" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 8px;">
                        {% for tKey, tInfo in triggers %}
                            <option value="{{ tKey }}" {% if workflow.trigger == tKey %}selected{% endif %}>
                                {{ tInfo.label }}
                            </option>
                        {% endfor %}
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="wf_description"><strong>Description / Note</strong> (optionnel)</label>
                <input type="text" id="wf_description" name="description" value="{{ workflow.description }}" placeholder="Ex: Envoie un webhook sur le canal #actualités lors de chaque parution." style="width: 100%; padding: 8px 12px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 8px;" />
            </div>

            <div style="background: var(--cb-bg-alt, #f8fafc); border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.85rem; border: 1px solid var(--cb-border, #e2e8f0);">
                <strong>Variables disponibles pour ce déclencheur :</strong>
                <div id="triggerSampleVariables" style="margin-top: 4px; font-family: monospace; color: #4f46e5;"></div>
            </div>
        </div>

        <!-- BLOC 2 : Conditions (Filtres) -->
        <div class="wf-card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <h3 class="wf-card-title" style="margin: 0;"><i class="fa-solid fa-filter" style="color: #f59e0b;"></i> 2. Conditions (Filtres Optionnels)</h3>
                <button type="button" id="btnAddCondition" class="button outline" style="font-size: 0.82rem; padding: 4px 10px;">
                    <i class="fa-solid fa-plus"></i> Ajouter une condition
                </button>
            </div>
            <p class="wf-card-desc">L'action ne sera exécutée que si toutes les conditions ci-dessous sont satisfaites (Règle ET).</p>

            <div id="conditionsList" style="display: flex; flex-direction: column; gap: 8px;">
                <!-- Rendu dynamique JS -->
            </div>
            <div id="noConditionsMsg" style="color: var(--cb-text-muted, #64748b); font-size: 0.88rem; font-style: italic; padding: 6px 0;">
                Aucune condition : le workflow s'exécutera systématiquement dès que l'événement survient.
            </div>
        </div>

        <!-- BLOC 3 : Actions Automatisées -->
        <div class="wf-card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <h3 class="wf-card-title" style="margin: 0;"><i class="fa-solid fa-paper-plane" style="color: #10b981;"></i> 3. Actions Automatisées</h3>
                <div style="display: flex; gap: 6px;">
                    <button type="button" id="btnAddWebhook" class="button outline" style="font-size: 0.82rem; padding: 4px 10px;">
                        <i class="fa-solid fa-plus"></i> Webhook HTTP
                    </button>
                    <button type="button" id="btnAddEmail" class="button outline" style="font-size: 0.82rem; padding: 4px 10px;">
                        <i class="fa-solid fa-plus"></i> E-mail
                    </button>
                </div>
            </div>
            <p class="wf-card-desc">Ce que le système doit déclencher automatiquement.</p>

            <div id="actionsList" style="display: flex; flex-direction: column; gap: 12px;">
                <!-- Rendu dynamique JS -->
            </div>
        </div>

        <!-- BLOC 4 : Options & Exécution -->
        <div class="wf-card" style="margin-bottom: 1.5rem;">
            <h3 class="wf-card-title"><i class="fa-solid fa-gear" style="color: #64748b;"></i> 4. Paramètres d'Exécution</h3>

            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 1rem;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_async" value="1" {% if workflow.is_async %}checked{% endif %} style="cursor: pointer;" />
                    <span><strong>Exécution en arrière-plan (Asynchrone via TaskScheduler)</strong> <small style="color: var(--cb-text-muted, #64748b);">(Recommandé pour ne jamais ralentir la navigation des visiteurs lors d'appels réseau externes)</small></span>
                </label>

                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_enabled" value="1" {% if workflow.is_enabled %}checked{% endif %} style="cursor: pointer;" />
                    <span><strong>Activer ce workflow immédiatement</strong></span>
                </label>
            </div>
        </div>

        <!-- Boutons d'Action & Test -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 2rem;">
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="button primary" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer le Workflow
                </button>
                <a href="{{ backUrl }}" class="button secondary">Annuler</a>
            </div>

            <div>
                <button type="button" id="btnTestWorkflow" class="button outline" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-vial-circle-check"></i> Tester ce Workflow en direct
                </button>
            </div>
        </div>
    </form>

    <!-- Modal / Zone de résultat de test -->
    <div id="testResultBox" style="display: none; margin-top: 1.5rem; padding: 1.25rem; border-radius: 8px; border: 1px solid var(--cb-border, #e2e8f0); background: var(--cb-card-bg, #ffffff);">
        <h4 style="margin: 0 0 0.5rem 0;"><i class="fa-solid fa-flask"></i> Résultat du Test de Simulation</h4>
        <div id="testResultContent" style="font-family: monospace; font-size: 0.88rem;"></div>
    </div>
</section>

<style>
.wf-card {
    background: var(--cb-card-bg, #ffffff);
    border: 1px solid var(--cb-border, #e2e8f0);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.wf-card-title { margin: 0 0 4px 0; font-size: 1.15rem; display: flex; align-items: center; gap: 8px; }
.wf-card-desc { margin: 0 0 1rem 0; color: var(--cb-text-muted, #64748b); font-size: 0.88rem; }

.wf-item-row {
    background: var(--cb-bg-alt, #f8fafc);
    border: 1px solid var(--cb-border, #e2e8f0);
    border-radius: 8px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.wf-action-card {
    background: var(--cb-bg-alt, #f8fafc);
    border: 1px solid var(--cb-border, #e2e8f0);
    border-radius: 8px;
    padding: 1rem;
}

/* Dark Mode */
:root[data-theme="dark"] .wf-card,
html[data-theme="dark"] .wf-card { background: #1e293b; border-color: #334155; }
:root[data-theme="dark"] .wf-item-row,
html[data-theme="dark"] .wf-item-row,
:root[data-theme="dark"] .wf-action-card,
html[data-theme="dark"] .wf-action-card { background: #0f172a; border-color: #334155; }
:root[data-theme="dark"] #testResultBox,
html[data-theme="dark"] #testResultBox { background: #0f172a; border-color: #334155; }
</style>

<script>
(function() {
    const rawConditions = {{ workflow.conditions|json_encode|raw }};
    const rawActions = {{ workflow.actions|json_encode|raw }};
    const triggersCatalog = {{ triggers|json_encode|raw }};
    const testUrl = '{{ testUrl }}';
    const csrfToken = '{{ token }}';

    let conditions = Array.isArray(rawConditions) ? rawConditions : [];
    let actions = Array.isArray(rawActions) && rawActions.length > 0 ? rawActions : [
        { type: 'webhook', url: '', method: 'POST', body: '', headers: '' }
    ];

    const triggerSelect = document.getElementById('wf_trigger');
    const sampleVarEl = document.getElementById('triggerSampleVariables');

    function updateTriggerSamples() {
        const selected = triggerSelect.value;
        const triggerInfo = triggersCatalog[selected];
        if (triggerInfo && triggerInfo.sample) {
            const keys = Object.keys(triggerInfo.sample).map(k => `{{` + k + `}}`);
            keys.push('{{site.name}}', '{{site.url}}', '{{date}}');
            sampleVarEl.textContent = keys.join('   ');
        } else {
            sampleVarEl.textContent = '{{site.name}}   {{site.url}}   {{date}}';
        }
    }

    if (triggerSelect) {
        triggerSelect.addEventListener('change', updateTriggerSamples);
        updateTriggerSamples();
    }

    // --- Gestion des Conditions ---
    const conditionsContainer = document.getElementById('conditionsList');
    const noCondMsg = document.getElementById('noConditionsMsg');
    const btnAddCond = document.getElementById('btnAddCondition');

    function renderConditions() {
        conditionsContainer.innerHTML = '';
        if (conditions.length === 0) {
            noCondMsg.style.display = 'block';
            return;
        }
        noCondMsg.style.display = 'none';

        conditions.forEach((c, idx) => {
            const row = document.createElement('div');
            row.className = 'wf-item-row';
            row.innerHTML = `
                <div style="flex: 2; min-width: 140px;">
                    <input type="text" placeholder="Champ (ex: category, price)" value="${escapeHtml(c.field || '')}" data-idx="${idx}" data-key="field" style="width: 100%; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;" />
                </div>
                <div style="flex: 2; min-width: 140px;">
                    <select data-idx="${idx}" data-key="operator" style="width: 100%; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;">
                        <option value="equals" ${c.operator === 'equals' ? 'selected' : ''}>égal à (=)</option>
                        <option value="not_equals" ${c.operator === 'not_equals' ? 'selected' : ''}>différent de (!=)</option>
                        <option value="contains" ${c.operator === 'contains' ? 'selected' : ''}>contient</option>
                        <option value="not_contains" ${c.operator === 'not_contains' ? 'selected' : ''}>ne contient pas</option>
                        <option value="greater_than" ${c.operator === 'greater_than' ? 'selected' : ''}>supérieur à (&gt;)</option>
                        <option value="less_than" ${c.operator === 'less_than' ? 'selected' : ''}>inférieur à (&lt;)</option>
                        <option value="is_not_empty" ${c.operator === 'is_not_empty' ? 'selected' : ''}>n'est pas vide</option>
                    </select>
                </div>
                <div style="flex: 3; min-width: 160px;">
                    <input type="text" placeholder="Valeur attendue" value="${escapeHtml(c.value || '')}" data-idx="${idx}" data-key="value" style="width: 100%; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;" />
                </div>
                <div>
                    <button type="button" class="button outline btn-remove-cond" data-idx="${idx}" style="color: #dc2626; border-color: #dc2626; padding: 4px 8px;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;
            conditionsContainer.appendChild(row);
        });

        // Listeners
        conditionsContainer.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', function() {
                const idx = parseInt(this.dataset.idx, 10);
                const key = this.dataset.key;
                conditions[idx][key] = this.value;
            });
        });

        conditionsContainer.querySelectorAll('.btn-remove-cond').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.idx, 10);
                conditions.splice(idx, 1);
                renderConditions();
            });
        });
    }

    if (btnAddCond) {
        btnAddCond.addEventListener('click', () => {
            conditions.push({ field: '', operator: 'equals', value: '' });
            renderConditions();
        });
    }
    renderConditions();

    // --- Gestion des Actions ---
    const actionsContainer = document.getElementById('actionsList');
    const btnAddWebhook = document.getElementById('btnAddWebhook');
    const btnAddEmail = document.getElementById('btnAddEmail');

    function renderActions() {
        actionsContainer.innerHTML = '';
        actions.forEach((a, idx) => {
            const card = document.createElement('div');
            card.className = 'wf-action-card';

            if (a.type === 'webhook') {
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <strong style="color: #10b981;"><i class="fa-solid fa-paper-plane"></i> Action ${idx + 1} : Webhook HTTP</strong>
                        ${actions.length > 1 ? `<button type="button" class="button outline btn-remove-act" data-idx="${idx}" style="color: #dc2626; border-color: #dc2626; padding: 2px 6px; font-size: 0.75rem;"><i class="fa-solid fa-trash"></i></button>` : ''}
                    </div>
                    <div class="form-row" style="display: flex; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div style="flex: 1; max-width: 120px;">
                            <label style="font-size: 0.8rem; font-weight: 600;">Méthode</label>
                            <select data-idx="${idx}" data-key="method" style="width: 100%; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;">
                                <option value="POST" ${a.method === 'POST' ? 'selected' : ''}>POST</option>
                                <option value="GET" ${a.method === 'GET' ? 'selected' : ''}>GET</option>
                                <option value="PUT" ${a.method === 'PUT' ? 'selected' : ''}>PUT</option>
                            </select>
                        </div>
                        <div style="flex: 4;">
                            <label style="font-size: 0.8rem; font-weight: 600;">URL Cible (ex: Discord, Slack, Ntfy, API tierce)</label>
                            <input type="url" placeholder="https://..." value="${escapeHtml(a.url || '')}" data-idx="${idx}" data-key="url" required style="width: 100%; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;" />
                        </div>
                    </div>
                    <div style="margin-bottom: 0.5rem;">
                        <label style="font-size: 0.8rem; font-weight: 600;">Corps JSON personnalisé (optionnel - si vide, le payload complet est envoyé)</label>
                        <textarea data-idx="${idx}" data-key="body" rows="2" placeholder='{"content": "Nouvel article: {{title}}", "url": "{{site.url}}"}' style="width: 100%; font-family: monospace; font-size: 0.84rem; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;">${escapeHtml(a.body || '')}</textarea>
                    </div>
                `;
            } else if (a.type === 'email') {
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <strong style="color: #6366f1;"><i class="fa-solid fa-envelope"></i> Action ${idx + 1} : Envoi d'E-mail</strong>
                        ${actions.length > 1 ? `<button type="button" class="button outline btn-remove-act" data-idx="${idx}" style="color: #dc2626; border-color: #dc2626; padding: 2px 6px; font-size: 0.75rem;"><i class="fa-solid fa-trash"></i></button>` : ''}
                    </div>
                    <div class="form-row" style="display: flex; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div style="flex: 2;">
                            <label style="font-size: 0.8rem; font-weight: 600;">Destinataire</label>
                            <input type="text" placeholder="{ADMIN_EMAIL} ou {{user.email}}" value="${escapeHtml(a.to || '{ADMIN_EMAIL}')}" data-idx="${idx}" data-key="to" style="width: 100%; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;" />
                        </div>
                        <div style="flex: 3;">
                            <label style="font-size: 0.8rem; font-weight: 600;">Sujet</label>
                            <input type="text" placeholder="Ex: [{{site.name}}] Notification : {{title}}" value="${escapeHtml(a.subject || '')}" data-idx="${idx}" data-key="subject" style="width: 100%; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;" />
                        </div>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600;">Message</label>
                        <textarea data-idx="${idx}" data-key="message" rows="3" placeholder="Bonjour,\nUn nouvel événement est survenu : {{title}}." style="width: 100%; font-size: 0.88rem; padding: 6px 10px; border: 1px solid var(--cb-border, #cbd5e1); border-radius: 6px;">${escapeHtml(a.message || '')}</textarea>
                    </div>
                `;
            }

            actionsContainer.appendChild(card);
        });

        // Listeners
        actionsContainer.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('input', function() {
                const idx = parseInt(this.dataset.idx, 10);
                const key = this.dataset.key;
                actions[idx][key] = this.value;
            });
        });

        actionsContainer.querySelectorAll('.btn-remove-act').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.idx, 10);
                actions.splice(idx, 1);
                renderActions();
            });
        });
    }

    if (btnAddWebhook) {
        btnAddWebhook.addEventListener('click', () => {
            actions.push({ type: 'webhook', url: '', method: 'POST', body: '', headers: '' });
            renderActions();
        });
    }

    if (btnAddEmail) {
        btnAddEmail.addEventListener('click', () => {
            actions.push({ type: 'email', to: '{ADMIN_EMAIL}', subject: 'Notification 299ko', message: '' });
            renderActions();
        });
    }
    renderActions();

    // --- Sauvegarde & Soumission Formulaire ---
    const form = document.getElementById('workflowForm');
    form.addEventListener('submit', function() {
        document.getElementById('conditionsJsonInput').value = JSON.stringify(conditions);
        document.getElementById('actionsJsonInput').value = JSON.stringify(actions);
    });

    // --- Bouton Test en Direct ---
    const btnTest = document.getElementById('btnTestWorkflow');
    const testBox = document.getElementById('testResultBox');
    const testContent = document.getElementById('testResultContent');

    if (btnTest) {
        btnTest.addEventListener('click', async function() {
            btnTest.disabled = true;
            btnTest.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Test en cours...';
            testBox.style.display = 'block';
            testContent.innerHTML = 'Envoi de la simulation en cours...';

            const payloadWf = {
                id: document.querySelector('input[name="id"]').value,
                name: document.getElementById('wf_name').value || 'Workflow Test',
                trigger: triggerSelect.value,
                conditions: conditions,
                actions: actions,
                is_async: 0
            };

            const formData = new FormData();
            formData.append('token', csrfToken);
            formData.append('workflow_data', JSON.stringify(payloadWf));

            try {
                const res = await fetch(testUrl, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                btnTest.disabled = false;
                btnTest.innerHTML = '<i class="fa-solid fa-vial-circle-check"></i> Tester ce Workflow en direct';

                if (data.success) {
                    testContent.innerHTML = `<span style="color: #10b981; font-weight: bold;"><i class="fa-solid fa-circle-check"></i> Test Réussi ! (${data.execution.duration_ms} ms)</span>\n\n`
                        + `Payload simulé : ` + JSON.stringify(data.sample_payload, null, 2) + `\n\n`
                        + `Résultats des actions : ` + JSON.stringify(data.execution.actions, null, 2);
                } else {
                    testContent.innerHTML = `<span style="color: #ef4444; font-weight: bold;"><i class="fa-solid fa-circle-xmark"></i> Échec du test : ${data.error || data.message || 'Anomalie'}</span>\n\n`
                        + (data.sample_payload ? `Payload simulé : ` + JSON.stringify(data.sample_payload, null, 2) : '');
                }
            } catch (err) {
                btnTest.disabled = false;
                btnTest.innerHTML = '<i class="fa-solid fa-vial-circle-check"></i> Tester ce Workflow en direct';
                testContent.innerHTML = `<span style="color: #ef4444;"><i class="fa-solid fa-circle-xmark"></i> Erreur réseau : ${err.message}</span>`;
            }
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
</script>

