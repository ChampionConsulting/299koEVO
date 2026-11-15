<?php

namespace Core\Content;

defined('ROOT') or exit('Access denied!');

/**
 * Définition complète d'un type de contenu enregistré dans ContentTypeManager.
 */
class ContentTypeDefinition
{
    public string $id;
    public string $label;
    public string $description;
    public string $icon;
    public string $plugin;
    public array $config;

    /** @var array<string,FieldDefinition> */
    public array $fields = [];

    public array $validation = [];
    public array $permissions = [];
    public array $display = [];

    public function __construct(
        string $id,
        string $label,
        string $description = '',
        string $icon = 'fa-solid fa-layer-group',
        string $plugin = 'core',
        array $config = [],
        array $fields = [],
        array $validation = [],
        array $permissions = [],
        array $display = []
    ) {
        $this->id = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($id));
        if ($this->id === '') {
            throw new \InvalidArgumentException('ContentTypeDefinition requires a valid non-empty "id".');
        }

        $this->label = trim($label) !== '' ? trim($label) : $this->id;
        $this->description = $description;
        $this->icon = $icon;
        $this->plugin = $plugin;
        $this->config = array_merge([
            'revisions' => true,
            'workflow' => true,
            'translatable' => false,
            'rest_enabled' => true,
            'default_status' => 'draft',
        ], $config);

        foreach ($fields as $idx => $fieldDef) {
            if ($fieldDef instanceof FieldDefinition) {
                $field = $fieldDef;
            } else {
                $arr = (array) $fieldDef;
                if (!isset($arr['order'])) {
                    $arr['order'] = (int) $idx;
                }
                $field = FieldDefinition::fromArray($arr);
            }
            $this->fields[$field->name] = $field;
        }
        $this->sortFields();

        $this->validation = $validation;
        $this->permissions = array_merge([
            'view' => 'public',
            'create' => 'content.create',
            'edit' => 'content.edit',
            'delete' => 'content.delete',
            'publish' => 'content.publish',
            'revisions' => 'content.revisions',
        ], $permissions);

        $this->display = array_merge([
            'title_field' => 'title',
            'summary_fields' => [],
            'default_sort' => ['created_at' => 'desc'],
            'template' => '',
        ], $display);
    }

    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? $data['slug'] ?? '');
        $label = (string) ($data['label'] ?? $data['name'] ?? $id);
        return new self(
            $id,
            $label,
            (string) ($data['description'] ?? ''),
            (string) ($data['icon'] ?? 'fa-solid fa-layer-group'),
            (string) ($data['plugin'] ?? 'core'),
            (array) ($data['config'] ?? []),
            (array) ($data['fields'] ?? []),
            (array) ($data['validation'] ?? []),
            (array) ($data['permissions'] ?? []),
            (array) ($data['display'] ?? [])
        );
    }

    public function addField(FieldDefinition|array $field): void
    {
        if (!$field instanceof FieldDefinition) {
            $field = FieldDefinition::fromArray($field);
        }
        $this->fields[$field->name] = $field;
        $this->sortFields();
    }

    /**
     * @return array<string,FieldDefinition>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $name): ?FieldDefinition
    {
        return $this->fields[$name] ?? null;
    }

    private function sortFields(): void
    {
        uasort($this->fields, fn(FieldDefinition $a, FieldDefinition $b) => $a->order <=> $b->order);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'plugin' => $this->plugin,
            'config' => $this->config,
            'fields' => array_map(fn(FieldDefinition $f) => $f->toArray(), array_values($this->fields)),
            'permissions' => $this->permissions,
            'display' => $this->display,
        ];
    }
}
