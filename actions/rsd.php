<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * Really Simple Discovery (RSD) for API access
 *
 * PHP version 5
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
 *
 * @category API
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * RSD action class
 *
 * Really Simple Discovery (RSD) is a simple (to a fault, maybe)
 * discovery tool for blog APIs.
 *
 * http://tales.phrasewise.com/rfc/rsd
 *
 * Anil Dash suggested that RSD be used for services that implement
 * the Twitter API:
 *
 * http://dashes.com/anil/2009/12/the-twitter-api-is-finished.html
 *
 * It's in use now for WordPress.com blogs:
 *
 * http://matt.wordpress.com/xmlrpc.php?rsd
 *
 * I (evan@status.net) have tried to stay faithful to the premise of
 * RSD, while adding information useful to StatusNet client developers.
 * In particular:
 *
 * - There is a link from each user's profile page to their personal
 *   RSD feed. A personal rsd.xml includes a 'blogID' element that is
 *   their username.
 * - There is a link from the public root to '/rsd.xml', a public RSD
 *   feed. It's identical to the personal rsd except it doesn't include
 *   a blogId.
 * - I've added a setting to the API to indicate that OAuth support is
 *   available.
 *
 * @category API
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class RsdAction extends Action
{
    /**
     * Optional attribute for the personal rsd.xml file.
     */
    var $user = null;

    /**
     * Prepare the action for use.
     *
     * Check for a nickname; redirect if non-canonical; if
     * not provided, assume public rsd.xml.
     *
     * @param array $args GET, POST, and URI arguments.
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        // optional argument

        $nickname_arg = $this->arg('nickname');

        if (empty($nickname_arg)) {
            $this->user = null;
        } else {
            $nickname = common_canonical_nickname($nickname_arg);

            // Permanent redirect on non-canonical nickname

            if ($nickname_arg != $nickname) {
                common_redirect(common_local_url('rsd',
                                                 array('nickname' => $nickname)),
                                301);
                return false;
            }

            $this->user = User::getKV('nickname', $nickname);

            if (empty($this->user)) {
                // TRANS: Client error.
                $this->clientError(_('No such user.'), 404);
                return false;
            }
        }

        return true;
    }

    /**
     * Action handler.
     *
     * Outputs the XML format for an RSD file. May include
     * personal information if this is a personal file
     * (based on whether $user attribute is set).
     *
     * @param array $args array of arguments
     *
     * @return nothing
     */
    function handle($args)
    {
        header('Content-Type: application/rsd+xml');

        $this->startXML();

        $rsdNS = 'http://archipelago.phrasewise.com/rsd';
        $this->elementStart('rsd', array('version' => '1.0',
                                         'xmlns' => $rsdNS));
        $this->elementStart('service');
        // TRANS: Engine name for RSD.
        $this->element('engineName', null, _('StatusNet'));
        $this->element('engineLink', null, 'http://status.net/');
        $this->elementStart('apis');
        if (Event::handle('StartRsdListApis', array($this, $this->user))) {

            $blogID   = (empty($this->user)) ? '' : $this->user->nickname;
            $apiAttrs = array('name' => 'Twitter',
                              'preferred' => 'true',
                              'apiLink' => $this->_apiRoot(),
                              'blogID' => $blogID);

            $this->elementStart('api', $apiAttrs);
            $this->elementStart('settings');
            $this->element('docs', null,
                           'http://status.net/wiki/TwitterCompatibleAPI');
            $this->element('setting', array('name' => 'OAuth'),
                           'true');
            $this->elementEnd('settings');
            $this->elementEnd('api');

            // Atom API

            if (empty($this->user)) {
                $service = common_local_url('ApiAtomService');
            } else {
                $service = common_local_url('ApiAtomService', array('id' => $this->user->nickname));
            }

            $this->element('api', array('name' => 'Atom',
                                        'preferred' => 'false',
                                        'apiLink' => $service,
                                        'blogID' => $blogID));

            Event::handle('EndRsdListApis', array($this, $this->user));
        }
        $this->elementEnd('apis');
        $this->elementEnd('service');
        $this->elementEnd('rsd');

        $this->endXML();

        return true;
    }

    /**
     * Returns last-modified date for use in caching
     *
     * Per-user rsd.xml is dated to last change of user
     * (in case of nickname change); public has no date.
     *
     * @return string date of last change of this page
     */
    function lastModified()
    {
        if (!empty($this->user)) {
            return $this->user->modified;
        } else {
            return null;
        }
    }

    /**
     * Flag to indicate if this action is read-only
     *
     * It is; it doesn't change the DB.
     *
     * @param array $args ignored
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Return current site's API root
     *
     * Varies based on URL parameters, like if fancy URLs are
     * turned on.
     *
     * @return string API root URI for this site
     */
    private function _apiRoot()
    {
        if (common_config('site', 'fancy')) {
            return common_path('api/', true);
        } else {
            return common_path('index.php/api/', true);
        }
    }
}
