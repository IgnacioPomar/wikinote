<?php

namespace WikiNote;

class DbSchema
{


	private static function isQuerySucess ($mysqli, $sql)
	{
		try
		{
			if (! $queryResult = $mysqli->query ($sql))
			{
				return false;
			}
			else
			{
				$queryResult->close ();
				return true;
			}
		}
		catch (\mysqli_sql_exception $e)
		{
			return false;
		}
	}


	private static function isExecutionSucess ($mysqli, $sql)
	{
		try
		{

			if (! $queryResult = $mysqli->query ($sql))
			{
				echo "Error: Execution failied: \n";
				echo "Query: " . $sql . "\n";
				echo "Errno: " . $mysqli->errno . "\n";
				echo "Error: " . $mysqli->error . "\n";
				exit ();
			}
			else
			{
				return $queryResult === TRUE;
			}
		}
		catch (\mysqli_sql_exception $e)
		{
			echo "Error: Execution failied: \n";
			echo "Query: " . $sql . "\n";
			echo "Errno: " . $mysqli->errno . "\n";
			echo "Error: " . $mysqli->error . "\n";
			exit ();
		}
	}


	private static function getColumnType ($field)
	{
		switch ($field ['type'])
		{
			case 'text':
			case 'textarea':
			case 'json':
			case 'string':
			case 'uuid':
				$defVal = (isset ($field ['default'])) ? "'{$field ['default']}'" : 'NULL';
				break;
			default:
				$defVal = $field ['default'] ?? 'NULL';
		}

		switch ($field ['type'])
		{
			case 'auto':
				return ' int NOT NULL AUTO_INCREMENT';
				break;
			case 'bool':
				return " bool DEFAULT $defVal";
				break;
			case 'int':
				return " int DEFAULT $defVal";
				break;
			case 'double':
				return " double DEFAULT $defVal";
				break;
			case 'decimal':
				return " DECIMAL(10, 2) DEFAULT $defVal";
				break;
			case 'date':
				return " DATE DEFAULT $defVal";
				break;
			case 'datetime':
				return " DATETIME DEFAULT $defVal";
				break;
			case 'text':
			case 'textarea':
				return " text DEFAULT $defVal";
				break;
			case 'json':
				return " json DEFAULT $defVal";
				break;
			case 'string':
				$length = $field ['length'] ?? ($field ['lenght'] ?? null);
				if ($length !== null)
				{
					return " varchar({$length}) DEFAULT $defVal";
				}
				else
				{
					return " text DEFAULT $defVal";
				}
				break;
			case 'uuid':
				$length = $field ['length'] ?? ($field ['lenght'] ?? 36);
				return " char({$length}) DEFAULT $defVal";
				break;
		}
	}


	private static function createTable ($mysqli, $tableInfo)
	{
		// TODO: Add the initial set of data from a JSON
		// TODO: Consider using DEFAULT NULL as other parameter
		$sql = 'CREATE TABLE ' . self::getTableName ($tableInfo) . '(';
		$separator = '';
		foreach ($tableInfo ['fields'] as $fieldName => $field)
		{
			$sql .= $separator;
			$sql .= $fieldName . self::getColumnType ($field);

			$separator = ', ';
		}

		// Search for the primary key
		foreach ($tableInfo ['indexes'] as $index)
		{
			$isPrimary = $index ['primary'] ?? FALSE;
			if ($isPrimary)
			{
				$sep = '';
				$sql .= ', PRIMARY KEY (';
				foreach ($index ['fields'] as $idxField)
				{
					$sql .= $sep . $idxField;
					$sep = ',';
				}
				$sql .= ')';
				break;
			}
		}

		// Remaining indexes
		foreach ($tableInfo ['indexes'] as $idxNum => $index)
		{
			$isPrimary = $index ['primary'] ?? FALSE;
			if (! $isPrimary)
			{
				$idxType = 'INDEX';
				if (isset ($index ['type']))
				{
					if ($index ['type'] == 'fulltext')
					{
						$idxType = 'FULLTEXT INDEX';
					}
					else if ($index ['type'] == 'unique')
					{
						$idxType = 'UNIQUE INDEX';
					}
				}
				$sql .= ", $idxType k_$idxNum (";
				$sep = '';
				foreach ($index ['fields'] as $idxField)
				{
					$sql .= $sep . $idxField;
					$sep = ',';
				}
				$sql .= ')';
			}
		}

		$sql .= ');';

		return self::isExecutionSucess ($mysqli, $sql);
	}


	private static function alterTable ($mysqli, $tableInfo)
	{
		// TODO: Maybe in the future, alse edit types
		// TODO: Maybe in teh future, change indexes
		// TODO: Decide what to do with the old fields (they are never removed)

		// TODO: Support table version for...
		// TODO: Support of "lambdas" to update the data in the tables
		$tablename = self::getTableName ($tableInfo);

		$sql = 'SHOW COLUMNS FROM ' . $tablename;
		$queryResult = $mysqli->query ($sql);

		if (! $queryResult) return - 1;

		$existingFields = array ();
		while ($row = $queryResult->fetch_assoc ())
		{
			$existingFields [] = $row ['Field'];
		}
		$queryResult->free ();

		$sql = '';
		$sep = 'ALTER TABLE ' . $tablename . ' ';

		$count = 0;

		foreach ($tableInfo ['fields'] as $fieldName => $field)
		{
			if (! in_array ($fieldName, $existingFields))
			{
				$sql .= $sep . 'ADD COLUMN ' . $fieldName . self::getColumnType ($field);
				$sep = ', ';
				$count ++;
			}
		}

		if ($count > 0)
		{
			if (self::isExecutionSucess ($mysqli, $sql))
			{
				return 1;
			}
			else
			{
				return - 1;
			}
		}
		else
		{
			return 0;
		}
	}


	public static function getTableName (array $tableInfo)
	{
		if (isset ($tableInfo ['varSchema']))
		{
			// Defined in a global var
			return $GLOBALS [$tableInfo ['varSchema']] . '.' . $tableInfo ['tablename'];
		}
		else if (isset ($tableInfo ['fixedSchema']))
		{
			return $tableInfo ['fixedSchema'] . '.' . $tableInfo ['tablename'];
		}
		else
		{
			return $tableInfo ['tablename'];
		}
	}


	/**
	 *
	 * @param \mysqli $mysqli
	 * @param array $fileInfo
	 * @return array: table name and exit code: 1 (ok), 0 (no action), -1 (error)
	 *        
	 */
	public static function createOrUpdateTable ($mysqli, $fileInfo)
	{
		$tableInfo = json_decode (file_get_contents ($fileInfo), true);

		$sql = 'SELECT 1 FROM ' . self::getTableName ($tableInfo) . ' LIMIT 1;';

		if (self::isQuerySucess ($mysqli, $sql))
		{
			$retVal = self::alterTable ($mysqli, $tableInfo);
		}
		else
		{
			$retVal = (self::createTable ($mysqli, $tableInfo)) ? 1 : - 1;
		}

		return array ($tableInfo ['tablename'], $retVal);
	}
}
