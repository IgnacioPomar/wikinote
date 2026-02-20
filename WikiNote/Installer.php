<?php

namespace WikiNote;


class Installer
{
	public \mysqli $mysqli;


	public static function installFromScratch ()
	{
		if (isset ($_POST ['dbname']))
		{
			readfile (Site::$nsPath . 'resources/install/templates/installResult.htm');
			$installer = new Installer ();
			print ($installer->installProcessFromScratch ());
		}
		else
		{
			Installer::showInstallSetup ();
		}
	}


	public function installWithConfigFile ()
	{
		if (isset ($_POST ['adminlogin']))
		{
			readfile (Site::$nsPath . 'resources/install/templates/installResult.htm');
			print ($this->installProcessCommon ());
		}
		else
		{
			self::renderInstallForm ('installFormNoCfg.htm');
		}
	}


	private static function showInstallSetup ()
	{
		self::renderInstallForm ('installForm.htm');
	}

	private static function renderInstallForm (string $templateName): void
	{
		$layout = file_get_contents (Site::$nsPath . 'resources/install/templates/' . $templateName);
		$warning = self::getPublicConfigWarning ();
		if ($warning !== '')
		{
			$rowWarning = "<tr><td colspan='2'>" . $warning . '</td></tr>';
			$layout = preg_replace (
				"/(<tr>\\s*<td colspan='2' class='title'><input type='submit'[^>]*><\\/td>\\s*<\\/tr>)/",
				$rowWarning . "\n$1",
				$layout,
				1
			);
		}

		header ('Content-Type: text/html; charset=utf-8');
		print ($layout);
	}


	/**
	 * Installer constructor.
	 *
	 * @param \mysqli|null $mysqli
	 */
	public function __construct ($mysqli = NULL)
	{
		if ($mysqli === NULL)
        {
            $dbServer = $_POST['dbserver'] ?? '';
            $dbUser   = $_POST['dbuser'] ?? '';
            $dbPass   = $_POST['dbpass'] ?? '';
            $dbName   = $_POST['dbname'] ?? '';
            $dbPort   = isset($_POST['dbport']) ? (int) $_POST['dbport'] : 3306;

            $this->mysqli = new \mysqli($dbServer, $dbUser, $dbPass, $dbName, $dbPort);
        }
        else
        {
            $this->mysqli = $mysqli;
        }
	}

	/**
	 * Escapes a string for safe output in HTML context
	 *
	 * @param string $value
	 * @return string
	 */
	private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

	private static function escStatic (string $value): string
	{
		return htmlspecialchars ($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private static function normalizePath (string $path): string
	{
		$normalized = str_replace (['/', '\\'], DIRECTORY_SEPARATOR, $path);
		$normalized = rtrim ($normalized, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		if (DIRECTORY_SEPARATOR === '\\')
		{
			$normalized = strtolower ($normalized);
		}
		return $normalized;
	}

	private static function isPathInside (string $basePath, string $targetPath): bool
	{
		$base = self::normalizePath ($basePath);
		$target = self::normalizePath ($targetPath);
		return strpos ($target, $base) === 0;
	}

	private static function isApacheRuntime (): bool
	{
		return isset ($_SERVER ['SERVER_SOFTWARE']) && stripos ((string) $_SERVER ['SERVER_SOFTWARE'], 'apache') !== false;
	}

	private static function isNginxRuntime (): bool
	{
		return isset ($_SERVER ['SERVER_SOFTWARE']) && stripos ((string) $_SERVER ['SERVER_SOFTWARE'], 'nginx') !== false;
	}

	private static function hasApacheDenyRule (string $cfgDir, string $docRoot): bool
	{
		if (! self::isApacheRuntime ())
		{
			return false;
		}

		$current = rtrim ($cfgDir, DIRECTORY_SEPARATOR);
		$docRoot = rtrim ($docRoot, DIRECTORY_SEPARATOR);

		while ($current !== '' && self::isPathInside ($docRoot, $current))
		{
			$htaccess = $current . DIRECTORY_SEPARATOR . '.htaccess';
			if (is_file ($htaccess))
			{
				$content = file_get_contents ($htaccess);
				if ($content !== false && preg_match ('/(require\s+all\s+denied|deny\s+from\s+all)/i', $content))
				{
					return true;
				}
			}

			$parent = dirname ($current);
			if ($parent === $current)
			{
				break;
			}
			$current = $parent;
		}

		return false;
	}

	private static function getCfgPublicPath (string $docRoot, string $cfgDir): string
	{
		$docRootNorm = self::normalizePath ($docRoot);
		$cfgNorm = self::normalizePath ($cfgDir);
		$relative = substr ($cfgNorm, strlen ($docRootNorm));
		$relative = trim ((string) $relative, '/\\');
		$path = '/' . str_replace ('\\', '/', $relative) . '/';
		return preg_replace ('#/+#', '/', $path);
	}

	private static function getCfgPublicUrl (string $cfgPublicPath): string
	{
		$host = $_SERVER ['HTTP_HOST'] ?? '';
		if ($host === '')
		{
			return $cfgPublicPath;
		}

		$isHttps = (! empty ($_SERVER ['HTTPS']) && $_SERVER ['HTTPS'] !== 'off') || (($_SERVER ['SERVER_PORT'] ?? '') === '443');
		$scheme = $isHttps ? 'https://' : 'http://';

		return $scheme . $host . $cfgPublicPath;
	}

	private static function getServerConfigSnippet (string $cfgPublicPath): string
	{
		if (self::isApacheRuntime ())
		{
			return '<Directory "' . $cfgPublicPath . "\">\n    Require all denied\n</Directory>";
		}

		if (self::isNginxRuntime ())
		{
			return 'location ^~ ' . $cfgPublicPath . " {\n    deny all;\n    return 403;\n}";
		}

		return "# Apache\n<Directory \"" . $cfgPublicPath . "\">\n    Require all denied\n</Directory>\n\n# Nginx\nlocation ^~ " . $cfgPublicPath . " {\n    deny all;\n    return 403;\n}";
	}

	private static function renderConfigWarningFragment (array $data): string
	{
		$templatePath = Site::$nsPath . 'resources/install/templates/configPublicWarning.htm';
		$fragment = file_get_contents ($templatePath);
		if ($fragment === false)
		{
			return '<div class="error"><b>Security warning</b>: Configuration file may be publicly reachable.</div>';
		}

		return strtr ($fragment, $data);
	}

	private static function getPublicConfigWarning (): string
	{
		$docRoot = $_SERVER ['DOCUMENT_ROOT'] ?? Site::$rootPath;
		$docRootReal = realpath ($docRoot) ?: $docRoot;
		$cfgFileReal = realpath (Site::$cfgFile) ?: Site::$cfgFile;
		$cfgDir = dirname ($cfgFileReal);

		if (! self::isPathInside ($docRootReal, $cfgDir))
		{
			return '';
		}

		if (self::hasApacheDenyRule ($cfgDir, $docRootReal))
		{
			return '';
		}

		$cfgPublicPath = self::getCfgPublicPath ($docRootReal, $cfgDir);
		$cfgPublicUrl = self::getCfgPublicUrl ($cfgPublicPath);

		return self::renderConfigWarningFragment ([
			'@@cfgFile@@' => self::escStatic (Site::$cfgFile),
			'@@cfgDir@@' => self::escStatic ($cfgDir),
			'@@cfgUrl@@' => self::escStatic ($cfgPublicUrl),
			'@@serverName@@' => self::isApacheRuntime () ? 'Apache' : (self::isNginxRuntime () ? 'Nginx' : 'Apache/Nginx'),
			'@@serverSnippet@@' => self::escStatic (self::getServerConfigSnippet ($cfgPublicPath)),
		]);
	}


	/**
	 * Steps for a full fresh installs
	 *
	 * @return string
	 */
	private function installProcessFromScratch ()
	{
		$outputMessage = '';

		// 0.- Check Params
		if (! $this->checkDbAccess ($outputMessage))
		{
			return $outputMessage;
		}

		// 1.- Save the new configuration file
		if (! $this->saveNewCfgFile ($outputMessage))
		{
			return $outputMessage;
		}

		include_once (Site::$cfgFile);
		Site::initCfg ();

		return $this->installProcessCommon ();
	}


	/**
	 * Steps starting with the database inicialization
	 *
	 * @return string
	 */
	private function installProcessCommon ()
	{
		// Out Msg
		$outputMessage = '';

		if (! $this->ensureJwtKeys ($outputMessage))
		{
			return $outputMessage;
		}

		// 2.- Create the database schema and add initial data
		$outputMessage .= $this->createCoreTables ();

		if (! $this->addInitialData ($outputMessage))
		{
			return $outputMessage;
		}

		return $outputMessage;
	}

	private function appendJwtCfgIfMissing (&$out): bool
	{
		$cfgContent = file_get_contents (Site::$cfgFile);
		if ($cfgContent === false)
		{
			$out .= '<div class="fail"><b>Error</b>: Unable to read config file to append JWT key settings.</div>';
			return false;
		}

		if (strpos ($cfgContent, "jwtPrivateKeyFile") !== false && strpos ($cfgContent, "jwtPublicKeyFile") !== false)
		{
			return true;
		}

		$append = "\n// JWT\n";
		$append .= "\$GLOBALS ['jwtAlgo'] = 'RS256';\n";
		$append .= "\$GLOBALS ['jwtPrivateKeyFile'] = __DIR__ . '/jwt_private.pem';\n";
		$append .= "\$GLOBALS ['jwtPublicKeyFile'] = __DIR__ . '/jwt_public.pem';\n";

		if (file_put_contents (Site::$cfgFile, $append, FILE_APPEND) === false)
		{
			$out .= '<div class="fail"><b>Error</b>: Unable to append JWT key settings to config file.</div>';
			return false;
		}

		return true;
	}

	private function ensureJwtKeys (&$out): bool
	{
		$privateKeyPath = Site::$cfgPath . 'jwt_private.pem';
		$publicKeyPath = Site::$cfgPath . 'jwt_public.pem';

		$privateExists = is_file ($privateKeyPath) && filesize ($privateKeyPath) > 0;
		$publicExists = is_file ($publicKeyPath) && filesize ($publicKeyPath) > 0;

		if ($privateExists && $publicExists)
		{
			if (! $this->appendJwtCfgIfMissing ($out))
			{
				return false;
			}
			$out .= '<div class="ok">JWT keys: <b>OK</b> (already present)</div>';
			return true;
		}

		if (! function_exists ('openssl_pkey_new'))
		{
			$out .= '<div class="fail"><b>Error</b>: OpenSSL extension is required to generate JWT keys.</div>';
			return false;
		}

		$keyConfig = [
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
			'private_key_bits' => 2048
		];

		$res = openssl_pkey_new ($keyConfig);
		if ($res === false)
		{
			$out .= '<div class="fail"><b>Error</b>: Unable to generate JWT key pair with OpenSSL.</div>';
			return false;
		}

		$privatePem = '';
		if (! openssl_pkey_export ($res, $privatePem, null, $keyConfig))
		{
			$out .= '<div class="fail"><b>Error</b>: Unable to export JWT private key.</div>';
			return false;
		}

		$details = openssl_pkey_get_details ($res);
		$publicPem = $details ['key'] ?? '';
		if ($publicPem === '')
		{
			$out .= '<div class="fail"><b>Error</b>: Unable to extract JWT public key.</div>';
			return false;
		}

		if (file_put_contents ($privateKeyPath, $privatePem) === false)
		{
			$out .= '<div class="fail"><b>Error</b>: Unable to write private JWT key: ' . $this->esc ($privateKeyPath) . '</div>';
			return false;
		}

		if (file_put_contents ($publicKeyPath, $publicPem) === false)
		{
			$out .= '<div class="fail"><b>Error</b>: Unable to write public JWT key: ' . $this->esc ($publicKeyPath) . '</div>';
			return false;
		}

		@chmod ($privateKeyPath, 0600);
		@chmod ($publicKeyPath, 0644);

		if (! $this->appendJwtCfgIfMissing ($out))
		{
			return false;
		}

		$out .= '<div class="ok">JWT keys generated: <b>OK</b></div>';
		return true;
	}


	/**
	 * Create the database schema
	 *
	 * @param string $outputMessage
	 */
	public function createCoreTables ()
	{
		$retVal = '';
        $tablesPath = Site::$nsPath . 'resources/install/tables/';

        if (!is_dir($tablesPath))
        {
            return '<div class="fail"><b>Error</b>: Tables folder not found: ' . $this->esc($tablesPath) . '</div>';
        }

        try
        {
            $dir = new \FilesystemIterator($tablesPath);
            foreach ($dir as $fileinfo)
            {
                if ($fileinfo->getExtension() === 'json')
                {
                    $upd = DbSchema::createOrUpdateTable($this->mysqli, $fileinfo->getPathname());
                    $retVal .= $this->formatDBAction($upd);
                }
            }
        }
        catch (\Throwable $e)
        {
            $retVal .= '<div class="fail"><b>Error</b>: ' . $this->esc($e->getMessage()) . '</div>';
        }

        return $retVal;
	}


	private function formatDBAction ($upd)
	{
		$label  = $this->esc((string)($upd[0] ?? 'Unknown action'));
		$status = (int)($upd[1] ?? -1);

    	if ($status === -1)
    	{
        	return '<div class="fail"><b>Error</b>: ' . $label . ' <br /> ' . $this->esc((string)$this->mysqli->error) . '</div>';
    	}
    	else if ($status === 1)
    	{
        	return '<div class="OK"><b>' . $label . ' </b>: Ok</div>';
    	}
    	else
    	{
	        return '<div class="none">' . $label . ' [No changes]</div>';
    	}
	}




	/**
	 * Add the just created admin account
	 *
	 * @param boolean $outputMessage
	 */
	public function addInitialData (&$out)
	{
		$adminName  = trim($_POST['adminname'] ?? '');
        $adminLogin = trim($_POST['adminlogin'] ?? '');
        $adminPass1 = $_POST['adminpass1'] ?? '';
        $adminPass2 = $_POST['adminpass2'] ?? '';

        if ($adminName === '' || $adminLogin === '' || $adminPass1 === '')
        {
            $out .= '<div class="fail"><b>Error</b>: Missing admin fields.</div>';
            return FALSE;
        }

        if ($adminPass1 !== $adminPass2)
        {
            $out .= '<div class="fail"><b>Error</b>: Password confirmation does not match.</div>';
            return FALSE;
        }

        $sql = 'INSERT INTO wnUsers (idUser, name, email, password, isActive, isAdmin) VALUES (?, ?, ?, ?, 1, 1)';
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt)
        {
            $out .= '<div class="fail"><b>Error</b>: Unable to prepare admin insert.<br />' . $this->esc($this->mysqli->error) . '</div>';
            return FALSE;
        }

        $idUser = UUIDv7::generateStd();
        $hash = password_hash($adminPass1, PASSWORD_DEFAULT);
        $stmt->bind_param('ssss', $idUser, $adminName, $adminLogin, $hash);

        if ($stmt->execute())
        {
            $out .= '<div class="ok">Creating admin credentials: <b>OK</b></div>';
            $stmt->close();
            return TRUE;
        }

        $out .= '<div class="fail"><b>Error</b>: Unable to create admin user.<br />' . $this->esc($stmt->error) . '</div>';
        $stmt->close();
        return FALSE;
	}


	/**
	 * Check if the database info is correct
	 *
	 * @param string $outputMessage
	 *        	the output message in we will append the new report
	 */
	public function checkDbAccess (&$outputMessage)
	{
		if ($this->mysqli->connect_error)
        {
            $outputMessage .= '<div class="fail">';
            $outputMessage .= '<b>Error</b>: MySQL connection failed.<br />';
            $outputMessage .= 'Error code: ' . (int)$this->mysqli->connect_errno . '.<br />';
            $outputMessage .= 'Description: ' . $this->esc((string)$this->mysqli->connect_error) . '.<br />';
            $outputMessage .= '</div>';
            return FALSE;
        }

        $outputMessage .= '<div class="ok">Database connection: <b>OK</b></div>';
        return TRUE;
	}


	/**
	 * @param boolean $outputMessage
	 */
	public function saveNewCfgFile (&$outputMessage)
	{
		  $outputSkin = Site::$rscPath . 'default/site_cfg.template';
		if (! is_file ($outputSkin))
		{
			$outputSkin = Site::$nsPath . 'resources/install/templates/site_cfg.php';
		}
        $cfgFile = file_get_contents($outputSkin);

        if ($cfgFile === false)
        {
            $outputMessage .= '<div class="fail"><b>Error</b>: Unable to read config template.</div>';
            return FALSE;
        }

        $replaceMap = [
            '@@dbserver@@'    => $_POST['dbserver'] ?? '',
            '@@dbport@@'      => $_POST['dbport'] ?? '3306',
            '@@dbuser@@'      => $_POST['dbuser'] ?? '',
            '@@dbpass@@'      => $_POST['dbpass'] ?? '',
            '@@dbname@@'      => $_POST['dbname'] ?? '',
            '@@plgs@@'        => $_POST['plgs'] ?? '',
            '@@skins@@'       => $_POST['skins'] ?? '',
            '@@menuType@@'    => $_POST['mnu'] ?? '',
            '@@authLog@@'     => isset($_POST['authLog']) ? 'TRUE' : 'FALSE',
            '@@authRecover@@' => isset($_POST['authRecover']) ? 'TRUE' : 'FALSE',
        ];

        $cfgFile = strtr($cfgFile, $replaceMap);

		$cfgDir = dirname (Site::$cfgFile);
		if (! is_dir ($cfgDir))
		{
			if (! @mkdir ($cfgDir, 0755, true) && ! is_dir ($cfgDir))
			{
				$outputMessage .= '<div class="fail">';
				$outputMessage .= '<b>Error</b>: Config directory does not exist and could not be created.<br />';
				$outputMessage .= 'Directory: ' . $this->esc ($cfgDir) . '</div>';
				return FALSE;
			}
		}

        if (file_put_contents(Site::$cfgFile, $cfgFile) === false)
        {
            $outputMessage .= '<div class="fail">';
            $outputMessage .= '<b>Error</b>: Unable to save the config file<br />';
            $outputMessage .= 'Workaround: save the file ' . $this->esc(Site::$cfgFile) . ' with this contents:<br /><pre>';
            $outputMessage .= $this->esc($cfgFile);
            $outputMessage .= '</pre></div>';
            return FALSE;
        }

        $outputMessage .= '<div class="ok">Config file: <b>OK</b></div>';
        return TRUE;
	}
}
