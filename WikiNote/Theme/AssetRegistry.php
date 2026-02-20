<?php

namespace WikiNote\Theme;

class AssetRegistry
{
	public static function toHtmlTags (string $skinBaseUri, Skin $skin, string $context): string
	{
		$tags = [];
		$baseUri = rtrim ($skinBaseUri, '/') . '/';
		foreach ($skin->getAssetsForContext ($context) as $asset)
		{
			$asset = (string) $asset;
			$filePath = $skin->getAssetFilePath ($asset);
			$version = is_file ($filePath) ? (string) filemtime ($filePath) : '0';
			$uri = $baseUri . 'assets/' . ltrim ($asset, '/') . '?v=' . rawurlencode ($version);
			if (substr ($asset, - 4) === '.css')
			{
				$tags [] = '<link rel="stylesheet" href="' . htmlspecialchars ($uri, ENT_QUOTES, 'UTF-8') . '" />';
			}
			else if (substr ($asset, - 3) === '.js')
			{
				$tags [] = '<script defer src="' . htmlspecialchars ($uri, ENT_QUOTES, 'UTF-8') . '"></script>';
			}
		}
		return implode (PHP_EOL, $tags);
	}
}