<?php

namespace Core\Content;

use Core\Auth\User;
use Core\Navigation\AccessPolicyEvaluator;
use Core\Navigation\ConditionContext;
use Core\Navigation\GenericResource;
use Utils\Sanitizer;

defined('ROOT') or exit('Access denied!');

/**
 * Gestionnaire central des types de champs, validation serveur, permissions par champ
 * et génération de contrôles de formulaire HTML sans dépendance externe.
 */
class FieldManager
{
    private static ?self $instance = null;

    /** @var array<string,array{validator:callable,renderer:?callable}> */
    private array $types = [];

    private function __construct()
    {
        $this->registerCoreTypes();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enregistre un type de champ personnalisé ou surcharge un type existant.
     *
     * @param callable $validator Signature: function(FieldDefinition $field, mixed $value): array{valid:bool,value:mixed,error:?string}
     * @param callable|null $renderer Signature: function(FieldDefinition $field, mixed $value, string $inputName, string $inputId): string
     */
    public function registerType(string $type, callable $validator, ?callable $renderer = null): void
    {
        $normalized = strtolower(trim($type));
        $canonical = FieldDefinition::TYPE_ALIASES[$normalized] ?? $normalized;
        $this->types[$canonical] = [
            'validator' => $validator,
            'renderer' => $renderer,
        ];
    }

    public function hasType(string $type): bool
    {
        $normalized = strtolower(trim($type));
        $canonical = FieldDefinition::TYPE_ALIASES[$normalized] ?? $normalized;
        return isset($this->types[$canonical]);
    }

    /**
     * @return string[]
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->types);
    }

    /**
     * @return array{valid:bool,value:mixed,error:?string}
     */
    public function validateField(FieldDefinition $field, mixed $value): array
    {
        if (!isset($this->types[$field->type])) {
            return [
                'valid' => false,
                'value' => null,
                'error' => "Type de champ inconnu : {$field->type}",
            ];
        }

        $isEmpty = $this->isEmptyValue($field, $value);
        if ($isEmpty) {
            if ($field->required) {
                return [
                    'valid' => false,
                    'value' => null,
                    'error' => "Le champ « {$field->label} » est obligatoire.",
                ];
            }
            $default = $field->default;
            if ($field->type === 'boolean' && $value !== null && $value !== '') {
                $default = false;
            }
            return [
                'valid' => true,
                'value' => $default,
                'error' => null,
            ];
        }

        $result = call_user_func($this->types[$field->type]['validator'], $field, $value);
        if (!($result['valid'] ?? false)) {
            return $result;
        }

        if (isset($field->rules['validator']) && is_callable($field->rules['validator'])) {
            $custom = call_user_func($field->rules['validator'], $result['value'], $field);
            if ($custom === false) {
                return [
                    'valid' => false,
                    'value' => null,
                    'error' => "La valeur du champ « {$field->label} » n'est pas valide.",
                ];
            }
            if (is_string($custom) && $custom !== '') {
                return [
                    'valid' => false,
                    'value' => null,
                    'error' => $custom,
                ];
            }
        }

        return $result;
    }

    /**
     * Valide un jeu de données complet ou partiel vis-à-vis des définitions et des droits d'écriture.
     *
     * @param FieldDefinition[] $fields
     * @return array{valid:bool,values:array<string,mixed>,errors:array<string,string>}
     */
    public function validatePayload(
        array $fields,
        array $input,
        ?User $user = null,
        bool $partial = false,
        array $existingValues = [],
        bool $isApiContext = false
    ): array {
        $values = $existingValues;
        $errors = [];

        foreach ($fields as $field) {
            if (!$field instanceof FieldDefinition) {
                $field = FieldDefinition::fromArray((array) $field);
            }
            $name = $field->name;
            $hasInput = array_key_exists($name, $input);

            if ($isApiContext && !$field->apiWritable) {
                if ($hasInput && (!array_key_exists($name, $existingValues) || $input[$name] !== $existingValues[$name])) {
                    $errors[$name] = "Le champ « {$field->label} » n'est pas modifiable via l'API.";
                } elseif (!array_key_exists($name, $values) && $field->default !== null) {
                    $values[$name] = $field->default;
                }
                continue;
            }

            if (!$this->canWriteField($field, $user)) {
                if ($hasInput && (!array_key_exists($name, $existingValues) || $input[$name] !== $existingValues[$name])) {
                    $errors[$name] = "Vous n'avez pas la permission de modifier le champ « {$field->label} ».";
                } elseif (!array_key_exists($name, $values) && $field->default !== null) {
                    $values[$name] = $field->default;
                }
                continue;
            }

            if (!$hasInput) {
                if ($field->type === 'boolean' && !$partial && !array_key_exists($name, $existingValues)) {
                    $raw = $field->default ?? false;
                } elseif ($partial && array_key_exists($name, $existingValues)) {
                    continue;
                } else {
                    $raw = $existingValues[$name] ?? $field->default;
                }
            } else {
                $raw = $input[$name];
            }

            $res = $this->validateField($field, $raw);
            if (!$res['valid']) {
                $errors[$name] = (string) $res['error'];
            } else {
                $values[$name] = $res['value'];
            }
        }

        return [
            'valid' => empty($errors),
            'values' => $values,
            'errors' => $errors,
        ];
    }

    public function canReadField(FieldDefinition $field, ?User $user = null): bool
    {
        return $this->evaluateFieldPermission($field->readPermission, $field->name . ':read', $user, true);
    }

    public function canWriteField(FieldDefinition $field, ?User $user = null): bool
    {
        return $this->evaluateFieldPermission($field->writePermission, $field->name . ':write', $user, true);
    }

    /**
     * Filtre les valeurs d'un enregistrement selon les permissions de lecture de chaque champ.
     *
     * @param FieldDefinition[] $fields
     */
    public function filterReadableValues(array $fields, array $values, ?User $user = null): array
    {
        $filtered = $values;
        foreach ($fields as $field) {
            if (!$field instanceof FieldDefinition) {
                $field = FieldDefinition::fromArray((array) $field);
            }
            if (!$this->canReadField($field, $user)) {
                unset($filtered[$field->name]);
            }
        }
        return $filtered;
    }

    /**
     * Filtre les valeurs exposées via l'API REST par liste blanche explicite (apiReadable + RBAC).
     * Seuls les champs explicitement déclarés sur le type de contenu avec apiReadable=true sont conservés.
     */
    public function filterApiReadableValues(array $fields, array $values, ?User $user = null): array
    {
        $filtered = [];
        foreach ($fields as $field) {
            if (!$field instanceof FieldDefinition) {
                $field = FieldDefinition::fromArray((array) $field);
            }
            if ($field->apiReadable && $this->canReadField($field, $user) && array_key_exists($field->name, $values)) {
                $filtered[$field->name] = $values[$field->name];
            }
        }
        return $filtered;
    }

    /**
     * Génère le HTML complet des champs autorisés en écriture/lecture pour l'administration.
     *
     * @param FieldDefinition[] $fields
     */
    public function renderFormFields(
        array $fields,
        array $values = [],
        ?User $user = null,
        string $inputPrefix = 'custom_fields'
    ): string {
        $sorted = $fields;
        usort($sorted, fn(FieldDefinition $a, FieldDefinition $b) => $a->order <=> $b->order);

        $html = '';
        foreach ($sorted as $field) {
            $html .= $this->renderFieldInput($field, $values[$field->name] ?? $field->default, $user, $inputPrefix);
        }
        return $html;
    }

    public function renderFieldInput(
        FieldDefinition $field,
        mixed $value = null,
        ?User $user = null,
        string $inputPrefix = 'custom_fields'
    ): string {
        $canRead = $this->canReadField($field, $user);
        $canWrite = $this->canWriteField($field, $user);
        if (!$canRead && !$canWrite) {
            return '';
        }

        $safeName = htmlspecialchars($field->name, ENT_QUOTES, 'UTF-8');
        $inputName = $inputPrefix !== '' ? $inputPrefix . '[' . $safeName . ']' : $safeName;
        $inputId = 'field_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $field->name);
        $label = htmlspecialchars($field->label, ENT_QUOTES, 'UTF-8');
        $reqMark = $field->required ? ' <span class="required" aria-hidden="true">*</span>' : '';
        $readonlyAttr = $canWrite ? '' : ' readonly disabled';
        $reqAttr = ($field->required && $canWrite) ? ' required' : '';
        $placeholderAttr = $field->placeholder !== '' ? ' placeholder="' . htmlspecialchars($field->placeholder, ENT_QUOTES, 'UTF-8') . '"' : '';

        if (!$canRead) {
            $value = null;
        } elseif ($value === null) {
            $value = $field->default;
        }

        $control = '';
        $renderer = $this->types[$field->type]['renderer'] ?? null;
        if (is_callable($renderer)) {
            $control = (string) call_user_func($renderer, $field, $value, $inputName, $inputId, $readonlyAttr . $reqAttr . $placeholderAttr);
        } else {
            $control = $this->renderDefaultControl($field, $value, $inputName, $inputId, $readonlyAttr, $reqAttr, $placeholderAttr);
        }

        $helpHtml = $field->help !== ''
            ? '<small class="field-help">' . htmlspecialchars($field->help, ENT_QUOTES, 'UTF-8') . '</small>'
            : '';

        return '<div class="evo-field evo-field-' . htmlspecialchars($field->type, ENT_QUOTES, 'UTF-8') . '">'
            . '<label for="' . $inputId . '">' . $label . $reqMark . '</label><br>'
            . $control
            . $helpHtml
            . '</div>';
    }

    private function evaluateFieldPermission(string|array|null $permission, string $resourceSuffix, ?User $user, bool $defaultAllow): bool
    {
        if ($permission === null || $permission === '' || $permission === 'public') {
            return $defaultAllow;
        }

        if ($permission === 'auth') {
            return $user !== null;
        }

        if ($user === null) {
            return false;
        }

        $evaluator = AccessPolicyEvaluator::getInstance();
        $ctx = new ConditionContext($user);

        if ($permission === 'admin') {
            return $evaluator->isAdmin($ctx);
        }

        $permHash = md5((string) json_encode($permission));
        if (is_array($permission)) {
            return $evaluator->canAccess(new GenericResource('field:' . $resourceSuffix . ':' . $permHash, $permission, ['PUB' => false]), $ctx);
        }

        return $evaluator->canAccess(
            new GenericResource('field:' . $resourceSuffix . ':' . $permHash, [['type' => 'permission', 'value' => (string) $permission]], ['PUB' => false]),
            $ctx
        );
    }

    private function renderDefaultControl(
        FieldDefinition $field,
        mixed $value,
        string $inputName,
        string $inputId,
        string $readonlyAttr,
        string $reqAttr,
        string $placeholderAttr
    ): string {
        $attrs = $readonlyAttr . $reqAttr . $placeholderAttr;

        switch ($field->type) {
            case 'textarea':
                $val = htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
                return '<textarea name="' . $inputName . '" id="' . $inputId . '" rows="4"' . $attrs . '>' . $val . '</textarea>';

            case 'boolean':
                $checked = $this->toBool($value) ? ' checked' : '';
                return '<input type="hidden" name="' . $inputName . '" value="0" />'
                    . '<input type="checkbox" name="' . $inputName . '" id="' . $inputId . '" value="1"' . $checked . $readonlyAttr . ' />';

            case 'select':
                $multiple = !empty($field->rules['multiple']);
                $nameAttr = $multiple ? $inputName . '[]' : $inputName;
                $multAttr = $multiple ? ' multiple' : '';
                $selectedValues = $multiple ? array_map('strval', (array) ($value ?? [])) : [(string) ($value ?? '')];
                $html = '<select name="' . $nameAttr . '" id="' . $inputId . '"' . $multAttr . $readonlyAttr . $reqAttr . '>';
                if (!$multiple) {
                    $html .= '<option value="">--</option>';
                }
                foreach ($this->normalizeSelectOptions($field->options) as $optVal => $optLabel) {
                    $sel = in_array((string) $optVal, $selectedValues, true) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars((string) $optVal, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                        . htmlspecialchars((string) $optLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                }
                return $html . '</select>';

            case 'geo':
                $lat = is_array($value) ? ($value['lat'] ?? '') : '';
                $lng = is_array($value) ? ($value['lng'] ?? '') : '';
                return '<div class="evo-geo-pair">'
                    . '<input type="number" step="any" min="-90" max="90" name="' . $inputName . '[lat]" id="' . $inputId . '_lat" value="' . htmlspecialchars((string) $lat, ENT_QUOTES, 'UTF-8') . '" placeholder="Latitude"' . $readonlyAttr . $reqAttr . ' /> '
                    . '<input type="number" step="any" min="-180" max="180" name="' . $inputName . '[lng]" id="' . $inputId . '_lng" value="' . htmlspecialchars((string) $lng, ENT_QUOTES, 'UTF-8') . '" placeholder="Longitude"' . $readonlyAttr . $reqAttr . ' />'
                    . '</div>';

            case 'integer':
                return '<input type="number" step="1" name="' . $inputName . '" id="' . $inputId . '" value="' . htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $attrs . ' />';

            case 'decimal':
                $step = htmlspecialchars((string) ($field->rules['step'] ?? 'any'), ENT_QUOTES, 'UTF-8');
                return '<input type="number" step="' . $step . '" name="' . $inputName . '" id="' . $inputId . '" value="' . htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $attrs . ' />';

            case 'datetime':
                $formatted = is_string($value) ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
                return '<input type="datetime-local" name="' . $inputName . '" id="' . $inputId . '" value="' . htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8') . '"' . $attrs . ' />';

            case 'date':
            case 'email':
            case 'url':
                return '<input type="' . $field->type . '" name="' . $inputName . '" id="' . $inputId . '" value="' . htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $attrs . ' />';

            case 'media':
            case 'text':
            default:
                return '<input type="text" name="' . $inputName . '" id="' . $inputId . '" value="' . htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $attrs . ' />';
        }
    }

    private function registerCoreTypes(): void
    {
        // 1. Texte court
        $this->registerType('text', function (FieldDefinition $field, mixed $value): array {
            if (is_array($value) || is_object($value)) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » attend un texte court."];
            }
            $str = trim((string) $value);
            return $this->validateStringConstraints($field, $str);
        });

        // 2. Texte long
        $this->registerType('textarea', function (FieldDefinition $field, mixed $value): array {
            if (is_array($value) || is_object($value)) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » attend un texte long."];
            }
            $str = trim((string) $value);
            if (!empty($field->rules['html'])) {
                $str = Sanitizer::cleanHtml($str);
            }
            return $this->validateStringConstraints($field, $str);
        });

        // 3. Nombre entier
        $this->registerType('integer', function (FieldDefinition $field, mixed $value): array {
            if (is_bool($value) || is_array($value)) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être un entier."];
            }
            $filtered = filter_var($value, FILTER_VALIDATE_INT);
            if ($filtered === false) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être un nombre entier valide."];
            }
            $intVal = (int) $filtered;
            return $this->validateNumericBounds($field, $intVal);
        });

        // 4. Nombre décimal
        $this->registerType('decimal', function (FieldDefinition $field, mixed $value): array {
            if (is_bool($value) || is_array($value)) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être un nombre décimal."];
            }
            $normalized = is_string($value) ? str_replace(',', '.', trim($value)) : $value;
            if (!is_numeric($normalized)) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être un nombre décimal valide."];
            }
            return $this->validateNumericBounds($field, (float) $normalized);
        });

        // 5. Date
        $this->registerType('date', function (FieldDefinition $field, mixed $value): array {
            $str = trim((string) $value);
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $str);
            if ($dt === false || $dt->format('Y-m-d') !== $str) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être une date valide (AAAA-MM-JJ)."];
            }
            if (isset($field->rules['min']) && $str < (string) $field->rules['min']) {
                return ['valid' => false, 'value' => null, 'error' => "La date de « {$field->label} » doit être >= {$field->rules['min']}."];
            }
            if (isset($field->rules['max']) && $str > (string) $field->rules['max']) {
                return ['valid' => false, 'value' => null, 'error' => "La date de « {$field->label} » doit être <= {$field->rules['max']}."];
            }
            return ['valid' => true, 'value' => $str, 'error' => null];
        });

        // 6. Date et heure
        $this->registerType('datetime', function (FieldDefinition $field, mixed $value): array {
            $str = trim((string) $value);
            $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', \DateTimeInterface::ATOM];
            $parsed = null;
            foreach ($formats as $fmt) {
                $dt = \DateTimeImmutable::createFromFormat($fmt, $str);
                $lastErrors = \DateTimeImmutable::getLastErrors();
                if ($dt !== false && ($lastErrors === false || ($lastErrors['warning_count'] === 0 && $lastErrors['error_count'] === 0))) {
                    $parsed = $dt;
                    break;
                }
            }
            if ($parsed === null) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être une date et heure valide."];
            }
            return ['valid' => true, 'value' => $parsed->format('Y-m-d H:i:s'), 'error' => null];
        });

        // 7. Booléen
        $this->registerType('boolean', function (FieldDefinition $field, mixed $value): array {
            if ($value === null) {
                return ['valid' => true, 'value' => false, 'error' => null];
            }
            $parsed = $this->parseBoolStrict($value);
            if ($parsed === null) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être un booléen."];
            }
            return ['valid' => true, 'value' => $parsed, 'error' => null];
        });

        // 8. Email
        $this->registerType('email', function (FieldDefinition $field, mixed $value): array {
            $str = trim((string) $value);
            if (filter_var($str, FILTER_VALIDATE_EMAIL) === false) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit contenir une adresse email valide."];
            }
            return ['valid' => true, 'value' => strtolower($str), 'error' => null];
        });

        // 9. URL
        $this->registerType('url', function (FieldDefinition $field, mixed $value): array {
            $str = trim((string) $value);
            if (preg_match('/^\s*(javascript|vbscript|data):/i', $str)) {
                return ['valid' => false, 'value' => null, 'error' => "Le schéma de l'URL « {$field->label} » est interdit."];
            }
            $allowRelative = $field->rules['allowRelative'] ?? true;
            $isRelative = $allowRelative && str_starts_with($str, '/') && !str_starts_with($str, '//');
            if (!$isRelative) {
                if (filter_var($str, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $str)) {
                    return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit contenir une URL HTTP(S) valide."];
                }
            }
            return ['valid' => true, 'value' => $str, 'error' => null];
        });

        // 10. Image ou fichier
        $this->registerType('media', function (FieldDefinition $field, mixed $value): array {
            $path = is_array($value) ? (string) ($value['path'] ?? $value['url'] ?? '') : trim((string) $value);
            if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
                return ['valid' => false, 'value' => null, 'error' => "Le chemin média du champ « {$field->label} » est invalide."];
            }
            if (preg_match('/^\s*(javascript|vbscript|data):/i', $path)) {
                return ['valid' => false, 'value' => null, 'error' => "URI média interdite pour « {$field->label} »."];
            }
            $cleanPath = strtok($path, '?#') ?: $path;
            $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
            $forbidden = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'sh', 'bash', 'exe'];
            if (in_array($ext, $forbidden, true)) {
                return ['valid' => false, 'value' => null, 'error' => "Extension de fichier dangereuse interdite pour « {$field->label} »."];
            }
            $allowed = $field->rules['extensions'] ?? null;
            if (is_array($allowed) && !empty($allowed)) {
                $allowedLower = array_map('strtolower', $allowed);
                if (!in_array($ext, $allowedLower, true)) {
                    return ['valid' => false, 'value' => null, 'error' => "Extension non autorisée pour « {$field->label} »."];
                }
            }
            return ['valid' => true, 'value' => $path, 'error' => null];
        });

        // 11. Liste de choix
        $this->registerType('select', function (FieldDefinition $field, mixed $value): array {
            $allowedMap = $this->normalizeSelectOptions($field->options);
            if (empty($allowedMap) && !empty($field->rules['in']) && is_array($field->rules['in'])) {
                $allowedMap = $this->normalizeSelectOptions($field->rules['in']);
            }
            $allowedKeys = array_map('strval', array_keys($allowedMap));
            $multiple = !empty($field->rules['multiple']);

            if ($multiple) {
                if (!is_array($value)) {
                    return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » attend une liste de valeurs."];
                }
                $normalized = [];
                foreach ($value as $item) {
                    $strItem = (string) $item;
                    if (!empty($allowedKeys) && !in_array($strItem, $allowedKeys, true)) {
                        return ['valid' => false, 'value' => null, 'error' => "Choix invalide pour « {$field->label} »."];
                    }
                    $normalized[] = $strItem;
                }
                return ['valid' => true, 'value' => array_values(array_unique($normalized)), 'error' => null];
            }

            if (is_array($value)) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » n'accepte qu'une seule sélection."];
            }
            $strVal = (string) $value;
            if (!empty($allowedKeys) && !in_array($strVal, $allowedKeys, true)) {
                return ['valid' => false, 'value' => null, 'error' => "La valeur sélectionnée pour « {$field->label} » n'est pas autorisée."];
            }
            return ['valid' => true, 'value' => $strVal, 'error' => null];
        });

        // 12. Coordonnées géographiques
        $this->registerType('geo', function (FieldDefinition $field, mixed $value): array {
            $lat = null;
            $lng = null;

            if (is_string($value) && str_contains($value, ',')) {
                $parts = array_map('trim', explode(',', $value, 2));
                $lat = $parts[0] ?? null;
                $lng = $parts[1] ?? null;
            } elseif (is_array($value)) {
                $lat = $value['lat'] ?? $value['latitude'] ?? $value[0] ?? null;
                $lng = $value['lng'] ?? $value['lon'] ?? $value['longitude'] ?? $value[1] ?? null;
            }

            if (!is_numeric($lat) || !is_numeric($lng)) {
                return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » requiert une latitude et une longitude numériques."];
            }

            $latF = (float) $lat;
            $lngF = (float) $lng;
            if ($latF < -90.0 || $latF > 90.0 || $lngF < -180.0 || $lngF > 180.0) {
                return ['valid' => false, 'value' => null, 'error' => "Coordonnées géographiques hors limites pour « {$field->label} »."];
            }

            return ['valid' => true, 'value' => ['lat' => $latF, 'lng' => $lngF], 'error' => null];
        });
    }

    private function isEmptyValue(FieldDefinition $field, mixed $value): bool
    {
        if ($field->type === 'boolean') {
            return $value === null || $value === '';
        }
        if ($field->type === 'geo' && is_array($value)) {
            $lat = $value['lat'] ?? $value['latitude'] ?? $value[0] ?? '';
            $lng = $value['lng'] ?? $value['lon'] ?? $value['longitude'] ?? $value[1] ?? '';
            return ($lat === '' || $lat === null) && ($lng === '' || $lng === null);
        }
        if (is_array($value)) {
            return count($value) === 0;
        }
        return $value === null || trim((string) $value) === '';
    }

    private function validateStringConstraints(FieldDefinition $field, string $str): array
    {
        $len = mb_strlen($str);
        if (isset($field->rules['minLength']) && $len < (int) $field->rules['minLength']) {
            return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit contenir au moins {$field->rules['minLength']} caractères."];
        }
        if (isset($field->rules['maxLength']) && $len > (int) $field->rules['maxLength']) {
            return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » ne doit pas dépasser {$field->rules['maxLength']} caractères."];
        }
        if (!empty($field->rules['pattern']) && @preg_match((string) $field->rules['pattern'], $str) !== 1) {
            return ['valid' => false, 'value' => null, 'error' => "Le format du champ « {$field->label} » est invalide."];
        }
        if (!empty($field->rules['in']) && is_array($field->rules['in']) && !in_array($str, $field->rules['in'], true)) {
            return ['valid' => false, 'value' => null, 'error' => "La valeur de « {$field->label} » n'est pas dans la liste autorisée."];
        }
        return ['valid' => true, 'value' => $str, 'error' => null];
    }

    private function validateNumericBounds(FieldDefinition $field, int|float $num): array
    {
        if (isset($field->rules['min']) && $num < $field->rules['min']) {
            return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être >= {$field->rules['min']}."];
        }
        if (isset($field->rules['max']) && $num > $field->rules['max']) {
            return ['valid' => false, 'value' => null, 'error' => "Le champ « {$field->label} » doit être <= {$field->rules['max']}."];
        }
        return ['valid' => true, 'value' => $num, 'error' => null];
    }

    private function normalizeSelectOptions(array $options): array
    {
        if (array_is_list($options)) {
            $map = [];
            foreach ($options as $val) {
                $map[(string) $val] = (string) $val;
            }
            return $map;
        }
        return $options;
    }

    private function parseBoolStrict(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === 0 || $value === '1' || $value === '0') {
            return (bool) (int) $value;
        }
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, ['true', 'on', 'yes', 'oui'], true)) {
                return true;
            }
            if (in_array($lower, ['false', 'off', 'no', 'non'], true)) {
                return false;
            }
        }
        return null;
    }

    private function toBool(mixed $value): bool
    {
        return $this->parseBoolStrict($value) ?? false;
    }
}
