<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * @package WebFingerPlugin
 * @author James Walker <james@status.net>
 * @author Mikael Nordfeldth <mmn@hethane.se>
 */
class WebfingerAction extends XrdAction
{
    protected $resource = null; // string with the resource URI
    protected $target   = null; // object of the WebFingerResource class

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        // throws exception if resource is empty
        $this->resource = Discovery::normalize($this->trimmed('resource'));

        try {
            if (Event::handle('StartGetWebFingerResource', array($this->resource, &$this->target, $this->args))) {
                Event::handle('EndGetWebFingerResource', array($this->resource, &$this->target, $this->args));
            }
        } catch (NoSuchUserException $e) {
            throw new ServerException($e->getMessage(), 404);
        }

        if (!$this->target instanceof WebFingerResource) {
            // TRANS: Error message when an object URI which we cannot find was requested
            throw new ServerException(_m('Resource not found in local database.'), 404);
        }

        return true;
    }

    protected function setXRD()
    {
        $this->xrd->subject = $this->resource;

        foreach ($this->target->getAliases() as $alias) {
            if ($alias != $this->xrd->subject && !in_array($alias, $this->xrd->aliases)) {
                $this->xrd->aliases[] = $alias;
            }
        }

        $this->target->updateXRD($this->xrd);
    }
}
