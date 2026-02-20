<?php

namespace WikiNote;

use WikiNote\Theme\ThemeManager;

class Launcher
{

	/**
	 * Establish connection to database
	 *
	 * @return boolean false if database connection failed
	 */
	private static function connectDb (&$context)
	{
		$context->mysqli = new \mysqli ($GLOBALS ['dbserver'], $GLOBALS ['dbuser'], $GLOBALS ['dbpass'], $GLOBALS ['dbname'], $GLOBALS ['dbport']);
		if ($context->mysqli->connect_errno)
		{
			print ('Database connection failed. Wait a minute, or contact with an administrator.');
			print ('Errno: ' . $context->mysqli->connect_errno . '<br />');
			print ('Error: ' . $context->mysqli->connect_error . '<br />');

			return false;
		}

		$context->mysqli->set_charset ("utf8");
		// $mysqli->query ("SET NAMES 'UTF8'");

		return true;
	}

	private static function initTheme (): void
	{
		ThemeManager::init (Site::$skinsRootPath, Site::$skinsUriPath, Site::$activeSkinId, Site::$fallbackSkinId);
	}

	private static function renderMaintenancePage (): void
	{
		try
		{
			self::initTheme ();
			echo ThemeManager::render ('layouts/maintenance.php', [
				'title' => 'Maintenance - WikiNote',
				'assetTags' => ThemeManager::assetsFor ('special'),
				'message' => 'WikiNote is under maintenance. Please try again in a few minutes.'
			]);
			return;
		}
		catch (\Throwable $e)
		{
			// If theming is not available, continue with legacy fallback.
		}

		if (file_exists (Site::$templatePath . 'maintenance.html'))
		{
			echo file_get_contents (Site::$templatePath . 'maintenance.html');
		}
		else if (file_exists (Site::$rscPath . 'skinTmplt/maintenance.html'))
		{
			echo file_get_contents (Site::$rscPath . 'skinTmplt/maintenance.html');
		}
		else
		{
			echo '<h1>Maintenance</h1><p>WikiNote is under maintenance.</p>';
		}
	}

	private static function renderLoginPage (): void
	{
		$content = ThemeManager::render ('page/login-form.php');

		echo ThemeManager::render ('layouts/login.php', [
			'title' => 'Login - WikiNote',
			'assetTags' => ThemeManager::assetsFor ('login'),
			'content' => $content
		]);
	}

	private static function renderHomePage (): void
	{
		$pageContent = ThemeManager::render ('page/note.php', [
			'noteTitle' => 'Welcome to WikiNote',
			'noteHtml' => '<p>The new multi-skin system is initialized.</p>'
		]);

		echo ThemeManager::render ('layouts/wiki.php', [
			'title' => 'WikiNote',
			'assetTags' => ThemeManager::assetsFor ('wiki'),
			'content' => $pageContent
		]);
	}


	/**
	 * Update the database to the current version
	 */
	private static function updateDb ($context)
	{
		if (self::connectDb ($context))
		{
			//Check the user and if has permissions, update the database
			Auth::login ($context);

			if ($context->userId !== NULL)
			{
				Installer::updateDb ($context);
			}
			else
			{
				//If the user does not have permissions, then simply show the maintenance page
				self::renderMaintenancePage ();
			}
		}
	}


	/**
	 * Check if installation is OK
	 *
	 * @return boolean false if There is no config file, or if version does not match
	 */
	private static function checkInstallation ()
	{
		if (! file_exists (Site::$cfgFile))
		{
			Installer::installFromScratch ();
			return false;
		}

		include_once (Site::$cfgFile);
		Site::initCfg ();

		if ($GLOBALS ['Version'] != Site::VERSION)
		{
			$context = new Context ();
			self::updateDb ($context);
			return false;
		}
		return true;
	}


	/**
	 * Check the user credentials
	 *
	 * @return boolean false If there is no valid user logged
	 */
	private static function checkAuth (&$context)
	{
		session_start ();

		Auth::login ($context);

		return ($context->userId !== NULL);
	}


	/**
	 * Entry point
	 */
	public static function main ($rootPath, $cfgFile)
	{
		Site::init ($rootPath, $cfgFile);

		$context = new Context ();
		if (self::checkInstallation () && self::connectDb ($context))
		{
			self::initTheme ();

			if (! self::checkAuth ($context))
			{
				self::renderLoginPage ();
			}
			else
			{
				self::renderHomePage ();
			}
		}
	}
}