<?php

namespace Core\Autoloader;

/**
 * Minimal portable HybridAutoloader with automatic cache generation and validation
 * - Resolves classes to files from a base directory (PSR-0/4 style mapping)
 * - Optional cache file path (not required to function)
 * - Automatic cache generation and validation based on file timestamps
 * - Exposes basic stats used by views/controllers
 */
class HybridAutoloader
{
    private array $baseDirs = [];
    private string $cacheFile;
    private bool $debug;
    private bool $authoritative;
    private array $loadedClasses = [];
    private ?array $classMap = null;
    private ?int $cacheTimestamp = null;

    private const EXCLUDED_DIR_NAMES = [
        'libs',
        'vendor',
        'node_modules',
        'dompdf',
        'mpdf',
        'PHPWord',
        'tinymce',
        '.git',
    ];

    /**
     * @param string|array $baseDirs Base directory or array of base directories
     */
    public function __construct($baseDirs, string $cacheFile, bool $debug = false, bool $authoritative = false)
    {
        $dirs = is_array($baseDirs) ? $baseDirs : [$baseDirs];
        foreach ($dirs as $dir) {
            $this->baseDirs[] = rtrim(str_replace('\\', '/', $dir), '/');
        }
        $this->cacheFile = $cacheFile;
        $this->debug = $debug;
        $this->authoritative = $authoritative;
        spl_autoload_register([$this, 'autoload'], true, true);
        $this->loadCache();
    }

    public function loadCache(): void
    {
        if (!$this->authoritative) {
            return;
        }

        if (is_file($this->cacheFile)) {
            $cacheData = @include $this->cacheFile;
            if (is_array($cacheData) && isset($cacheData['__metadata__'])) {
                $this->cacheTimestamp = $cacheData['__metadata__']['generated_at'] ?? null;
                $this->classMap = $cacheData['__classmap__'] ?? null;
                
                // Validate cache: check if any file has been modified
                if ($this->isCacheValid()) {
                    return;
                }
            }
        }
        
        // Cache invalid or doesn't exist, generate it
        $this->generateCache();
    }

    /**
     * Check if cache is still valid by comparing file timestamps
     */
    private function isCacheValid(): bool
    {
        if ($this->classMap === null || $this->cacheTimestamp === null) {
            return false;
        }

        // Check if cache file itself exists and is readable
        if (!is_file($this->cacheFile)) {
            return false;
        }

        // Check if any mapped file has been modified since cache generation
        foreach ($this->classMap as $class => $data) {
            $file = is_array($data) ? $data['file'] : $data;
            $baseIndex = is_array($data) ? ($data['base'] ?? 0) : 0;
            
            if (!isset($this->baseDirs[$baseIndex])) {
                return false;
            }

            $fullPath = $this->baseDirs[$baseIndex] . '/' . $file;
            if (!is_file($fullPath)) {
                return false; // File was deleted
            }
            if (filemtime($fullPath) > $this->cacheTimestamp) {
                return false; // File was modified
            }
        }

        return true;
    }

    /**
     * Generate cache by scanning project PHP files in base directories (excluding vendor subtrees).
     */
    public function generateCache(): void
    {
        if (!$this->authoritative) {
            return;
        }

        $classMap = [];

        foreach ($this->baseDirs as $index => $baseDir) {
            if (!is_dir($baseDir)) {
                continue;
            }

            $dirIterator = new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $filterIterator = new \RecursiveCallbackFilterIterator(
                $dirIterator,
                static function (\SplFileInfo $current): bool {
                    if ($current->isDir()) {
                        return !in_array($current->getFilename(), self::EXCLUDED_DIR_NAMES, true);
                    }
                    $name = $current->getFilename();
                    return $current->getExtension() === 'php' && !str_contains($name, '.bak');
                }
            );
            $iterator = new \RecursiveIteratorIterator($filterIterator, \RecursiveIteratorIterator::LEAVES_ONLY);

            foreach ($iterator as $file) {
                $fullPath = str_replace('\\', '/', $file->getPathname());
                $relativePath = ltrim(substr($fullPath, strlen($baseDir)), '/');
                $declaredClasses = $this->extractClassesFromFile($fullPath);

                foreach ($declaredClasses as $fullClassName) {
                    $canonicalPsr = str_replace('\\', '/', $fullClassName) . '.php';
                    $isCanonical = ($relativePath === $canonicalPsr);
                    if (!isset($classMap[$fullClassName]) || $isCanonical) {
                        $classMap[$fullClassName] = [
                            'file' => $relativePath,
                            'base' => $index,
                        ];
                    }
                }
            }
        }

        // Create cache directory if it doesn't exist
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        // Write cache file with metadata
        $cacheData = [
            '__metadata__' => [
                'generated_at' => time(),
                'base_directories' => $this->baseDirs,
                'total_classes' => count($classMap)
            ],
            '__classmap__' => $classMap
        ];

        $cacheContent = "<?php\n";
        $cacheContent .= "// Autoloader cache generated at " . date('Y-m-d H:i:s') . "\n";
        $cacheContent .= "// Do not edit this file manually\n\n";
        $cacheContent .= "return " . var_export($cacheData, true) . ";\n";

        @file_put_contents($this->cacheFile, $cacheContent, LOCK_EX);
        
        $this->classMap = $classMap;
        $this->cacheTimestamp = time();

        if ($this->debug) {
            error_log("Autoloader cache generated: " . count($classMap) . " classes mapped from " . count($this->baseDirs) . " directories");
        }
    }

    /**
     * Check whether a class/interface/trait/enum is currently loaded without triggering autoload.
     */
    private function isDeclared(string $class): bool
    {
        return class_exists($class, false)
            || interface_exists($class, false)
            || trait_exists($class, false)
            || (function_exists('enum_exists') && enum_exists($class, false));
    }

    /**
     * PSR-4 implementation that works across multiple base directories
     */
    public function load(string $class): bool
    {
        $class = ltrim($class, '\\');
        if ($class === '' || isset($this->loadedClasses[$class]) || $this->isDeclared($class)) {
            return true;
        }

        // 1. Check in ClassMap
        if (is_array($this->classMap) && isset($this->classMap[$class])) {
            $data = $this->classMap[$class];
            $file = is_array($data) ? $data['file'] : $data;
            $baseIndex = is_array($data) ? ($data['base'] ?? 0) : 0;

            if (isset($this->baseDirs[$baseIndex])) {
                $fullPath = $this->baseDirs[$baseIndex] . '/' . $file;
                if (is_file($fullPath)) {
                    require_once $fullPath;
                    if ($this->isDeclared($class)) {
                        $this->loadedClasses[$class] = $fullPath;
                        return true;
                    }
                }
            }
        }

        // 2. Fallback to PSR-4 lookup in each base directory (with case and src/ tolerance for plugin dirs)
        $psrPath = str_replace('\\', '/', $class) . '.php';
        $pathCandidates = [$psrPath];

        $segments = explode('/', $psrPath);
        if (count($segments) > 1) {
            $root = $segments[0];
            $rootVariants = [
                $root,
                strtolower($root),
                lcfirst($root),
            ];
            if (preg_match('/^([a-zA-Z]+)(\d+)$/', $root, $m)) {
                $rootVariants[] = strtolower($m[2] . $m[1]);
                $rootVariants[] = $m[2] . $m[1];
            }
            $rootVariants = array_values(array_unique($rootVariants));

            $fileSeg = $segments[count($segments) - 1];
            $midSegs = array_slice($segments, 1, -1);
            $midVariants = [''];
            if (!empty($midSegs)) {
                $midVariants = [
                    implode('/', $midSegs),
                    implode('/', array_map('strtolower', $midSegs)),
                    implode('/', array_map('lcfirst', $midSegs)),
                ];
                $midVariants = array_values(array_unique($midVariants));
            }

            foreach ($rootVariants as $rv) {
                foreach ($midVariants as $mv) {
                    $sub = ($mv !== '') ? $mv . '/' . $fileSeg : $fileSeg;
                    $pathCandidates[] = $rv . '/' . $sub;
                    $pathCandidates[] = $rv . '/src/' . $sub;
                }
            }
            $pathCandidates = array_values(array_unique($pathCandidates));
        }

        $targetLower = strtolower($class);
        foreach ($this->baseDirs as $baseDir) {
            foreach ($pathCandidates as $candidate) {
                $fullPath = $baseDir . '/' . $candidate;
                if (!is_file($fullPath)) {
                    continue;
                }

                // For relaxed non-PSR4 fallback paths, verify the file actually declares $class before executing it
                if ($candidate !== $psrPath) {
                    $declared = $this->extractClassesFromFile($fullPath);
                    $declaredLower = array_map('strtolower', $declared);
                    if (!in_array($targetLower, $declaredLower, true)) {
                        continue;
                    }
                }

                require_once $fullPath;
                if (!$this->isDeclared($class)) {
                    continue;
                }

                $this->loadedClasses[$class] = $fullPath;

                // Add to class map if authoritative
                if ($this->authoritative && is_array($this->classMap)) {
                    $index = array_search($baseDir, $this->baseDirs);
                    $this->classMap[$class] = [
                        'file' => $candidate,
                        'base' => $index
                    ];
                }
                return true;
            }
        }

        return false;
    }

    public function autoload(string $class): void
    {
        $this->load($class);
    }

    /**
     * Extract all FQCN (classes, interfaces, traits, enums) declared in a PHP file using token_get_all().
     * Handles both semicolon (`namespace A\B;`) and bracketed (`namespace A\B { ... }`) syntax accurately.
     *
     * @return string[]
     */
    private function extractClassesFromFile(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            return [];
        }

        $tokens = @token_get_all($content);
        $count = count($tokens);
        $classes = [];
        $currentNamespace = '';
        $inBracketedNamespace = false;
        $namespaceBraceDepth = 0;
        $braceDepth = 0;

        $enumToken = defined('T_ENUM') ? T_ENUM : -1;
        $nameQualified = defined('T_NAME_QUALIFIED') ? T_NAME_QUALIFIED : -1;
        $prevSigToken = null;

        for ($i = 0; $i < $count; $i++) {
            $tok = $tokens[$i];

            if (!is_array($tok)) {
                if ($tok === '{') {
                    $braceDepth++;
                } elseif ($tok === '}') {
                    if ($braceDepth > 0) {
                        $braceDepth--;
                    }
                    if ($inBracketedNamespace && $braceDepth < $namespaceBraceDepth) {
                        $currentNamespace = '';
                        $inBracketedNamespace = false;
                    }
                }
                if ($tok !== '') {
                    $prevSigToken = $tok;
                }
                continue;
            }

            $id = $tok[0];
            $text = $tok[1];

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $braceDepth++;
                $prevSigToken = $id;
                continue;
            }

            if ($id === T_NAMESPACE) {
                // Skip relative `namespace\foo` operator expressions
                $j = $i + 1;
                while ($j < $count && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE || $tokens[$j][0] === T_COMMENT || $tokens[$j][0] === T_DOC_COMMENT)) {
                    $j++;
                }
                if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_NS_SEPARATOR) {
                    $prevSigToken = $id;
                    continue;
                }

                $nsParts = '';
                $inBracket = false;
                while ($j < $count) {
                    $t = $tokens[$j];
                    if (is_array($t)) {
                        if ($t[0] === T_STRING || $t[0] === $nameQualified || $t[0] === T_NS_SEPARATOR) {
                            $nsParts .= $t[1];
                        }
                    } else {
                        if ($t === ';') {
                            break;
                        }
                        if ($t === '{') {
                            $inBracket = true;
                            $braceDepth++;
                            $namespaceBraceDepth = $braceDepth;
                            $inBracketedNamespace = true;
                            break;
                        }
                    }
                    $j++;
                }
                $currentNamespace = trim($nsParts, "\\ \t\r\n");
                $i = $j;
                $prevSigToken = $inBracket ? '{' : ';';
                continue;
            }

            if (($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT || $id === $enumToken)
                && $prevSigToken !== T_DOUBLE_COLON
                && $prevSigToken !== T_NEW
            ) {
                $j = $i + 1;
                while ($j < $count && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE || $tokens[$j][0] === T_COMMENT || $tokens[$j][0] === T_DOC_COMMENT)) {
                    $j++;
                }
                if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $shortName = $tokens[$j][1];
                    $classes[] = $currentNamespace !== '' ? ($currentNamespace . '\\' . $shortName) : $shortName;
                    $i = $j;
                    $prevSigToken = T_STRING;
                    continue;
                }
            }

            $prevSigToken = $id;
        }

        return array_values(array_unique($classes));
    }

    public function useClass(string $class): bool
    {
        return $this->load($class);
    }

    /**
     * Force cache regeneration
     */
    public function regenerateCache(): void
    {
        $this->generateCache();
    }

    /**
     * Clear cache file
     */
    public function clearCache(): void
    {
        if (is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
        $this->classMap = null;
        $this->cacheTimestamp = null;
    }

    public function getLoadedClasses(): array
    {
        return $this->loadedClasses;
    }

    public function getStats(): array
    {
        return [
            'total_classes_mapped' => is_array($this->classMap) ? count($this->classMap) : 0,
            'loaded_classes' => count($this->loadedClasses),
            'cache_file' => str_replace('\\', '/', $this->cacheFile),
            'base_directories' => $this->baseDirs,
            'cache_valid' => $this->isCacheValid(),
            'cache_timestamp' => $this->cacheTimestamp,
            'authoritative' => $this->authoritative,
        ];
    }
}

