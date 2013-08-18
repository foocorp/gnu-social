<?php
/**
 * Table Definition for msn_waiting_message
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Msn_waiting_message extends Managed_DataObject {

    public $__table = 'msn_waiting_message'; // table name
    public $id;                              // int primary_key not_null auto_increment
    public $screenname;                      // varchar(255) not_null
    public $message;                         // text not_null
    public $created;                         // datetime() not_null
    public $claimed;                         // datetime()

    /**
    * return table definition for DB_DataObject
    *
    * DB_DataObject needs to know something about the table to manipulate
    * instances. This method provides all the DB_DataObject needs to know.
    *
    * @return array array of column definitions
    */
    public function table() {
        return array('id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'screenname' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'message' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'created' => DB_DATAOBJECT_TIME + DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'claimed' => DB_DATAOBJECT_TIME + DB_DATAOBJECT_STR);
    }

    /**
    * return key definitions for DB_DataObject
    *
    * DB_DataObject needs to know about keys that the table has, since it
    * won't appear in StatusNet's own keys list. In most cases, this will
    * simply reference your keyTypes() function.
    *
    * @return array list of key field names
    */
    public function keys() {
        return array_keys($this->keyTypes());
    }

    /**
    * return key definitions for Memcached_DataObject
    *
    * Our caching system uses the same key definitions, but uses a different
    * method to get them. This key information is used to store and clear
    * cached data, so be sure to list any key that will be used for static
    * lookups.
    *
    * @return array associative array of key definitions, field name to type:
    *         'K' for primary key: for compound keys, add an entry for each component;
    *         'U' for unique keys: compound keys are not well supported here.
    */
    public function keyTypes() {
        return array('id' => 'K');
    }

    /**
    * Magic formula for non-autoincrementing integer primary keys
    *
    * If a table has a single integer column as its primary key, DB_DataObject
    * assumes that the column is auto-incrementing and makes a sequence table
    * to do this incrementation. Since we don't need this for our class, we
    * overload this method and return the magic formula that DB_DataObject needs.
    *
    * @return array magic three-false array that stops auto-incrementing.
    */
    function sequenceKey() {
        return array(false, false, false);
    }

    /**
     * @param string $screenname screenname or array of screennames to pull from
     *                          If not specified, checks all queues in the system.
     */
    public static function top($screenname = null) {
        $wm = new Msn_waiting_message();
        if ($screenname) {
            if (is_array($screenname)) {
                // @fixme use safer escaping
                $list = implode("','", array_map('addslashes', $screenname));
                $wm->whereAdd("screenname in ('$list')");
            } else {
                $wm->screenname = $screenname;
            }
        }
        $wm->orderBy('created');
        $wm->whereAdd('claimed is null');

        $wm->limit(1);

        $cnt = $wm->find(true);

        if ($cnt) {
            // XXX: potential race condition
            // can we force it to only update if claimed is still null
            // (or old)?
            common_log(LOG_INFO, 'claiming msn waiting message id = ' . $wm->id);
            $orig = clone($wm);
            $wm->claimed = common_sql_now();
            $result = $wm->update($orig);
            if ($result) {
                common_log(LOG_INFO, 'claim succeeded.');
                return $wm;
            } else {
                common_log(LOG_INFO, 'claim failed.');
            }
        }
        $wm = null;
        return null;
    }

    /**
     * Release a claimed item.
     */
    public function releaseClaim() {
        // DB_DataObject doesn't let us save nulls right now
        $sql = sprintf("UPDATE msn_waiting_message SET claimed=NULL WHERE id=%d", $this->id);
        $this->query($sql);

        $this->claimed = null;
        $this->encache();
    }
}
