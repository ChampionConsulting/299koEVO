<?php

namespace Core\Api;

defined('ROOT') or exit('Access denied!');

/**
 * Descripteur d'une route de l'API REST du cœur ou d'un plugin.
 */
class ApiRoute
{
    public readonly string $name;
    public readonly string $method;
    public readonly string $pattern;
    public readonly string $regex;
    /** @var callable|string */
    public readonly mixed $handler;
    /** @var string|array */
    public readonly string|array $permission;
    public readonly array $paramRules;
    public readonly bool $csrfProtected;
    public readonly string $description;
    public readonly bool $authRequired;

    public function __construct(array $config)
    {
        $method = strtoupper(trim((string) ($config['method'] ?? 'GET')));
        $pattern = '/' . ltrim(trim((string) ($config['pattern'] ?? '/api/v1/')), '/');
        $permission = $config['permission'] ?? 'auth';
        $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        $this->name = (string) ($config['name'] ?? strtolower($method . ':' . $pattern));
        $this->method = $method;
        $this->pattern = $pattern;
        $this->regex = $this->compilePattern($pattern);
        $this->handler = $config['handler'] ?? static fn() => null;
        $this->permission = $permission;
        $this->paramRules = is_array($config['params'] ?? $config['paramRules'] ?? null)
            ? ($config['params'] ?? $config['paramRules'])
            : [];
        $this->csrfProtected = (bool) ($config['csrfProtected'] ?? $isMutating);
        $this->description = (string) ($config['description'] ?? '');
        $this->authRequired = $permission !== 'public';
    }

    /**
     * Tente de faire correspondre un chemin HTTP et extrait les paramètres nommés AltoRouter-like ([a:type], [i:id], etc.).
     */
    public function matchPath(string $path): ?array
    {
        $normalized = '/' . trim($path, '/');
        if ($normalized === '//') {
            $normalized = '/';
        }
        if (preg_match($this->regex, $normalized, $matches) !== 1) {
            return null;
        }
        $params = [];
        foreach ($matches as $k => $v) {
            if (!is_int($k)) {
                $params[$k] = $v;
            }
        }
        return $params;
    }

    public function toDocumentationArray(): array
    {
        return [
            'name' => $this->name,
            'method' => $this->method,
            'pattern' => $this->pattern,
            'auth_required' => $this->authRequired,
            'csrf_protected_on_session' => $this->csrfProtected,
            'permission' => $this->permission,
            'params' => $this->paramRules,
            'description' => $this->description,
        ];
    }

    private function compilePattern(string $pattern): string
    {
        $trimmed = '/' . trim($pattern, '/');
        $regex = preg_replace_callback('/\[([ai*]):([A-Za-z0-9_]+)\]/', static function (array $m): string {
            $sub = match ($m[1]) {
                'i' => '[0-9]+',
                'a' => '[0-9A-Za-z_-]+',
                '*' => '.+?',
                default => '[^/]+',
            };
            return '(?P<' . $m[2] . '>' . $sub . ')';
        }, $trimmed);

        return '#^' . $regex . '/?$#';
    }
}
