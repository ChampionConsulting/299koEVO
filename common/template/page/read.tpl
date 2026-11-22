<section>
	{% if page.isUnlocked(pageItem) %}
		{% HOOK.page:content-header(pageItem) %}
		{{pageItem.getParsedContent()|raw}}
	{% else %}
		<header>
			<div class="item-head">
				<p>This page is password-protected.</p>
			</div>
		</header>
		<form method="post" action="">
			<input type="hidden" name="unlock" value="{{sendUrl}}"/>
			<input type="hidden" name="_csrf" value="{{_csrfToken}}"/>
			<p>
				<label>Password</label><br>
				<input required="required" type="password" name="password" value=""/>
			</p>
			<p>
				<input type="submit" value="Submit"/>
			</p>
		</form>
	{% endif %}
</section>
