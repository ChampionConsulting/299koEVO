<div class="dc-error-wrap">
    <div class="dc-error-card">
        <div class="dc-error-icon tone-{{errTone|default('danger')}}" aria-hidden="true">
            <i class="{{errIcon|default('fa-solid fa-compass')}}"></i>
        </div>

        <div class="dc-error-badge tone-{{errTone|default('danger')}}">
            {{errBadge|default('ERROR')}}
        </div>

        <h1 class="dc-error-title">{{errTitle|default('Page not found')}}</h1>

        <p class="dc-error-msg">{{errMessage|default('The requested page is not available.')}}</p>

        <div class="dc-error-actions">
            <a href="{{homeUrl|default('/')}}" id="dcErrorHomeLink" class="dc-error-btn" style="color: #ffffff !important; background: var(--evo-accent, var(--ag-accent, #B83E22)) !important;">
                <i class="fa-solid fa-house" style="color: #ffffff !important;"></i>
                <span style="color: #ffffff !important;">Return to the site</span>
            </a>
        </div>

        <p class="dc-error-redirect" aria-live="polite">
            Automatic redirection to the homepage in<strong id="dcErrorCountdown">8</strong> seconds…
        </p>
    </div>
</div>

<script>
(function() {
    let remaining = 8;
    const el = document.getElementById('dcErrorCountdown');
    const link = document.getElementById('dcErrorHomeLink');
    const targetUrl = link ? link.getAttribute('href') : '/';
    const timer = setInterval(() => {
        remaining -= 1;
        if (el) el.textContent = String(Math.max(0, remaining));
        if (remaining <= 0) {
            clearInterval(timer);
            window.location.href = targetUrl;
        }
    }, 1000);
})();
</script>
