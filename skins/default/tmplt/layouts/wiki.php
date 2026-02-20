<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo htmlspecialchars ((string) ($title ?? 'WikiNote'), ENT_QUOTES, 'UTF-8'); ?></title>
	<?php echo $assetTags ?? ''; ?>
</head>
<body class="wn-layout wn-layout-wiki">
	<?php include __DIR__ . '/../partials/header.php'; ?>
	<main class="wn-main">
		<?php echo $content ?? ''; ?>
	</main>
	<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
