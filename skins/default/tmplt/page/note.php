<article class="wn-card">
	<h1><?php echo htmlspecialchars ((string) ($noteTitle ?? 'Note'), ENT_QUOTES, 'UTF-8'); ?></h1>
	<div class="wn-note-body"><?php echo $noteHtml ?? ''; ?></div>
</article>
