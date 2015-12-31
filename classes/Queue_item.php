<?php
/**
 * Table Definition for queue_item
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Queue_item extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'queue_item';                      // table name
    public $id;                              // int(4)  primary_key not_null
    public $frame;                           // blob not_null
    public $transport;                       // varchar(32)
    public $created;                         // datetime()   not_null
    public $claimed;                         // datetime()

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
                'frame' => array('type' => 'blob', 'not null' => true, 'description' => 'data: object reference or opaque string'),
                'transport' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'queue for what? "email", "xmpp", "sms", "irc", ...'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'claimed' => array('type' => 'datetime', 'description' => 'date this item was claimed'),
            ),
            'primary key' => array('id'),
            'indexes' => array(
                'queue_item_created_idx' => array('created'),
            ),
        );
    }

    /**
     * @param mixed $transports name of a single queue or array of queues to pull from
     *                          If not specified, checks all queues in the system.
     */
    static function top($transports=null, array $ignored_transports=array()) {

        $qi = new Queue_item();
        if ($transports) {
            if (is_array($transports)) {
                // @fixme use safer escaping
                $list = implode("','", array_map(array($qi, 'escape'), $transports));
                $qi->whereAdd("transport in ('$list')");
            } else {
                $qi->transport = $transports;
            }
        }
        if (!empty($ignored_transports)) {
            // @fixme use safer escaping
            $list = implode("','", array_map(array($qi, 'escape'), $ignored_transports));
            $qi->whereAdd("transport NOT IN ('$list')");
        }
        $qi->orderBy('created');
        $qi->whereAdd('claimed is null');

        $qi->limit(1);

        $cnt = $qi->find(true);

        if ($cnt) {
            // XXX: potential race condition
            // can we force it to only update if claimed is still null
            // (or old)?
            common_log(LOG_INFO, 'claiming queue item id = ' . $qi->getID() . ' for transport ' . $qi->transport);
            $orig = clone($qi);
            $qi->claimed = common_sql_now();
            $result = $qi->update($orig);
            if ($result) {
                common_log(LOG_DEBUG, 'claim succeeded.');
                return $qi;
            } else {
                common_log(LOG_ERR, 'claim of queue item id= ' . $qi->getID() . ' for transport ' . $qi->transport . ' failed.');
            }
        }
        $qi = null;
        return null;
    }

    /**
     * Release a claimed item.
     */
    function releaseClaim()
    {
        // DB_DataObject doesn't let us save nulls right now
        $sql = sprintf("UPDATE queue_item SET claimed=NULL WHERE id=%d", $this->getID());
        $this->query($sql);

        $this->claimed = null;
        $this->encache();
    }
}
