<?php

namespace Core\Responses;

use Core\Responses\Response;
use Core\Theme;
use Template\Template;
use Core\Core;

/**
 * @copyright (C) 2024, 299Ko
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GPLv3
 * @author Maxence Cauderlier <mx.koder@gmail.com>
 * 
 * @package 299Ko https://github.com/299Ko/299ko
 */
defined('ROOT') OR exit('Access denied!');

class StringResponse extends Response {

    /**
     * Current theme name
     * @var Theme
     */
    protected Theme $theme;

    /**
     * Optional raw string content
     */
    protected string $rawContent = '';

    private bool $hasRawContent = false;
    private ?string $contentType = null;
    private ?int $statusCode = null;
    private array $headers = [];
    private bool $headersEmitted = false;

    public function __construct() {
        parent::__construct();
        $this->theme = new Theme(Core::getInstance()->getConfigVal('theme'));
    }

    /**
     * Set raw response content for MVC non-template endpoints.
     */
    public function setContent(string $content): self
    {
        $this->rawContent = $content;
        $this->hasRawContent = true;
        return $this;
    }

    public function hasRawContent(): bool
    {
        return $this->hasRawContent;
    }

    public function setContentType(string $mime): self
    {
        $this->contentType = $mime;
        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode ?? 200;
    }

    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function isRawOrNonHtml(): bool
    {
        return $this->hasRawContent
            || ($this->contentType !== null && stripos($this->contentType, 'text/html') === false);
    }

    public function sendHeaders(): void
    {
        if ($this->headersEmitted || headers_sent()) {
            return;
        }
        $this->headersEmitted = true;
        if ($this->statusCode !== null) {
            http_response_code($this->statusCode);
        }
        if ($this->contentType !== null) {
            header('Content-Type: ' . $this->contentType);
        }
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * Return the response
     * @return string Content of raw string or all templates
     */
    public function output(): string
    {
        $this->sendHeaders();
        if ($this->hasRawContent) {
            return $this->rawContent;
        }
        $content = '';
        foreach ($this->templates as $tpl) {
            $content .= $tpl->output();
        }
        return $content;
    }

    /**
     * Create a new Template, from plugin or core module
     * Eg : if plugin is 'blog' & asked template is 'read', look for 'THEMES/theme/template/blog.read.tpl'
     * else create tpl with PLUGINS/blog/template/read.tpl or COMMON/template/{pluginName}/{templateName}.tpl for core modules
     * @param string $pluginName
     * @param string $templateName
     * @return Template
     */
    public function createPluginTemplate(string $pluginName, string $templateName): Template {
        // 1. Try Theme override first (if applicable to string response, usually for fragments)
        $themeFile = $this->theme->getPluginTemplatePath($pluginName, $templateName) ?? '';
        if (file_exists($themeFile)) {
            return new Template($themeFile);
        }

        // 2. Try Admin Templates (Standard location for Core Modules and Admin Overrides)
        $adminFile = ADMIN_PATH . 'templates' . DS . $pluginName . DS . $templateName . '.tpl';
        if (file_exists($adminFile)) {
            return new Template($adminFile);
        }

        // 3. Try Plugin's internal template directory (Standard location for Plugins)
        $pluginFile = PLUGINS . $pluginName . DS . 'template' . DS . $templateName . '.tpl';
        if (file_exists($pluginFile)) {
            return new Template($pluginFile);
        }

        // 4. Last fallback: Core common templates
        $commonFile = COMMON . 'template' . DS . $pluginName . DS . $templateName . '.tpl';
        if (file_exists($commonFile)) {
            return new Template($commonFile);
        }

        throw new \RuntimeException("String template not found: $pluginName/$templateName");
    }

}