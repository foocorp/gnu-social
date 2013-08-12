<?php
/**
 * Handle an updateprofile action
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once dirname(__FILE__) . '/../lib/omb.php';
require_once dirname(__FILE__) . '/../extlib/libomb/service_provider.php';

/**
 * Handle an updateprofile action
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class UpdateprofileAction extends Action
{
    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        StatusNet::setApi(true); // Send smaller error pages

        parent::prepare($argarray);
        $license      = $_POST['omb_listenee_license'];
        $site_license = common_config('license', 'url');
        if (!common_compatible_license($license, $site_license)) {
            // TRANS: Client error displayed when trying to update profile with an incompatible license.
            // TRANS: %1$s is the license incompatible with site license %2$s.
            $this->clientError(sprintf(_('Listenee stream license "%1$s" is not '.
                                         'compatible with site license "%2$s".'),
                                       $license, $site_license));
            return false;
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        try {
            $srv = new OMB_Service_Provider(null, omb_oauth_datastore(),
                                            omb_oauth_server());
            $srv->handleUpdateProfile();
        } catch (OMB_RemoteServiceException $rse) {
            $msg = $rse->getMessage();
            if (preg_match('/Revoked accesstoken/', $msg) ||
                preg_match('/No subscriber/', $msg)) {
                $this->clientError($msg, 403);
            } else {
                $this->clientError($msg);
            }
        } catch (Exception $e) {
            $this->serverError($e->getMessage());
            return;
        }
    }
}
