<?php

namespace Core\Storage;

use Core\Interfaces\StorageInterface;

/**
 * JsonActiveRecord is now a storage-agnostic Base Class (despite its name).
 * It delegates to specific StorageInterface implementations (Json or SQL).
 */
abstract class JsonActiveRecord implements \ArrayAccess
{
    /** Drivers constants */
    const DRIVER_JSON = 'json';
    const DRIVER_SQL = 'sql';

    protected static string $filePath;
    protected static string $tableName;
    protected static string $primaryKey = 'id';
    protected static string $storageDriver = self::DRIVER_JSON;
    protected static string $connectionName = 'default';
    protected static array $attributeMapping = [];
    protected static array $casts = [];
    protected static array $indexes = [];
    protected static bool $isBooted = false;
    private static array $bootedModels = [];
    private static array $filePathOverrides = [];
    private static ?array $storageManifestCache = null;

    protected $queryConditions = [];
    protected $queryOrderBy = [];
    protected $queryLimit = null;
    protected $queryOffset = null;
    protected $queryGroups = [];
    protected $currentGroup = null;
    protected $withRelations = [];
    protected $querySelect = ['*'];

    public array $attributes = [];
    protected array $trueAttributes = [];

    /**
     * Construct a new instance, optionally with attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $this->normalizeAttributes($attributes);
        $this->applyCastsOnLoad();

        foreach ($this->trueAttributes as $attribute) {
            if (isset($this->attributes[$attribute])) {
                $this->{$attribute} = $this->attributes[$attribute];
            }
        }
    }

    /**
     * Map storage-specific column names back to model attribute names.
     */
    protected function normalizeAttributes(array $attributes): array
    {
        if (empty(static::$attributeMapping)) {
            return $attributes;
        }

        $normalized = [];
        $reverseMapping = array_flip(static::$attributeMapping);

        foreach ($attributes as $key => $value) {
            $normalizedKey = $reverseMapping[$key] ?? $key;
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }

    protected function applyCastsOnLoad(): void
    {
        foreach (static::$casts as $key => $type) {
            if (isset($this->attributes[$key]) && is_string($this->attributes[$key])) {
                if ($type === 'array' || $type === 'json') {
                    $this->attributes[$key] = json_decode($this->attributes[$key], true) ?: [];
                }
            }
        }
    }

    /**
     * Set the file path for the JSON file (Legacy), isolated per subclass.
     */
    public static function setFilePath(string $path): void
    {
        self::$filePathOverrides[static::class] = $path;
    }

    /**
     * Return the primary key field name
     */
    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * Inspect inheritance chain from static::class up to self::class (exclusive).
     *
     * @return \ReflectionClass[]
     */
    private static function getSubclassChain(): array
    {
        $chain = [];
        $curr = new \ReflectionClass(static::class);
        while ($curr && $curr->getName() !== self::class) {
            $chain[] = $curr;
            $curr = $curr->getParentClass();
        }
        return $chain;
    }

    /**
     * Load and index plugin storage.json manifests once per process.
     */
    private static function getStorageManifests(): array
    {
        if (self::$storageManifestCache !== null) {
            return self::$storageManifestCache;
        }
        $byClass = [];
        $byTable = [];
        $pluginsRoot = defined('PLUGINS') ? PLUGINS : (defined('ROOT') ? ROOT . 'plugin/' : '');
        if ($pluginsRoot !== '' && is_dir($pluginsRoot)) {
            $files = @glob(rtrim($pluginsRoot, '/\\') . '/*/param/storage.json') ?: [];
            foreach ($files as $file) {
                $raw = @file_get_contents($file);
                if ($raw === false) {
                    continue;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    continue;
                }
                foreach ($decoded as $entityKey => $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $table = (string) ($entry['table'] ?? '');
                    $class = ltrim((string) ($entry['class'] ?? ''), '\\');
                    $fileRel = (string) ($entry['file'] ?? $entry['json_file'] ?? '');
                    $pk = (string) ($entry['primaryKey'] ?? '');
                    $columns = self::parseManifestSchema($entry['schema'] ?? null);
                    $normalized = [
                        'entity' => (string) $entityKey,
                        'class' => $class,
                        'table' => $table,
                        'file' => $fileRel,
                        'primaryKey' => $pk,
                        'columns' => $columns,
                    ];
                    if ($class !== '') {
                        $byClass[strtolower($class)] = $normalized;
                    }
                    if ($table !== '') {
                        $byTable[strtolower($table)] = $normalized;
                    }
                }
            }
        }
        self::$storageManifestCache = ['byClass' => $byClass, 'byTable' => $byTable];
        return self::$storageManifestCache;
    }

    /**
     * Parse storage.json schema (either column=>type map or CREATE TABLE DDL string).
     *
     * @param mixed $schema
     * @return array<string, string>
     */
    private static function parseManifestSchema($schema): array
    {
        if (is_array($schema)) {
            $cols = [];
            foreach ($schema as $col => $type) {
                if (is_string($col) && $col !== '' && is_string($type)) {
                    $cols[$col] = $type;
                }
            }
            return $cols;
        }
        if (!is_string($schema) || trim($schema) === '') {
            return [];
        }
        if (!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\[]?[a-zA-Z0-9_]+[`"\]]?\s*\((.*)\)/is', $schema, $m)) {
            return [];
        }
        $body = $m[1];
        $parts = [];
        $current = '';
        $depth = 0;
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                if ($depth > 0) {
                    $depth--;
                }
            }
            if ($ch === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $ch;
            }
        }
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        $cols = [];
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^(?:PRIMARY\s+KEY|UNIQUE|INDEX|KEY|FULLTEXT|CONSTRAINT|FOREIGN\s+KEY)\b/i', $part)) {
                continue;
            }
            if (preg_match('/^[`"\[]?([a-zA-Z_][a-zA-Z0-9_]*)[`"\]]?\s+(.+)$/is', $part, $cm)) {
                $colName = $cm[1];
                $colDef = trim($cm[2]);
                // Extract clean SQL type token (e.g. VARCHAR(255), DECIMAL(10,2), INT, DATETIME, TINYINT(1), TEXT)
                if (preg_match('/^([a-zA-Z]+(?:\s*\([^)]+\))?)/i', $colDef, $tm)) {
                    $cols[$colName] = strtoupper(preg_replace('/\s+/', '', $tm[1]));
                } else {
                    $cols[$colName] = 'TEXT';
                }
            }
        }
        return $cols;
    }

    /**
     * Find matching storage.json manifest entry for current class or table name.
     */
    private static function resolveManifestEntry(?string $tableName = null): ?array
    {
        $manifests = self::getStorageManifests();
        $fqcnKey = strtolower(ltrim(static::class, '\\'));
        if (isset($manifests['byClass'][$fqcnKey])) {
            return $manifests['byClass'][$fqcnKey];
        }
        if ($tableName !== null && $tableName !== '' && isset($manifests['byTable'][strtolower($tableName)])) {
            return $manifests['byTable'][strtolower($tableName)];
        }
        return null;
    }

    /**
     * Resolve effective table/entity name cleanly along subclass hierarchy ($tableName > $table per class).
     */
    protected static function resolveTableName(?string $className = null): string
    {
        foreach (self::getSubclassChain() as $cls) {
            foreach (['tableName', 'table'] as $propName) {
                if ($cls->hasProperty($propName)) {
                    $prop = $cls->getProperty($propName);
                    if ($prop->getDeclaringClass()->getName() === $cls->getName() && $prop->isStatic() && $prop->isInitialized()) {
                        $val = (string) $prop->getValue();
                        if ($val !== '') {
                            return $val;
                        }
                    }
                }
            }
        }
        $manifest = self::resolveManifestEntry(null);
        if ($manifest !== null && $manifest['table'] !== '') {
            return $manifest['table'];
        }
        $short = ($className !== null && $className !== '') ? $className : basename(str_replace('\\', '/', static::class));
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        return $snake;
    }

    /**
     * Resolve effective JSON storage file path without leaking parent static $filePath overrides.
     */
    protected static function resolveFilePath(?string $className = null): string
    {
        if (isset(self::$filePathOverrides[static::class]) && self::$filePathOverrides[static::class] !== '') {
            return self::$filePathOverrides[static::class];
        }
        foreach (self::getSubclassChain() as $cls) {
            if ($cls->hasProperty('filePath')) {
                $prop = $cls->getProperty('filePath');
                if ($prop->getDeclaringClass()->getName() === $cls->getName() && $prop->isStatic() && $prop->isInitialized()) {
                    $val = (string) $prop->getValue();
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
        }
        $table = static::resolveTableName($className);
        $base = defined('DATA_PLUGIN') ? DATA_PLUGIN : (defined('DATA') ? DATA . 'plugin/' : '');
        $manifest = self::resolveManifestEntry($table);
        if ($manifest !== null && $manifest['file'] !== '') {
            return $base . ltrim($manifest['file'], '/\\');
        }
        return $base . $table . '/' . $table . '.json';
    }

    /**
     * Return the table name
     */
    public function getTableName(): string
    {
        $className = basename(str_replace('\\', '/', get_called_class()));
        return static::resolveTableName($className);
    }

    /**
     * Get a new instance for query building
     */
    public static function queryBuilder(): self
    {
        return new static();
    }

    /**
     * Alias for queryBuilder()
     */
    public static function query(): self
    {
        return static::queryBuilder();
    }

    /**
     * Convert attributes to array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Fill attributes from array
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    /**
     * Helper for grouped conditions (minimal implementation)
     */
    public function nest(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    /**
     * Set columns to select
     */
    public function select(array $columns): self
    {
        $this->querySelect = $columns;
        return $this;
    }
    
    public static function __callStatic($name, $arguments)
    {
        if (in_array($name, ['first', 'last'])) {
            return (new static())->$name(...$arguments);
        }
        throw new \BadMethodCallException("Static method $name does not exist on " . get_called_class());
    }

    /**
     * Check whether the calling subclass explicitly overrides static $storageDriver.
     */
    private static function getSubclassStorageDriver(): ?string
    {
        foreach (self::getSubclassChain() as $cls) {
            if ($cls->hasProperty('storageDriver')) {
                $prop = $cls->getProperty('storageDriver');
                if ($prop->getDeclaringClass()->getName() === $cls->getName() && $prop->isStatic() && $prop->isInitialized()) {
                    $val = (string) $prop->getValue();
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Factory to get the delegated storage implementation.
     */
    protected static function getStorage(): StorageInterface
    {
        $db = \Core\Database::getInstance();
        $className = basename(str_replace('\\', '/', get_called_class()));
        $fqcn = ltrim(static::class, '\\');

        // Priority: 1. Model config in env.json (FQCN or short class), 2. Subclass static::$storageDriver override, 3. Global storage.default, 4. JSON
        $driver = $db->getConfig($fqcn)
            ?? $db->getConfig($className)
            ?? self::getSubclassStorageDriver()
            ?? $db->getConfig('default')
            ?? self::DRIVER_JSON;

        EmergencyLogger::log("STORAGE_HUB: Model=$className selected_driver=$driver");

        $resolvedTable = static::resolveTableName($className);
        $resolvedFile = static::resolveFilePath($className);

        $storage = null;
        if ($driver !== self::DRIVER_JSON) {
            // Si le driver n'est pas "json", c'est soit "sql" (default), soit un nom de connexion spécifique (ex: "sqlite")
            $connectionName = ($driver === self::DRIVER_SQL) ? static::$connectionName : $driver;

            $primary = new SqlStorage(
                $db->setActiveConnection($connectionName),
                $resolvedTable,
                static::$primaryKey,
                static::$attributeMapping
            );

            // Fallback JSON automatique
            $fallback = new JsonStorage($resolvedFile, static::$primaryKey);

            $storage = new FallbackStorage($primary, $fallback);
        } else {
            $connectionName = 'json';
            $storage = new JsonStorage($resolvedFile, static::$primaryKey);
        }

        // Auto-Migration (Lazy boot per concrete model class, active driver, and DB target)
        $modelClass = static::class;
        $fullConf = $db->getFullConfig();
        $connConf = ($connectionName === 'default')
            ? ($fullConf['database'] ?? [])
            : ($fullConf['databases'][$connectionName] ?? $fullConf['database'] ?? []);
        $bootKey = $modelClass . '|' . (string) $driver . '|' . $resolvedTable . '|' . md5((string) json_encode($connConf));
        if (empty(self::$bootedModels[$bootKey])) {
            static::$isBooted = true;
            $ensured = $storage->ensureTable(
                $resolvedTable,
                static::$primaryKey,
                static::getSchema(),
                static::$indexes
            );
            if ($ensured) {
                self::$bootedModels[$bootKey] = true;
            }
        }

        return $storage;
    }

    protected static function getSchema(): array
    {
        $schema = [];
        $table = static::resolveTableName();
        $manifest = self::resolveManifestEntry($table);
        if ($manifest !== null && !empty($manifest['columns'])) {
            $schema = $manifest['columns'];
        }
        foreach (array_reverse(self::getSubclassChain()) as $cls) {
            if ($cls->hasProperty('schema')) {
                $prop = $cls->getProperty('schema');
                if ($prop->getDeclaringClass()->getName() === $cls->getName() && $prop->isStatic() && $prop->isInitialized()) {
                    $val = $prop->getValue();
                    if (is_array($val)) {
                        $schema = array_merge($schema, $val);
                    }
                }
            }
        }
        foreach (static::$casts as $key => $type) {
            if (!isset($schema[$key])) {
                $schema[$key] = $type;
            }
        }
        return $schema;
    }

    public static function beginTransaction(): bool
    {
        return static::getStorage()->beginTransaction();
    }

    public static function commit(): bool
    {
        return static::getStorage()->commit();
    }

    public static function rollBack(): bool
    {
        return static::getStorage()->rollBack();
    }

    /**
     * Filter results
     */
    public function where(string $key, $operator, $value = null): self
    {
        if ($value === null && func_num_args() < 3) {
            $value = $operator;
            $operator = '=';
        }
        $this->queryConditions[] = [
            'column' => $key,
            'operator' => $operator,
            'value' => $value
        ];
        return $this;
    }

    public function orWhere(string $key, $operator, $value = null): self
    {
        return $this->where($key, $operator, $value);
    }

    public function whereIn(string $key, array $values): self
    {
        $this->queryConditions[] = [
            'column' => $key,
            'operator' => 'IN',
            'value' => $values
        ];
        return $this;
    }

    /**
     * Set result limit and optional offset
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        $this->queryLimit = $limit;
        if ($offset !== null) {
            $this->queryOffset = $offset;
        }
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->queryOffset = $offset;
        return $this;
    }

    public function orderBy(string $key, string $direction = 'asc'): self
    {
        $this->queryOrderBy[] = ['key' => $key, 'direction' => $direction];
        return $this;
    }

    /**
     * Alias for orderBy()
     */
    public function sortBy(string $key, string $direction = 'asc'): self
    {
        return $this->orderBy($key, $direction);
    }

    /**
     * Count records matching criteria — optimized, does NOT load all records into memory.
     */
    public function count(): int
    {
        $storage = static::getStorage();

        $criteria = [
            'conditions' => $this->queryConditions,
            'order'      => [],
            'limit'      => null,
            'offset'     => 0,
        ];

        // Use dedicated count method if available (SqlStorage)
        if (method_exists($storage, 'count')) {
            return $storage->count($criteria);
        }

        // Fallback: delegate to findAll (still memory-efficient for JSON since files are small)
        return count($storage->findAll($criteria));
    }

    public function with(array $relations): self
    {
        $this->withRelations = $relations;
        return $this;
    }

    /**
     * Execute and return objects.
     */
    public function get(): array
    {
        $storage = static::getStorage();

        // On construit le package complet pour le storage
        $criteria = [
            'conditions' => $this->queryConditions,
            'order' => $this->queryOrderBy,
            'limit' => $this->queryLimit,
            'offset' => $this->queryOffset,
            'select' => $this->querySelect
        ];

        $rawRecords = $storage->findAll($criteria);

        $objects = array_map(fn($attr) => new static($attr), $rawRecords);

        if (!empty($this->withRelations)) {
            $this->loadRelations($objects);
        }

        return $objects;
    }

    /**
     * Get first record of the query
     */
    public function first(): ?self
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Get last record of the query
     */
    public function last(): ?self
    {
        // Not efficient but works for now as a generic fallback
        $results = $this->get();
        return $results ? $results[count($results) - 1] : null;
    }

    // Alias pour compatibilité descendante (certains plugins utilisent all avec conditions)
    public static function all($criteria = []): array
    {
        $instance = new static();
        if (!empty($criteria)) {
            // Si c'est un tableau associatif legacy, on convertit
            foreach ($criteria as $key => $val) {
                if (is_numeric($key) && is_array($val)) {
                    $instance->queryConditions[] = $val;
                } else {
                    $instance->where($key, '=', $val);
                }
            }
        }
        return $instance->get();
    }

    /**
     * Save the record to chosen storage.
     */
    public function save(): bool
    {
        foreach ($this->trueAttributes as $attr) {
            $this->attributes[$attr] = $this->{$attr};
        }

        $pk = static::$primaryKey;
        $id = (string) ($this->attributes[$pk] ?? '');
        $wasEmptyId = ($id === '');
        $storage = static::getStorage();

        $isSql = ($storage instanceof \Core\Storage\SqlStorage) || ($storage instanceof \Core\Storage\FallbackStorage);

        $schema = static::getSchema();
        $pkType = strtolower((string) ($schema[$pk] ?? ''));
        $isStringPk = ($pk !== 'id' && strpos($pkType, 'int') === false && strpos($pkType, 'serial') === false)
            || strpos($pkType, 'varchar') !== false
            || strpos($pkType, 'char') !== false
            || strpos($pkType, 'text') !== false
            || strpos($pkType, 'string') !== false;

        if ($wasEmptyId) {
            if ($isSql) {
                if ($isStringPk) {
                    $id = bin2hex(random_bytes(8));
                    $this->attributes[$pk] = $id;
                }
            } else {
                if (property_exists(static::class, 'autoIncrementId') && static::$autoIncrementId) {
                    $existing = $storage->findAll([]);
                    $maxId = 0;
                    foreach ($existing as $row) {
                        $rowId = (int) ($row[$pk] ?? 0);
                        if ($rowId > $maxId) {
                            $maxId = $rowId;
                        }
                    }
                    $id = (string) ($maxId + 1);
                } else {
                    $id = bin2hex(random_bytes(8));
                }
            }
        }

        // Apply casts for storage (encode arrays to JSON if using SQL)
        $storageData = $this->attributes;
        if ($isSql) {
            foreach ($storageData as $key => $val) {
                if (is_array($val)) {
                    $storageData[$key] = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
            }
        }

        $success = $storage->save($id, $storageData);

        if ($success) {
            if ($isSql && $id === '') {
                $id = \Core\Database::getInstance()->lastInsertId();
            }
            $this->attributes[$pk] = $id;
        }
        return $success;
    }

    public function delete(): bool
    {
        $id = (string) ($this->attributes[static::$primaryKey] ?? '');
        if (empty($id))
            return false;
        return static::getStorage()->delete($id);
    }

    public static function deletePK($id): bool
    {
        return static::getStorage()->delete((string)$id);
    }

    /** Legacy / Static helpers */
    public static function findPK($id): ?self
    {
        $data = static::getStorage()->find((string) $id);
        return $data ? new static($data) : null;
    }

    public static function find($key, $value): ?self
    {
        return (new static())->where($key, '=', $value)->get()[0] ?? null;
    }

    public function hasOne(string $relatedClass, string $foreignKey, $localKey = false): ?self
    {
        $localVal = $this->attributes[$localKey ?: static::$primaryKey] ?? null;
        return $relatedClass::find($foreignKey, $localVal);
    }

    public function hasMany(string $relatedClass, string $foreignKey, $localKey = false): array
    {
        $localVal = $this->attributes[$localKey ?: static::$primaryKey] ?? null;
        return $relatedClass::queryBuilder()->where($foreignKey, '=', $localVal)->get();
    }

    public function belongsTo(string $relatedClass, string $foreignKey, $localKey = false): ?self
    {
        $foreignVal = $this->attributes[$foreignKey] ?? null;
        return $relatedClass::findPK($foreignVal);
    }

    private array $gettingAttributes = [];

    // ... other magic methods __get, __set, __isset remain valid ...
    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }
    public function __get($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
        $getter = "get$name";
        if (method_exists($this, $getter) && empty($this->gettingAttributes[$name])) {
            $this->gettingAttributes[$name] = true;
            try {
                return $this->{$getter}();
            } finally {
                unset($this->gettingAttributes[$name]);
            }
        }
        return null;
    }
    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }

    protected function loadRelations(array &$objects): void
    {
        foreach ($this->withRelations as $relation) {
            foreach ($objects as $object) {
                if (method_exists($object, $relation)) {
                    $object->attributes[$relation] = $object->{$relation}();
                }
            }
        }
    }
    /** ArrayAccess implementation */
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->__get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->__set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }
}