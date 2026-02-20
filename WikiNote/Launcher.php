<?php

namespace WikiNote;


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
				if (file_exists (Site::$templatePath . 'maintenance.html'))
				{
					echo file_get_contents (Site::$templatePath . 'maintenance.html');
				}
				else
				{
					echo file_get_contents (Site::$rscPath . 'skinTmplt/maintenance.html');
				}
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
            if (! self::checkAuth ($context))
            {
                // If beeing logged is mandatory, show login page, otherwise continue with empty user
                //TODO: make this configurable
            }
            else
            {
                //TODO: Make this
            }

		}
	}
}