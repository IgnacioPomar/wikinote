<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo htmlspecialchars ((string) ($title ?? 'Login - WikiNote'), ENT_QUOTES, 'UTF-8'); ?></title>
	<?php echo $assetTags ?? ''; ?>
</head>
<body class="wn-layout wn-layout-login">
	<main class="wn-main wn-main-login">
		<?php echo $content ?? ''; ?>
	</main>
</body>
</html>
