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
    public $claimed;                         // datetime()
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'Unique ID for entry'),
                'data' => array('type' => 'blob', 'not null' => true, 'description' => 'data blob'),
                'prioritise' => array('type' => 'int', 'size' => 'tiny', 'description' => 'tinyint priority value'),
                'attempts' => array('type' => 'int', 'not null' => true, 'description' => 'attempts count'),
                'claimed' => array('type' => 'datetime', 'description' => 'date this irc message was claimed'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'indexes' => array(
                'irc_waiting_message_prioritise_idx' => array('prioritise'),
            ),
        );
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
