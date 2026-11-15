<?php

namespace Core\Blocks;

use Core\Core;
use Template\Template;
use Utils\Sanitizer;

defined('ROOT') or exit('Access denied!');

/**
 * Gestionnaire central du moteur de blocs du cœur.
 * Distinct de HomeBuilder\Entities\BlockManager (aucun conflit de namespace).
 */
class BlockManager
{
    private static ?self $instance = null;

    /** @var array<string,BlockInterface> */
    private array $blocks = [];

    private bool $hooksDispatched = false;

    private function __construct()
    {
        $this->registerCoreBlocks();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function register(array|BlockInterface $definition): void
    {
        self::getInstance()->registerBlock($definition);
    }

    public function registerBlock(array|BlockInterface $definition): void
    {
        $block = $definition instanceof BlockInterface
            ? $definition
            : new BlockDefinition($definition);

        $this->blocks[$block->getType()] = $block;
    }

    public function has(string $type): bool
    {
        $this->ensureHookBlocksLoaded();
        return isset($this->blocks[strtolower(trim($type))]);
    }

    public function get(string $type): ?BlockInterface
    {
        $this->ensureHookBlocksLoaded();
        return $this->blocks[strtolower(trim($type))] ?? null;
    }

    /**
     * @return array<string,BlockInterface>
     */
    public function all(): array
    {
        $this->ensureHookBlocksLoaded();
        return $this->blocks;
    }

    public function createBlock(string $type, array $data = [], array $settings = [], ?string $id = null): array
    {
        $def = $this->get($type);
        $defaults = $def ? $def->getDefaultData() : [];
        return [
            'id' => $id ?: ('blk_' . bin2hex(random_bytes(5))),
            'type' => strtolower(trim($type)),
            'order' => 0,
            'data' => array_merge($defaults, $data),
            'settings' => $settings,
        ];
    }

    public function addBlock(array $blocks, array $block, ?int $position = null): array
    {
        $normalized = [
            'id' => (string) ($block['id'] ?? ('blk_' . bin2hex(random_bytes(5)))),
            'type' => strtolower(trim((string) ($block['type'] ?? 'text'))),
            'order' => 0,
            'data' => is_array($block['data'] ?? null) ? $block['data'] : [],
            'settings' => is_array($block['settings'] ?? null) ? $block['settings'] : [],
        ];

        $list = array_values($blocks);
        if ($position !== null && $position >= 0 && $position <= count($list)) {
            array_splice($list, $position, 0, [$normalized]);
        } else {
            $list[] = $normalized;
        }
        return $this->reindexOrder($list);
    }

    public function updateBlock(array $blocks, string $blockId, array $data, ?array $settings = null): array
    {
        foreach ($blocks as &$blk) {
            if (($blk['id'] ?? '') === $blockId) {
                $blk['data'] = array_merge(is_array($blk['data'] ?? null) ? $blk['data'] : [], $data);
                if ($settings !== null) {
                    $blk['settings'] = array_merge(is_array($blk['settings'] ?? null) ? $blk['settings'] : [], $settings);
                }
                break;
            }
        }
        unset($blk);
        return $this->reindexOrder($blocks);
    }

    public function removeBlock(array $blocks, string $blockId): array
    {
        $filtered = array_values(array_filter(
            $blocks,
            static fn(array $blk): bool => (string) ($blk['id'] ?? '') !== $blockId
        ));
        return $this->reindexOrder($filtered);
    }

    public function reorderBlocks(array $blocks, array $orderedIds): array
    {
        $byId = [];
        foreach ($blocks as $blk) {
            $id = (string) ($blk['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $blk;
            }
        }

        $reordered = [];
        foreach ($orderedIds as $id) {
            $key = (string) $id;
            if (isset($byId[$key])) {
                $reordered[] = $byId[$key];
                unset($byId[$key]);
            }
        }
        foreach ($byId as $remaining) {
            $reordered[] = $remaining;
        }

        return $this->reindexOrder($reordered);
    }

    /**
     * @return array{valid:bool,block:array,errors:string[]}
     */
    public function validateBlock(array $block): array
    {
        $type = strtolower(trim((string) ($block['type'] ?? '')));
        $def = $this->get($type);
        if ($def === null) {
            return [
                'valid' => false,
                'block' => $block,
                'errors' => ["Type de bloc inconnu : {$type}"],
            ];
        }

        $rawData = is_array($block['data'] ?? null) ? $block['data'] : [];
        $res = $def->validate($rawData);
        $cleanBlock = [
            'id' => (string) ($block['id'] ?? ('blk_' . bin2hex(random_bytes(5)))),
            'type' => $type,
            'order' => (int) ($block['order'] ?? 0),
            'data' => $res['data'],
            'settings' => $this->sanitizeSettings(is_array($block['settings'] ?? null) ? $block['settings'] : []),
        ];

        return [
            'valid' => $res['valid'],
            'block' => $cleanBlock,
            'errors' => $res['errors'],
        ];
    }

    /**
     * @return array{valid:bool,blocks:array,errors:string[]}
     */
    public function validateComposition(array $blocks): array
    {
        $validated = [];
        $errors = [];

        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                $errors[] = "Bloc #{$index} invalide.";
                continue;
            }
            $res = $this->validateBlock($block);
            if (!$res['valid']) {
                foreach ($res['errors'] as $err) {
                    $errors[] = "Bloc #{$index} ({$block['type']}) : {$err}";
                }
            } else {
                $validated[] = $res['block'];
            }
        }

        return [
            'valid' => empty($errors),
            'blocks' => $this->reindexOrder($validated),
            'errors' => $errors,
        ];
    }

    public function renderBlock(array $block, array $context = []): string
    {
        $type = strtolower(trim((string) ($block['type'] ?? '')));
        $def = $this->get($type);
        if ($def === null) {
            return '';
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
        $blockId = htmlspecialchars((string) ($block['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $customClass = preg_replace('/[^A-Za-z0-9_\-\s]/', '', (string) ($settings['class'] ?? ''));

        // Priorité à un éventuel gabarit de thème : theme/<active>/template/blocks/<type>.tpl
        $html = $this->tryRenderThemeOverride($type, $block, $data, $settings, $context);
        if ($html === null) {
            $html = $def->render($data, $context);
        }

        $classes = trim('evo-block evo-block-' . preg_replace('/[^a-z0-9_-]/', '', $type) . ' ' . $customClass);
        return '<div class="' . $classes . '" data-block-id="' . $blockId . '" data-block-type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">'
            . $html
            . '</div>';
    }

    public function renderBlocks(array $blocks, array $context = []): string
    {
        $sorted = $blocks;
        usort($sorted, static fn(array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));

        $output = '';
        foreach ($sorted as $block) {
            if (is_array($block)) {
                $output .= $this->renderBlock($block, $context);
            }
        }
        return '<div class="evo-blocks-container">' . $output . '</div>';
    }

    private function tryRenderThemeOverride(string $type, array $block, array $data, array $settings, array $context): ?string
    {
        if (!class_exists('\Core\Core') || !defined('THEMES')) {
            return null;
        }
        $theme = (string) Core::getInstance()->getConfigVal('theme');
        if ($theme === '') {
            return null;
        }
        $tplFile = THEMES . $theme . DS . 'template' . DS . 'blocks' . DS . $type . '.tpl';
        if (!is_file($tplFile)) {
            return null;
        }

        $tpl = new Template($tplFile);
        $tpl->set('block', $block);
        $tpl->set('data', $data);
        $tpl->set('settings', $settings);
        $tpl->set('context', $context);
        return $tpl->output();
    }

    private function reindexOrder(array $blocks): array
    {
        $result = [];
        foreach (array_values($blocks) as $index => $blk) {
            $blk['order'] = $index;
            $result[] = $blk;
        }
        return $result;
    }

    private function sanitizeSettings(array $settings): array
    {
        $clean = [];
        foreach ($settings as $k => $v) {
            if ($k === 'class' && is_string($v)) {
                $clean['class'] = trim(preg_replace('/[^A-Za-z0-9_\-\s]/', '', $v));
            } elseif (is_scalar($v)) {
                $clean[(string) $k] = $v;
            }
        }
        return $clean;
    }

    private function isSafeMediaOrUrl(string $url, bool $allowRelative = true): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '' || str_contains($trimmed, '..') || str_contains($trimmed, "\0")) {
            return false;
        }
        if (preg_match('/^\s*(javascript|vbscript|data):/i', $trimmed)) {
            return false;
        }
        if ($allowRelative && (str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//'))) {
            return true;
        }
        return filter_var($trimmed, FILTER_VALIDATE_URL) !== false && (bool) preg_match('#^https?://#i', $trimmed);
    }

    private function registerCoreBlocks(): void
    {
        // 1. Texte riche
        $this->registerBlock([
            'type' => 'text',
            'label' => 'Texte enrichi',
            'icon' => 'fa-solid fa-align-left',
            'category' => 'text',
            'default_data' => ['heading' => '', 'content' => ''],
            'validator' => function (array $data): array {
                $heading = trim(strip_tags((string) ($data['heading'] ?? '')));
                $rawContent = (string) ($data['content'] ?? $data['html'] ?? '');
                $cleanContent = Sanitizer::cleanHtml($rawContent);
                if ($heading === '' && trim(strip_tags($cleanContent)) === '') {
                    return ['valid' => false, 'data' => $data, 'errors' => ['Le bloc texte ne peut pas être vide.']];
                }
                return ['valid' => true, 'data' => ['heading' => $heading, 'content' => $cleanContent], 'errors' => []];
            },
            'renderer' => static function (array $data): string {
                $out = '';
                if (!empty($data['heading'])) {
                    $out .= '<h2>' . htmlspecialchars((string) $data['heading'], ENT_QUOTES, 'UTF-8') . '</h2>';
                }
                $out .= '<div class="evo-block-text-body">' . Sanitizer::cleanHtml((string) ($data['content'] ?? '')) . '</div>';
                return $out;
            },
        ]);

        // 2. Image
        $this->registerBlock([
            'type' => 'image',
            'label' => 'Image',
            'icon' => 'fa-regular fa-image',
            'category' => 'media',
            'default_data' => ['src' => '', 'alt' => '', 'caption' => '', 'link' => ''],
            'validator' => function (array $data): array {
                $src = trim((string) ($data['src'] ?? ''));
                if (!$this->isSafeMediaOrUrl($src, true)) {
                    return ['valid' => false, 'data' => $data, 'errors' => ['Source image invalide ou schéma interdit.']];
                }
                $ext = strtolower(pathinfo((string) (strtok($src, '?#') ?: $src), PATHINFO_EXTENSION));
                if (in_array($ext, ['php', 'phtml', 'phar', 'sh', 'exe'], true)) {
                    return ['valid' => false, 'data' => $data, 'errors' => ['Extension de fichier interdite pour une image.']];
                }
                $link = trim((string) ($data['link'] ?? ''));
                if ($link !== '' && !$this->isSafeMediaOrUrl($link, true)) {
                    return ['valid' => false, 'data' => $data, 'errors' => ['Lien sur image invalide.']];
                }
                return [
                    'valid' => true,
                    'data' => [
                        'src' => $src,
                        'alt' => trim((string) ($data['alt'] ?? '')),
                        'caption' => trim((string) ($data['caption'] ?? '')),
                        'link' => $link,
                    ],
                    'errors' => [],
                ];
            },
            'renderer' => static function (array $data): string {
                $src = htmlspecialchars((string) ($data['src'] ?? ''), ENT_QUOTES, 'UTF-8');
                $alt = htmlspecialchars((string) ($data['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
                $caption = trim((string) ($data['caption'] ?? ''));
                $img = '<img src="' . $src . '" alt="' . $alt . '" loading="lazy" />';
                if (!empty($data['link'])) {
                    $img = '<a href="' . htmlspecialchars((string) $data['link'], ENT_QUOTES, 'UTF-8') . '">' . $img . '</a>';
                }
                $figCaption = $caption !== '' ? '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>' : '';
                return '<figure class="evo-block-figure">' . $img . $figCaption . '</figure>';
            },
        ]);

        // 3. Galerie
        $this->registerBlock([
            'type' => 'gallery',
            'label' => 'Galerie d’images',
            'icon' => 'fa-solid fa-images',
            'category' => 'media',
            'default_data' => ['items' => [], 'columns' => 3],
            'validator' => function (array $data): array {
                $items = is_array($data['items'] ?? null) ? $data['items'] : [];
                $cols = max(1, min(6, (int) ($data['columns'] ?? 3)));
                $cleanItems = [];
                foreach ($items as $item) {
                    if (!is_array($item) || !$this->isSafeMediaOrUrl((string) ($item['src'] ?? ''), true)) {
                        return ['valid' => false, 'data' => $data, 'errors' => ['Un élément de galerie contient une URL invalide.']];
                    }
                    $cleanItems[] = [
                        'src' => trim((string) $item['src']),
                        'alt' => trim((string) ($item['alt'] ?? '')),
                        'caption' => trim((string) ($item['caption'] ?? '')),
                    ];
                }
                if (empty($cleanItems)) {
                    return ['valid' => false, 'data' => $data, 'errors' => ['La galerie doit contenir au moins une image.']];
                }
                return ['valid' => true, 'data' => ['items' => $cleanItems, 'columns' => $cols], 'errors' => []];
            },
            'renderer' => static function (array $data): string {
                $cols = max(1, min(6, (int) ($data['columns'] ?? 3)));
                $html = '<div class="evo-block-gallery" style="display:grid;grid-template-columns:repeat(' . $cols . ',1fr);gap:1rem;">';
                foreach ($data['items'] ?? [] as $item) {
                    $src = htmlspecialchars((string) ($item['src'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $alt = htmlspecialchars((string) ($item['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $html .= '<figure><img src="' . $src . '" alt="' . $alt . '" loading="lazy" />';
                    if (!empty($item['caption'])) {
                        $html .= '<figcaption>' . htmlspecialchars((string) $item['caption'], ENT_QUOTES, 'UTF-8') . '</figcaption>';
                    }
                    $html .= '</figure>';
                }
                return $html . '</div>';
            },
        ]);

        // 4. Vidéo
        $this->registerBlock([
            'type' => 'video',
            'label' => 'Vidéo',
            'icon' => 'fa-solid fa-film',
            'category' => 'media',
            'default_data' => ['url' => '', 'title' => ''],
            'validator' => function (array $data): array {
                $url = trim((string) ($data['url'] ?? ''));
                if (!$this->isSafeMediaOrUrl($url, true)) {
                    return ['valid' => false, 'data' => $data, 'errors' => ['URL vidéo invalide.']];
                }
                return [
                    'valid' => true,
                    'data' => ['url' => $url, 'title' => trim((string) ($data['title'] ?? ''))],
                    'errors' => [],
                ];
            },
            'renderer' => static function (array $data): string {
                $url = (string) ($data['url'] ?? '');
                $title = htmlspecialchars((string) ($data['title'] ?? 'Vidéo'), ENT_QUOTES, 'UTF-8');
                if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_\-]{6,})#', $url, $m)) {
                    return '<div class="evo-block-video-embed"><iframe src="https://www.youtube.com/embed/' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '" title="' . $title . '" loading="lazy" allowfullscreen></iframe></div>';
                }
                if (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
                    return '<div class="evo-block-video-embed"><iframe src="https://player.vimeo.com/video/' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '" title="' . $title . '" loading="lazy" allowfullscreen></iframe></div>';
                }
                return '<video class="evo-block-video-player" controls preload="metadata"><source src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" /></video>';
            },
        ]);

        // 5. Bouton / Appel à l'action
        $this->registerBlock([
            'type' => 'button',
            'label' => 'Bouton d’action',
            'icon' => 'fa-solid fa-square-arrow-up-right',
            'category' => 'action',
            'default_data' => ['label' => '', 'url' => '', 'variant' => 'primary', 'target' => '_self'],
            'validator' => function (array $data): array {
                $label = trim((string) ($data['label'] ?? ''));
                $url = trim((string) ($data['url'] ?? ''));
                if ($label === '') {
                    return ['valid' => false, 'data' => $data, 'errors' => ['Le libellé du bouton est obligatoire.']];
                }
                if (!$this->isSafeMediaOrUrl($url, true)) {
                    return ['valid' => false, 'data' => $data, 'errors' => ['URL de bouton invalide ou dangereuse.']];
                }
                $variant = in_array($data['variant'] ?? 'primary', ['primary', 'secondary', 'outline'], true)
                    ? (string) $data['variant']
                    : 'primary';
                $target = ($data['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                return ['valid' => true, 'data' => compact('label', 'url', 'variant', 'target'), 'errors' => []];
            },
            'renderer' => static function (array $data): string {
                $url = htmlspecialchars((string) ($data['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars((string) ($data['label'] ?? 'Action'), ENT_QUOTES, 'UTF-8');
                $variant = preg_replace('/[^a-z]/', '', (string) ($data['variant'] ?? 'primary'));
                $target = ($data['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
                return '<a class="evo-btn evo-btn-' . $variant . '" href="' . $url . '"' . $target . '>' . $label . '</a>';
            },
        ]);

        // 6. Colonnes
        $this->registerBlock([
            'type' => 'columns',
            'label' => 'Colonnes',
            'icon' => 'fa-solid fa-table-columns',
            'category' => 'layout',
            'default_data' => ['layout' => '2', 'columns' => [['content' => ''], ['content' => '']]],
            'validator' => function (array $data): array {
                $columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
                if (count($columns) < 1 || count($columns) > 4) {
                    return ['valid' => false, 'data' => $data, 'errors' => ['Un bloc colonnes accepte entre 1 et 4 colonnes.']];
                }
                $cleanCols = [];
                foreach ($columns as $col) {
                    if (is_array($col['blocks'] ?? null)) {
                        $nested = $this->validateComposition($col['blocks']);
                        if (!$nested['valid']) {
                            return ['valid' => false, 'data' => $data, 'errors' => $nested['errors']];
                        }
                        $cleanCols[] = ['blocks' => $nested['blocks']];
                    } else {
                        $cleanCols[] = ['content' => Sanitizer::cleanHtml((string) ($col['content'] ?? ''))];
                    }
                }
                $layout = (string) ($data['layout'] ?? (string) count($cleanCols));
                return ['valid' => true, 'data' => ['layout' => $layout, 'columns' => $cleanCols], 'errors' => []];
            },
            'renderer' => function (array $data, array $context): string {
                $columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
                $count = max(1, count($columns));
                $html = '<div class="evo-block-columns" style="display:grid;grid-template-columns:repeat(' . $count . ',1fr);gap:1.5rem;">';
                foreach ($columns as $col) {
                    $html .= '<div class="evo-block-column">';
                    if (!empty($col['blocks']) && is_array($col['blocks'])) {
                        $html .= $this->renderBlocks($col['blocks'], $context);
                    } else {
                        $html .= Sanitizer::cleanHtml((string) ($col['content'] ?? ''));
                    }
                    $html .= '</div>';
                }
                return $html . '</div>';
            },
        ]);

        // 7. Citation
        $this->registerBlock([
            'type' => 'quote',
            'label' => 'Citation',
            'icon' => 'fa-solid fa-quote-left',
            'category' => 'text',
            'default_data' => ['text' => '', 'author' => '', 'source' => ''],
            'validator' => static function (array $data): array {
                $text = trim((string) ($data['text'] ?? ''));
                if ($text === '') {
                    return ['valid' => false, 'data' => $data, 'errors' => ['Le texte de la citation est obligatoire.']];
                }
                return [
                    'valid' => true,
                    'data' => [
                        'text' => $text,
                        'author' => trim((string) ($data['author'] ?? '')),
                        'source' => trim((string) ($data['source'] ?? '')),
                    ],
                    'errors' => [],
                ];
            },
            'renderer' => static function (array $data): string {
                $html = '<blockquote class="evo-block-quote"><p>' . nl2br(htmlspecialchars((string) ($data['text'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
                $author = trim((string) ($data['author'] ?? ''));
                $source = trim((string) ($data['source'] ?? ''));
                if ($author !== '' || $source !== '') {
                    $html .= '<footer>' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8');
                    if ($source !== '') {
                        $html .= ' <cite>' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8') . '</cite>';
                    }
                    $html .= '</footer>';
                }
                return $html . '</blockquote>';
            },
        ]);

        // 8. Liste
        $this->registerBlock([
            'type' => 'list',
            'label' => 'Liste à puces ou numérotée',
            'icon' => 'fa-solid fa-list',
            'category' => 'text',
            'default_data' => ['style' => 'unordered', 'items' => []],
            'validator' => static function (array $data): array {
                $style = ($data['style'] ?? 'unordered') === 'ordered' ? 'ordered' : 'unordered';
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
                $items = [];
                foreach ($rawItems as $item) {
                    $txt = trim((string) $item);
                    if ($txt !== '') {
                        $items[] = $txt;
                    }
                }
                if (empty($items)) {
                    return ['valid' => false, 'data' => $data, 'errors' => ['La liste doit contenir au moins un élément.']];
                }
                return ['valid' => true, 'data' => ['style' => $style, 'items' => $items], 'errors' => []];
            },
            'renderer' => static function (array $data): string {
                $tag = ($data['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
                $html = '<' . $tag . ' class="evo-block-list">';
                foreach ($data['items'] ?? [] as $item) {
                    $html .= '<li>' . htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                return $html . '</' . $tag . '>';
            },
        ]);

        // 9. Contenu de plugin intégré
        $this->registerBlock([
            'type' => 'plugin_content',
            'label' => 'Contenu de plugin',
            'icon' => 'fa-solid fa-plug',
            'category' => 'dynamic',
            'default_data' => ['plugin' => '', 'mode' => 'latest', 'item_id' => '', 'limit' => 5],
            'validator' => static function (array $data): array {
                $plugin = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($data['plugin'] ?? ''));
                if ($plugin === '') {
                    return ['valid' => false, 'data' => $data, 'errors' => ['Le nom du plugin cible est obligatoire.']];
                }
                $limit = max(1, min(50, (int) ($data['limit'] ?? 5)));
                $mode = in_array($data['mode'] ?? 'latest', ['latest', 'single', 'list'], true) ? (string) $data['mode'] : 'latest';
                return [
                    'valid' => true,
                    'data' => [
                        'plugin' => $plugin,
                        'mode' => $mode,
                        'item_id' => trim((string) ($data['item_id'] ?? '')),
                        'limit' => $limit,
                    ],
                    'errors' => [],
                ];
            },
            'renderer' => function (array $data, array $context): string {
                return $this->renderPluginContentAdapter($data, $context);
            },
        ]);
    }

    private function renderPluginContentAdapter(array $data, array $context): string
    {
        $plugin = (string) ($data['plugin'] ?? '');
        if ($plugin === '') {
            return '';
        }

        // 1. Laisser d'abord les plugins fournir leur rendu via hook s'ils le souhaitent
        if (class_exists('\Core\Core')) {
            $hookRes = Core::getInstance()->callHook('blocks:render-plugin-content', [
                'plugin' => $plugin,
                'data' => $data,
                'context' => $context,
                'html' => '',
            ]);
            if (is_string($hookRes) && trim($hookRes) !== '') {
                return $hookRes;
            }
            if (is_array($hookRes) && !empty($hookRes['html']) && is_string($hookRes['html'])) {
                return $hookRes['html'];
            }
        }

        // 2. Fallback vers un adaptateur natif léger sur les données JSON standard des plugins cœur/officiels
        $limit = max(1, min(20, (int) ($data['limit'] ?? 5)));
        $dataFileMap = [
            'blog' => DATA_PLUGIN . 'blog' . DS . 'articles.json',
            'agenda' => DATA_PLUGIN . 'agenda' . DS . 'events.json',
            'docs' => DATA_PLUGIN . 'docs' . DS . 'docs.json',
            'galerie' => DATA_PLUGIN . 'galerie' . DS . 'items.json',
        ];
        if (isset($dataFileMap[$plugin]) && is_file($dataFileMap[$plugin])) {
            $decoded = json_decode((string) file_get_contents($dataFileMap[$plugin]), true);
            if (is_array($decoded) && !empty($decoded)) {
                $slice = array_slice(array_values($decoded), 0, $limit);
                $html = '<ul class="evo-block-plugin-list" data-plugin="' . htmlspecialchars($plugin, ENT_QUOTES, 'UTF-8') . '">';
                foreach ($slice as $row) {
                    $title = (string) ($row['title'] ?? $row['name'] ?? $row['label'] ?? ('#' . ($row['id'] ?? '')));
                    $html .= '<li>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                return $html . '</ul>';
            }
        }

        // 3. Fallback si un type de contenu personnalisé porte le même identifiant dans ContentTypeManager
        if (class_exists('\Core\Content\ContentTypeManager') && \Core\Content\ContentTypeManager::getInstance()->has($plugin)) {
            $entries = \Core\Content\ContentTypeManager::getInstance()->findEntries($plugin, [], ['created_at' => 'desc'], $limit);
            if (!empty($entries)) {
                $html = '<ul class="evo-block-plugin-list" data-content-type="' . htmlspecialchars($plugin, ENT_QUOTES, 'UTF-8') . '">';
                foreach ($entries as $entry) {
                    $html .= '<li>' . htmlspecialchars($entry->getTitle(), ENT_QUOTES, 'UTF-8') . '</li>';
                }
                return $html . '</ul>';
            }
        }

        return '<div class="evo-block-plugin-placeholder" data-plugin="' . htmlspecialchars($plugin, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars("Module intégré : {$plugin}", ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    private function ensureHookBlocksLoaded(): void
    {
        if ($this->hooksDispatched) {
            return;
        }
        $this->hooksDispatched = true;
        if (class_exists('\Core\Core')) {
            Core::getInstance()->callHook('blocks:register', $this);
        }
    }
}
