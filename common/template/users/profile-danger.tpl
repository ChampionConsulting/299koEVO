<section class="user-profile-danger">
    <div class="profile-danger-header">
        <h1>Danger zone </h1>
        <a href="{{ ROUTER.generate("profile") }}" class="button secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to profile
        </a>
    </div>

    <!-- Section de suppression de compte -->
    <div class="user-delete-account">
        <h2>
            <i class="fa-solid fa-exclamation-triangle"></i> Account deletion
        </h2>
        <p class="user-delete-lead">  
		<strong>Warning:</strong> Deleting your account is permanent and irreversible. All your data will be deleted, including:
        </p>
        <ul class="user-delete-list"> 
		<li>Your personal information</li> 
		<li>Your contributions (claims, suggestions, proposals)</li> 
		<li>Your photos and uploaded content</li>       
		<li>Your private messages</li>               
		<li>All data associated with your account</li>
        </ul>
        <p class="user-delete-warn">
            This action cannot be undone. Are you absolutely sure you want to continue?
        </p>
        <form method="post" action="{{profileDeleteLink}}" autocomplete="off" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This action is irreversible, and all your data will be permanently lost.');">
            <input type="hidden" name="_csrf" value="{{_csrfToken}}">
            <p>
                <label for="delete-confirmation" class="user-delete-label">
                   To confirm, type "delete" below:
                </label>
                <input id="delete-confirmation" type="text" name="delete_confirmation"
                       placeholder="delete"
                       class="user-delete-confirm-input"
                       required autocomplete="off">
            </p>
            <p class="user-delete-actions">
                <button type="submit" class="button alert user-delete-submit-btn">
                    <i class="fa-solid fa-trash"></i> Delete my account permanently
                </button>
                <a href="{{ ROUTER.generate("profile") }}" class="button secondary user-delete-cancel-btn">Cancel</a>
            </p>                                                                                                          
        </form>
    </div>
</section>


