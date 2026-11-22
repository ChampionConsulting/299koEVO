{% INCLUDE COMMON ~ "template/users/auth-assets.tpl" %}
<section class="user-profile-edit">
    <div class="profile-edit-header" style="margin-bottom: 1.5rem;">
        <a href="{{ ROUTER.generate("profile") }}" class="button secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to profile
        </a>
    </div>

    <!-- BARRE DE NAVIGATION PAR ONGLETS -->
    <div class="profile-tabs-nav">
        <button type="button" class="profile-tab-btn active" data-tab="tab-account">
            <i class="fa-solid fa-user-gear"></i> Account information
        </button>
        <button type="button" class="profile-tab-btn" data-tab="tab-photo">
            <i class="fa-solid fa-camera"></i> Profile picture
        </button>
        <button type="button" class="profile-tab-btn" data-tab="tab-public">
            <i class="fa-solid fa-id-badge"></i> Public Profile & Networks
        </button>
    </div>

    <!-- CONTENEUR DES ONGLETS -->
    <div class="profile-tabs-content">
        <!-- 1. ONGLET PHOTO DE PROFIL -->
        <div class="profile-tab-pane" id="tab-photo" style="display: none;">
            <div class="user-auth-form user-profile-photo-container">
                <h2>Profile picture</h2>
                {% IF hasProfilePhoto %}
                <div class="profile-photo-preview">
                    <img src="{{profilePhotoUrl}}" alt="Profile picture">
                </div>
                <form method="post" action="{{profilePhotoDeleteLink}}" class="profile-photo-actions">
                    <input type="hidden" name="_csrf" value="{{_csrfToken}}">
                    <button type="submit" class="button alert">
                        <i class="fa-solid fa-trash"></i> Delete the photo
                    </button>
                </form>
                {% ENDIF %}
                
                <form method="post" action="{{profilePhotoUploadLink}}" enctype="multipart/form-data" class="profile-photo-upload-form">
                    <input type="hidden" name="_csrf" value="{{_csrfToken}}">
                    <p>
                        <label for="profile-photo-input">{% IF hasProfilePhoto %}Replace la photo{% ELSE %}Add a photo{% ENDIF %}</label>
                        <input id="profile-photo-input" type="file" name="profile_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" required>
                        <small class="form-help">Accepted formats: JPG, PNG, GIF, WebP. Maximum size: 5 MB. The image will be resized to a maximum of 500x500px.</small>
                    </p>
                    <p>
                        <button type="submit" class="button success">
                            <i class="fa-solid fa-upload"></i> {% IF hasProfilePhoto %}Replace{% ELSE %}Download{% ENDIF %}
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <!-- FORMULAIRE COMPTE & PROFIL PUBLIC -->
        <form method="post" action="{{profileSaveLink}}" autocomplete="off" id="profile-main-form">
            <input type="hidden" name="_csrf" value="{{_csrfToken}}">

            <!-- 2. ONGLET COMPTE & IDENTITÉ & MOT DE PASSE -->
            <div class="profile-tab-pane active" id="tab-account">
                <div class="user-auth-form">
                    <h2>Account information</h2>

                    <p>
                        <label for="profile-username">{{Lang.users-registration-username}}</label>
                        <input id="profile-username" type="text" name="username" value="{{user.username}}" required minlength="3" maxlength="60">
                    </p>

                    <p>
                        <label for="profile-firstname">First Name </label>
                        <input id="profile-firstname" type="text" name="firstname" value="{{firstname}}" maxlength="60" placeholder="first name">
                    </p>

                    <p>
                        <label for="profile-lastname">Last Name</label>
                        <input id="profile-lastname" type="text" name="lastname" value="{{lastname}}" maxlength="60" placeholder="last name">
                    </p>

                    <p>
                        <label for="profile-email">{{Lang.users-registration-email}}</label>
                        <input id="profile-email" type="email" name="email" value="{{user.email}}" required>
                    </p>

                    <div style="margin-top: 2rem; border-top: 1px solid var(--border-color, #eee); padding-top: 1.5rem;">
                        <h3>{{Lang.users-registration-password}}</h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                            Leave blank to keep your current password.
                        </p>
                        
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="profile-password">{{Lang.users-registration-password}}</label>
                            <div class="password-input-wrapper">
                                <input id="profile-password" type="password" name="password" autocomplete="new-password">
                                <button type="button" class="toggle-password" aria-label="Show/Hide password" title="Show/Hide password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="profile-password-confirm">{{Lang.users-registration-password-confirm}}</label>
                            <div class="password-input-wrapper">
                                <input id="profile-password-confirm" type="password" name="password_confirm" autocomplete="new-password">
                                <button type="button" class="toggle-password" aria-label="Show/Hide password" title="Show/Hide password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 2rem; border-top: 1px solid var(--border-color, #eee); padding-top: 1rem;">
                        <button type="submit" class="button success">{{Lang.submit}}</button>
                        <a href="{{ ROUTER.generate("profile") }}" class="button secondary" style="margin-left: 0.5rem;">Cancel</a>
                    </div>
                </div>
            </div>

            <!-- 3. ONGLET PROFIL SOCIAL & PUBLIC -->
            <div class="profile-tab-pane" id="tab-public" style="display: none;">
                <div class="user-auth-form">
                    <h2>Social Profile and Public Information</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                        This information is visible on your public community profile.
                    </p>

                    <div class="dynamic-profile-fields">
                        {% HOOK.user:profile-edit-dynamic-fields(user) %}
                    </div>

                    <div style="margin-top: 2rem; border-top: 1px solid var(--border-color, #eee); padding-top: 1rem;">
                        <button type="submit" class="button success">{{Lang.submit}}</button>
                        <a href="{{ ROUTER.generate("profile") }}" class="button secondary" style="margin-left: 0.5rem;">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<style>
.profile-tabs-nav {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-color, rgba(255, 255, 255, 0.1));
    padding-bottom: 0.25rem;
    flex-wrap: wrap;
}

.profile-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border: none;
    background: transparent;
    color: var(--text-muted, #94a3b8);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
    transition: all 0.2s ease;
    border-bottom: 3px solid transparent;
    margin-bottom: -4px;
}

.profile-tab-btn:hover {
    color: var(--text-primary, #fff);
    background: rgba(255, 255, 255, 0.05);
}

.profile-tab-btn.active {
    color: var(--evo-accent, #1d9bf0);
    border-bottom-color: var(--evo-accent, #1d9bf0);
    background: rgba(29, 155, 240, 0.08);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tabButtons = document.querySelectorAll('.profile-tab-btn');
    var tabPanes = document.querySelectorAll('.profile-tab-pane');

    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = btn.getAttribute('data-tab');
            tabButtons.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');

            tabPanes.forEach(function(pane) {
                if (pane.id === targetId) {
                    pane.style.display = 'block';
                    pane.classList.add('active');
                } else {
                    pane.style.display = 'none';
                    pane.classList.remove('active');
                }
            });

            if (history.replaceState) {
                history.replaceState(null, null, '#' + targetId);
            }
        });
    });

    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        var activeBtn = document.querySelector('.profile-tab-btn[data-tab="' + hash + '"]');
        if (activeBtn) {
            activeBtn.click();
        }
    }
});
</script>
