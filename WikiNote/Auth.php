<?php

namespace WikiNote;

class Auth
{
	private const DEFAULT_TTL_SECONDS = 3600;

	public static function login ($context): void
	{
		$context->userId = null;
		$context->isAdmin = false;
		$context->groups = [];
		$context->jwtClaims = null;

		if (session_status () !== PHP_SESSION_ACTIVE)
		{
			@session_start ();
		}

		if (! empty ($_SESSION ['userId']))
		{
			$context->userId = (string) $_SESSION ['userId'];
		}

		$token = self::extractBearerToken ();
		if ($token !== null)
		{
			$mysqli = isset ($context->mysqli) ? $context->mysqli : null;
			$claims = self::validateAccessToken ($token, $mysqli);
			if ($claims !== null)
			{
				$context->userId = (string) ($claims ['userUuid'] ?? '');
				$context->isAdmin = (bool) ($claims ['isAdmin'] ?? false);
				$context->groups = is_array ($claims ['groups'] ?? null) ? $claims ['groups'] : [];
				$context->jwtClaims = $claims;
				$_SESSION ['userId'] = $context->userId;
				return;
			}
		}

		$loginId = trim ((string) ($_POST ['user'] ?? ''));
		$password = (string) ($_POST ['password'] ?? '');
		if ($loginId === '' || $password === '' || ! isset ($context->mysqli))
		{
			return;
		}

		$user = self::loadUserByLogin ($context->mysqli, $loginId);
		if ($user === null)
		{
			return;
		}

		$hash = (string) ($user ['password'] ?? '');
		if ($hash === '' && isset ($user ['passwordHash']))
		{
			$hash = (string) $user ['passwordHash'];
		}

		if (! password_verify ($password, $hash))
		{
			return;
		}

		$context->userId = (string) $user ['idUser'];
		$context->isAdmin = (bool) ((int) ($user ['isAdmin'] ?? 0));
		$context->groups = self::loadUserGroups ($context->mysqli, $context->userId);
		$_SESSION ['userId'] = $context->userId;
	}

	public static function issueAccessTokenForUser (\mysqli $mysqli, string $userUuid): ?string
	{
		$user = self::loadUserById ($mysqli, $userUuid);
		if ($user === null)
		{
			return null;
		}

		$now = time ();
		$ttl = (int) ($GLOBALS ['jwtTtlSeconds'] ?? self::DEFAULT_TTL_SECONDS);
		if ($ttl <= 0)
		{
			$ttl = self::DEFAULT_TTL_SECONDS;
		}

		$claims = [
			'iss' => (string) ($GLOBALS ['jwtIssuer'] ?? Site::$uriPath ?? ''),
			'aud' => (string) ($GLOBALS ['jwtAudience'] ?? 'wikinote'),
			'iat' => $now,
			'exp' => $now + $ttl,
			'jti' => UUIDv7::generateStd (),
			'userUuid' => (string) $user ['idUser'],
			'username' => (string) ($user ['username'] ?? ''),
			'groups' => self::loadUserGroups ($mysqli, (string) $user ['idUser']),
			'isAdmin' => ((int) ($user ['isAdmin'] ?? 0)) === 1,
			'ver' => (int) ($user ['tokenVersion'] ?? 0)
		];

		return self::encodeJwt ($claims);
	}

	public static function validateAccessToken (string $token, ?\mysqli $mysqli = null): ?array
	{
		$decoded = self::decodeJwt ($token);
		if ($decoded === null)
		{
			return null;
		}

		$header = $decoded ['header'];
		$claims = $decoded ['payload'];
		$signature = $decoded ['signature'];
		$signingInput = $decoded ['signingInput'];
		$alg = (string) ($header ['alg'] ?? '');

		if (! self::verifySignature ($alg, $signingInput, $signature))
		{
			return null;
		}

		$now = time ();
		if (($claims ['exp'] ?? 0) < $now)
		{
			return null;
		}

		if (($claims ['iat'] ?? $now + 1) > $now + 60)
		{
			return null;
		}

		$expectedIss = (string) ($GLOBALS ['jwtIssuer'] ?? Site::$uriPath ?? '');
		if ($expectedIss !== '' && ($claims ['iss'] ?? '') !== $expectedIss)
		{
			return null;
		}

		$expectedAud = (string) ($GLOBALS ['jwtAudience'] ?? 'wikinote');
		if ($expectedAud !== '' && ($claims ['aud'] ?? '') !== $expectedAud)
		{
			return null;
		}

		$userUuid = (string) ($claims ['userUuid'] ?? '');
		if ($userUuid === '')
		{
			return null;
		}

		if ($mysqli !== null)
		{
			$user = self::loadUserById ($mysqli, $userUuid);
			if ($user === null || ((int) ($user ['isActive'] ?? 0)) !== 1)
			{
				return null;
			}

			$verClaim = (int) ($claims ['ver'] ?? - 1);
			$verDb = (int) ($user ['tokenVersion'] ?? 0);
			if ($verClaim !== $verDb)
			{
				return null;
			}
		}

		return $claims;
	}

	private static function loadUserByLogin (\mysqli $mysqli, string $loginId): ?array
	{
		$sql = 'SELECT idUser, username, email, password, passwordHash, isAdmin, isActive, tokenVersion FROM wnUsers WHERE (email = ? OR username = ?) LIMIT 1';
		$stmt = $mysqli->prepare ($sql);
		if (! $stmt)
		{
			return null;
		}

		$stmt->bind_param ('ss', $loginId, $loginId);
		if (! $stmt->execute ())
		{
			$stmt->close ();
			return null;
		}

		$result = $stmt->get_result ();
		$user = $result ? $result->fetch_assoc () : null;
		$stmt->close ();
		if (! is_array ($user))
		{
			return null;
		}

		if ((int) ($user ['isActive'] ?? 0) !== 1)
		{
			return null;
		}

		return $user;
	}

	private static function loadUserById (\mysqli $mysqli, string $userUuid): ?array
	{
		$sql = 'SELECT idUser, username, email, isAdmin, isActive, tokenVersion FROM wnUsers WHERE idUser = ? LIMIT 1';
		$stmt = $mysqli->prepare ($sql);
		if (! $stmt)
		{
			return null;
		}

		$stmt->bind_param ('s', $userUuid);
		if (! $stmt->execute ())
		{
			$stmt->close ();
			return null;
		}

		$result = $stmt->get_result ();
		$user = $result ? $result->fetch_assoc () : null;
		$stmt->close ();

		return is_array ($user) ? $user : null;
	}

	private static function loadUserGroups (\mysqli $mysqli, string $userUuid): array
	{
		$sql = 'SELECT g.name FROM wnGroups g JOIN wnUserGroups ug ON ug.groupId = g.idGroup WHERE ug.userUuid = ?';
		$stmt = $mysqli->prepare ($sql);
		if (! $stmt)
		{
			return [];
		}

		$stmt->bind_param ('s', $userUuid);
		if (! $stmt->execute ())
		{
			$stmt->close ();
			return [];
		}

		$result = $stmt->get_result ();
		$groups = [];
		if ($result)
		{
			while ($row = $result->fetch_assoc ())
			{
				$name = (string) ($row ['name'] ?? '');
				if ($name !== '')
				{
					$groups [] = $name;
				}
			}
		}
		$stmt->close ();

		return $groups;
	}

	private static function extractBearerToken (): ?string
	{
		$header = '';

		if (! empty ($_SERVER ['HTTP_AUTHORIZATION']))
		{
			$header = (string) $_SERVER ['HTTP_AUTHORIZATION'];
		}
		else if (! empty ($_SERVER ['Authorization']))
		{
			$header = (string) $_SERVER ['Authorization'];
		}
		else if (function_exists ('apache_request_headers'))
		{
			$headers = apache_request_headers ();
			foreach ($headers as $key => $value)
			{
				if (strtolower ((string) $key) === 'authorization')
				{
					$header = (string) $value;
					break;
				}
			}
		}

		if (stripos ($header, 'Bearer ') !== 0)
		{
			return null;
		}

		$token = trim (substr ($header, 7));
		return $token === '' ? null : $token;
	}

	private static function encodeJwt (array $claims): ?string
	{
		$alg = (string) ($GLOBALS ['jwtAlgo'] ?? 'RS256');
		$header = [
			'alg' => $alg,
			'typ' => 'JWT'
		];

		$headerJson = json_encode ($header);
		$payloadJson = json_encode ($claims);
		if (! is_string ($headerJson) || ! is_string ($payloadJson))
		{
			return null;
		}

		$headerB64 = self::b64UrlEncode ($headerJson);
		$payloadB64 = self::b64UrlEncode ($payloadJson);
		$signingInput = $headerB64 . '.' . $payloadB64;
		$signature = self::sign ($alg, $signingInput);
		if ($signature === null)
		{
			return null;
		}

		return $signingInput . '.' . self::b64UrlEncode ($signature);
	}

	private static function decodeJwt (string $token): ?array
	{
		$parts = explode ('.', $token);
		if (count ($parts) !== 3)
		{
			return null;
		}

		$headerJson = self::b64UrlDecode ($parts [0]);
		$payloadJson = self::b64UrlDecode ($parts [1]);
		$signature = self::b64UrlDecode ($parts [2]);
		if ($headerJson === null || $payloadJson === null || $signature === null)
		{
			return null;
		}

		$header = json_decode ($headerJson, true);
		$payload = json_decode ($payloadJson, true);
		if (! is_array ($header) || ! is_array ($payload))
		{
			return null;
		}

		return [
			'header' => $header,
			'payload' => $payload,
			'signature' => $signature,
			'signingInput' => $parts [0] . '.' . $parts [1]
		];
	}

	private static function sign (string $alg, string $data): ?string
	{
		if ($alg === 'RS256')
		{
			$privateKeyPath = (string) ($GLOBALS ['jwtPrivateKeyFile'] ?? '');
			if ($privateKeyPath === '' || ! is_file ($privateKeyPath))
			{
				return null;
			}

			$privateKey = file_get_contents ($privateKeyPath);
			if ($privateKey === false)
			{
				return null;
			}

			$sig = '';
			$ok = openssl_sign ($data, $sig, $privateKey, OPENSSL_ALGO_SHA256);
			return $ok ? $sig : null;
		}

		if ($alg === 'HS256')
		{
			$secret = (string) ($GLOBALS ['jwtSecret'] ?? '');
			if ($secret === '')
			{
				return null;
			}

			return hash_hmac ('sha256', $data, $secret, true);
		}

		return null;
	}

	private static function verifySignature (string $alg, string $data, string $signature): bool
	{
		if ($alg === 'RS256')
		{
			$publicKeyPath = (string) ($GLOBALS ['jwtPublicKeyFile'] ?? '');
			if ($publicKeyPath === '' || ! is_file ($publicKeyPath))
			{
				return false;
			}

			$publicKey = file_get_contents ($publicKeyPath);
			if ($publicKey === false)
			{
				return false;
			}

			return openssl_verify ($data, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
		}

		if ($alg === 'HS256')
		{
			$secret = (string) ($GLOBALS ['jwtSecret'] ?? '');
			if ($secret === '')
			{
				return false;
			}

			$calc = hash_hmac ('sha256', $data, $secret, true);
			return hash_equals ($calc, $signature);
		}

		return false;
	}

	private static function b64UrlEncode (string $raw): string
	{
		return rtrim (strtr (base64_encode ($raw), '+/', '-_'), '=');
	}

	private static function b64UrlDecode (string $encoded): ?string
	{
		$padded = strtr ($encoded, '-_', '+/');
		$padLen = strlen ($padded) % 4;
		if ($padLen > 0)
		{
			$padded .= str_repeat ('=', 4 - $padLen);
		}

		$out = base64_decode ($padded, true);
		return $out === false ? null : $out;
	}
}
