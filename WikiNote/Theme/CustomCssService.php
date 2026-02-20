<?php

namespace WikiNote\Theme;

class CustomCssService
{
	public static function getCssPath (): string
	{
		return \WikiNote\Site::$cfgPath . 'custom.css';
	}

	public static function getRevision (): string
	{
		$path = self::getCssPath ();
		if (! is_file ($path))
		{
			return '0';
		}
		return (string) filemtime ($path);
	}

	public static function buildTag (): string
	{
		$enabled = (bool) ($GLOBALS ['customCssEnabled'] ?? true);
		if (! $enabled)
		{
			return '';
		}

		$path = self::getCssPath ();
		if (! is_file ($path))
		{
			return '';
		}

		$uri = rtrim (\WikiNote\Site::$uriPath, '/') . '/cfg/custom.css?v=' . rawurlencode (self::getRevision ());
		return '<link rel="stylesheet" href="' . htmlspecialchars ($uri, ENT_QUOTES, 'UTF-8') . '" />';
	}
}
