<?php

namespace WikiNote\Theme;

class TemplateEngine
{
	public static function renderPhpTemplate (string $filePath, array $vars = []): string
	{
		extract ($vars, EXTR_SKIP);
		ob_start ();
		require $filePath;
		return (string) ob_get_clean ();
	}
}
