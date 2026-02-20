<section class="wn-card">
	<h1>Custom CSS</h1>
	<p class="wn-special-hint">Use this area for site-specific visual tweaks. Changes are loaded after skin CSS.</p>
	<form class="wn-special-form" method="post">
		<label for="css">CSS</label>
		<textarea id="css" name="css" rows="20"><?php echo htmlspecialchars ((string) ($css ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
		<button type="submit">Save</button>
	</form>
</section>
