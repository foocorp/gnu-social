<?php
/**
 * Table Definition for notice_location
 */

class Notice_location extends Managed_DataObject
{
    public $__table = 'notice_location';     // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $lat;                             // decimal(10,7)
    public $lon;                             // decimal(10,7)
    public $location_id;                     // int(4)
    public $location_ns;                     // int(4)
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice that is the reply'),
                'lat' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'latitude'),
                'lon' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'longitude'),
                'location_id' => array('type' => 'int', 'description' => 'location id if possible'),
                'location_ns' => array('type' => 'int', 'description' => 'namespace for location'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('notice_id'),
            'foreign keys' => array(
                'notice_location_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
            ),
            'indexes' => array(
                'notice_location_location_id_idx' => array('location_id'),
            ),
        );
    }    

    static function locFromStored(Notice $stored)
    {
        $loc = new Notice_location();
        $loc->notice_id = $stored->getID();
        if (!$loc->find(true)) {
            throw new NoResultException($loc);
        }
        return $loc->asLocation();
    }

    static function fromLocation(Location $location)
    {
        $notloc = new Notice_location();
        $notloc->lat = $location->lat;
        $notloc->lon = $location->lon;
        $notloc->location_ns = $location->location_ns;
        $notloc->location_id = $location->location_id;
        return $notloc;
    }

    public function asLocation()
    {
        $location = null;

        if (!empty($this->location_id) && !empty($this->location_ns)) {
            $location = Location::fromId($this->location_id, $this->location_ns);
        }

        if (is_null($location)) { // no ID, or Location::fromId() failed
            $location = Location::fromLatLon($this->lat, $this->lon);
        }

        if (is_null($location)) {
            throw new ServerException('Location could not be looked up from existing data.');
        }

        return $location;
    }
}
