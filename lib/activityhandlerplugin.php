<?php
/*  
 * GNU Social - a federating social network  
 * Copyright (C) 2014, Free Software Foundation, Inc.
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
 * Superclass for plugins which add Activity types and such
 *
 * @category  Activity
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2014 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://gnu.io/social
 */
abstract class ActivityHandlerPlugin extends Plugin
{
    /**
     * Return a list of ActivityStreams object type IRIs
     * which this micro-app handles. Default implementations
     * of the base class will use this list to check if a
     * given ActivityStreams object belongs to us, via
     * $this->isMyNotice() or $this->isMyActivity.
     *
     * An empty list means any type is ok. (Favorite verb etc.)
     *
     * All micro-app classes must override this method.
     *
     * @return array of strings
     */
    abstract function types();

    /**
     * Return a list of ActivityStreams verb IRIs which
     * this micro-app handles. Default implementations
     * of the base class will use this list to check if a
     * given ActivityStreams verb belongs to us, via
     * $this->isMyNotice() or $this->isMyActivity.
     *
     * All micro-app classes must override this method.
     *
     * @return array of strings
     */
    function verbs() {
        return array(ActivityVerb::POST);
    }

    /**
     * Check if a given ActivityStreams activity should be handled by this
     * micro-app plugin.
     *
     * The default implementation checks against the activity type list
     * returned by $this->types(), and requires that exactly one matching
     * object be present. You can override this method to expand
     * your checks or to compare the activity's verb, etc.
     *
     * @param Activity $activity
     * @return boolean
     */
    function isMyActivity(Activity $act) {
        return (count($act->objects) == 1
            && ($act->objects[0] instanceof ActivityObject)
            && $this->isMyVerb($act->verb)
            && $this->isMyType($act->objects[0]->type));
    }

    /**
     * Check if a given notice object should be handled by this micro-app
     * plugin.
     * 
     * The default implementation checks against the activity type list 
     * returned by $this->types(). You can override this method to expand 
     * your checks, but follow the execution chain to get it right. 
     * 
     * @param Notice $notice 
     * @return boolean 
     */ 
    function isMyNotice(Notice $notice) {
        return $this->isMyVerb($notice->verb) && $this->isMyType($notice->object_type);
    }

    function isMyVerb($verb) {
        $verb = $verb ?: ActivityVerb::POST;    // post is the default verb
        return ActivityUtils::compareTypes($verb, $this->verbs());
    }

    function isMyType($type) {
        return count($this->types())===0 || ActivityUtils::compareTypes($type, $this->types());
    }
}
