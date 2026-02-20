<?php

namespace WikiNote;

class UUIDv7
{
	private static int $subMillisecondCounter = 0;


	/**
	 * Generate a version 7 (time-based) UUID
	 *
	 * @return string
	 */
	public static function generateBinary (): string
	{
		$counter = 0xFFF & self::$subMillisecondCounter ++;

		// $timeInNanoseconds = hrtime (true);
		// $milliseconds = (int) ($timeInNanoseconds / 1e6);
		$milliseconds = (int) (microtime (true) * 1000);

		$mostSigBits = ($milliseconds << 16) | (7 << 12) | ($counter >> 2);
		$leastSigBits = (($counter & 0x3) << 62) | (random_int (0, PHP_INT_MAX) & 0x3FFFFFFFFFFFFFFF);

		// Combine mostSigBits and leastSigBits into a binary string, then convert to a UUID string format
		return pack ('J', $mostSigBits) . pack ('J', $leastSigBits);
	}


	/**
	 * represent the UUID as a Base64 string
	 *
	 * @return string
	 */
	public static function generateBase64 ($uuidBin = NULL): string
	{
		$uuidBin ??= self::generateBinary ();
		return rtrim (strtr (base64_encode ($uuidBin), '+/', '-_'), '=');
	}


	/**
	 * represent the UUID as a standard way of representing UUIDs
	 *
	 * @return string
	 */
	public static function generateStd ($uuidBin = NULL): string
	{
		$uuidBin ??= self::generateBinary ();
		$hex = bin2hex ($uuidBin);

		// Split the hex string into the UUID format parts
		$time_low = substr ($hex, 0, 8);
		$time_mid = substr ($hex, 8, 4);
		// We have to adjust the version and the variant according to the UUID standards.
		$version_and_time_high = substr ($hex, 12, 4);
		$version_and_time_high = (hexdec ($version_and_time_high));
		$clock_seq_and_variant = substr ($hex, 16, 4);
		$clock_seq_and_variant = (hexdec ($clock_seq_and_variant));
		$node = substr ($hex, 20);

		return sprintf ('%08s-%04s-%04x-%04x-%012s', $time_low, $time_mid, $version_and_time_high, $clock_seq_and_variant, $node);
	}


	/**
	 * Decode a base64-like encoded UUID and return the time it was generated
	 *
	 * @param string $uuid
	 * @return string
	 */
	public static function inverseB64 ($uuid): string
	{
		// Decode the base64-like encoded UUID back to its binary representation
		$uuidBytes = base64_decode (strtr ($uuid, '-_', '+/'));

		$mostSigBits = 0;
		$leastSigBits = 0;

		// Convert the first 8 bytes to the most significant bits component
		for($i = 0; $i < 8; $i ++)
		{
			$mostSigBits = ($mostSigBits << 8) | (ord ($uuidBytes [$i]) & 0xFF);
		}

		// Convert the next 8 bytes to the least significant bits component
		for($i = 8; $i < 16; $i ++)
		{
			$leastSigBits = ($leastSigBits << 8) | (ord ($uuidBytes [$i]) & 0xFF);
		}

		// Extract the time from the most significant bits component
		$time = ($mostSigBits >> 16) & 0xFFFFFFFFFFFF;

		// Convert milliseconds into a date/time
		$dateTime = (new \DateTime ())->setTimestamp ((int) ($time / 1000));
		return $dateTime->format ('Y-m-d H:i:s.v');
	}
}
