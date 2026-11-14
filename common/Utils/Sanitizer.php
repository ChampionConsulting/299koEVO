<?php

namespace Utils;

defined('ROOT') or exit('Access denied!');

/**
 * Classe utilitaire pour nettoyer les entrées HTML (Anti-XSS léger)
 * Remplace HTMLPurifier qui est absent.
 */
class Sanitizer
{
    /**
     * Tags autorisés pour TinyMCE / Contenu riche
     */
    private const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><s><blockquote><a><ul><ol><li><h1><h2><h3><h4><h5><h6><img><span><div><table><thead><tbody><tr><th><td>';

    /**
     * Nettoie une chaîne HTML
     * 
     * @param string $html Contenu à nettoyer
     * @return string Contenu nettoyé
     */
    public static function cleanHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Supprimer les balises dangereuses avec leur contenu avant strip_tags
        $clean = preg_replace('/<(script|style|iframe|object|embed|applet|svg|math|form)\b[^>]*>.*?<\/\1>/is', '', $html);
        $clean = preg_replace('/<(script|style|iframe|object|embed|applet|svg|math|form|meta|link|base)\b[^>]*\/?>/is', '', (string)$clean);

        // 2. strip_tags pour ne garder que les balises safe
        $clean = strip_tags((string)$clean, self::ALLOWED_TAGS);

        // 3. Supprimer les attributs d'événements on* (avec ou sans quotes)
        $clean = preg_replace('/\s+on[a-z0-9_]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);

        // 4. Neutraliser les schémas dangereux (javascript:, vbscript:, data: hors images raster base64)
        $clean = preg_replace_callback(
            '/\b(href|src|action|formaction|xlink:href|poster)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
            function (array $m): string {
                $attr = $m[1];
                $val = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : ($m[4] ?? ''));
                $decoded = html_entity_decode(preg_replace('/[\x00-\x20\x7f]+/', '', $val), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('/^(javascript|vbscript):/i', $decoded)) {
                    return $attr . '="#"';
                }
                if (preg_match('/^data:/i', $decoded) && !preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $decoded)) {
                    return $attr . '="#"';
                }
                return $m[0];
            },
            $clean
        );
        $clean = preg_replace('/\s+style\s*=\s*(?:"[^"]*(?:expression|javascript:|vbscript:|url\s*\(\s*["\']?\s*javascript:)[^"]*"|\'[^\']*(?:expression|javascript:|vbscript:|url\s*\(\s*["\']?\s*javascript:)[^\']*\')/i', '', (string)$clean);

        return (string)$clean;
    }

    /**
     * Neutralise les vecteurs XSS actifs tout en préservant le balisage HTML éditorial légitime.
     */
    public static function stripXssVectors(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $clean = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $clean = preg_replace('/<script\b[^>]*\/?>/is', '', (string)$clean);
        $clean = preg_replace(
            '/\s+on(?:error|load|mouseover|mouseenter|focus|blur|animationstart|animationend|transitionend|toggle|wheel|pointerover|beforescriptexecute)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            '',
            (string)$clean
        );
        $clean = preg_replace('/\b(href|src)\s*=\s*(["\']?)\s*(?:javascript|vbscript):[^"\'\s>]*/i', '$1=$2#', (string)$clean);

        return (string)$clean;
    }

    /**
     * Nettoie une chaîne texte simple (pas de HTML)
     */
    public static function cleanText(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
