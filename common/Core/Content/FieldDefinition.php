<?php

namespace Core\Content;

defined('ROOT') or exit('Access denied!');

/**
 * Définition structurée d'un champ personnalisé attaché à un type de contenu.
 */
class FieldDefinition
{
    public const TYPE_ALIASES = [
        'string' => 'text',
        'short_text' => 'text',
        'long_text' => 'textarea',
        'richtext' => 'textarea',
        'int' => 'integer',
        'float' => 'decimal',
        'number' => 'decimal',
        'bool' => 'boolean',
        'image' => 'media',
        'file' => 'media',
        'choice' => 'select',
        'list' => 'select',
        'coordinates' => 'geo',
        'geopoint' => 'geo',
    ];

    public string $name;
    public string $label;
    public string $type;
    public mixed $default = null;
    public bool $required = false;
    public bool $translatable = false;
    public array $rules = [];
    public array $options = [];
    public int $order = 0;
    public string|array|null $readPermission = null;
    public string|array|null $writePermission = null;
    public bool $apiReadable = true;
    public bool $apiWritable = true;
    public string $placeholder = '';
    public string $help = '';

    public function __construct(
        string $name,
        string $label,
        string $type = 'text',
        mixed $default = null,
        bool $required = false,
        bool $translatable = false,
        array $rules = [],
        array $options = [],
        int $order = 0,
        string|array|null $readPermission = null,
        string|array|null $writePermission = null,
        string $placeholder = '',
        string $help = '',
        bool $apiReadable = true,
        bool $apiWritable = true
    ) {
        $this->name = trim($name);
        $this->label = trim($label) !== '' ? trim($label) : $this->name;
        $normalizedType = strtolower(trim($type));
        $this->type = self::TYPE_ALIASES[$normalizedType] ?? ($normalizedType !== '' ? $normalizedType : 'text');
        $this->default = $default;
        $this->required = $required;
        $this->translatable = $translatable;
        $this->rules = $rules;
        $this->options = $options;
        $this->order = $order;
        $this->readPermission = $readPermission;
        $this->writePermission = $writePermission;
        $this->placeholder = $placeholder;
        $this->help = $help;
        $this->apiReadable = $apiReadable;
        $this->apiWritable = $apiWritable;
    }

    public static function fromArray(array $definition): self
    {
        $name = (string) ($definition['name'] ?? $definition['id'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('FieldDefinition requires a non-empty "name" or "id".');
        }

        $permissions = is_array($definition['permissions'] ?? null) ? $definition['permissions'] : [];
        $readPermission = $definition['readPermission'] ?? $definition['read_permission'] ?? ($permissions['read'] ?? null);
        $writePermission = $definition['writePermission'] ?? $definition['write_permission'] ?? ($permissions['write'] ?? null);

        $rules = is_array($definition['rules'] ?? null) ? $definition['rules'] : [];
        foreach (['min', 'max', 'minLength', 'maxLength', 'pattern', 'in', 'extensions', 'multiple', 'step', 'html', 'allowRelative', 'validator'] as $ruleKey) {
            if (array_key_exists($ruleKey, $definition) && !array_key_exists($ruleKey, $rules)) {
                $rules[$ruleKey] = $definition[$ruleKey];
            }
        }

        $options = is_array($definition['options'] ?? null) ? $definition['options'] : [];
        if (empty($options) && is_array($definition['choices'] ?? null)) {
            $options = $definition['choices'];
        }

        return new self(
            $name,
            (string) ($definition['label'] ?? $name),
            (string) ($definition['type'] ?? 'text'),
            $definition['default'] ?? null,
            (bool) ($definition['required'] ?? false),
            (bool) ($definition['translatable'] ?? false),
            $rules,
            $options,
            (int) ($definition['order'] ?? 0),
            $readPermission,
            $writePermission,
            (string) ($definition['placeholder'] ?? ''),
            (string) ($definition['help'] ?? $definition['description'] ?? ''),
            (bool) ($definition['api_readable'] ?? $definition['apiReadable'] ?? true),
            (bool) ($definition['api_writable'] ?? $definition['apiWritable'] ?? true)
        );
    }

    public function toArray(): array
    {
        $rulesSerializable = [];
        foreach ($this->rules as $k => $v) {
            if ($k === 'validator' && is_callable($v)) {
                continue;
            }
            $rulesSerializable[$k] = $v;
        }

        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'default' => $this->default,
            'required' => $this->required,
            'translatable' => $this->translatable,
            'rules' => $rulesSerializable,
            'options' => $this->options,
            'order' => $this->order,
            'readPermission' => $this->readPermission,
            'writePermission' => $this->writePermission,
            'api_readable' => $this->apiReadable,
            'api_writable' => $this->apiWritable,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
        ];
    }
}
