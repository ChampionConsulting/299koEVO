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
    const STATUS_ERROR = 'HTTP/1.1 500 Internal Server Error';

    public string $status = self::STATUS_NOT_FOUND;
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

    public function output():string {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            $originHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?? ''));
            $currentHost = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
            if ($originHost !== '' && $currentHost !== '' && hash_equals($currentHost, $originHost)) {
                header("Access-Control-Allow-Origin: " . $origin, true);
                header("Vary: Origin", true);
            }
        }
        foreach($this->headers as $header){
			header($header,true);
		};
        header($this->status, true);
        if ($this->body === null) {
            return json_encode(new \stdClass()); // Return {} for null body
        }
        return json_encode($this->body);
    }

}