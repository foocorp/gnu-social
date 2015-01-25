<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Wrapper for Memcached_DataObject which knows its own schema definition.
 * Builds its own damn settings from a schema definition.
 *
 * @author Brion Vibber <brion@status.net>
 */
abstract class Managed_DataObject extends Memcached_DataObject
{
    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Get an instance by key
     *
     * @param string $k Key to use to lookup (usually 'id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return get_called_class() object if found, or null for no hits
     *
     */
    static function getKV($k,$v=NULL)
    {
        return parent::getClassKV(get_called_class(), $k, $v);
    }

    /**
     * Get an instance by compound key
     *
     * This is a utility method to get a single instance with a given set of
     * key-value pairs. Usually used for the primary key for a compound key; thus
     * the name.
     *
     * @param array $kv array of key-value mappings
     *
     * @return get_called_class() object if found, or null for no hits
     *
     */
    static function pkeyGet(array $kv)
    {
        return parent::pkeyGetClass(get_called_class(), $kv);
    }

    /**
     * Get multiple items from the database by key
     *
     * @param string  $keyCol    name of column for key
     * @param array   $keyVals   key values to fetch
     * @param boolean $skipNulls return only non-null results?
     *
     * @return array Array of objects, in order
     */
	static function multiGet($keyCol, array $keyVals, $skipNulls=true)
	{
	    return parent::multiGetClass(get_called_class(), $keyCol, $keyVals, $skipNulls);
	}

    /**
     * Get multiple items from the database by key
     *
     * @param string  $keyCol    name of column for key
     * @param array   $keyVals   key values to fetch
     * @param array   $otherCols Other columns to hold fixed
     *
     * @return array Array mapping $keyVals to objects, or null if not found
     */
	static function pivotGet($keyCol, array $keyVals, array $otherCols=array())
	{
	    return parent::pivotGetClass(get_called_class(), $keyCol, $keyVals, $otherCols);
	}

    /**
     * Get a multi-instance object
     *
     * This is a utility method to get multiple instances with a given set of
     * values for a specific column.
     *
     * @param string $keyCol  key column name
     * @param array  $keyVals array of key values
     *
     * @return get_called_class() object with multiple instances if found,
     *         Exception is thrown when no entries are found.
     *
     */
    static function listFind($keyCol, array $keyVals)
    {
        return parent::listFindClass(get_called_class(), $keyCol, $keyVals);
    }

    /**
     * Get a multi-instance object separated into an array
     *
     * This is a utility method to get multiple instances with a given set of
     * values for a specific key column. Usually used for the primary key when
     * multiple values are desired. Result is an array.
     *
     * @param string $keyCol  key column name
     * @param array  $keyVals array of key values
     *
     * @return array with an get_called_class() object for each $keyVals entry
     *
     */
    static function listGet($keyCol, array $keyVals)
    {
        return parent::listGetClass(get_called_class(), $keyCol, $keyVals);
    }

    /**
     * get/set an associative array of table columns
     *
     * @access public
     * @return array (associative)
     */
    public function table()
    {
        $table = static::schemaDef();
        return array_map(array($this, 'columnBitmap'), $table['fields']);
    }

    /**
     * get/set an  array of table primary keys
     *
     * Key info is pulled from the table definition array.
     * 
     * @access private
     * @return array
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * Get a sequence key
     *
     * Returns the first serial column defined in the table, if any.
     *
     * @access private
     * @return array (column,use_native,sequence_name)
     */

    function sequenceKey()
    {
        $table = static::schemaDef();
        foreach ($table['fields'] as $name => $column) {
            if ($column['type'] == 'serial') {
                // We have a serial/autoincrement column.
                // Declare it to be a native sequence!
                return array($name, true, false);
            }
        }

        // No sequence key on this table.
        return array(false, false, false);
    }

    /**
     * Return key definitions for DB_DataObject and Memcache_DataObject.
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        $table = static::schemaDef();
        $keys = array();

        if (!empty($table['unique keys'])) {
            foreach ($table['unique keys'] as $idx => $fields) {
                foreach ($fields as $name) {
                    $keys[$name] = 'U';
                }
            }
        }

        if (!empty($table['primary key'])) {
            foreach ($table['primary key'] as $name) {
                $keys[$name] = 'K';
            }
        }
        return $keys;
    }

    /**
     * Build the appropriate DB_DataObject bitfield map for this field.
     *
     * @param array $column
     * @return int
     */
    function columnBitmap($column)
    {
        $type = $column['type'];

        // For quoting style...
        $intTypes = array('int',
                          'integer',
                          'float',
                          'serial',
                          'numeric');
        if (in_array($type, $intTypes)) {
            $style = DB_DATAOBJECT_INT;
        } else {
            $style = DB_DATAOBJECT_STR;
        }

        // Data type formatting style...
        $formatStyles = array('blob' => DB_DATAOBJECT_BLOB,
                              'text' => DB_DATAOBJECT_TXT,
                              'date' => DB_DATAOBJECT_DATE,
                              'time' => DB_DATAOBJECT_TIME,
                              'datetime' => DB_DATAOBJECT_DATE | DB_DATAOBJECT_TIME,
                              'timestamp' => DB_DATAOBJECT_MYSQLTIMESTAMP);

        if (isset($formatStyles[$type])) {
            $style |= $formatStyles[$type];
        }

        // Nullable?
        if (!empty($column['not null'])) {
            $style |= DB_DATAOBJECT_NOTNULL;
        }

        return $style;
    }

    function links()
    {
        $links = array();

        $table = static::schemaDef();

        foreach ($table['foreign keys'] as $keyname => $keydef) {
            if (count($keydef) == 2 && is_string($keydef[0]) && is_array($keydef[1]) && count($keydef[1]) == 1) {
                if (isset($keydef[1][0])) {
                    $links[$keydef[1][0]] = $keydef[0].':'.$keydef[1][1];
                }
            }
        }
        return $links;
    }

    /**
     * Return a list of all primary/unique keys / vals that will be used for
     * caching. This will understand compound unique keys, which
     * Memcached_DataObject doesn't have enough info to handle properly.
     *
     * @return array of strings
     */
    function _allCacheKeys()
    {
        $table = static::schemaDef();
        $ckeys = array();

        if (!empty($table['unique keys'])) {
            $keyNames = $table['unique keys'];
            foreach ($keyNames as $idx => $fields) {
                $val = array();
                foreach ($fields as $name) {
                    $val[$name] = self::valueString($this->$name);
                }
                $ckeys[] = self::multicacheKey($this->tableName(), $val);
            }
        }

        if (!empty($table['primary key'])) {
            $fields = $table['primary key'];
            $val = array();
            foreach ($fields as $name) {
                $val[$name] = self::valueString($this->$name);
            }
            $ckeys[] = self::multicacheKey($this->tableName(), $val);
        }
        return $ckeys;
    }

    /**
     * Returns an ID, checked that it is set and reasonably valid
     *
     * If this dataobject uses a special id field (not 'id'), just
     * implement your ID getting method in the child class.
     *
     * @return int ID of dataobject
     * @throws Exception (when ID is not available or not set yet)
     */
    public function getID()
    {
        // FIXME: Make these exceptions more specific (their own classes)
        if (!isset($this->id)) {
            throw new Exception('No ID set.');
        } elseif (empty($this->id)) {
            throw new Exception('Empty ID for object! (not inserted yet?).');
        }

        // FIXME: How about forcing to return an int? Or will that overflow eventually?
        return $this->id;
    }

    // 'update' won't write key columns, so we have to do it ourselves.
    // This also automatically calls "update" _before_ it sets the keys.
    public function updateWithKeys(&$orig)
    {
        if (!$orig instanceof $this) {
            throw new ServerException('Tried updating a DataObject with a different class than itself.');
        }

        // do it in a transaction
        $this->query('BEGIN');

        $parts = array();
        foreach ($this->keys() as $k) {
            if (strcmp($this->$k, $orig->$k) != 0) {
                $parts[] = $k . ' = ' . $this->_quote($this->$k);
            }
        }
        if (count($parts) == 0) {
            // No changes to keys, it's safe to run ->update(...)
            if ($this->update($orig) === false) {
                common_log_db_error($this, 'UPDATE', __FILE__);
                // rollback as something bad occurred
                $this->query('ROLLBACK');
                throw new ServerException("Could not UPDATE non-keys for {$this->__table}");
            }
            return true;
        }
        $toupdate = implode(', ', $parts);

        $table = common_database_tablename($this->tableName());
        $qry = 'UPDATE ' . $table . ' SET ' . $toupdate .
          ' WHERE id = ' . $this->getID();
        $orig->decache();
        $result = $this->query($qry);
        if ($result === false) {
            common_log_db_error($this, 'UPDATE', __FILE__);
            // rollback as something bad occurred
            $this->query('ROLLBACK');
            throw new ServerException("Could not UPDATE key fields for {$this->__table}");
        }

        // Update non-keys too, if the previous endeavour worked.
        // The ->update call uses "$this" values for keys, that's why we can't do this until
        // the keys are updated (because they might differ from $orig and update the wrong entries).
        if ($this->update($orig) === false) {
            common_log_db_error($this, 'UPDATE', __FILE__);
            // rollback as something bad occurred
            $this->query('ROLLBACK');
            throw new ServerException("Could not UPDATE non-keys for {$this->__table}");
        }
        $this->encache();

        // commit our db transaction
        $this->query('COMMIT');
        return $result;
    }
}
