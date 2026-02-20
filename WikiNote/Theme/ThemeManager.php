<?php

namespace WikiNote\Theme;

class ThemeManager
{
	private static ?Skin $activeSkin = null;
	private static string $skinsRootPath = '';
	private static string $skinsRootUri = '';
	private static string $fallbackSkinId = 'default';
	private static array $resolvedTemplateCache = [];

	public static function init (string $skinsRootPath, string $skinsRootUri, string $activeSkinId, string $fallbackSkinId = 'default'): void
	{
		self::$skinsRootPath = rtrim ($skinsRootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		self::$skinsRootUri = rtrim ($skinsRootUri, '/') . '/';
		self::$fallbackSkinId = $fallbackSkinId !== '' ? $fallbackSkinId : 'default';
		self::$activeSkin = self::loadSkinOrFallback ($activeSkinId);
		self::$resolvedTemplateCache = [];
	}

	public static function getActiveSkin (): Skin
	{
		if (self::$activeSkin === null)
		{
			throw new \RuntimeException ('ThemeManager is not initialized.');
		}
		return self::$activeSkin;
	}

	public static function render (string $templateRelativePath, array $vars = []): string
	{
		$filePath = self::resolveTemplate ($templateRelativePath);
		return TemplateEngine::renderPhpTemplate ($filePath, $vars);
	}

	public static function assetsFor (string $context): string
	{
		$skin = self::getActiveSkin ();
		$skinUri = self::$skinsRootUri . $skin->getId () . '/';
		$baseTags = AssetRegistry::toHtmlTags ($skinUri, $skin, $context);
		$customCssTag = CustomCssService::buildTag ();
		return trim ($baseTags . PHP_EOL . $customCssTag);
	}

	public static function resolveTemplate (string $templateRelativePath): string
	{
		$key = self::getActiveSkin ()->getId () . '::' . $templateRelativePath;
		if (isset (self::$resolvedTemplateCache [$key]))
		{
			return self::$resolvedTemplateCache [$key];
		}

		$candidates = self::candidateSkinIds ();
		foreach ($candidates as $skinId)
		{
			$skinPath = self::$skinsRootPath . $skinId . DIRECTORY_SEPARATOR;
			$manifestPath = $skinPath . 'manifest.json';
			if (! is_file ($manifestPath))
			{
				continue;
			}
			$manifest = SkinManifest::load ($manifestPath);
			$tmpltDir = (string) $manifest ['templatesDir'];
			$filePath = $skinPath . $tmpltDir . DIRECTORY_SEPARATOR . str_replace ('/', DIRECTORY_SEPARATOR, $templateRelativePath);
			if (is_file ($filePath))
			{
				self::$resolvedTemplateCache [$key] = $filePath;
				return $filePath;
			}
		}

		throw new \RuntimeException ('Template not found: ' . $templateRelativePath);
	}

	private static function loadSkinOrFallback (string $skinId): Skin
	{
		foreach ([$skinId, self::$fallbackSkinId, 'default'] as $candidate)
		{
			if (! is_string ($candidate) || $candidate === '')
			{
				continue;
			}
			$manifestPath = self::$skinsRootPath . $candidate . DIRECTORY_SEPARATOR . 'manifest.json';
			if (is_file ($manifestPath))
			{
				return new Skin (self::$skinsRootPath . $candidate, SkinManifest::load ($manifestPath));
			}
		}

		throw new \RuntimeException ('No valid skin manifest found.');
	}

	private static function candidateSkinIds (): array
	{
		$skin = self::getActiveSkin ();
		$candidates = [$skin->getId ()];
		$parentId = $skin->getParentId ();
		if ($parentId !== null && $parentId !== '')
		{
			$candidates [] = $parentId;
		}
		if (! in_array (self::$fallbackSkinId, $candidates, true))
		{
			$candidates [] = self::$fallbackSkinId;
		}
		if (! in_array ('default', $candidates, true))
		{
			$candidates [] = 'default';
		}
		return $candidates;
	}
}
