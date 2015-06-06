<?php
if (!defined('GNUSOCIAL')) { exit(1); }

class GS_DataObject extends DB_DataObject
{
    public function _autoloadClass($class, $table=false)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::_autoloadClass($class, $table);

        // reset
        error_reporting($old);
        return $res;
    }

    // wraps the _connect call so we don't throw E_STRICT warnings during it
    public function _connect()
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::_connect();

        // reset
        error_reporting($old);
        return $res;
    }

    // wraps the _loadConfig call so we don't throw E_STRICT warnings during it
    // doesn't actually return anything, but we'll follow the same model as the rest of the wrappers
    public function _loadConfig()
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::_loadConfig();

        // reset
        error_reporting($old);
        return $res;
    }

    // wraps the count call so we don't throw E_STRICT warnings during it
    public function count($countWhat = false,$whereAddOnly = false)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::count($countWhat, $whereAddOnly);

        // reset
        error_reporting($old);
        return $res;
    }

    static public function debugLevel($v = null)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::debugLevel($v);

        // reset
        error_reporting($old);
        return $res;
    }

    static public function factory($table = '')
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::factory($table);

        // reset
        error_reporting($old);
        return $res;
    }

    public function get($k = null, $v = null)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::get($k, $v);

        // reset
        error_reporting($old);
        return $res;
    }

    public function fetch()
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::fetch();

        // reset
        error_reporting($old);
        return $res;
    }

    public function find($n = false)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::find($n);

        // reset
        error_reporting($old);
        return $res;
    }

    public function fetchRow($row = null)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::fetchRow($row);

        // reset
        error_reporting($old);
        return $res;
    }

    public function links()
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::links();

        // reset
        error_reporting($old);
        return $res;
    }

    // wraps the update call so we don't throw E_STRICT warnings during it
    public function update($dataObject = false)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::update($dataObject);

        // reset
        error_reporting($old);
        return $res;
    }

    static public function staticGet($class, $k, $v = null)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::staticGet($class, $k, $v);

        // reset
        error_reporting($old);
        return $res;
    }

    public function staticGetAutoloadTable($table)
    {
        // avoid those annoying PEAR::DB strict standards warnings it causes
        $old = error_reporting();
        error_reporting(error_reporting() & ~E_STRICT);

        $res = parent::staticGetAutoloadTable($table);

        // reset
        error_reporting($old);
        return $res;
    }
}
