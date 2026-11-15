<?php

namespace Core\Content\Controllers;

use Core\Blocks\BlockManager;
use Core\Content\ContentEntry;
use Core\Content\ContentTypeManager;
use Core\Content\FieldManager;
use Core\Controllers\AdminController;
use Core\Editorial\RevisionManager;
use Core\Editorial\WorkflowManager;
use Core\Lang;
use Core\Responses\AdminResponse;
use Core\Security\Csrf;
use Core\Translation\TranslationManager;
use Utils\Show;

defined('ROOT') or exit('Access denied!');

/**
 * Contrôleur back-office complet pour la gestion des types de contenus et de leurs entrées.
 */
class ContentAdminController extends AdminController
{
    public function index(): AdminResponse
    {
        $ctm = ContentTypeManager::getInstance();
        $types = [];
        foreach ($ctm->all() as $typeDef) {
            $types[] = [
                'id' => $typeDef->id,
                'label' => $typeDef->label,
                'description' => $typeDef->description,
                'icon' => $typeDef->icon,
                'plugin' => $typeDef->plugin,
                'fieldsCount' => count($typeDef->getFields()),
                'entriesCount' => $ctm->countEntries($typeDef->id, [], $this->user),
                'isCustom' => $ctm->isPersistedType($typeDef->id),
                'restEnabled' => !empty($typeDef->config['rest_enabled']),
                'translatable' => !empty($typeDef->config['translatable']),
            ];
        }

        $response = new AdminResponse();
        $tpl = $response->createPluginTemplate('content', 'index');
        $tpl->set('types', $types);
        $tpl->set('token', $this->user->token);
        $tpl->set('csrfToken', Csrf::token());
        $response->addTemplate($tpl);
        return $response;
    }

    public function editType(?string $type = null): AdminResponse
    {
        $this->requirePermission('content.edit');
        $ctm = ContentTypeManager::getInstance();
        $fm = FieldManager::getInstance();

        $typeDef = $type !== null ? $ctm->get($type) : null;
        $isNew = $typeDef === null;
        $typeData = $typeDef ? $typeDef->toArray() : [
            'id' => '',
            'label' => '',
            'description' => '',
            'icon' => 'fa-solid fa-layer-group',
            'config' => [
                'revisions' => true,
                'workflow' => true,
                'translatable' => false,
                'rest_enabled' => true,
                'default_status' => 'draft',
            ],
            'permissions' => [
                'view' => 'public',
                'create' => 'content.create',
                'edit' => 'content.edit',
                'delete' => 'content.delete',
                'publish' => 'content.publish',
                'revisions' => 'content.revisions',
            ],
            'fields' => [],
            'api_exposed_fields' => [],
        ];

        $response = new AdminResponse();
        $tpl = $response->createPluginTemplate('content', 'edit_type');
        $tpl->set('isNew', $isNew);
        $tpl->set('typeData', $typeData);
        $tpl->set('fieldsJson', json_encode($typeData['fields'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $tpl->set('supportedFieldTypes', $fm->getSupportedTypes());
        $tpl->set('apiExposedFieldsCsv', implode(', ', $typeData['api_exposed_fields'] ?? []));
        $tpl->set('token', $this->user->token);
        $tpl->set('csrfToken', Csrf::token());
        $response->addTemplate($tpl);
        return $response;
    }

    public function saveType()
    {
        $this->requirePermission('content.edit');
        if (!$this->isValidPostToken()) {
            Show::msg(Lang::get('core-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('content-admin-index'));
            return null;
        }

        $rawId = strtolower(trim((string) ($_POST['type_id'] ?? '')));
        $cleanId = preg_replace('/[^a-z0-9_-]/', '', $rawId);
        if ($cleanId === '') {
            Show::msg('Identifiant de type de contenu invalide (lettres minuscules, chiffres, _ ou -).', 'error');
            $this->core->redirect($this->router->generate('content-admin-type-new'));
            return null;
        }

        $fieldsInput = [];
        if (isset($_POST['fields_json']) && trim((string) $_POST['fields_json']) !== '') {
            $decoded = json_decode((string) $_POST['fields_json'], true);
            if (is_array($decoded)) {
                $fieldsInput = $decoded;
            }
        } elseif (isset($_POST['fields']) && is_array($_POST['fields'])) {
            $fieldsInput = $_POST['fields'];
        }

        $sanitizedFields = [];
        foreach (array_values($fieldsInput) as $idx => $f) {
            if (!is_array($f) || empty(trim((string) ($f['name'] ?? '')))) {
                continue;
            }
            $rules = is_array($f['rules'] ?? null) ? $f['rules'] : [];
            if (isset($f['min']) && $f['min'] !== '') {
                $rules['min'] = is_numeric($f['min']) ? (float) $f['min'] : $f['min'];
            }
            if (isset($f['max']) && $f['max'] !== '') {
                $rules['max'] = is_numeric($f['max']) ? (float) $f['max'] : $f['max'];
            }
            if (isset($f['minLength']) && $f['minLength'] !== '') {
                $rules['minLength'] = (int) $f['minLength'];
            }
            if (isset($f['maxLength']) && $f['maxLength'] !== '') {
                $rules['maxLength'] = (int) $f['maxLength'];
            }
            if (!empty($f['pattern'])) {
                $rules['pattern'] = (string) $f['pattern'];
            }

            $options = is_array($f['options'] ?? null) ? $f['options'] : [];
            if (empty($options) && !empty($f['options_csv']) && is_string($f['options_csv'])) {
                $options = array_values(array_filter(array_map('trim', explode(',', $f['options_csv']))));
            }

            $sanitizedFields[] = [
                'name' => preg_replace('/[^a-zA-Z0-9_]/', '', trim((string) $f['name'])),
                'label' => trim((string) ($f['label'] ?? $f['name'])),
                'type' => (string) ($f['type'] ?? 'text'),
                'default' => $f['default'] ?? null,
                'required' => !empty($f['required']),
                'translatable' => !empty($f['translatable']),
                'order' => isset($f['order']) ? (int) $f['order'] : $idx,
                'rules' => $rules,
                'options' => $options,
                'read_permission' => !empty($f['readPermission']) ? trim((string) $f['readPermission']) : (!empty($f['read_permission']) ? trim((string) $f['read_permission']) : null),
                'write_permission' => !empty($f['writePermission']) ? trim((string) $f['writePermission']) : (!empty($f['write_permission']) ? trim((string) $f['write_permission']) : null),
                'api_readable' => array_key_exists('api_readable', $f) ? !empty($f['api_readable']) : true,
                'api_writable' => array_key_exists('api_writable', $f) ? !empty($f['api_writable']) : true,
            ];
        }

        $exposedRaw = trim((string) ($_POST['api_exposed_fields'] ?? ''));
        $apiExposed = $exposedRaw !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $exposedRaw))))
            : [];

        $config = [
            'id' => $cleanId,
            'label' => trim((string) ($_POST['label'] ?? $cleanId)),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'icon' => trim((string) ($_POST['icon'] ?? 'fa-solid fa-layer-group')) ?: 'fa-solid fa-layer-group',
            'plugin' => 'admin',
            'config' => [
                'revisions' => isset($_POST['cfg_revisions']) ? !empty($_POST['cfg_revisions']) : true,
                'workflow' => isset($_POST['cfg_workflow']) ? !empty($_POST['cfg_workflow']) : true,
                'translatable' => !empty($_POST['cfg_translatable']),
                'rest_enabled' => isset($_POST['cfg_rest_enabled']) ? !empty($_POST['cfg_rest_enabled']) : true,
                'default_status' => (string) ($_POST['default_status'] ?? 'draft'),
            ],
            'permissions' => [
                'view' => trim((string) ($_POST['perm_view'] ?? 'public')) ?: 'public',
                'create' => trim((string) ($_POST['perm_create'] ?? 'content.create')) ?: 'content.create',
                'edit' => trim((string) ($_POST['perm_edit'] ?? 'content.edit')) ?: 'content.edit',
                'delete' => trim((string) ($_POST['perm_delete'] ?? 'content.delete')) ?: 'content.delete',
                'publish' => trim((string) ($_POST['perm_publish'] ?? 'content.publish')) ?: 'content.publish',
                'revisions' => trim((string) ($_POST['perm_revisions'] ?? 'content.revisions')) ?: 'content.revisions',
            ],
            'fields' => $sanitizedFields,
            'api_exposed_fields' => $apiExposed,
        ];

        ContentTypeManager::getInstance()->saveCustomType($config);
        Show::msg("Type de contenu « {$config['label']} » enregistré.", 'success');
        $this->core->redirect($this->router->generate('content-admin-entries', ['type' => $cleanId]));
        return null;
    }

    public function deleteType(string $type)
    {
        $this->requirePermission('content.edit');
        if (!$this->isValidPostToken()) {
            Show::msg(Lang::get('core-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('content-admin-index'));
            return null;
        }
        $ctm = ContentTypeManager::getInstance();
        if ($ctm->deleteCustomType($type)) {
            Show::msg("Type de contenu « {$type} » supprimé.", 'success');
        } else {
            Show::msg("Impossible de supprimer le type « {$type} ».", 'error');
        }
        $this->core->redirect($this->router->generate('content-admin-index'));
        return null;
    }

    public function listEntries(string $type): AdminResponse
    {
        $ctm = ContentTypeManager::getInstance();
        $typeDef = $ctm->get($type);
        if ($typeDef === null) {
            $this->core->error404();
        }
        if (!$ctm->canPerform($type, 'view', $this->user)) {
            Show::msg(Lang::get('permissions.denied'), 'error');
            $this->core->redirect($this->router->generate('content-admin-index'));
        }

        $entries = $ctm->findEntries($type, [], ['updated_at' => 'desc'], null, 0, $this->user);
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = $entry->toPublicArray($typeDef, $this->user);
        }

        $response = new AdminResponse();
        $tpl = $response->createPluginTemplate('content', 'entries_list');
        $tpl->set('typeDef', $typeDef);
        $tpl->set('entries', $rows);
        $tpl->set('canCreate', $ctm->canPerform($type, 'create', $this->user));
        $tpl->set('token', $this->user->token);
        $tpl->set('csrfToken', Csrf::token());
        $response->addTemplate($tpl);
        return $response;
    }

    public function editEntry(string $type, ?string $id = null): AdminResponse
    {
        $ctm = ContentTypeManager::getInstance();
        $typeDef = $ctm->get($type);
        if ($typeDef === null) {
            $this->core->error404();
        }

        $entry = null;
        if ($id !== null && $id !== '') {
            $entry = $ctm->getEntry($type, $id, $this->user);
            if ($entry === null) {
                Show::msg('Entrée introuvable ou accès refusé.', 'error');
                $this->core->redirect($this->router->generate('content-admin-entries', ['type' => $type]));
            }
        }

        $wf = WorkflowManager::getInstance();
        $currentStatus = $entry ? $entry->getStatus() : ($typeDef->config['default_status'] ?? WorkflowManager::STATUS_DRAFT);
        $statuses = $wf->getStatuses();
        $availableStatuses = $entry
            ? $wf->getAvailableStatuses($currentStatus, $this->user, $typeDef->permissions['publish'] ?? 'content.publish', $typeDef->permissions['edit'] ?? 'content.edit')
            : array_filter($statuses, fn($lbl, $st) => $wf->canSetInitialStatus($st, $this->user, $typeDef->permissions['publish'] ?? 'content.publish', $typeDef->permissions['edit'] ?? 'content.edit'), ARRAY_FILTER_USE_BOTH);

        $fieldsHtml = FieldManager::getInstance()->renderFormFields(
            $typeDef->getFields(),
            $entry ? $entry->getFields() : [],
            $this->user,
            'custom_fields'
        );

        $bm = BlockManager::getInstance();
        $blockDefs = [];
        foreach ($bm->all() as $blk) {
            $blockDefs[$blk->getType()] = [
                'type' => $blk->getType(),
                'label' => $blk->getLabel(),
                'category' => $blk->getCategory(),
                'defaultData' => $blk->getDefaultData(),
            ];
        }

        $revisions = $entry
            ? array_map(static fn($r): array => $r->toArray(), RevisionManager::getInstance()->getHistory('content:' . $type, (string) $entry->getId(), 20))
            : [];

        $tm = TranslationManager::getInstance();
        $response = new AdminResponse();
        $tpl = $response->createPluginTemplate('content', 'edit_entry');
        $tpl->set('typeDef', $typeDef);
        $tpl->set('entry', $entry);
        $tpl->set('fieldsHtml', $fieldsHtml);
        $tpl->set('currentStatus', $currentStatus);
        $tpl->set('statusLabels', $statuses);
        $tpl->set('availableStatuses', $availableStatuses);
        $tpl->set('blocksJson', json_encode($entry ? $entry->getBlocks() : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $tpl->set('blockDefsJson', json_encode($blockDefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $tpl->set('revisions', $revisions);
        $tpl->set('enabledLocales', $tm->getEnabledLocales());
        $tpl->set('currentLocale', $entry ? $entry->getLocale() : $tm->getDefaultLocale());
        $tpl->set('translationGroup', $entry ? $entry->getTranslationGroup() : '');
        $tpl->set('token', $this->user->token);
        $tpl->set('csrfToken', Csrf::token());
        $response->addTemplate($tpl);
        return $response;
    }

    public function saveEntry(string $type)
    {
        if (!$this->isValidPostToken()) {
            Show::msg(Lang::get('core-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('content-admin-entries', ['type' => $type]));
            return null;
        }

        $ctm = ContentTypeManager::getInstance();
        $id = trim((string) ($_POST['id'] ?? ''));
        $blocks = [];
        if (isset($_POST['blocks_json']) && trim((string) $_POST['blocks_json']) !== '') {
            $decoded = json_decode((string) $_POST['blocks_json'], true);
            if (is_array($decoded)) {
                $blocks = $decoded;
            }
        }

        $payload = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'draft')),
            'locale' => trim((string) ($_POST['locale'] ?? 'fr')),
            'translation_group' => trim((string) ($_POST['translation_group'] ?? '')),
            'fields' => is_array($_POST['custom_fields'] ?? null) ? $_POST['custom_fields'] : [],
            'blocks' => $blocks,
            'revision_summary' => trim((string) ($_POST['revision_summary'] ?? 'Édition back-office')),
        ];

        $res = $id !== ''
            ? $ctm->updateEntry($type, $id, $payload, $this->user, false)
            : $ctm->createEntry($type, $payload, $this->user);

        if (!$res['success'] || $res['entry'] === null) {
            Show::msg(implode(' | ', $res['errors']), 'error');
            $target = $id !== ''
                ? $this->router->generate('content-admin-entry-edit', ['type' => $type, 'id' => $id])
                : $this->router->generate('content-admin-entry-new', ['type' => $type]);
            $this->core->redirect($target);
            return null;
        }

        Show::msg('Contenu enregistré avec succès.', 'success');
        $this->core->redirect($this->router->generate('content-admin-entry-edit', ['type' => $type, 'id' => (string) $res['entry']->getId()]));
        return null;
    }

    public function deleteEntry(string $type, string $id)
    {
        if (!$this->isValidPostToken()) {
            Show::msg(Lang::get('core-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('content-admin-entries', ['type' => $type]));
            return null;
        }

        if (ContentTypeManager::getInstance()->deleteEntry($type, $id, $this->user)) {
            Show::msg('Entrée supprimée.', 'success');
        } else {
            Show::msg('Suppression refusée ou entrée introuvable.', 'error');
        }
        $this->core->redirect($this->router->generate('content-admin-entries', ['type' => $type]));
        return null;
    }

    public function restoreRevision(string $type)
    {
        if (!$this->isValidPostToken()) {
            Show::msg(Lang::get('core-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('content-admin-entries', ['type' => $type]));
            return null;
        }

        $revId = (string) ($_POST['revision_id'] ?? '');
        $res = RevisionManager::getInstance()->restoreRevision($revId, $this->user);
        Show::msg($res['message'], !empty($res['success']) ? 'success' : 'error');

        $entryId = (string) ($_POST['entry_id'] ?? '');
        if ($entryId !== '') {
            $this->core->redirect($this->router->generate('content-admin-entry-edit', ['type' => $type, 'id' => $entryId]));
        } else {
            $this->core->redirect($this->router->generate('content-admin-entries', ['type' => $type]));
        }
        return null;
    }

    private function isValidPostToken(): bool
    {
        $csrf = $_POST['_csrf'] ?? null;
        if ($csrf !== null && Csrf::validate((string) $csrf, false)) {
            return true;
        }
        return isset($_POST['token']) && hash_equals((string) $this->user->token, (string) $_POST['token']);
    }
}
