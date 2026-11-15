<?php

namespace Core\Responses;

use Core\Responses\Response;

/**
 * @copyright (C) 2024, 299Ko
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GPLv3
 * @author Maxence Cauderlier <mx.koder@gmail.com>
 * 
 * @package 299Ko https://github.com/299Ko/299ko
 */
defined('ROOT') or exit('Access denied!');

class ApiResponse extends Response
{

    const STATUS_SUCCESS = 'HTTP/1.1 200 OK';
    const STATUS_CREATED = 'HTTP/1.1 201 Created';
    const STATUS_ACCEPTED = 'HTTP/1.1 202 Accepted';
    const STATUS_NO_CONTENT = 'HTTP/1.1 204 No Content';
    const STATUS_BAD_REQUEST = 'HTTP/1.1 400 Bad Request';
    const STATUS_NOT_AUTHORIZED = 'HTTP/1.1 401 Not Authorized';
    const STATUS_FORBIDDEN = 'HTTP/1.1 403 Forbidden';
    const STATUS_NOT_FOUND = 'HTTP/1.1 404 Not Found';
    const STATUS_UNPROCESSABLE = 'HTTP/1.1 422 Unprocessable Entity';
    const STATUS_TOO_MANY_REQUESTS = 'HTTP/1.1 429 Too Many Requests';
    const STATUS_ERROR = 'HTTP/1.1 500 Internal Server Error';

    public string $status = self::STATUS_NOT_FOUND;
    public int $httpCode = 404;
    public $body = null;

    protected $headers = [
        "Content-Type: application/json; charset=UTF-8",
        "Cache-Control: no-cache",
        "Pragma: no-cache"
    ];

    /**
     * Explicit opt-in CORS header for public cross-origin endpoints.
     */
    public function allowCors(string $origin = '*'): void
    {
        $this->headers[] = "Access-Control-Allow-Origin: " . $origin;
        if ($origin !== '*') {
            $this->headers[] = "Vary: Origin";
        }
    }

    /**
     * Fabrique une réponse JSON normalisée {success, data, meta, errors}.
     */
    public static function normalized(
        bool $success,
        mixed $data = null,
        array $meta = [],
        array $errors = [],
        int $httpCode = 200
    ): self {
        $resp = new self();
        $resp->httpCode = $httpCode;
        $resp->status = self::httpStatusLine($httpCode);
        $resp->body = [
            'success' => $success,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ];
        return $resp;
    }

    public static function success(mixed $data = null, array $meta = [], int $httpCode = 200): self
    {
        return self::normalized(true, $data, $meta, [], $httpCode);
    }

    public static function error(string|array $errors, int $httpCode = 400, array $meta = [], mixed $data = null): self
    {
        $errorList = is_array($errors) ? $errors : [['message' => $errors]];
        return self::normalized(false, $data, $meta, $errorList, $httpCode);
    }

    public function getHttpCode(): int
    {
        if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $this->status, $m)) {
            return (int) $m[1];
        }
        return $this->httpCode;
    }

    public static function httpStatusLine(int $code): string
    {
        return match ($code) {
            200 => self::STATUS_SUCCESS,
            201 => self::STATUS_CREATED,
            202 => self::STATUS_ACCEPTED,
            204 => self::STATUS_NO_CONTENT,
            400 => self::STATUS_BAD_REQUEST,
            401 => self::STATUS_NOT_AUTHORIZED,
            403 => self::STATUS_FORBIDDEN,
            404 => self::STATUS_NOT_FOUND,
            422 => self::STATUS_UNPROCESSABLE,
            429 => self::STATUS_TOO_MANY_REQUESTS,
            default => self::STATUS_ERROR,
        };
    }

    public function output(): string {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($origin !== '') {
                $originHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?? ''));
                $currentHost = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
                if ($originHost !== '' && $currentHost !== '' && hash_equals($currentHost, $originHost)) {
                    header("Access-Control-Allow-Origin: " . $origin, true);
                    header("Vary: Origin", true);
                }
            }
            foreach ($this->headers as $header) {
                header($header, true);
            }
            header($this->status, true);
        }
        if ($this->body === null) {
            return json_encode(new \stdClass()); // Return {} for null body
        }
        return (string) json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

}