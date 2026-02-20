<?php

namespace WikiNote\Theme;

class SkinManifest
{
	public static function load (string $manifestPath): array
	{
		if (! is_file ($manifestPath))
		{
			throw new \RuntimeException ('Skin manifest not found: ' . $manifestPath);
		}

		$json = file_get_contents ($manifestPath);
		if ($json === false)
		{
			throw new \RuntimeException ('Unable to read skin manifest: ' . $manifestPath);
		}

		$data = json_decode ($json, true);
		if (! is_array ($data))
		{
			throw new \RuntimeException ('Invalid JSON skin manifest: ' . $manifestPath);
		}

		if (empty ($data ['id']) || empty ($data ['templatesDir']))
		{
			throw new \RuntimeException ('Skin manifest missing required keys (id, templatesDir): ' . $manifestPath);
		}

		if (! isset ($data ['assets']) || ! is_array ($data ['assets']))
		{
			$data ['assets'] = [];
		}

		return $data;
	}
}
