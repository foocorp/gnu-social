<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class Memcached_DataObject extends Safe_DataObject
{
    /**
     * Wrapper for DB_DataObject's static lookup using memcached
     * as backing instead of an in-process cache array.
     *
     * @param string $cls classname of object type to load
     * @param mixed $k key field name, or value for primary key
     * @param mixed $v key field value, or leave out for primary key lookup
     * @return mixed Memcached_DataObject subtype or false
     */
    function &staticGet($cls, $k, $v=null)
    {
        if (is_null($v)) {
            $v = $k;
            # XXX: HACK!
            $i = new $cls;
            $keys = $i->keys();
            $k = $keys[0];
            unset($i);
        }
        $i = Memcached_DataObject::getcached($cls, $k, $v);
        if ($i === false) { // false == cache miss
            $i = DB_DataObject::factory($cls);
            if (empty($i)) {
                $i = false;
                return $i;
            }
            $result = $i->get($k, $v);
            if ($result) {
                // Hit!
                $i->encache();
            } else {
                // save the fact that no such row exists
                $c = self::memcache();
                if (!empty($c)) {
                    $ck = self::cachekey($cls, $k, $v);
                    $c->set($ck, null);
                }
                $i = false;
            }
        }
        return $i;
    }

    /**
     * @fixme Should this return false on lookup fail to match staticGet?
     */
    function pkeyGet($cls, $kv)
    {
        $i = Memcached_DataObject::multicache($cls, $kv);
        if ($i !== false) { // false == cache miss
            return $i;
        } else {
            $i = DB_DataObject::factory($cls);
            if (empty($i)) {
                return false;
            }
            foreach ($kv as $k => $v) {
                $i->$k = $v;
            }
            if ($i->find(true)) {
                $i->encache();
            } else {
                $i = null;
                $c = self::memcache();
                if (!empty($c)) {
                    $ck = self::multicacheKey($cls, $kv);
                    $c->set($ck, null);
                }
            }
            return $i;
        }
    }

    function insert()
    {
        $result = parent::insert();
        if ($result) {
            $this->fixupTimestamps();
            $this->encache(); // in case of cached negative lookups
        }
        return $result;
    }

    function update($orig=null)
    {
        if (is_object($orig) && $orig instanceof Memcached_DataObject) {
            $orig->decache(); # might be different keys
        }
        $result = parent::update($orig);
        if ($result) {
            $this->fixupTimestamps();
            $this->encache();
        }
        return $result;
    }

    function delete()
    {
        $this->decache(); # while we still have the values!
        return parent::delete();
    }

    static function memcache() {
        return common_memcache();
    }

    static function cacheKey($cls, $k, $v) {
        if (is_object($cls) || is_object($k) || (is_object($v) && !($v instanceof DB_DataObject_Cast))) {
            $e = new Exception();
            common_log(LOG_ERR, __METHOD__ . ' object in param: ' .
                str_replace("\n", " ", $e->getTraceAsString()));
        }
        $vstr = self::valueString($v);
        return common_cache_key(strtolower($cls).':'.$k.':'.$vstr);
    }

    static function getcached($cls, $k, $v) {
        $c = Memcached_DataObject::memcache();
        if (!$c) {
            return false;
        } else {
            $obj = $c->get(Memcached_DataObject::cacheKey($cls, $k, $v));
            if (0 == strcasecmp($cls, 'User')) {
                // Special case for User
                if (is_object($obj) && is_object($obj->id)) {
                    common_log(LOG_ERR, "User " . $obj->nickname . " was cached with User as ID; deleting");
                    $c->delete(Memcached_DataObject::cacheKey($cls, $k, $v));
                    return false;
                }
            }
            return $obj;
        }
    }

    function keyTypes()
    {
        // ini-based classes return number-indexed arrays. handbuilt
        // classes return column => keytype. Make this uniform.

        $keys = $this->keys();

        $keyskeys = array_keys($keys);

        if (is_string($keyskeys[0])) {
            return $keys;
        }

        global $_DB_DATAOBJECT;
        if (!isset($_DB_DATAOBJECT['INI'][$this->_database][$this->__table."__keys"])) {
            $this->databaseStructure();

        }
        return $_DB_DATAOBJECT['INI'][$this->_database][$this->__table."__keys"];
    }

    function encache()
    {
        $c = $this->memcache();

        if (!$c) {
            return false;
        } else if ($this->tableName() == 'user' && is_object($this->id)) {
            // Special case for User bug
            $e = new Exception();
            common_log(LOG_ERR, __METHOD__ . ' caching user with User object as ID ' .
                       str_replace("\n", " ", $e->getTraceAsString()));
            return false;
        } else {
		$keys = $this->_allCacheKeys();

		foreach ($keys as $key) {
		    $c->set($key, $this);
		}
        }
    }

    function decache()
    {
        $c = $this->memcache();

        if (!$c) {
            return false;
        }

        $keys = $this->_allCacheKeys();

        foreach ($keys as $key) {
            $c->delete($key, $this);
        }
    }

    function _allCacheKeys()
    {
        $ckeys = array();

        $types = $this->keyTypes();
        ksort($types);

        $pkey = array();
        $pval = array();

        foreach ($types as $key => $type) {

            assert(!empty($key));

            if ($type == 'U') {
                if (empty($this->$key)) {
                    continue;
                }
                $ckeys[] = $this->cacheKey($this->tableName(), $key, self::valueString($this->$key));
            } else if ($type == 'K' || $type == 'N') {
                $pkey[] = $key;
                $pval[] = self::valueString($this->$key);
            } else {
                // Low level exception. No need for i18n as discussed with Brion.
                throw new Exception("Unknown key type $key => $type for " . $this->tableName());
            }
        }

        assert(count($pkey) > 0);

        // XXX: should work for both compound and scalar pkeys
        $pvals = implode(',', $pval);
        $pkeys = implode(',', $pkey);

        $ckeys[] = $this->cacheKey($this->tableName(), $pkeys, $pvals);

        return $ckeys;
    }

    function multicache($cls, $kv)
    {
        ksort($kv);
        $c = self::memcache();
        if (!$c) {
            return false;
        } else {
            return $c->get(self::multicacheKey($cls, $kv));
        }
    }

    static function multicacheKey($cls, $kv)
    {
        ksort($kv);
        $pkeys = implode(',', array_keys($kv));
        $pvals = implode(',', array_values($kv));
        return self::cacheKey($cls, $pkeys, $pvals);
    }

    function getSearchEngine($table)
    {
        require_once INSTALLDIR.'/lib/search_engines.php';
        static $search_engine;
        if (!isset($search_engine)) {
            if (Event::handle('GetSearchEngine', array($this, $table, &$search_engine))) {
                if ('mysql' === common_config('db', 'type')) {
                    $type = common_config('search', 'type');
                    if ($type == 'like') {
                        $search_engine = new MySQLLikeSearch($this, $table);
                    } else if ($type == 'fulltext') {
                        $search_engine = new MySQLSearch($this, $table);
                    } else {
                        // Low level exception. No need for i18n as discussed with Brion.
                        throw new ServerException('Unknown search type: ' . $type);
                    }
                } else {
                    $search_engine = new PGSearch($this, $table);
                }
            }
        }
        return $search_engine;
    }

    static function cachedQuery($cls, $qry, $expiry=3600)
    {
        $c = Memcached_DataObject::memcache();
        if (!$c) {
            $inst = new $cls();
            $inst->query($qry);
            return $inst;
        }
        $key_part = common_keyize($cls).':'.md5($qry);
        $ckey = common_cache_key($key_part);
        $stored = $c->get($ckey);

        if ($stored !== false) {
            return new ArrayWrapper($stored);
        }

        $inst = new $cls();
        $inst->query($qry);
        $cached = array();
        while ($inst->fetch()) {
            $cached[] = clone($inst);
        }
        $inst->free();
        $c->set($ckey, $cached, Cache::COMPRESSED, $expiry);
        return new ArrayWrapper($cached);
    }

    /**
     * sends query to database - this is the private one that must work
     *   - internal functions use this rather than $this->query()
     *
     * Overridden to do logging.
     *
     * @param  string  $string
     * @access private
     * @return mixed none or PEAR_Error
     */
    function _query($string)
    {
        if (common_config('db', 'annotate_queries')) {
            $string = $this->annotateQuery($string);
        }

        $start = microtime(true);
        $result = parent::_query($string);
        $delta = microtime(true) - $start;

        $limit = common_config('db', 'log_slow_queries');
        if (($limit > 0 && $delta >= $limit) || common_config('db', 'log_queries')) {
            $clean = $this->sanitizeQuery($string);
            common_log(LOG_DEBUG, sprintf("DB query (%0.3fs): %s", $delta, $clean));
        }
        return $result;
    }

    /**
     * Find the first caller in the stack trace that's not a
     * low-level database function and add a comment to the
     * query string. This should then be visible in process lists
     * and slow query logs, to help identify problem areas.
     *
     * Also marks whether this was a web GET/POST or which daemon
     * was running it.
     *
     * @param string $string SQL query string
     * @return string SQL query string, with a comment in it
     */
    function annotateQuery($string)
    {
        $ignore = array('annotateQuery',
                        '_query',
                        'query',
                        'get',
                        'insert',
                        'delete',
                        'update',
                        'find');
        $ignoreStatic = array('staticGet',
                              'pkeyGet',
                              'cachedQuery');
        $here = get_class($this); // if we get confused
        $bt = debug_backtrace();

        // Find the first caller that's not us?
        foreach ($bt as $frame) {
            $func = $frame['function'];
            if (isset($frame['type']) && $frame['type'] == '::') {
                if (in_array($func, $ignoreStatic)) {
                    continue;
                }
                $here = $frame['class'] . '::' . $func;
                break;
            } else if (isset($frame['type']) && $frame['type'] == '->') {
                if ($frame['object'] === $this && in_array($func, $ignore)) {
                    continue;
                }
                if (in_array($func, $ignoreStatic)) {
                    continue; // @fixme this shouldn't be needed?
                }
                $here = get_class($frame['object']) . '->' . $func;
                break;
            }
            $here = $func;
            break;
        }

        if (php_sapi_name() == 'cli') {
            $context = basename($_SERVER['PHP_SELF']);
        } else {
            $context = $_SERVER['REQUEST_METHOD'];
        }

        // Slip the comment in after the first command,
        // or DB_DataObject gets confused about handling inserts and such.
        $parts = explode(' ', $string, 2);
        $parts[0] .= " /* $context $here */";
        return implode(' ', $parts);
    }

    // Sanitize a query for logging
    // @fixme don't trim spaces in string literals
    function sanitizeQuery($string)
    {
        $string = preg_replace('/\s+/', ' ', $string);
        $string = trim($string);
        return $string;
    }

    // We overload so that 'SET NAMES "utf8"' is called for
    // each connection

    function _connect()
    {
        global $_DB_DATAOBJECT;

        $sum = $this->_getDbDsnMD5();

        if (!empty($_DB_DATAOBJECT['CONNECTIONS'][$sum]) &&
            !PEAR::isError($_DB_DATAOBJECT['CONNECTIONS'][$sum])) {
            $exists = true;
        } else {
            $exists = false;
       }

        // @fixme horrible evil hack!
        //
        // In multisite configuration we don't want to keep around a separate
        // connection for every database; we could end up with thousands of
        // connections open per thread. In an ideal world we might keep
        // a connection per server and select different databases, but that'd
        // be reliant on having the same db username/pass as well.
        //
        // MySQL connections are cheap enough we're going to try just
        // closing out the old connection and reopening when we encounter
        // a new DSN.
        //
        // WARNING WARNING if we end up actually using multiple DBs at a time
        // we'll need some fancier logic here.
        if (!$exists && !empty($_DB_DATAOBJECT['CONNECTIONS']) && php_sapi_name() == 'cli') {
            foreach ($_DB_DATAOBJECT['CONNECTIONS'] as $index => $conn) {
                if (!empty($conn)) {
                    $conn->disconnect();
                }
                unset($_DB_DATAOBJECT['CONNECTIONS'][$index]);
            }
        }

        $result = parent::_connect();

        if ($result && !$exists) {
            $DB = &$_DB_DATAOBJECT['CONNECTIONS'][$this->_database_dsn_md5];
            if (common_config('db', 'type') == 'mysql' &&
                common_config('db', 'utf8')) {
                $conn = $DB->connection;
                if (!empty($conn)) {
                    if ($DB instanceof DB_mysqli) {
                        mysqli_set_charset($conn, 'utf8');
                    } else if ($DB instanceof DB_mysql) {
                        mysql_set_charset('utf8', $conn);
                    }
                }
            }
        }

        return $result;
    }

    // XXX: largely cadged from DB_DataObject

    function _getDbDsnMD5()
    {
        if ($this->_database_dsn_md5) {
            return $this->_database_dsn_md5;
        }

        $dsn = $this->_getDbDsn();

        if (is_string($dsn)) {
            $sum = md5($dsn);
        } else {
            /// support array based dsn's
            $sum = md5(serialize($dsn));
        }

        return $sum;
    }

    function _getDbDsn()
    {
        global $_DB_DATAOBJECT;

        if (empty($_DB_DATAOBJECT['CONFIG'])) {
            DB_DataObject::_loadConfig();
        }

        $options = &$_DB_DATAOBJECT['CONFIG'];

        // if the databse dsn dis defined in the object..

        $dsn = isset($this->_database_dsn) ? $this->_database_dsn : null;

        if (!$dsn) {

            if (!$this->_database) {
                $this->_database = isset($options["table_{$this->__table}"]) ? $options["table_{$this->__table}"] : null;
            }

            if ($this->_database && !empty($options["database_{$this->_database}"]))  {
                $dsn = $options["database_{$this->_database}"];
            } else if (!empty($options['database'])) {
                $dsn = $options['database'];
            }
        }

        if (!$dsn) {
            // TRANS: Exception thrown when database name or Data Source Name could not be found.
            throw new Exception(_("No database name or DSN found anywhere."));
        }

        return $dsn;
    }

    static function blow()
    {
        $c = self::memcache();

        if (empty($c)) {
            return false;
        }

        $args = func_get_args();

        $format = array_shift($args);

        $keyPart = vsprintf($format, $args);

        $cacheKey = common_cache_key($keyPart);

        return $c->delete($cacheKey);
    }

    function fixupTimestamps()
    {
        // Fake up timestamp columns
        $columns = $this->table();
        foreach ($columns as $name => $type) {
            if ($type & DB_DATAOBJECT_MYSQLTIMESTAMP) {
                $this->$name = common_sql_now();
            }
        }
    }

    function debugDump()
    {
        common_debug("debugDump: " . common_log_objstring($this));
    }

    function raiseError($message, $type = null, $behaviour = null)
    {
        $id = get_class($this);
        if (!empty($this->id)) {
            $id .= ':' . $this->id;
        }
        if ($message instanceof PEAR_Error) {
            $message = $message->getMessage();
        }
        // Low level exception. No need for i18n as discussed with Brion.
        throw new ServerException("[$id] DB_DataObject error [$type]: $message");
    }

    static function cacheGet($keyPart)
    {
        $c = self::memcache();

        if (empty($c)) {
            return false;
        }

        $cacheKey = common_cache_key($keyPart);

        return $c->get($cacheKey);
    }

    static function cacheSet($keyPart, $value, $flag=null, $expiry=null)
    {
        $c = self::memcache();

        if (empty($c)) {
            return false;
        }

        $cacheKey = common_cache_key($keyPart);

        return $c->set($cacheKey, $value, $flag, $expiry);
    }

    static function valueString($v)
    {
        $vstr = null;
        if (is_object($v) && $v instanceof DB_DataObject_Cast) {
            switch ($v->type) {
            case 'date':
                $vstr = $v->year . '-' . $v->month . '-' . $v->day;
                break;
            case 'blob':
            case 'string':
            case 'sql':
            case 'datetime':
            case 'time':
                // Low level exception. No need for i18n as discussed with Brion.
                throw new ServerException("Unhandled DB_DataObject_Cast type passed as cacheKey value: '$v->type'");
                break;
            default:
                // Low level exception. No need for i18n as discussed with Brion.
                throw new ServerException("Unknown DB_DataObject_Cast type passed as cacheKey value: '$v->type'");
                break;
            }
        } else {
            $vstr = strval($v);
        }
        return $vstr;
    }
}

