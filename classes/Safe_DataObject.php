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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Extended DB_DataObject to improve a few things:
 * - free global resources from destructor
 * - remove bogus global references from serialized objects
 * - don't leak memory when loading already-used .ini files
 *   (eg when using the same schema on thousands of databases)
 */
class Safe_DataObject extends GS_DataObject
{
    /**
     * Destructor to free global memory resources associated with
     * this data object when it's unset or goes out of scope.
     * DB_DataObject doesn't do this yet by itself.
     */

    function __destruct()
    {
        $this->free();
        if (method_exists('DB_DataObject', '__destruct')) {
            parent::__destruct();
        }
    }

    /**
     * Magic function called at clone() time.
     *
     * We use this to drop connection with some global resources.
     * This supports the fairly common pattern where individual
     * items being read in a loop via a single object are cloned
     * for individual processing, then fall out of scope when the
     * loop comes around again.
     *
     * As that triggers the destructor, we want to make sure that
     * the original object doesn't have its database result killed.
     * It will still be freed properly when the original object
     * gets destroyed.
     */
    function __clone()
    {
        $this->_DB_resultid = false;
    }

    /**
     * Magic function called at serialize() time.
     *
     * We use this to drop a couple process-specific references
     * from DB_DataObject which can cause trouble in future
     * processes.
     *
     * @return array of variable names to include in serialization.
     */
    function __sleep()
    {
        $vars = array_keys(get_object_vars($this));
        $skip = array('_DB_resultid', '_link_loaded');
        return array_diff($vars, $skip);
    }

    /**
     * Magic function called at unserialize() time.
     *
     * Clean out some process-specific variables which might
     * be floating around from a previous process's cached
     * objects.
     *
     * Old cached objects may still have them.
     */
    function __wakeup()
    {
        // Refers to global state info from a previous process.
        // Clear this out so we don't accidentally break global
        // state in *this* process.
        $this->_DB_resultid = null;
        // We don't have any local DBO refs, so clear these out.
        $this->_link_loaded = false;
    }

    /**
     * Magic function called when someone attempts to call a method
     * that doesn't exist. DB_DataObject uses this to implement
     * setters and getters for fields, but neglects to throw an error
     * when you just misspell an actual method name. This leads to
     * silent failures which can cause all kinds of havoc.
     *
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    function __call($method, $params)
    {
        $return = null;
        // Yes, that's _call with one underscore, which does the
        // actual implementation.
        if ($this->_call($method, $params, $return)) {
            return $return;
        } else {
            // Low level exception. No need for i18n as discussed with Brion.
            throw new Exception('Call to undefined method ' .
                get_class($this) . '::' . $method);
        }
    }

    /**
     * Work around memory-leak bugs...
     * Had to copy-paste the whole function in order to patch a couple lines of it.
     * Would be nice if this code was better factored.
     *
     * @param optional string  name of database to assign / read
     * @param optional array   structure of database, and keys
     * @param optional array  table links
     *
     * @access public
     * @return true or PEAR:error on wrong paramenters.. or false if no file exists..
     *              or the array(tablename => array(column_name=>type)) if called with 1 argument.. (databasename)
     */
    function databaseStructure()
    {
        global $_DB_DATAOBJECT;

        // Assignment code

        if ($args = func_get_args()) {

            if (count($args) == 1) {

                // this returns all the tables and their structure..
                if (!empty($_DB_DATAOBJECT['CONFIG']['debug'])) {
                    $this->debug("Loading Generator as databaseStructure called with args",1);
                }

                $x = new DB_DataObject;
                $x->_database = $args[0];
                $this->_connect();
                $DB = &$_DB_DATAOBJECT['CONNECTIONS'][$this->_database_dsn_md5];

                $tables = $DB->getListOf('tables');
                class_exists('DB_DataObject_Generator') ? '' :
                    require_once 'DB/DataObject/Generator.php';

                foreach($tables as $table) {
                    $y = new DB_DataObject_Generator;
                    $y->fillTableSchema($x->_database,$table);
                }
                return $_DB_DATAOBJECT['INI'][$x->_database];
            } else {

                $_DB_DATAOBJECT['INI'][$args[0]] = isset($_DB_DATAOBJECT['INI'][$args[0]]) ?
                    $_DB_DATAOBJECT['INI'][$args[0]] + $args[1] : $args[1];

                if (isset($args[1])) {
                    $_DB_DATAOBJECT['LINKS'][$args[0]] = isset($_DB_DATAOBJECT['LINKS'][$args[0]]) ?
                        $_DB_DATAOBJECT['LINKS'][$args[0]] + $args[2] : $args[2];
                }
                return true;
            }

        }
        if (!$this->_database) {
            $this->_connect();
        }

        // loaded already?
        if (!empty($_DB_DATAOBJECT['INI'][$this->_database])) {

            // database loaded - but this is table is not available..
            if (
                    empty($_DB_DATAOBJECT['INI'][$this->_database][$this->tableName()])
                    && !empty($_DB_DATAOBJECT['CONFIG']['proxy'])
                ) {
                if (!empty($_DB_DATAOBJECT['CONFIG']['debug'])) {
                    $this->debug("Loading Generator to fetch Schema",1);
                }
                class_exists('DB_DataObject_Generator') ? '' :
                    require_once 'DB/DataObject/Generator.php';


                $x = new DB_DataObject_Generator;
                $x->fillTableSchema($this->_database,$this->tableName());
            }
            return true;
        }

        if (empty($_DB_DATAOBJECT['CONFIG'])) {
            self::_loadConfig();
        }

        // if you supply this with arguments, then it will take those
        // as the database and links array...

        $schemas = isset($_DB_DATAOBJECT['CONFIG']['schema_location']) ?
            array("{$_DB_DATAOBJECT['CONFIG']['schema_location']}/{$this->_database}.ini") :
            array() ;

        if (isset($_DB_DATAOBJECT['CONFIG']["ini_{$this->_database}"])) {
            $schemas = is_array($_DB_DATAOBJECT['CONFIG']["ini_{$this->_database}"]) ?
                $_DB_DATAOBJECT['CONFIG']["ini_{$this->_database}"] :
                explode(PATH_SEPARATOR,$_DB_DATAOBJECT['CONFIG']["ini_{$this->_database}"]);
        }

        /* BEGIN CHANGED FROM UPSTREAM */
        $_DB_DATAOBJECT['INI'][$this->_database] = $this->parseIniFiles($schemas);
        /* END CHANGED FROM UPSTREAM */

        // now have we loaded the structure..

        if (!empty($_DB_DATAOBJECT['INI'][$this->_database][$this->tableName()])) {
            return true;
        }
        // - if not try building it..
        if (!empty($_DB_DATAOBJECT['CONFIG']['proxy'])) {
            class_exists('DB_DataObject_Generator') ? '' :
                require_once 'DB/DataObject/Generator.php';

            $x = new DB_DataObject_Generator;
            $x->fillTableSchema($this->_database,$this->tableName());
            // should this fail!!!???
            return true;
        }
        $this->debug("Cant find database schema: {$this->_database}/{$this->tableName()} \n".
                    "in links file data: " . print_r($_DB_DATAOBJECT['INI'],true),"databaseStructure",5);
        // we have to die here!! - it causes chaos if we don't (including looping forever!)
        // Low level exception. No need for i18n as discussed with Brion.
        $this->raiseError( "Unable to load schema for database and table (turn debugging up to 5 for full error message)", DB_DATAOBJECT_ERROR_INVALIDARGS, PEAR_ERROR_DIE);
        return false;
    }

    /** For parseIniFiles */
    protected static $iniCache = array();

    /**
     * When switching site configurations, DB_DataObject was loading its
     * .ini files over and over, leaking gobs of memory.
     * This refactored helper function uses a local cache of .ini files
     * to minimize the leaks.
     *
     * @param array of .ini file names $schemas
     * @return array
     */
    protected function parseIniFiles(array $schemas)
    {
        $key = implode("|", $schemas);
        if (!isset(Safe_DataObject::$iniCache[$key])) {
            $data = array();
            foreach ($schemas as $ini) {
                if (file_exists($ini) && is_file($ini)) {
                    $data = array_merge($data, parse_ini_file($ini, true));

                    if (!empty($_DB_DATAOBJECT['CONFIG']['debug'])) {
                        if (!is_readable ($ini)) {
                            $this->debug("ini file is not readable: $ini","databaseStructure",1);
                        } else {
                            $this->debug("Loaded ini file: $ini","databaseStructure",1);
                        }
                    }
                } else {
                    if (!empty($_DB_DATAOBJECT['CONFIG']['debug'])) {
                        $this->debug("Missing ini file: $ini","databaseStructure",1);
                    }
                }
            }
            Safe_DataObject::$iniCache[$key] = $data;
        }

        return Safe_DataObject::$iniCache[$key];
    }
}
