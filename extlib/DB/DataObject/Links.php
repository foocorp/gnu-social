<?php
/**
 * Link tool for DB_DataObject
 *
 * PHP versions 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Database
 * @package    DB_DataObject
 * @author     Alan Knowles <alan@akbkhome.com>
 * @copyright  1997-2006 The PHP Group
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    : FIXME
 * @link       http://pear.php.net/package/DB_DataObject
 */


/**
 *
 * Example of how this could be used..
 * 
 * The lind method are now in here.
 *
 * Currenly only supports existing methods, and new 'link()' method
 *
 */
  
  
/**
 * Links class
 *
 * @package DB_DataObject
 */
class DB_DataObject_Links 
{
     /**
     * @property {DB_DataObject}      do   DataObject to apply this to.
     */
    var $do = false;
    
    
    /**
     * @property {Array|String} load    What to load, 'all' or an array of properties. (default all)
     */
    var $load = 'all';
    /**
     * @property {String|Boolean}       scanf   use part of column name as resulting
     *                                          property name. (default false)
     */
    var $scanf = false;
    /**
     * @property {String|Boolean}       printf  use column name as sprintf for resulting property name..
     *                                     (default %s_link if apply is true, otherwise it is %s)
     */
    var $printf = false;
    /**
     * @property {Boolean}      cached  cache the result, so future queries will use cache rather
     *                                  than running the expensive sql query.
     */
    var $cached = false;
    /**
     * @property {Boolean}      apply   apply the result to this object, (default true)
     */
    var $apply = true;
   
    
    //------------------------- RETURN ------------------------------------
    /**
     * @property {Array}      links    key value associative array of links.
     */
    var $links;
    
    
    /**
     * Constructor
     *   -- good ole style..
     *  @param {DB_DataObject}           do  DataObject to apply to.
     *  @param {Array}           cfg  Configuration (basically properties of this object)
     */
    
    function DB_DataObject_Links($do,$cfg= array())
    {
        // check if do is set!!!?
        $this->do = $do;
        
        foreach($cfg as $k=>$v) {
            $this->$k = $v;
        }
       
        
    }
     
    /**
     * return name from related object
     *
     * The relies on  a <dbname>.links.ini file, unless you specify the arguments.
     * 
     * you can also use $this->getLink('thisColumnName','otherTable','otherTableColumnName')
     *
     *
     * @param string $field|array    either row or row.xxxxx or links spec.
     * @param string|DB_DataObject $table  (optional) name of table to look up value in
     * @param string $link   (optional)  name of column in other table to match
     * @author Tim White <tim@cyface.com>
     * @access public
     * @return mixed object on success false on failure or '0' when not linked
     */
    function getLink($field, $table= false, $link='')
    {
        
        static $cache = array();
        
        // GUESS THE LINKED TABLE.. (if found - recursevly call self)
        
        if ($table == false) {
            
            
            $info = $this->linkInfo($field);
            
            if ($info) {
                return $this->getLink($field, $info[0],  $link === false ? $info[1] : $link );
            }
            
            // no links defined.. - use borked BC method...
                  // use the old _ method - this shouldnt happen if called via getLinks()
            if (!($p = strpos($field, '_'))) {
                return false;
            }
            $table = substr($field, 0, $p);
            return $this->getLink($field, $table);
            
            

        }
         
        $tn = is_string($table) ? $table : $table->tableName();
         
            
 
        if (!isset($this->do->$field)) {
            $this->do->raiseError("getLink: row not set $field", DB_DATAOBJECT_ERROR_NODATA);
            return false;
        }
        
        // check to see if we know anything about this table..
        
      
        if (empty($this->do->$field) || $this->do->$field < 0) {
            return 0; // no record. 
        }
        
        if ($this->cached && isset($cache[$tn.':'. $link .':'. $this->do->$field])) {
            return $cache[$tn.':'. $link .':'. $this->do->$field];    
        }
        
        $obj = is_string($table) ? $this->do->factory($tn) : $table;;
        
        if (!is_a($obj,'DB_DataObject')) {
            $this->do->raiseError(
                "getLink:Could not find class for row $field, table $tn", 
                DB_DATAOBJECT_ERROR_INVALIDCONFIG);
            return false;
        }
        // -1 or 0 -- no referenced record..
       
        $ret = false;
        if ($link) {
            
            if ($obj->get($link, $this->do->$field)) {
                $ret = $obj;
            }
            
            
        // this really only happens when no link config is set (old BC stuff)    
        } else if ($obj->get($this->do->$field)) {
            $ret= $obj;
             
        }
        if ($this->cached) {
            $cache[$tn.':'. $link .':'. $this->do->$field] = $ret;
        }
        return $ret;
        
    }
    /**
     * get link information for a field or field specification
     *
     * alll link (and join methods accept the 'link' info ) in various ways
     * string : 'field' = which field to get (uses ???.links.ini to work out what)
     * array(2) : 'field', 'table:remote_col' << just like the links.ini def.
     * array(3) : 'field', $dataobject, 'remote_col'  (handy for joinAdd to do nested joins.)
     *
     * @param string|array $field or link spec to use. 
     * @return (false|array) array of dataobject and linked field or false.
     *
     *
     */
    
    function linkInfo($field)
    {
         
        if (is_array($field)) {
            if (count($field) == 3) {
                // array with 3 args:
                // local_col , dataobject, remote_col
                return array(
                    $field[1],
                    $field[2],
                    $field[0]
                );
                
            } 
            list($table,$link) = explode(':', $field[1]);
            
            return array(
                $this->do->factory($table),
                $link,
                $field[0]
            );
            
        }
        // work out the link.. (classic way)
        
        $links = $this->do->links();
        
        if (empty($links) || !is_array($links)) {
             
            return false;
        }
            
            
        if (!isset($links[$field])) {
            
            return false;
        }
        list($table,$link) = explode(':', $links[$field]);
    
        
        //??? needed???
        if ($p = strpos($field,".")) {
            $field = substr($field,0,$p);
        }
        
        return array(
            $this->do->factory($table),
            $link,
            $field
        );
        
        
         
        
    }
    
    
        
    /**
     *  a generic geter/setter provider..
     *
     *  provides a generic getter setter for the referenced object
     *  eg.
     *  $link->link('company_id') returns getLink for the object
     *  if nothing is linked (it will return an empty dataObject)
     *  $link->link('company_id', array(1)) - just sets the 
     *
     *  also array as the field speck supports
     *      $link->link(array('company_id', 'company:id'))
     *  
     *
     *  @param  string|array   $field   the field to fetch or link spec.
     *  @params array          $args    the arguments sent to the getter setter
     *  @return mixed true of false on set, the object on getter.
     *
     */
    function link($field, $args = array())
    {
        $info = $this->linkInfo($field);
         
        if (!$info) {
            $this->do->raiseError(
                "getLink:Could not find link for row $field", 
                DB_DATAOBJECT_ERROR_INVALIDCONFIG);
            return false;
        }
        $field = $info[2];
        
        
        if (empty($args)) { // either an empty array or really empty....
            
            if (!isset($this->do->$field)) {
                return $info[0]; // empty dataobject.
            }
            
            $ret = $this->getLink($field);
            // nothing linked -- return new object..
            return ($ret === 0) ? $info[0] : $ret;
            
        }
        $assign = is_array($args) ? $args[0] : $args;
         
        // otherwise it's a set call..
        if (!is_a($assign , 'DB_DataObject')) {
            
            if (is_numeric($assign) && is_integer($assign * 1)) {
                if ($assign  > 0) {
                    
                    if (!$info) {
                        return false;
                    }
                    // check that record exists..
                    if (!$info[0]->get($info[1], $assign )) {
                        return false;
                    }
                    
                }
                
                $this->do->$field = $assign ;
                return true;
            }
            
            return false;
        }
        
        // otherwise we are assigning it ...
        
        $this->do->$field = $assign->{$info[1]};
        return true;
        
        
    }
    /**
     * load related objects
     *
     * Generally not recommended to use this.
     * The generator should support creating getter_setter methods which are better suited.
     *
     * Relies on  <dbname>.links.ini
     *
     * Sets properties on the calling dataobject  you can change what
     * object vars the links are stored in by  changeing the format parameter
     *
     *
     * @param  string format (default _%s) where %s is the table name.
     * @author Tim White <tim@cyface.com>
     * @access public
     * @return boolean , true on success
     */
    
    function applyLinks($format = '_%s')
    {
         
        // get table will load the options.
        if ($this->do->_link_loaded) {
            return true;
        }
        
        $this->do->_link_loaded = false;
        $cols  = $this->do->table();
        $links = $this->do->links();
         
        $loaded = array();
        
        if ($links) {   
            foreach($links as $key => $match) {
                list($table,$link) = explode(':', $match);
                $k = sprintf($format, str_replace('.', '_', $key));
                // makes sure that '.' is the end of the key;
                if ($p = strpos($key,'.')) {
                      $key = substr($key, 0, $p);
                }
                
                $this->do->$k = $this->getLink($key, $table, $link);
                
                if (is_object($this->do->$k)) {
                    $loaded[] = $k; 
                }
            }
            $this->do->_link_loaded = $loaded;
            return true;
        }
        // this is the autonaming stuff..
        // it sends the column name down to getLink and lets that sort it out..
        // if there is a links file then it is not used!
        // IT IS DEPRECATED!!!! - DO NOT USE 
        if (!is_null($links)) {    
            return false;
        }
        
        
        foreach (array_keys($cols) as $key) {
            if (!($p = strpos($key, '_'))) {
                continue;
            }
            // does the table exist.
            $k =sprintf($format, $key);
            $this->do->$k = $this->getLink($key);
            if (is_object($this->do->$k)) {
                $loaded[] = $k; 
            }
        }
        $this->do->_link_loaded = $loaded;
        return true;
    }
    
    /**
     * getLinkArray
     * Fetch an array of related objects. This should be used in conjunction with a
     * <dbname>.links.ini file configuration (see the introduction on linking for details on this).
     *
     * You may also use this with all parameters to specify, the column and related table.
     * 
     * @access public
     * @param string $field- either column or column.xxxxx
     * @param string $table (optional) name of table to look up value in
     * @param string $fkey (optional) fetchall key see DB_DataObject::fetchAll()
     * @param string $fval (optional)fetchall val DB_DataObject::fetchAll()
     * @param string $fval (optional) fetchall method DB_DataObject::fetchAll()
     * @return array - array of results (empty array on failure)
     * 
     * Example - Getting the related objects
     * 
     * $person = new DataObjects_Person;
     * $person->get(12);
     * $children = $person->getLinkArray('children');
     * 
     * echo 'There are ', count($children), ' descendant(s):<br />';
     * foreach ($children as $child) {
     *     echo $child->name, '<br />';
     * }
     * 
     */
    function getLinkArray($field, $table = null, $fkey = false, $fval = false, $fmethod = false)
    {
        
        $ret = array();
        if (!$table)  {
            
            
            $links = $this->do->links();
            
            if (is_array($links)) {
                if (!isset($links[$field])) {
                    // failed..
                    return $ret;
                }
                list($table,$link) = explode(':',$links[$field]);
                return $this->getLinkArray($field,$table);
            } 
            if (!($p = strpos($field,'_'))) {
                return $ret;
            }
            return $this->getLinkArray($field,substr($field,0,$p));


        }
        
        $c  = $this->do->factory($table);
        
        if (!is_object($c) || !is_a($c,'DB_DataObject')) {
            $this->do->raiseError(
                "getLinkArray:Could not find class for row $field, table $table", 
                DB_DATAOBJECT_ERROR_INVALIDCONFIG
            );
            return $ret;
        }

        // if the user defined method list exists - use it...
        if (method_exists($c, 'listFind')) {
            $c->listFind($this->id);
            while ($c->fetch()) {
                $ret[] = clone($c);
            }
            return $ret;
        } 
        return $c->fetchAll($fkey, $fval, $fmethod);
        
        
    }

}