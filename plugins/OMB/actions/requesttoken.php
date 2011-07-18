<?php
/**
 * Request token action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once dirname(__FILE__) . '/../lib/omb.php';
require_once dirname(__FILE__) . '/../extlib/libomb/service_provider.php';

/**
 * Request token action class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class RequesttokenAction extends Action
{
     /**
     * Is read only?
     *
     * @return boolean false
     */
    function isReadOnly()
    {
        return false;
    }

    /**
     * Class handler.
     *
     * @param array $args array of arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        try {
            $srv = new OMB_Service_Provider(null, omb_oauth_datastore(),
                                            omb_oauth_server());
            $srv->writeRequestToken();
        } catch (Exception $e) {
            $this->serverError($e->getMessage());
        }
    }
}
