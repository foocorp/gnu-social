<?php
/**
 * Handle postnotice action
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
 * Handler for postnotice action
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class PostnoticeAction extends Action
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

        try {
            $this->checkNotice();
        } catch (Exception $e) {
            $this->clientError($e->getMessage());
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
            $srv->handlePostNotice();
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

    function checkNotice()
    {
        $content = common_shorten_links($_POST['omb_notice_content']);
        if (Notice::contentTooLong($content)) {
            // TRANS: Client error displayed if the notice posted has too many characters.
            $this->clientError(_('Invalid notice content.'), 400);
            return false;
        }
        $license      = $_POST['omb_notice_license'];
        $site_license = common_config('license', 'url');
        if ($license && !common_compatible_license($license, $site_license)) {
            // TRANS: Exception thrown if a notice's license is not compatible with the StatusNet site license.
            // TRANS: %1$s is the notice license, %2$s is the StatusNet site's license.
            throw new Exception(sprintf(_('Notice license "%1$s" is not ' .
                                          'compatible with site license "%2$s".'),
                                        $license, $site_license));
        }
    }
}
