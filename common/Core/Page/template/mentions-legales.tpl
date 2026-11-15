<div class="legal-page-wrapper">
    <div class="legal-container">
        <header class="legal-header">
            <h1><i class="fa-solid fa-scale-balanced"></i> Mentions Légales</h1>
            <p class="legal-subtitle">Informations légales et politique de confidentialité de <?= htmlspecialchars($siteName ?? 'ce site') ?>.</p>
        </header>

        <section class="legal-section">
            <h2>1. Éditeur du site</h2>
            <p>Le site internet <strong><?= htmlspecialchars($siteName ?? '') ?></strong> est accessible à l'adresse suivante : <a href="<?= htmlspecialchars($siteUrl ?? '/') ?>"><?= htmlspecialchars($siteUrl ?? '') ?></a>.</p>
            <p>Il est édité et administré par l'équipe de <strong><?= htmlspecialchars($siteName ?? 'Déclic') ?></strong>.</p>
            <p><strong>Contact :</strong> Pour toute question ou réclamation, vous pouvez nous contacter via la page <a href="/contact">Contact</a> du site.</p>
        </section>

        <section class="legal-section">
            <h2>2. Hébergement</h2>
            <p>Le site est hébergé sur des infrastructures serveurs sécurisées assurant la disponibilité et la protection des données des utilisateurs.</p>
        </section>

        <section class="legal-section">
            <h2>3. Propriété intellectuelle</h2>
            <p>L'ensemble des contenus (textes, images, graphismes, logo, icônes, logiciels) présents sur ce site relève de la législation française et internationale sur le droit d'auteur et la propriété intellectuelle.</p>
            <p>Toute reproduction, distribution, modification, adaptation, retransmission ou publication de ces différents éléments est strictement interdite sans notre accord exprès par écrit.</p>
        </section>

        <section class="legal-section">
            <h2>4. Données personnelles (RGPD)</h2>
            <p>Conformément au Règlement Général sur la Protection des Données (RGPD) et à la loi « Informatique et Libertés », vous disposez d'un droit d'accès, de rectification, de portabilité et de suppression des données qui vous concernent.</p>
            <p>Les informations recueillies lors de votre inscription ou lors de la publication de messages sont utilisées exclusivement pour le bon fonctionnement des services communautaires du site et ne sont en aucun cas cédées ou vendues à des tiers.</p>
        </section>

        <section class="legal-section">
            <h2>5. Cookies et traceurs</h2>
            <p>Ce site utilise des cookies de session strictement nécessaires à son fonctionnement technique (maintien de la connexion, sécurité CSRF) et au confort de navigation de ses membres.</p>
        </section>
    </div>
</div>

<style>
.legal-page-wrapper {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1.5rem;
    color: inherit;
    line-height: 1.65;
}
.legal-container {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 2.5rem;
    backdrop-filter: blur(10px);
}
.legal-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 1.5rem;
    margin-bottom: 2rem;
}
.legal-header h1 {
    margin: 0 0 0.5rem;
    font-size: 1.8rem;
    font-weight: 800;
}
.legal-subtitle {
    margin: 0;
    opacity: 0.7;
    font-size: 0.95rem;
}
.legal-section {
    margin-bottom: 2rem;
}
.legal-section h2 {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 0.75rem;
    color: var(--sx-primary, #3b82f6);
}
.legal-section p {
    margin: 0 0 0.75rem;
    font-size: 0.95rem;
    opacity: 0.85;
}
.legal-section a {
    color: var(--sx-primary, #3b82f6);
    text-decoration: none;
}
.legal-section a:hover {
    text-decoration: underline;
}
</style>

