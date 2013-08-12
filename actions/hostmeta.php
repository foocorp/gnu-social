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

/**
 * @category Action
 * @package  StatusNet
 * @maintainer James Walker <james@status.net>
 * @author   Craig Andrews <candrews@integralblue.com>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

// @todo XXX: Add documentation.
class HostMetaAction extends Action
{
    /**
     * Is read only?
     *
     * @return boolean true
     */
    function isReadOnly()
    {
        return true;
    }

    function handle()
    {
        parent::handle();

        $domain = common_config('site', 'server');

        $xrd = new XRD();
        $xrd->host = $domain;

        if(Event::handle('StartHostMetaLinks', array(&$xrd->links))) {
            $url = common_local_url('userxrd');
            $url.= '?uri={uri}';
            $xrd->links[] = array('rel' => Discovery::LRDD_REL,
                      'template' => $url,
                      'title' => array('Resource Descriptor'));
            Event::handle('EndHostMetaLinks', array(&$xrd->links));
        }

        // Output Cross-Origin Resource Sharing (CORS) header
        if (common_config('discovery', 'cors')) {
            header('Access-Control-Allow-Origin: *');
        }

        header('Content-type: application/xrd+xml');

        print $xrd->toXML();
    }
}
