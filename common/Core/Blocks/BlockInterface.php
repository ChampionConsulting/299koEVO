<?php

namespace Core\Blocks;

defined('ROOT') or exit('Access denied!');

/**
 * Contrat d'un type de bloc éditorial enregistrable dans le cœur.
 */
interface BlockInterface
{
    public function getType(): string;

    public function getLabel(): string;

    public function getIcon(): string;

    public function getCategory(): string;

    public function getDefaultData(): array;

    /**
     * Valide et assainit les données du bloc avant persistance.
     *
     * @return array{valid:bool,data:array,errors:array<string>}
     */
    public function validate(array $data): array;

    /**
     * Génère le rendu HTML du bloc.
     */
    public function render(array $data, array $context = []): string;
}
