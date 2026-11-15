<?php

namespace Core\Blocks;

defined('ROOT') or exit('Access denied!');

/**
 * Définition d'un type de bloc (cœur ou plugin).
 */
class BlockDefinition implements BlockInterface
{
    public readonly string $type;
    public readonly string $label;
    public readonly string $description;
    public readonly string $icon;
    public readonly string $category;
    public readonly array $defaultData;
    public readonly array $schema;
    /** @var callable|null */
    private $validator;
    /** @var callable|null */
    private $renderer;

    public function __construct(array $config)
    {
        $type = strtolower(trim((string) ($config['type'] ?? $config['id'] ?? '')));
        if ($type === '') {
            throw new \InvalidArgumentException('Un bloc doit posséder un identifiant de type.');
        }

        $this->type = $type;
        $this->label = trim((string) ($config['label'] ?? ucfirst($type)));
        $this->description = (string) ($config['description'] ?? '');
        $this->icon = (string) ($config['icon'] ?? 'fa-solid fa-cubes');
        $this->category = (string) ($config['category'] ?? 'general');
        $this->defaultData = is_array($config['default_data'] ?? $config['defaultData'] ?? null)
            ? ($config['default_data'] ?? $config['defaultData'])
            : [];
        $this->schema = is_array($config['schema'] ?? null) ? $config['schema'] : [];
        $this->validator = isset($config['validator']) && is_callable($config['validator']) ? $config['validator'] : null;
        $this->renderer = isset($config['renderer']) && is_callable($config['renderer']) ? $config['renderer'] : null;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getDefaultData(): array
    {
        return $this->defaultData;
    }

    public function validate(array $data): array
    {
        $merged = array_merge($this->defaultData, $data);
        if ($this->validator !== null) {
            $result = call_user_func($this->validator, $merged, $this);
            if (is_array($result)) {
                return [
                    'valid' => (bool) ($result['valid'] ?? true),
                    'data' => is_array($result['data'] ?? null) ? $result['data'] : $merged,
                    'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
                ];
            }
        }
        return ['valid' => true, 'data' => $merged, 'errors' => []];
    }

    public function render(array $data, array $context = []): string
    {
        if ($this->renderer !== null) {
            return (string) call_user_func($this->renderer, $data, $context, $this);
        }
        return '';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'category' => $this->category,
            'defaultData' => $this->defaultData,
            'schema' => $this->schema,
        ];
    }
}
