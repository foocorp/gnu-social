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

    static function pkeyCols()
    {
        return parent::pkeyColsClass(get_called_class());
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

    public function escapedTableName()
    {
        return common_database_tablename($this->tableName());
    }

    /**
     * Returns an object by looking at the primary key column(s).
     *
     * Will require all primary key columns to be defined in an associative array
     * and ignore any keys which are not part of the primary key.
     *
     * Will NOT accept NULL values as part of primary key.
     *
     * @param   array   $vals       Must match all primary key columns for the dataobject.
     *
     * @return  Managed_DataObject  of the get_called_class() type
     * @throws  NoResultException   if no object with that primary key
     */
    static function getByPK(array $vals)
    {
        $classname = get_called_class();

        $pkey = static::pkeyCols();
        if (is_null($pkey)) {
            throw new ServerException("Failed to get primary key columns for class '{$classname}'");
        }

        $object = new $classname();
        foreach ($pkey as $col) {
            if (!array_key_exists($col, $vals)) {
                throw new ServerException("Missing primary key column '{$col}' for ".get_called_class()." among provided keys: ".implode(',', array_keys($vals)));
            } elseif (is_null($vals[$col])) {
                throw new ServerException("NULL values not allowed in getByPK for column '{$col}'");
            }
            $object->$col = $vals[$col];
        }
        if (!$object->find(true)) {
            throw new NoResultException($object);
        }
        return $object;
    }

    /**
     * Returns an object by looking at given unique key columns.
     *
     * Will NOT accept NULL values for a unique key column. Ignores non-key values.
     *
     * @param   array   $vals       All array keys which are set must be non-null.
     *
     * @return  Managed_DataObject  of the get_called_class() type
     * @throws  NoResultException   if no object with that primary key
     */
    static function getByKeys(array $vals)
    {
        $classname = get_called_class();

        $object = new $classname();

        $keys = $object->keys();
        if (is_null($keys)) {
            throw new ServerException("Failed to get key columns for class '{$classname}'");
        }

        foreach ($keys as $col) {
            if (!array_key_exists($col, $vals)) {
                continue;
            } elseif (is_null($vals[$col])) {
                throw new ServerException("NULL values not allowed in getByKeys for column '{$col}'");
            }
            $object->$col = $vals[$col];
        }
        if (!$object->find(true)) {
            throw new NoResultException($object);
        }
        return $object;
    }

    static function getByID($id)
    {
        if (empty($id)) {
            throw new EmptyIdException(get_called_class());
        }
        // getByPK throws exception if id is null
        // or if the class does not have a single 'id' column as primary key
        return static::getByPK(array('id' => $id));
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
    // FIXME: This only works with single-column primary keys so far! Beware!
    /**
     * @param DB_DataObject &$orig  Must be "instanceof" $this
     * @param string         $pid   Primary ID column (no escaping is done on column name!)
     */
    public function updateWithKeys(Managed_DataObject $orig, $pid=null)
    {
        if (!$orig instanceof $this) {
            throw new ServerException('Tried updating a DataObject with a different class than itself.');
        }

        if ($this->N <1) {
            throw new ServerException('DataObject must be the result of a query (N>=1) before updateWithKeys()');
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
                throw new ServerException("Could not UPDATE non-keys for {$this->tableName()}");
            }
            $orig->decache();
            $this->encache();

            // commit our db transaction since we won't reach the COMMIT below
            $this->query('COMMIT');
            // @FIXME return true only if something changed (otherwise 0)
            return true;
        }

        if ($pid === null) {
            $schema = static::schemaDef();
            $pid = $schema['primary key'];
            unset($schema);
        }
        $pidWhere = array();
        foreach((array)$pid as $pidCol) { 
            $pidWhere[] = sprintf('%1$s = %2$s', $pidCol, $this->_quote($orig->$pidCol));
        }
        if (empty($pidWhere)) {
            throw new ServerException('No primary ID column(s) set for updateWithKeys');
        }

        $qry = sprintf('UPDATE %1$s SET %2$s WHERE %3$s',
                            common_database_tablename($this->tableName()),
                            implode(', ', $parts),
                            implode(' AND ', $pidWhere));

        $result = $this->query($qry);
        if ($result === false) {
            common_log_db_error($this, 'UPDATE', __FILE__);
            // rollback as something bad occurred
            $this->query('ROLLBACK');
            throw new ServerException("Could not UPDATE key fields for {$this->tableName()}");
        }

        // Update non-keys too, if the previous endeavour worked.
        // The ->update call uses "$this" values for keys, that's why we can't do this until
        // the keys are updated (because they might differ from $orig and update the wrong entries).
        if ($this->update($orig) === false) {
            common_log_db_error($this, 'UPDATE', __FILE__);
            // rollback as something bad occurred
            $this->query('ROLLBACK');
            throw new ServerException("Could not UPDATE non-keys for {$this->tableName()}");
        }
        $orig->decache();
        $this->encache();

        // commit our db transaction
        $this->query('COMMIT');
        // @FIXME return true only if something changed (otherwise 0)
        return $result;
    }

    static public function beforeSchemaUpdate()
    {
        // NOOP
    }

    static function newUri(Profile $actor, Managed_DataObject $object, $created=null)
    {
        if (is_null($created)) {
            $created = common_sql_now();
        }
        return TagURI::mint(strtolower(get_called_class()).':%d:%s:%d:%s',
                                        $actor->getID(),
                                        ActivityUtils::resolveUri($object->getObjectType(), true),
                                        $object->getID(),
                                        common_date_iso8601($created));
    }
}
