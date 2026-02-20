<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo htmlspecialchars ((string) ($title ?? 'Maintenance - WikiNote'), ENT_QUOTES, 'UTF-8'); ?></title>
	<?php echo $assetTags ?? ''; ?>
</head>
<body class="wn-layout wn-layout-maintenance">
	<main class="wn-main">
		<section class="wn-card">
			<h1>Maintenance</h1>
			<p><?php echo htmlspecialchars ((string) ($message ?? 'WikiNote is under maintenance.'), ENT_QUOTES, 'UTF-8'); ?></p>
		</section>
	</main>
</body>
</html>
