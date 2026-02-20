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
	// Folder who stores the skin
	public static $skinPath;

	// the Browser path to the skin
	public static $uriSkinPath;

	// Folder who stores the templates
	public static $templatePath;

	// Browser path to installer templates
	public static $installTemplatesUriPath;


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

		self::$rscPath = self::$nsPath . 'rsc' . DIRECTORY_SEPARATOR;
		self::$rscUriPath = self::$uriPath . $reflector->getNamespaceName () . DIRECTORY_SEPARATOR . 'rsc' . DIRECTORY_SEPARATOR;
		self::$installTemplatesUriPath = self::$uriPath . $reflector->getNamespaceName () . '/resources/install/templates/';
	}


	/**
	 * Initialization of config dependent Vars
	 */
	public static function initCfg ()
	{
		self::$skinPath = self::$rootPath . $GLOBALS ['skin'] . DIRECTORY_SEPARATOR;
		self::$templatePath = self::$skinPath . 'tmplt' . DIRECTORY_SEPARATOR;
		self::$uriSkinPath = self::$uriPath . $GLOBALS ['skin'] . DIRECTORY_SEPARATOR;
	}
}



