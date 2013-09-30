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
 * @author   James Walker <james@status.net>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 */

if (!defined('GNUSOCIAL')) { exit(1); }

// @todo XXX: Add documentation.
class HostMetaAction extends XrdAction
{
    protected $defaultformat = 'xml';

    protected function setXRD()
    {
        if(Event::handle('StartHostMetaLinks', array(&$this->xrd->links))) {
            Event::handle('EndHostMetaLinks', array(&$this->xrd->links));
        }
    }
}
