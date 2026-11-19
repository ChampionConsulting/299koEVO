/* theme-toggle.js
   - gère 3 états : 'auto' (suivre le système), 'dark', 'light'
   - persiste le choix dans localStorage sous la clé 'user-theme'
   - applique une classe sur <body> : theme-dark ou theme-light (ou aucune classe pour 'auto')
   - réagit aux changements de préférence système quand l'état est 'auto'
*/

(function () {
    const THEME_KEY = 'theme_preference'; // clé localStorage compatible avec l'existant
    // IDs utilisés dans le layout (à adapter si besoin)
    let toggleBtn = null;
    const mq = window.matchMedia('(prefers-color-scheme: dark)');

    // Applique l'état sur le DOM (ajoute/enlève classes sur <body> et <html>)
    function applyTheme(theme) {
        const body = document.body;
        const html = document.documentElement;
        if (!body) return;

        // Retire toutes classes "forcées"
        body.classList.remove('theme-dark', 'theme-light');
        if (html) {
            html.classList.remove('theme-dark', 'theme-light');
        }

        if (theme === 'dark') {
            body.classList.add('theme-dark');
            if (html) {
                html.classList.add('theme-dark');
                html.setAttribute('data-theme', 'dark');
            }
        } else if (theme === 'light') {
            body.classList.add('theme-light');
            if (html) {
                html.classList.add('theme-light');
                html.setAttribute('data-theme', 'light');
            }
        } else {
            // 'auto'
            const prefersDark = mq.matches;
            if (html) {
                html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
                html.classList.add(prefersDark ? 'theme-dark' : 'theme-light');
            }
            body.classList.add(prefersDark ? 'theme-dark' : 'theme-light');
        }
        updateToggleUI(theme);
    }

    // Met à jour l'UI du bouton pour indiquer l'état
    function updateToggleUI(theme) {
        if (!toggleBtn) return;

        // Gestion checkbox (si c'est un input type checkbox comme dans profile.tpl)
        if (toggleBtn.type === 'checkbox') {
            const isDark = (theme === 'dark') || (theme === 'auto' && mq.matches);
            toggleBtn.checked = isDark;
            return;
        }

        // Gestion bouton simple (si c'est un <button> ou <a>)
        toggleBtn.setAttribute('aria-pressed', String(theme !== 'auto'));
        toggleBtn.dataset.state = theme;

        const effectiveDark = (theme === 'dark') || (theme === 'auto' && mq.matches);

        // Optionnel : icône/texte si existent
        const themeIcon = document.getElementById('theme-icon');
        const themeLabel = document.getElementById('theme-label');
        if (themeIcon) {
            themeIcon.textContent = (theme === 'dark') ? '🌙' : (theme === 'light') ? '☀️' : (effectiveDark ? '🌙' : '☀️');
        }
        if (themeLabel) {
            themeLabel.textContent = (theme === 'dark') ? 'Sombre' : (theme === 'light') ? 'Clair' : 'Auto';
        }
        toggleBtn.setAttribute('title', 'Mode : ' + (theme === 'dark' ? 'Sombre' : (theme === 'light' ? 'Clair' : 'Auto')) + ' (Cliquer pour changer)');
    }

    // Sauvegarde la préférence et l'applique
    function setTheme(theme) {
        localStorage.setItem(THEME_KEY, theme);
        document.cookie = "theme=" + theme + "; path=/; max-age=31536000; samesite=lax"; // Sync cookie pour PHP
        applyTheme(theme);

        // Appel AJAX optionnel pour sauvegarder en BDD si logué (gardé pour compatibilité)
        if (window.saveThemePreference && typeof window.saveThemePreference === 'function') {
            window.saveThemePreference(theme);
        }
    }

    // Lecture initiale au chargement
    function init() {
        toggleBtn = document.getElementById('theme-toggle-btn') || document.getElementById('theme-toggle');

        const saved = localStorage.getItem(THEME_KEY) || 'auto';
        applyTheme(saved);

        // Si l'utilisateur est en 'auto', on veut écouter les changements système
        mq.addEventListener('change', () => {
            const current = localStorage.getItem(THEME_KEY) || 'auto';
            if (current === 'auto') {
                applyTheme('auto'); // déclenche relecture via CSS :prefers-color-scheme
            }
        });

        // Gestion du clic
        if (toggleBtn) {
            if (toggleBtn.tagName === 'INPUT' && toggleBtn.type === 'checkbox') {
                // Checkbox logic: toggle between dark and light (skip auto for simplicity in checkbox UI)
                toggleBtn.addEventListener('change', () => {
                    const next = toggleBtn.checked ? 'dark' : 'light';
                    setTheme(next);
                });
            } else {
                // Button logic: cycle auto -> dark -> light -> auto
                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const cur = localStorage.getItem(THEME_KEY) || 'auto';
                    const next = (cur === 'auto') ? 'dark' : (cur === 'dark') ? 'light' : 'auto';
                    setTheme(next);
                });
            }
        }
    }

    // Exécution initiale
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
