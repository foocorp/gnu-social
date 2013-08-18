<?php
/**
 * Table Definition for irc_waiting_message
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Irc_waiting_message extends Managed_DataObject {

    public $__table = 'irc_waiting_message'; // table name
    public $id;                              // int primary_key not_null auto_increment
    public $data;                            // blob not_null
    public $prioritise;                      // tinyint(1) not_null
    public $attempts;                        // int not_null
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
                     'data' => DB_DATAOBJECT_BLOB + DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'prioritise' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
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
    public function sequenceKey() {
        return array(false, false, false);
    }

    /**
     * Get the next item in the queue
     *
     * @return Irc_waiting_message Next message if there is one
     */
    public static function top() {
        $wm = new Irc_waiting_message();

        $wm->orderBy('prioritise DESC, created');
        $wm->whereAdd('claimed is null');

        $wm->limit(1);

        $cnt = $wm->find(true);

        if ($cnt) {
            // XXX: potential race condition
            // can we force it to only update if claimed is still null
            // (or old)?
            common_log(LOG_INFO, 'claiming IRC waiting message id = ' . $wm->id);
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
    * Increment the attempts count
    *
    * @return void
    * @throws Exception
    */
    public function incAttempts() {
        $orig = clone($this);
        $this->attempts++;
        $result = $this->update($orig);

        if (!$result) {
            // TRANS: Exception thrown when an IRC attempts count could not be updated.
            // TRANS: %d is the object ID for which the count could not be updated.
            throw Exception(sprintf(_m('Could not increment attempts count for %d.'), $this->id));
        }
    }

    /**
     * Release a claimed item.
     */
    public function releaseClaim() {
        // DB_DataObject doesn't let us save nulls right now
        $sql = sprintf("UPDATE irc_waiting_message SET claimed=NULL WHERE id=%d", $this->id);
        $this->query($sql);

        $this->claimed = null;
        $this->encache();
    }
}
