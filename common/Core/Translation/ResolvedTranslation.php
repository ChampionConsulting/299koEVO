<?php

namespace Core\Translation;

defined('ROOT') or exit('Access denied!');

/**
 * Résultat explicite de résolution multilingue (expose le recours éventuel au fallback).
 */
class ResolvedTranslation
{
    public function __construct(
        public readonly mixed $entity,
        public readonly string $requestedLocale,
        public readonly string $actualLocale,
        public readonly bool $isFallback,
        public readonly string $translationGroup,
        public readonly array $availableLocales = []
    ) {
    }

    public function found(): bool
    {
        return $this->entity !== null;
    }

    public function toMetaArray(): array
    {
        return [
            'requested_locale' => $this->requestedLocale,
            'actual_locale' => $this->actualLocale,
            'is_fallback' => $this->isFallback,
            'translation_group' => $this->translationGroup,
            'available_locales' => array_keys($this->availableLocales),
        ];
    }
}
