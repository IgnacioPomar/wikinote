<?php

namespace WikiNote;

/**
 * Contains the Wikinote Version, and all the Paths Info
 */
class Site
{
	const VERSION = '0.1';

	// ----------- Universal Folders -----------
	// The browser PATH
	public static $uriPath;

	// The felesystempath to the Site root
	public static $rootPath;

	// The folder with configuration files
	public static $cfgPath;

	// Main configuration file
	public static $cfgFile;

	// The felesystempath to the namespace folder
	public static $nsPath;

	// The felesystempath to the resource folder
	public static $rscPath;

	// The browser path to the resource folder
	public static $rscUriPath;

	// ----------- Configurated Folders -----------
	// Folder who stores all skins
	public static $skinsRootPath;

	// Browser path to all skins
	public static $skinsUriPath;

	// Active skin id
	public static $activeSkinId;

	// Fallback skin id
	public static $fallbackSkinId;

	// Folder who stores the skin
	public static $skinPath;

	// the Browser path to the skin
	public static $uriSkinPath;

	// Folder who stores the templates
	public static $templatePath;

	// Browser path to installer templates
	public static $installTemplatesUriPath;

	// Folder for runtime cache
	public static $cachePath;


	// ----------- Methods -----------
	/**
	 * Initialization of universal Vars
	 */
	public static function init ($rootPath, $cfgFile)
	{
		self::$uriPath = dirname ($_SERVER ['SCRIPT_NAME']); // Remove index.php or siteAdmin.php
		self::$uriPath = rtrim (self::$uriPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR; // Force end with slash

		$reflector = new \ReflectionClass (self::class);
		self::$nsPath = dirname ($reflector->getFileName ()) . DIRECTORY_SEPARATOR;
		self::$rootPath = $rootPath;

		// Make sure it ends with slash;
		if (substr (self::$rootPath, - 1) !== DIRECTORY_SEPARATOR)
		{
			self::$rootPath .= DIRECTORY_SEPARATOR;
		}

		self::$cfgFile = self::$rootPath . $cfgFile;
		self::$cfgPath = dirname (self::$cfgFile) . DIRECTORY_SEPARATOR;
		self::$cachePath = self::$cfgPath . 'cache' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR;

		self::$rscPath = self::$nsPath . 'rsc' . DIRECTORY_SEPARATOR;
		self::$rscUriPath = self::$uriPath . $reflector->getNamespaceName () . DIRECTORY_SEPARATOR . 'rsc' . DIRECTORY_SEPARATOR;
		self::$installTemplatesUriPath = self::$uriPath . $reflector->getNamespaceName () . '/resources/install/templates/';

		self::$skinsRootPath = self::$rootPath . 'skins' . DIRECTORY_SEPARATOR;
		self::$skinsUriPath = self::$uriPath . 'skins' . DIRECTORY_SEPARATOR;
	}


	/**
	 * Initialization of config dependent Vars
	 */
	public static function initCfg ()
	{
		self::$activeSkinId = self::normalizeSkinId ((string) ($GLOBALS ['skin'] ?? 'default'));
		self::$fallbackSkinId = self::normalizeSkinId ((string) ($GLOBALS ['skinFallback'] ?? 'default'));

		if (self::$activeSkinId === '')
		{
			self::$activeSkinId = 'default';
		}
		if (self::$fallbackSkinId === '')
		{
			self::$fallbackSkinId = 'default';
		}

		$activeSkinPath = self::$skinsRootPath . self::$activeSkinId . DIRECTORY_SEPARATOR;
		if (! is_dir ($activeSkinPath))
		{
			$activeSkinPath = self::$skinsRootPath . self::$fallbackSkinId . DIRECTORY_SEPARATOR;
			self::$activeSkinId = is_dir ($activeSkinPath) ? self::$fallbackSkinId : 'default';
			$activeSkinPath = self::$skinsRootPath . self::$activeSkinId . DIRECTORY_SEPARATOR;
		}

		self::$skinPath = $activeSkinPath;
		self::$templatePath = self::$skinPath . 'tmplt' . DIRECTORY_SEPARATOR;
		self::$uriSkinPath = self::$skinsUriPath . self::$activeSkinId . DIRECTORY_SEPARATOR;
	}

	private static function normalizeSkinId (string $skinValue): string
	{
		$skinValue = trim ($skinValue);
		if ($skinValue === '')
		{
			return 'default';
		}

		$skinValue = str_replace ('\\', '/', $skinValue);
		$skinValue = trim ($skinValue, '/');

		if (substr ($skinValue, 0, 6) === 'skins/')
		{
			$skinValue = substr ($skinValue, 6);
		}

		if ($skinValue === '' || strpos ($skinValue, '..') !== false)
		{
			return 'default';
		}

		$parts = explode ('/', $skinValue);
		return (string) end ($parts);
	}
}