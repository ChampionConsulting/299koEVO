<div class="evo-admin-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
    <div>
        <h2><i class="fa-solid fa-cubes"></i> Types de contenus</h2>
        <p><small>Déclarez des types de contenus personnalisés, configurez leurs champs, permissions et entrées.</small></p>
    </div>
    <div>
        <a href="{{ROUTER.generate('content-admin-type-new')}}" class="button primary">+ Nouveau type de contenu</a>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Identifiant</th>
            <th>Libellé</th>
            <th>Champs</th>
            <th>Entrées</th>
            <th>API REST</th>
            <th>Multilingue</th>
            <th>Origine</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        {% for t in types %}
        <tr>
            <td><code>{{t.id}}</code></td>
            <td><i class="{{t.icon}}"></i> <strong>{{t.label}}</strong><br><small>{{t.description}}</small></td>
            <td>{{t.fieldsCount}}</td>
            <td>{{t.entriesCount}}</td>
            <td>{% if t.restEnabled %}Oui{% else %}Non{% endif %}</td>
            <td>{% if t.translatable %}Oui{% else %}Non{% endif %}</td>
            <td>{% if t.isCustom %}Back-office{% else %}{{t.plugin}}{% endif %}</td>
            <td style="white-space:nowrap;">
                {% if t.id != 'page' %}
                    <a href="{{ROUTER.generate('content-admin-entries', ['type' => t.id])}}" class="button small">Entrées</a>
                {% endif %}
                <a href="{{ROUTER.generate('content-admin-type-edit', ['type' => t.id])}}" class="button small secondary">Configurer</a>
                {% if t.isCustom %}
                <form method="post" action="{{ROUTER.generate('content-admin-type-delete', ['type' => t.id])}}" style="display:inline;" onsubmit="return confirm('Supprimer ce type de contenu ?');">
                    <input type="hidden" name="token" value="{{token}}" />
                    <input type="hidden" name="_csrf" value="{{csrfToken}}" />
                    <button type="submit" class="button small alert">Supprimer</button>
                </form>
                {% endif %}
            </td>
        </tr>
        {% endfor %}
    </tbody>
</table>
