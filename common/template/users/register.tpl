{% INCLUDE COMMON ~ "template/users/auth-assets.tpl" %}
<section class="user-auth-form auth-card">
    <form method="post" action="{{registerLink}}" autocomplete="off">
        <input type="hidden" name="_csrf" value="{{_csrfToken}}" class="csrf-hidden">
        <input type="text" name="_hp_website" value="" class="hp-field" aria-hidden="true" tabindex="-1" autocomplete="off" style="display:none; position:absolute; left:-9999px; visibility:hidden; pointer-events:none;" rel="nofollow">

        <p class="field">
            <label for="register-username">{{Lang.users-registration-username}}</label>
            <input id="register-username" type="text" name="username" required minlength="3" maxlength="60" autocomplete="username" placeholder="ex: username">
            <span class="field-validation" id="username-validation"></span>
        </p>

        <p class="field">
            <label for="register-email">{{Lang.users-registration-email}}</label>
            <input id="register-email" type="email" name="email" required autocomplete="email">
            <span class="field-validation" id="email-validation"></span>
        </p>

        <p class="field">
            <label for="register-password">{{Lang.users-registration-password}}</label>
            <div class="password-input-wrapper">
                <input id="register-password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <button type="button" class="toggle-password" data-target="register-password" aria-label="Show/Hide password" title="Show/Hide password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <div class="password-requirements" id="password-requirements">
                <small>The password must contain:</small>
                <ul>
                    <li id="req-length" class="requirement">At least 8 characters</li>
                </ul>
            </div>
            <span class="field-validation" id="password-validation"></span>
        </p>

        <p class="field">
            <label for="register-password-confirm">{{Lang.users-registration-password-confirm}}</label>
            <div class="password-input-wrapper">
                <input id="register-password-confirm" type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
                <button type="button" class="toggle-password" data-target="register-password-confirm" aria-label="Show/Hide password" title="Show/Hide password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <span class="field-validation" id="password-confirm-validation"></span>
        </p>

        {% if antispam %}
            <div class="field antispam-wrapper">
                {{antispamField|raw}}
            </div>
        {% endif %}

        <p class="actions">
            <button type="submit" class="button success">{{Lang.users-registration-submit}}</button>
        </p>
    </form>
</section>



