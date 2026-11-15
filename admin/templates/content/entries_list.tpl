<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <div>
        <a href="{{ROUTER.generate('content-admin-index')}}" class="button small secondary">← Types de contenus</a>
        <h2 style="margin-top:.5rem;"><i class="{{typeDef.icon}}"></i> {{typeDef.label}} — Entrées</h2>
    </div>
    <div>
        {% if canCreate %}
            <a href="{{ROUTER.generate('content-admin-entry-new', ['type' => typeDef.id])}}" class="button primary">+ Nouvelle entrée</a>
        {% endif %}
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Titre</th>
            <th>Slug</th>
            <th>Langue</th>
            <th>Statut</th>
            <th>Mise à jour</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        {% if entries %}
            {% for item in entries %}
            <tr>
                <td>#{{item.id}}</td>
                <td><strong>{{item.title}}</strong></td>
                <td><code>{{item.slug}}</code></td>
                <td>{{item.locale}}</td>
                <td>{{item.status}}</td>
                <td>{{item.updated_at}}</td>
                <td style="white-space:nowrap;">
                    <a href="{{ROUTER.generate('content-admin-entry-edit', ['type' => typeDef.id, 'id' => item.id])}}" class="button small">Modifier</a>
                    <form method="post" action="{{ROUTER.generate('content-admin-entry-delete', ['type' => typeDef.id, 'id' => item.id])}}" style="display:inline;" onsubmit="return confirm('Supprimer cette entrée ?');">
                        <input type="hidden" name="token" value="{{token}}" />
                        <input type="hidden" name="_csrf" value="{{csrfToken}}" />
                        <button type="submit" class="button small alert">Supprimer</button>
                    </form>
                </td>
            </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="7">Aucune entrée pour ce type de contenu.</td></tr>
        {% endif %}
    </tbody>
</table>
