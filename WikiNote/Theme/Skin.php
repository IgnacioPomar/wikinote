<?php

namespace WikiNote\Theme;

class Skin
{
	private string $rootPath;
	private string $id;
	private string $templatesDir;
	private ?string $parent;
	private array $assets;

	public function __construct (string $rootPath, array $manifest)
	{
		$this->rootPath = rtrim ($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$this->id = (string) $manifest ['id'];
		$this->templatesDir = (string) $manifest ['templatesDir'];
		$this->parent = isset ($manifest ['parent']) && $manifest ['parent'] !== null ? (string) $manifest ['parent'] : null;
		$this->assets = is_array ($manifest ['assets'] ?? null) ? $manifest ['assets'] : [];
	}

	public function getId (): string
	{
		return $this->id;
	}

	public function getParentId (): ?string
	{
		return $this->parent;
	}

	public function getTemplatesPath (): string
	{
		return $this->rootPath . $this->templatesDir . DIRECTORY_SEPARATOR;
	}

	public function getAssetsForContext (string $context): array
	{
		$assets = $this->assets [$context] ?? [];
		return is_array ($assets) ? $assets : [];
	}

	public function getAssetFilePath (string $relativeAssetPath): string
	{
		return $this->rootPath . 'assets' . DIRECTORY_SEPARATOR . str_replace ('/', DIRECTORY_SEPARATOR, $relativeAssetPath);
	}
}
