<?php

/*
 * Collection primarily as the root of an Activity Streams doc but can be used as the value
 * of extension properties in a variety of situations.
 *
 * A valid Collection object serialization MUST contain at least the url or items properties.
 */
class JSONActivityCollection {

    /* Non-negative integer specifying the total number of activities within the stream */
    protected $totalItems;

    /* An array containing a listing of Objects of any object type */
    protected $items;

    /* IRI referencing a JSON document containing the full listing of objects in the collection */
    protected $url;

    /**
     * Constructor
     *
     * @param array  $items       array of activity items
     * @param string $url         url of a doc list all the objs in the collection
     * @param int    $totalItems  total number of items in the collection
     */
    function __construct($items = null, $url = null)
    {
        $this->items      = empty($items) ? array() : $items;
        $this->totalItems = count($items);
        $this->url        = $url;
    }

    /**
     * Get the total number of items in the collection
     *
     * @return int total the total
     */
    public function getTotalItems()
    {
        $this->totalItems = count($items);
        return $this->totalItems;
    }
}
