<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2019, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2019, British Columbia Institute of Technology (https://bcit.ca/)
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 * @since	Version 1.3.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MySQLi Utility Class
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/user_guide/database/
 */
class CI_DB_mysqli_utility extends CI_DB_utility {

	/**
	 * List databases statement
	 *
	 * @var	string
	 */
	protected $_list_databases	= 'SHOW DATABASES';

	/**
	 * OPTIMIZE TABLE statement
	 *
	 * @var	string
	 */
	protected $_optimize_table	= 'OPTIMIZE TABLE %s';

	/**
	 * REPAIR TABLE statement
	 *
	 * @var	string
	 */
	protected $_repair_table	= 'REPAIR TABLE %s';

	// --------------------------------------------------------------------

	/**
	 * Export
	 *
	 * @param	array	$params	Preferences
	 * @return	mixed
	 */
	protected function _backup($params = array())
	{
		if (count($params) === 0)
		{
			return FALSE;
		}

		// Extract the prefs for simplicity
		extract($params);

		// Build the output
		$output = '';

		// Do we need to include a statement to disable foreign key checks?
		if ($foreign_key_checks === FALSE)
		{
			$output .= 'SET foreign_key_checks = 0;'.$newline;
		}

		foreach ( (array) $tables as $table)
		{
			// Is the table in the "ignore" list?
			if (in_array($table, (array) $ignore, TRUE))
			{
				continue;
			}

			// Get the table schema
			$query = $this->db->query('SHOW CREATE TABLE '.$this->db->escape_identifiers($this->db->database.'.'.$table));

			// No result means the table name was invalid
			if ($query === FALSE)
			{
				continue;
			}

			// Write out the table schema
			$output .= '#'.$newline.'# TABLE STRUCTURE FOR: '.$table.$newline.'#'.$newline.$newline;

			if ($add_drop === TRUE)
			{
				$output .= 'DROP TABLE IF EXISTS '.$this->db->protect_identifiers($table).';'.$newline.$newline;
			}

			$i = 0;
			$result = $query->result_array();
			foreach ($result[0] as $val)
			{
				if ($i++ % 2)
				{
					$output .= $val.';'.$newline.$newline;
				}
			}

			// If inserts are not needed we're done...
			if ($add_insert === FALSE)
			{
				continue;
			}

			// Generated values belong in the DDL, never in an INSERT value list.
			// Explicit names also retain ordinary invisible columns omitted by SELECT *.
			$columns = $this->_backup_writable_columns($table);
			$selection = count($columns) === 0 ? '1 AS ci_backup_row' : implode(', ', array_map(array($this->db, 'escape_identifiers'), $columns));
			$query = $this->db->query('SELECT '.$selection.' FROM '.$this->db->protect_identifiers($table));
			if ($query === FALSE)
			{
				throw new RuntimeException('database_backup_data_failed');
			}

			// Some engines permit constant generated-only tables. Preserve row count
			// without supplying a non-DEFAULT value for any generated column.
			if (count($columns) === 0)
			{
				foreach ($query->result_array() as $row)
				{
					$output .= 'INSERT INTO '.$this->db->protect_identifiers($table).' () VALUES ();'.$newline;
				}
				continue;
			}

			if ($query->num_rows() === 0)
			{
				continue;
			}

			// Fetch the field names and determine if the field is an
			// integer type. We use this info to decide whether to
			// surround the data with quotes or not

			$i = 0;
			$field_str = '';
			$is_int = array();
			while ($field = $query->result_id->fetch_field())
			{
				// Most versions of MySQL store timestamp as a string
				$is_int[$i] = in_array($field->type, array(MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_INT24, MYSQLI_TYPE_LONG), TRUE);

				// Create a string of field names
				$field_str .= $this->db->escape_identifiers($field->name).', ';
				$i++;
			}

			// Trim off the end comma
			$field_str = preg_replace('/, $/' , '', $field_str);

			// Build the insert string
			foreach ($query->result_array() as $row)
			{
				$val_str = '';

				$i = 0;
				foreach ($row as $v)
				{
					// Is the value NULL?
					if ($v === NULL)
					{
						$val_str .= 'NULL';
					}
					else
					{
						// Escape the data if it's not an integer
						$val_str .= ($is_int[$i] === FALSE) ? $this->db->escape($v) : $v;
					}

					// Append a comma
					$val_str .= ', ';
					$i++;
				}

				// Remove the comma at the end of the string
				$val_str = preg_replace('/, $/' , '', $val_str);

				// Build the INSERT string
				$output .= 'INSERT INTO '.$this->db->protect_identifiers($table).' ('.$field_str.') VALUES ('.$val_str.');'.$newline;
			}

			$output .= $newline.$newline;
		}

		// Do we need to include a statement to re-enable foreign key checks?
		if ($foreign_key_checks === FALSE)
		{
			$output .= 'SET foreign_key_checks = 1;'.$newline;
		}

		return $output;
	}

	/** Fail closed if column metadata cannot prove the writable INSERT projection. */
	protected function _backup_writable_columns($table)
	{
		$flags = array();
		foreach (array('db_debug', 'save_queries') as $flag)
		{
			if (property_exists($this->db, $flag))
			{
				$flags[$flag] = $this->db->$flag;
				$this->db->$flag = FALSE;
			}
		}
		try
		{
			$query = $this->db->query('SHOW FULL COLUMNS FROM '.$this->db->escape_identifiers($this->db->database.'.'.$table));
			if ($query === FALSE) { throw new RuntimeException('metadata unavailable'); }
			$metadata = $query->result_array();
			if (count($metadata) === 0) { throw new RuntimeException('metadata missing'); }
			$columns = array();
			$seen = array();
			foreach ($metadata as $column)
			{
				if (!isset($column['Field'], $column['Extra']) || !is_string($column['Field']) || $column['Field'] === ''
					|| !is_string($column['Extra']) || isset($seen[$column['Field']]))
				{
					throw new RuntimeException('metadata malformed');
				}
				$seen[$column['Field']] = TRUE;
				$generated = preg_match('/\b(?:VIRTUAL|STORED|PERSISTENT)\s+GENERATED\b/i', $column['Extra']);
				if (!$generated && preg_match('/\bGENERATED\b/i', $column['Extra']))
				{
					throw new RuntimeException('generation metadata unsupported');
				}
				// DEFAULT_GENERATED denotes an ordinary expression default, not a
				// generated column, and must remain in the INSERT projection.
				if (!$generated) { $columns[] = $column['Field']; }
			}
			return $columns;
		}
		catch (Throwable $error)
		{
			// Never emit a partial dump, SQL or database error details on failure.
			throw new RuntimeException('database_backup_metadata_failed');
		}
		finally
		{
			foreach ($flags as $flag => $value) { $this->db->$flag = $value; }
		}
	}

}
