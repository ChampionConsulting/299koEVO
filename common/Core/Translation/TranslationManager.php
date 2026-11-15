<?php

namespace Core\Translation;

use Core\Auth\User;
use Core\Content\ContentEntry;
use Core\Content\ContentTypeDefinition;
use Core\Content\ContentTypeManager;
use Core\Core;
use Core\Lang;
use Core\Page\PageItem;

defined('ROOT') or exit('Access denied!');

/**
 * Gestionnaire central des contenus multilingues (groupes de traduction,
 * champs traduisibles vs communs, fallback explicite, URL préfixées et hreflang).
 */
class TranslationManager
{
    private static ?self $instance = null;

    private bool $syncing = false;

    private const RESERVED_PREFIXES = [
        'admin',
        'api',
        'users',
        'page',
        'theme',
        'common',
        'plugin',
        'upload',
        'mentions-legales',
        'pro',
        'comment-revendiquer',
    ];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return string[]
     */
    public function getEnabledLocales(): array
    {
        $configured = class_exists('\Core\Core') ? Core::getInstance()->getConfigVal('locales_enabled') : false;
        if (is_array($configured) && !empty($configured)) {
            $clean = array_values(array_unique(array_filter(array_map(
                static fn($l): string => strtolower(trim((string) $l)),
                $configured
            ))));
            if (!empty($clean)) {
                return $clean;
            }
        }

        $avail = class_exists('\Core\Lang') ? array_keys(Lang::$availablesLocales) : [];
        if (!empty($avail)) {
            return $avail;
        }
        return ['fr', 'en', 'de'];
    }

    public function isEnabledLocale(string $locale): bool
    {
        return in_array(strtolower(trim($locale)), $this->getEnabledLocales(), true);
    }

    public function getDefaultLocale(): string
    {
        $lang = class_exists('\Core\Core') ? (string) (Core::getInstance()->getConfigVal('siteLang') ?: 'fr') : 'fr';
        return strtolower(trim($lang)) ?: 'fr';
    }

    public function isFallbackEnabled(): bool
    {
        if (!class_exists('\Core\Core')) {
            return true;
        }
        $val = Core::getInstance()->getConfigVal('translation_fallback');
        return $val === false ? true : (bool) $val;
    }

    public function getFallbackLocale(): string
    {
        $fallback = class_exists('\Core\Core') ? Core::getInstance()->getConfigVal('fallback_locale') : false;
        if (is_string($fallback) && trim($fallback) !== '') {
            return strtolower(trim($fallback));
        }
        return $this->getDefaultLocale();
    }

    public function registerTranslation(
        string $entityType,
        int|string $entityId,
        string $locale,
        ?string $group = null
    ): string {
        $loc = strtolower(trim($locale));
        $idStr = (string) $entityId;
        if ($loc === '' || $idStr === '') {
            return (string) $group;
        }

        $existingForEntity = TranslationLink::queryBuilder()
            ->where('entity_type', '=', $entityType)
            ->where('entity_id', '=', $idStr)
            ->first();

        $resolvedGroup = trim((string) $group);
        if ($resolvedGroup === '' && $existingForEntity instanceof TranslationLink) {
            $resolvedGroup = $existingForEntity->getGroup();
        }
        if ($resolvedGroup === '') {
            $resolvedGroup = 'tr_' . bin2hex(random_bytes(6));
        }

        // S'il existe déjà un lien pour le même (group, entity_type, locale) sur un autre ID, le mettre à jour
        $sameLocale = TranslationLink::queryBuilder()
            ->where('translation_group', '=', $resolvedGroup)
            ->where('entity_type', '=', $entityType)
            ->where('locale', '=', $loc)
            ->first();

        $target = $existingForEntity instanceof TranslationLink
            ? $existingForEntity
            : ($sameLocale instanceof TranslationLink ? $sameLocale : new TranslationLink());

        $target->attributes['translation_group'] = $resolvedGroup;
        $target->attributes['entity_type'] = $entityType;
        $target->attributes['entity_id'] = $idStr;
        $target->attributes['locale'] = $loc;
        $target->attributes['is_default'] = $loc === $this->getDefaultLocale() ? 1 : 0;
        if (empty($target->attributes['created_at'])) {
            $target->attributes['created_at'] = date('Y-m-d H:i:s');
        }
        $target->save();

        return $resolvedGroup;
    }

    /**
     * Lie plusieurs entités existantes sous un même groupe de traduction.
     *
     * @param array<string,int|string> $localeToEntityId Ex: ['fr' => 1, 'en' => 2]
     */
    public function linkEntities(string $entityType, array $localeToEntityId, ?string $group = null): string
    {
        $resolvedGroup = trim((string) $group) ?: ('tr_' . bin2hex(random_bytes(6)));
        foreach ($localeToEntityId as $locale => $entityId) {
            $this->registerTranslation($entityType, (string) $entityId, (string) $locale, $resolvedGroup);
            if ($entityType === 'page') {
                $p = PageItem::findPK((string) $entityId);
                if ($p instanceof PageItem) {
                    $p->setLocale((string) $locale);
                    $p->setTranslationGroup($resolvedGroup);
                    $p->save();
                }
            } elseif (str_starts_with($entityType, 'content:')) {
                $e = ContentEntry::findPK((string) $entityId);
                if ($e instanceof ContentEntry) {
                    $e->attributes['locale'] = strtolower(trim((string) $locale));
                    $e->attributes['translation_group'] = $resolvedGroup;
                    $e->save();
                }
            }
        }
        return $resolvedGroup;
    }

    /**
     * @return array<string,TranslationLink> Indexé par locale
     */
    public function getTranslations(string $entityType, string $group): array
    {
        if (trim($group) === '') {
            return [];
        }
        /** @var TranslationLink[] $rows */
        $rows = TranslationLink::queryBuilder()
            ->where('entity_type', '=', $entityType)
            ->where('translation_group', '=', $group)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->getLocale()] = $row;
        }
        return $map;
    }

    /**
     * @return array<string,TranslationLink>
     */
    public function getTranslationsForEntity(string $entityType, int|string $entityId): array
    {
        $link = TranslationLink::queryBuilder()
            ->where('entity_type', '=', $entityType)
            ->where('entity_id', '=', (string) $entityId)
            ->first();

        if (!$link instanceof TranslationLink) {
            return [];
        }
        return $this->getTranslations($entityType, $link->getGroup());
    }

    /**
     * Synchronise les champs communs (translatable === false) entre toutes les variantes linguistiques
     * d'un groupe de traduction ContentEntry sans modifier les champs traduisibles.
     */
    public function syncCommonFields(ContentTypeDefinition $type, ContentEntry $sourceEntry): void
    {
        $group = $sourceEntry->getTranslationGroup();
        if ($this->syncing || $group === '') {
            return;
        }

        $sharedValues = [];
        $sourceFields = $sourceEntry->getFields();
        foreach ($type->getFields() as $field) {
            if (!$field->translatable && array_key_exists($field->name, $sourceFields)) {
                $sharedValues[$field->name] = $sourceFields[$field->name];
            }
        }
        if (empty($sharedValues)) {
            return;
        }

        $this->syncing = true;
        try {
            $links = $this->getTranslations('content:' . $type->id, $group);
            foreach ($links as $link) {
                $siblingId = $link->getEntityId();
                if ($siblingId === (string) $sourceEntry->getId()) {
                    continue;
                }
                $sibling = ContentEntry::findPK($siblingId);
                if (!$sibling instanceof ContentEntry) {
                    continue;
                }
                $merged = array_merge($sibling->getFields(), $sharedValues);
                $sibling->setFields($merged);
                $sibling->attributes['updated_at'] = date('Y-m-d H:i:s');
                $sibling->save();
            }
        } finally {
            $this->syncing = false;
        }
    }

    public function resolveContentEntry(
        string $typeId,
        int|string $entryIdOrGroup,
        ?string $requestedLocale = null,
        ?User $user = null
    ): ResolvedTranslation {
        $reqLoc = strtolower(trim($requestedLocale ?: Core::getInstance()->getLocale() ?: $this->getDefaultLocale()));
        $entityType = 'content:' . $typeId;
        $ctm = ContentTypeManager::getInstance();

        $group = '';
        $directEntry = ContentEntry::findPK((string) $entryIdOrGroup);
        if ($directEntry instanceof ContentEntry && $directEntry->getContentType() === $typeId) {
            $group = $directEntry->getTranslationGroup();
        } elseif (is_string($entryIdOrGroup) && str_starts_with($entryIdOrGroup, 'tr_')) {
            $group = $entryIdOrGroup;
        }

        if ($group === '' && $directEntry instanceof ContentEntry) {
            $links = $this->getTranslationsForEntity($entityType, $directEntry->getId());
            foreach ($links as $link) {
                $group = $link->getGroup();
                break;
            }
        } else {
            $links = $this->getTranslations($entityType, $group);
        }

        $availableMap = [];
        foreach ($links as $loc => $link) {
            $availableMap[$loc] = $link->getEntityId();
        }

        // 1. Correspondance exacte de la langue demandée
        if (isset($availableMap[$reqLoc])) {
            $exact = $ctm->getEntry($typeId, $availableMap[$reqLoc], $user);
            if ($exact !== null) {
                return new ResolvedTranslation($exact, $reqLoc, $reqLoc, false, $group, $availableMap);
            }
        } elseif ($directEntry !== null && strtolower($directEntry->getLocale()) === $reqLoc) {
            $visibleDirect = $ctm->getEntry($typeId, $directEntry->getId(), $user);
            if ($visibleDirect !== null) {
                return new ResolvedTranslation($visibleDirect, $reqLoc, $reqLoc, false, $group, $availableMap);
            }
        }

        // 2. Fallback configurable avec signalement explicite
        if (!$this->isFallbackEnabled()) {
            return new ResolvedTranslation(null, $reqLoc, $reqLoc, false, $group, $availableMap);
        }

        $candidates = array_values(array_unique(array_merge(
            [$this->getFallbackLocale(), $this->getDefaultLocale()],
            array_keys($availableMap)
        )));

        foreach ($candidates as $candLoc) {
            if (!isset($availableMap[$candLoc])) {
                continue;
            }
            $candEntry = $ctm->getEntry($typeId, $availableMap[$candLoc], $user);
            if ($candEntry !== null) {
                return new ResolvedTranslation($candEntry, $reqLoc, $candLoc, $candLoc !== $reqLoc, $group, $availableMap);
            }
        }

        if ($directEntry !== null) {
            $visibleDirect = $ctm->getEntry($typeId, $directEntry->getId(), $user);
            if ($visibleDirect !== null) {
                $actLoc = strtolower($visibleDirect->getLocale() ?: $this->getDefaultLocale());
                return new ResolvedTranslation($visibleDirect, $reqLoc, $actLoc, $actLoc !== $reqLoc, $group, $availableMap);
            }
        }

        return new ResolvedTranslation(null, $reqLoc, $reqLoc, false, $group, $availableMap);
    }

    public function resolvePageItem(int|string $pageIdOrGroup, ?string $requestedLocale = null): ResolvedTranslation
    {
        $reqLoc = strtolower(trim($requestedLocale ?: Core::getInstance()->getLocale() ?: $this->getDefaultLocale()));
        $direct = PageItem::findPK((string) $pageIdOrGroup);
        $group = '';
        if ($direct instanceof PageItem) {
            $group = $direct->getTranslationGroup();
        } elseif (is_string($pageIdOrGroup) && str_starts_with($pageIdOrGroup, 'tr_')) {
            $group = $pageIdOrGroup;
        }

        $links = $group !== ''
            ? $this->getTranslations('page', $group)
            : ($direct instanceof PageItem ? $this->getTranslationsForEntity('page', (string) $direct->getId()) : []);

        $availableMap = [];
        foreach ($links as $loc => $link) {
            $availableMap[$loc] = $link->getEntityId();
        }

        if (isset($availableMap[$reqLoc])) {
            $p = PageItem::findPK($availableMap[$reqLoc]);
            if ($p instanceof PageItem && $p->isPublished()) {
                return new ResolvedTranslation($p, $reqLoc, $reqLoc, false, $group, $availableMap);
            }
        } elseif ($direct instanceof PageItem && $direct->isPublished() && ($direct->getLocale() === '' || strtolower($direct->getLocale()) === $reqLoc)) {
            return new ResolvedTranslation($direct, $reqLoc, $direct->getLocale() ?: $reqLoc, false, $group, $availableMap);
        }

        if (!$this->isFallbackEnabled()) {
            return new ResolvedTranslation(null, $reqLoc, $reqLoc, false, $group, $availableMap);
        }

        $candidates = array_values(array_unique(array_merge(
            [$this->getFallbackLocale(), $this->getDefaultLocale()],
            array_keys($availableMap)
        )));
        foreach ($candidates as $candLoc) {
            if (!isset($availableMap[$candLoc])) {
                continue;
            }
            $p = PageItem::findPK($availableMap[$candLoc]);
            if ($p instanceof PageItem && $p->isPublished()) {
                return new ResolvedTranslation($p, $reqLoc, $candLoc, $candLoc !== $reqLoc, $group, $availableMap);
            }
        }

        if ($direct instanceof PageItem && $direct->isPublished()) {
            $actLoc = strtolower($direct->getLocale() ?: $this->getDefaultLocale());
            return new ResolvedTranslation($direct, $reqLoc, $actLoc, $actLoc !== $reqLoc, $group, $availableMap);
        }

        return new ResolvedTranslation(null, $reqLoc, $reqLoc, false, $group, $availableMap);
    }

    /**
     * Analyse une URI pour extraire un préfixe linguistique (/en/...) sans collisions avec les modules cœur ou plugins.
     *
     * @return array{locale:?string,path:string}
     */
    public function extractLocaleFromPath(string $uri): array
    {
        $parsedPath = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $trimmed = trim($parsedPath, '/');
        if ($trimmed === '') {
            return ['locale' => null, 'path' => '/'];
        }

        $parts = explode('/', $trimmed, 2);
        $first = strtolower($parts[0]);

        if (in_array($first, self::RESERVED_PREFIXES, true) || !$this->isEnabledLocale($first)) {
            return ['locale' => null, 'path' => $parsedPath];
        }

        // Ne pas intercepter un plugin existant portant le même identifiant court
        if (is_dir(PLUGINS . $first)) {
            return ['locale' => null, 'path' => $parsedPath];
        }

        $rest = isset($parts[1]) && $parts[1] !== '' ? '/' . ltrim($parts[1], '/') : '/';
        return ['locale' => $first, 'path' => $rest];
    }

    public function buildLocalizedUrl(string $path, ?string $locale = null, bool $alwaysPrefixDefault = false): string
    {
        $extracted = $this->extractLocaleFromPath($path);
        $cleanPath = '/' . ltrim($extracted['path'], '/');
        $targetLocale = strtolower(trim($locale ?: $this->getDefaultLocale()));
        $defaultLocale = $this->getDefaultLocale();

        if (!$alwaysPrefixDefault && $targetLocale === $defaultLocale) {
            return $cleanPath === '/' ? '/' : $cleanPath;
        }
        return '/' . $targetLocale . ($cleanPath === '/' ? '/' : $cleanPath);
    }

    public function renderHreflangTags(string $entityType, int|string $entityId, callable $urlResolver): string
    {
        $translations = $this->getTranslationsForEntity($entityType, $entityId);
        if (empty($translations)) {
            return '';
        }

        $tags = [];
        $defaultUrl = null;
        $defaultLocale = $this->getDefaultLocale();

        foreach ($translations as $locale => $link) {
            $url = (string) call_user_func($urlResolver, $locale, $link->getEntityId());
            if ($url === '') {
                continue;
            }
            $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $escapedLoc = htmlspecialchars($locale, ENT_QUOTES, 'UTF-8');
            $tags[] = '<link rel="alternate" hreflang="' . $escapedLoc . '" href="' . $escapedUrl . '" />';
            if ($locale === $defaultLocale || $defaultUrl === null) {
                $defaultUrl = $escapedUrl;
            }
        }

        if ($defaultUrl !== null) {
            $tags[] = '<link rel="alternate" hreflang="x-default" href="' . $defaultUrl . '" />';
        }

        return implode("\n", $tags);
    }

    public function renderCanonicalTag(string $url): string
    {
        $clean = trim($url);
        if ($clean === '') {
            return '';
        }
        return '<link rel="canonical" href="' . htmlspecialchars($clean, ENT_QUOTES, 'UTF-8') . '" />';
    }

    /**
     * Supprime le(s) lien(s) de traduction d'une entité et réassigne `is_default` si la version par défaut est supprimée.
     */
    public function removeEntityTranslations(string $entityType, int|string $entityId): void
    {
        $idStr = (string) $entityId;
        if ($idStr === '') {
            return;
        }
        /** @var TranslationLink[] $links */
        $links = TranslationLink::queryBuilder()
            ->where('entity_type', '=', $entityType)
            ->where('entity_id', '=', $idStr)
            ->get();

        if (empty($links)) {
            return;
        }

        $affectedGroups = [];
        foreach ($links as $link) {
            $group = $link->getGroup();
            $wasDefault = $link->isDefault();
            $link->delete();
            if ($wasDefault && $group !== '') {
                $affectedGroups[$group] = true;
            }
        }

        foreach (array_keys($affectedGroups) as $group) {
            $remaining = $this->getTranslations($entityType, $group);
            if (!empty($remaining)) {
                $hasDefault = false;
                foreach ($remaining as $remLink) {
                    if ($remLink->isDefault()) {
                        $hasDefault = true;
                        break;
                    }
                }
                if (!$hasDefault) {
                    $first = reset($remaining);
                    if ($first instanceof TranslationLink) {
                        $first->attributes['is_default'] = 1;
                        $first->save();
                    }
                }
            }
        }
    }
}
