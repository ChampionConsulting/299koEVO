<?php

namespace Core\Api\Controllers;

use Core\Api\ApiManager;
use Core\Content\ContentTypeManager;
use Core\Controllers\Controller;
use Core\Responses\ApiResponse;
use Core\Router\Router;

defined('ROOT') or exit('Access denied!');

/**
 * Contrôleur HTTP & programmatique de l'API REST générique v1.
 */
class RestApiController extends Controller
{
    public function routesIndex(): ApiResponse
    {
        return ApiManager::getInstance()->dispatch('GET', '/api/v1/routes', $_GET, [], $this->collectHeaders());
    }

    public function listContent(string $type): ApiResponse
    {
        return ApiManager::getInstance()->dispatch('GET', "/api/v1/content/{$type}", $_GET, [], $this->collectHeaders());
    }

    public function getContent(string $type, string $id): ApiResponse
    {
        return ApiManager::getInstance()->dispatch('GET', "/api/v1/content/{$type}/{$id}", $_GET, [], $this->collectHeaders());
    }

    public function createContent(string $type): ApiResponse
    {
        return ApiManager::getInstance()->dispatch('POST', "/api/v1/content/{$type}", $_GET, $this->readBodyPayload(), $this->collectHeaders());
    }

    public function updateContent(string $type, string $id): ApiResponse
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'PUT');
        if ($method !== 'PATCH') {
            $method = 'PUT';
        }
        return ApiManager::getInstance()->dispatch($method, "/api/v1/content/{$type}/{$id}", $_GET, $this->readBodyPayload(), $this->collectHeaders());
    }

    public function deleteContent(string $type, string $id): ApiResponse
    {
        return ApiManager::getInstance()->dispatch('DELETE', "/api/v1/content/{$type}/{$id}", $_GET, $this->readBodyPayload(), $this->collectHeaders());
    }

    public function handleDynamic(string $endpoint): ApiResponse
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = '/api/v1/' . ltrim($endpoint, '/');
        return ApiManager::getInstance()->dispatch($method, $path, $_GET, $this->readBodyPayload(), $this->collectHeaders());
    }

    // --- Handlers internes appelés par ApiManager ---

    public function apiListContent(array $ctx): ApiResponse
    {
        $typeId = (string) ($ctx['pathParams']['type'] ?? '');
        $user = $ctx['user'] ?? null;
        $query = is_array($ctx['query'] ?? null) ? $ctx['query'] : [];

        $ctm = ContentTypeManager::getInstance();
        $typeDef = $ctm->get($typeId);
        if ($typeDef === null || empty($typeDef->config['rest_enabled'])) {
            return ApiResponse::error("Type de contenu introuvable ou non exposé en API : {$typeId}", 404);
        }
        if (!$ctm->canPerform($typeId, 'view', $user)) {
            return ApiResponse::error('Accès refusé à ce type de contenu.', $user === null ? 401 : 403);
        }

        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $reserved = ['page', 'per_page', 'sort', 'order'];
        $filters = [];
        foreach ($query as $k => $v) {
            if (!in_array((string) $k, $reserved, true) && $v !== '') {
                $filters[(string) $k] = $v;
            }
        }

        $sortField = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($query['sort'] ?? 'created_at'));
        $sortDir = strtolower((string) ($query['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy = $sortField !== '' ? [$sortField => $sortDir] : ['created_at' => 'desc'];

        $total = $ctm->countEntries($typeId, $filters, $user);
        $entries = $ctm->findEntries($typeId, $filters, $orderBy, $perPage, $offset, $user);
        $items = array_map(static fn($e): array => $e->toPublicArray($typeDef, $user, true), $entries);

        return ApiResponse::success($items, [
            'type' => $typeId,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ], 200);
    }

    public function apiGetContent(array $ctx): ApiResponse
    {
        $typeId = (string) ($ctx['pathParams']['type'] ?? '');
        $id = (string) ($ctx['pathParams']['id'] ?? '');
        $user = $ctx['user'] ?? null;

        $ctm = ContentTypeManager::getInstance();
        $typeDef = $ctm->get($typeId);
        if ($typeDef === null || empty($typeDef->config['rest_enabled'])) {
            return ApiResponse::error("Type de contenu introuvable ou non exposé en API : {$typeId}", 404);
        }
        $entry = $ctm->getEntry($typeId, $id, $user);
        if ($entry === null) {
            return ApiResponse::error('Contenu introuvable ou accès refusé.', 404);
        }

        return ApiResponse::success($entry->toPublicArray($typeDef, $user, true), ['type' => $typeId], 200);
    }

    public function apiCreateContent(array $ctx): ApiResponse
    {
        $typeId = (string) ($ctx['pathParams']['type'] ?? '');
        $user = $ctx['user'] ?? null;
        $payload = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];

        $ctm = ContentTypeManager::getInstance();
        $typeDef = $ctm->get($typeId);
        if ($typeDef === null || empty($typeDef->config['rest_enabled'])) {
            return ApiResponse::error("Type de contenu introuvable ou non exposé en API : {$typeId}", 404);
        }

        $res = $ctm->createEntry($typeId, $payload, $user, true);
        if (!$res['success'] || $res['entry'] === null) {
            return ApiResponse::error($res['errors'], (int) ($res['httpCode'] ?? 422));
        }

        return ApiResponse::success($res['entry']->toPublicArray($typeDef, $user, true), ['type' => $typeId], 201);
    }

    public function apiUpdateContent(array $ctx): ApiResponse
    {
        return $this->doUpdate($ctx, false);
    }

    public function apiPatchContent(array $ctx): ApiResponse
    {
        return $this->doUpdate($ctx, true);
    }

    public function apiDeleteContent(array $ctx): ApiResponse
    {
        $typeId = (string) ($ctx['pathParams']['type'] ?? '');
        $id = (string) ($ctx['pathParams']['id'] ?? '');
        $user = $ctx['user'] ?? null;

        $ctm = ContentTypeManager::getInstance();
        $typeDef = $ctm->get($typeId);
        if ($typeDef === null || empty($typeDef->config['rest_enabled'])) {
            return ApiResponse::error("Type de contenu introuvable ou non exposé en API : {$typeId}", 404);
        }
        if ($ctm->getEntry($typeId, $id, $user) === null) {
            return ApiResponse::error('Contenu introuvable.', 404);
        }
        if (!$ctm->deleteEntry($typeId, $id, $user)) {
            return ApiResponse::error('Permission insuffisante ou suppression impossible.', 403);
        }

        return ApiResponse::success(['deleted' => true, 'id' => $id], ['type' => $typeId], 200);
    }

    private function doUpdate(array $ctx, bool $partial): ApiResponse
    {
        $typeId = (string) ($ctx['pathParams']['type'] ?? '');
        $id = (string) ($ctx['pathParams']['id'] ?? '');
        $user = $ctx['user'] ?? null;
        $payload = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];

        $ctm = ContentTypeManager::getInstance();
        $typeDef = $ctm->get($typeId);
        if ($typeDef === null || empty($typeDef->config['rest_enabled'])) {
            return ApiResponse::error("Type de contenu introuvable ou non exposé en API : {$typeId}", 404);
        }

        $res = $ctm->updateEntry($typeId, $id, $payload, $user, $partial, true);
        if (!$res['success'] || $res['entry'] === null) {
            return ApiResponse::error($res['errors'], (int) ($res['httpCode'] ?? 422));
        }

        return ApiResponse::success($res['entry']->toPublicArray($typeDef, $user, true), ['type' => $typeId], 200);
    }

    private function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with((string) $k, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr((string) $k, 5)));
                $headers[$name] = (string) $v;
            }
        }
        return $headers;
    }

    private function readBodyPayload(): array
    {
        $raw = (string) file_get_contents('php://input');
        if (trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }
}
