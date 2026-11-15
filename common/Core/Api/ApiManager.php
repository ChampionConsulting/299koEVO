<?php

namespace Core\Api;

use Core\Auth\User;
use Core\Auth\UsersManager;
use Core\Content\ContentTypeManager;
use Core\Core;
use Core\Navigation\AccessPolicyEvaluator;
use Core\Navigation\ConditionContext;
use Core\Navigation\GenericResource;
use Core\Responses\ApiResponse;
use Core\Router\Router;
use Core\Security\Csrf;

defined('ROOT') or exit('Access denied!');

/**
 * Registre central, garde d'authentification/RBAC/CSRF/validation et répartiteur REST générique.
 */
class ApiManager
{
    private static ?self $instance = null;

    /** @var ApiRoute[] */
    private array $routes = [];

    private bool $hooksDispatched = false;

    /** @var string[] Clés sensibles automatiquement filtrées de toute sortie API */
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'pwd',
        'token',
        'tokens',
        'secret',
        'api_key',
        'private_key',
    ];

    private function __construct()
    {
        $this->registerCoreRoutes();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function register(array|ApiRoute $route): ApiRoute
    {
        return self::getInstance()->registerRoute($route);
    }

    public function registerRoute(array|ApiRoute $route): ApiRoute
    {
        $obj = $route instanceof ApiRoute ? $route : new ApiRoute($route);
        $this->routes[$obj->method . ' ' . $obj->pattern] = $obj;
        return $obj;
    }

    /**
     * @return ApiRoute[]
     */
    public function getRoutes(): array
    {
        $this->ensureHookRoutesLoaded();
        return array_values($this->routes);
    }

    /**
     * Enregistre les routes REST dans AltoRouter (y compris le catch-all /api/v1/... pour les plugins tardifs).
     */
    public function registerRouterRoutes(Router $router): void
    {
        $router->map('GET', '/api/v1/routes[/?]', 'Core\Api\Controllers\RestApiController#routesIndex', 'api-v1-routes');
        $router->map('GET', '/api/v1/content/[a:type][/?]', 'Core\Api\Controllers\RestApiController#listContent', 'api-v1-content-list');
        $router->map('GET', '/api/v1/content/[a:type]/[a:id][/?]', 'Core\Api\Controllers\RestApiController#getContent', 'api-v1-content-get');
        $router->map('POST', '/api/v1/content/[a:type][/?]', 'Core\Api\Controllers\RestApiController#createContent', 'api-v1-content-create');
        $router->map('PUT|PATCH', '/api/v1/content/[a:type]/[a:id][/?]', 'Core\Api\Controllers\RestApiController#updateContent', 'api-v1-content-update');
        $router->map('DELETE', '/api/v1/content/[a:type]/[a:id][/?]', 'Core\Api\Controllers\RestApiController#deleteContent', 'api-v1-content-delete');
        $router->map('GET|POST|PUT|PATCH|DELETE', '/api/v1/[*:endpoint]', 'Core\Api\Controllers\RestApiController#handleDynamic', 'api-v1-dynamic');
    }

    /**
     * Résout, sécurise et exécute une requête API complète, en retournant une ApiResponse normalisée.
     */
    public function dispatch(
        string $method,
        string $path,
        array $queryParams = [],
        array $payload = [],
        array $headers = [],
        ?User $explicitUser = null
    ): ApiResponse {
        $this->ensureHookRoutesLoaded();
        $httpMethod = strtoupper(trim($method));
        $normalizedPath = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');

        $matchedRoute = null;
        $pathParams = [];
        foreach ($this->routes as $route) {
            if ($route->method !== $httpMethod) {
                continue;
            }
            $params = $route->matchPath($normalizedPath);
            if ($params !== null) {
                $matchedRoute = $route;
                $pathParams = $params;
                break;
            }
        }

        if ($matchedRoute === null) {
            return ApiResponse::error('Route API introuvable.', 404, ['path' => $normalizedPath, 'method' => $httpMethod]);
        }

        // 1. Authentification (Session ou Bearer / X-API-Token)
        $authResult = $this->authenticate($headers, $explicitUser);
        if ($authResult['invalidToken']) {
            return ApiResponse::error('Jeton API invalide ou révoqué.', 401);
        }

        /** @var ?User $user */
        $user = $authResult['user'];
        $authMode = $authResult['mode']; // 'token' | 'session' | 'anonymous'

        // 2. Garde CSRF pour les requêtes mutantes authentifiées par session cookie
        $isMutating = in_array($httpMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($isMutating && $matchedRoute->csrfProtected && $authMode === 'session') {
            $csrfToken = $headers['X-CSRF-Token']
                ?? $headers['x-csrf-token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $payload['_csrf']
                ?? $_POST['_csrf']
                ?? null;
            if (!Csrf::validate($csrfToken !== null ? (string) $csrfToken : null, false)) {
                return ApiResponse::error('Jeton CSRF invalide ou manquant pour une session navigateur.', 403);
            }
        }

        // 3. Garde RBAC/ABAC par route
        if ($matchedRoute->authRequired && $user === null) {
            return ApiResponse::error('Authentification requise pour cette route API.', 401);
        }
        if (!$this->authorizeRoute($matchedRoute, $user, $pathParams)) {
            return ApiResponse::error('Permission insuffisante pour accéder à cette ressource API.', 403);
        }

        // 4. Validation stricte des paramètres d'entrée
        $allParams = array_merge($queryParams, $pathParams, $payload);
        $paramErrors = $this->validateParams($matchedRoute->paramRules, $allParams);
        if (!empty($paramErrors)) {
            return ApiResponse::error($paramErrors, 422);
        }

        // 5. Exécution du handler
        try {
            $result = $this->invokeHandler($matchedRoute->handler, [
                'route' => $matchedRoute,
                'user' => $user,
                'authMode' => $authMode,
                'pathParams' => $pathParams,
                'query' => $queryParams,
                'payload' => $payload,
                'params' => $allParams,
            ]);

            if ($result instanceof ApiResponse) {
                if (is_array($result->body) && array_key_exists('data', $result->body)) {
                    $result->body['data'] = $this->sanitizeOutputData($result->body['data']);
                }
                return $result;
            }

            if (is_array($result) && isset($result['success']) && array_key_exists('data', $result)) {
                return ApiResponse::normalized(
                    (bool) $result['success'],
                    $this->sanitizeOutputData($result['data']),
                    is_array($result['meta'] ?? null) ? $result['meta'] : [],
                    is_array($result['errors'] ?? null) ? $result['errors'] : [],
                    (int) ($result['httpCode'] ?? ($result['success'] ? 200 : 400))
                );
            }

            return ApiResponse::success($this->sanitizeOutputData($result), ['route' => $matchedRoute->name], 200);
        } catch (\Throwable $e) {
            return ApiResponse::error('Erreur interne du serveur API.', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Authentifie l'appelant via Bearer token, X-API-Token ou session utilisateur active.
     *
     * @return array{user:?User,mode:string,invalidToken:bool}
     */
    public function authenticate(array $headers = [], ?User $explicitUser = null): array
    {
        if ($explicitUser !== null) {
            return ['user' => $explicitUser, 'mode' => 'token', 'invalidToken' => false];
        }

        $token = $this->extractApiToken($headers);
        if ($token !== null) {
            $user = $this->findUserByApiToken($token);
            if ($user === null) {
                return ['user' => null, 'mode' => 'anonymous', 'invalidToken' => true];
            }
            return ['user' => $user, 'mode' => 'token', 'invalidToken' => false];
        }

        $sessionUser = UsersManager::getCurrentUser();
        if ($sessionUser !== null) {
            return ['user' => $sessionUser, 'mode' => 'session', 'invalidToken' => false];
        }

        return ['user' => null, 'mode' => 'anonymous', 'invalidToken' => false];
    }

    public function sanitizeOutputData(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        $clean = [];
        foreach ($data as $key => $val) {
            $keyLower = strtolower((string) $key);
            if (in_array($keyLower, self::SENSITIVE_KEYS, true)) {
                continue;
            }
            $clean[$key] = is_array($val) ? $this->sanitizeOutputData($val) : $val;
        }
        return $clean;
    }

    private function authorizeRoute(ApiRoute $route, ?User $user, array $pathParams): bool
    {
        $perm = $route->permission;
        if ($perm === 'public') {
            return true;
        }
        if ($user === null) {
            return false;
        }
        if ($perm === 'auth') {
            return true;
        }

        $evaluator = AccessPolicyEvaluator::getInstance();
        $ctx = new ConditionContext($user, $pathParams);
        $permHash = md5((string) json_encode($perm));
        $resourceId = 'api:' . $route->name . ':' . $permHash;

        if ($perm === 'admin') {
            return $user->isSuperAdmin() || $evaluator->isAdmin($ctx);
        }

        $policy = is_array($perm) ? $perm : [['type' => 'permission', 'value' => (string) $perm]];
        return $evaluator->canAccess(new GenericResource($resourceId, $policy, ['PUB' => false]), $ctx);
    }

    private function validateParams(array $rules, array $params): array
    {
        $errors = [];
        foreach ($rules as $name => $rule) {
            $exists = array_key_exists($name, $params) && $params[$name] !== '' && $params[$name] !== null;
            if (!empty($rule['required']) && !$exists) {
                $errors[] = ['field' => $name, 'message' => "Paramètre requis manquant : {$name}."];
                continue;
            }
            if (!$exists) {
                continue;
            }
            $val = $params[$name];
            $type = $rule['type'] ?? 'string';
            if ($type === 'int' && filter_var($val, FILTER_VALIDATE_INT) === false) {
                $errors[] = ['field' => $name, 'message' => "Le paramètre {$name} doit être un entier."];
            } elseif ($type === 'float' && !is_numeric($val)) {
                $errors[] = ['field' => $name, 'message' => "Le paramètre {$name} doit être numérique."];
            } elseif ($type === 'email' && filter_var((string) $val, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = ['field' => $name, 'message' => "Le paramètre {$name} doit être un email valide."];
            } elseif ($type === 'array' && !is_array($val)) {
                $errors[] = ['field' => $name, 'message' => "Le paramètre {$name} doit être un tableau."];
            }
            if (!empty($rule['in']) && is_array($rule['in']) && !in_array($val, $rule['in'], true)) {
                $errors[] = ['field' => $name, 'message' => "Valeur non autorisée pour {$name}."];
            }
        }
        return $errors;
    }

    private function invokeHandler(mixed $handler, array $requestCtx): mixed
    {
        if (is_callable($handler)) {
            return call_user_func($handler, $requestCtx);
        }
        if (is_string($handler) && str_contains($handler, '#')) {
            [$class, $method] = explode('#', $handler, 2);
            if (class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, $method)) {
                    return $instance->{$method}($requestCtx);
                }
            }
        }
        throw new \RuntimeException('Gestionnaire de route API introuvable.');
    }

    private function extractApiToken(array $headers): ?string
    {
        $normalized = [];
        foreach ($headers as $k => $v) {
            $normalized[strtolower((string) $k)] = (string) $v;
        }
        $authHeader = $normalized['authorization']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($authHeader !== '' && preg_match('/^\s*Bearer\s+(.+)$/i', $authHeader, $m)) {
            $tok = trim($m[1]);
            return $tok !== '' ? $tok : null;
        }

        $xToken = $normalized['x-api-token'] ?? $_SERVER['HTTP_X_API_TOKEN'] ?? '';
        if (trim((string) $xToken) !== '') {
            return trim((string) $xToken);
        }

        return null;
    }

    private function findUserByApiToken(string $providedToken): ?User
    {
        foreach (UsersManager::getUsers() as $user) {
            if ($user->status() !== 'active') {
                continue;
            }
            $candidates = [];
            $primaryToken = (string) ($user->attributes['token'] ?? '');
            if ($primaryToken !== '') {
                $candidates[] = $primaryToken;
            }
            $extraTokens = $user->attributes['tokens'] ?? [];
            if (is_array($extraTokens)) {
                foreach ($extraTokens as $t) {
                    if (is_string($t) && $t !== '') {
                        $candidates[] = $t;
                    }
                }
            }
            foreach ($candidates as $candidate) {
                if (hash_equals($candidate, $providedToken)) {
                    return $user;
                }
            }
        }
        return null;
    }

    private function registerCoreRoutes(): void
    {
        $this->registerRoute([
            'name' => 'api-routes-doc',
            'method' => 'GET',
            'pattern' => '/api/v1/routes',
            'permission' => 'public',
            'description' => 'Liste auto-documentée des routes API enregistrées (cœur et plugins).',
            'handler' => function (): ApiResponse {
                $docs = array_map(static fn(ApiRoute $r): array => $r->toDocumentationArray(), $this->getRoutes());
                return ApiResponse::success(['routes' => $docs], ['count' => count($docs), 'version' => 'v1']);
            },
        ]);

        $this->registerRoute([
            'name' => 'api-content-list',
            'method' => 'GET',
            'pattern' => '/api/v1/content/[a:type]',
            'permission' => 'public',
            'params' => [
                'page' => ['type' => 'int'],
                'per_page' => ['type' => 'int'],
            ],
            'description' => 'Liste paginée et filtrable des entrées d\'un type de contenu activé.',
            'handler' => 'Core\Api\Controllers\RestApiController#apiListContent',
        ]);

        $this->registerRoute([
            'name' => 'api-content-get',
            'method' => 'GET',
            'pattern' => '/api/v1/content/[a:type]/[a:id]',
            'permission' => 'public',
            'description' => 'Récupère une entrée d\'un type de contenu activé avec masquage des champs restreints.',
            'handler' => 'Core\Api\Controllers\RestApiController#apiGetContent',
        ]);

        $this->registerRoute([
            'name' => 'api-content-create',
            'method' => 'POST',
            'pattern' => '/api/v1/content/[a:type]',
            'permission' => 'auth',
            'csrfProtected' => true,
            'description' => 'Crée une nouvelle entrée de contenu validée côté serveur.',
            'handler' => 'Core\Api\Controllers\RestApiController#apiCreateContent',
        ]);

        $this->registerRoute([
            'name' => 'api-content-update',
            'method' => 'PUT',
            'pattern' => '/api/v1/content/[a:type]/[a:id]',
            'permission' => 'auth',
            'csrfProtected' => true,
            'description' => 'Met à jour complètement une entrée de contenu.',
            'handler' => 'Core\Api\Controllers\RestApiController#apiUpdateContent',
        ]);

        $this->registerRoute([
            'name' => 'api-content-patch',
            'method' => 'PATCH',
            'pattern' => '/api/v1/content/[a:type]/[a:id]',
            'permission' => 'auth',
            'csrfProtected' => true,
            'description' => 'Met à jour partiellement une entrée de contenu.',
            'handler' => 'Core\Api\Controllers\RestApiController#apiPatchContent',
        ]);

        $this->registerRoute([
            'name' => 'api-content-delete',
            'method' => 'DELETE',
            'pattern' => '/api/v1/content/[a:type]/[a:id]',
            'permission' => 'auth',
            'csrfProtected' => true,
            'description' => 'Supprime une entrée de contenu existante.',
            'handler' => 'Core\Api\Controllers\RestApiController#apiDeleteContent',
        ]);
    }

    private function ensureHookRoutesLoaded(): void
    {
        if ($this->hooksDispatched) {
            return;
        }
        $this->hooksDispatched = true;
        if (class_exists('\Core\Core')) {
            Core::getInstance()->callHook('api:register', $this);
        }
    }
}
