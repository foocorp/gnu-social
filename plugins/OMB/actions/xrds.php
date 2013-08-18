<?php
/**
 * XRDS for OpenMicroBlogging
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
require_once dirname(__FILE__) . '/../extlib/libomb/xrds_mapper.php';
require_once dirname(__FILE__) . '/../lib/xrdsoutputter.php';

/**
 * XRDS for OpenMicroBlogging
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class XrdsAction extends Action
{
    var $user;

    /**
     * Is read only?
     *
     * @return boolean true
     */
    function isReadOnly()
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);
        $nickname = $this->trimmed('nickname');
        $this->user     = User::getKV('nickname', $nickname);
        if (!$this->user) {
            // TRANS: Client error displayed providing a non-existing nickname.
            $this->clientError(_('No such user.'));
            return;
        }
        return true;
    }

    /**
     * Class handler.
     *
     * @param array $args query arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $xrdsOutputter = new XRDSOutputter();
        $xrdsOutputter->startXRDS();

        Event::handle('StartUserXRDS', array($this,&$xrdsOutputter));

        //oauth
        $xrdsOutputter->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'xml:id' => 'oauth',
                                          'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                                          'version' => '2.0'));
        $xrdsOutputter->element('Type', null, 'xri://$xrds*simple');
        $xrdsOutputter->showXrdsService(OAUTH_ENDPOINT_REQUEST,
                            common_local_url('requesttoken'),
                            array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY, OAUTH_HMAC_SHA1),
                            null,
                            $this->user->uri);
        $xrdsOutputter->showXrdsService( OAUTH_ENDPOINT_AUTHORIZE,
                            common_local_url('userauthorization'),
                            array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY, OAUTH_HMAC_SHA1));
        $xrdsOutputter->showXrdsService(OAUTH_ENDPOINT_ACCESS,
                            common_local_url('accesstoken'),
                            array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY, OAUTH_HMAC_SHA1));
        $xrdsOutputter->showXrdsService(OAUTH_ENDPOINT_RESOURCE,
                            null,
                            array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY, OAUTH_HMAC_SHA1));
        $xrdsOutputter->elementEnd('XRD');

        //omb
        $xrdsOutputter->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'xml:id' => 'omb',
                                          'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                                          'version' => '2.0'));
        $xrdsOutputter->element('Type', null, 'xri://$xrds*simple');
        $xrdsOutputter->showXrdsService(OMB_ENDPOINT_POSTNOTICE,
                            common_local_url('postnotice'));
        $xrdsOutputter->showXrdsService(OMB_ENDPOINT_UPDATEPROFILE,
                            common_local_url('updateprofile'));
        $xrdsOutputter->elementEnd('XRD');

        Event::handle('EndUserXRDS', array($this,&$xrdsOutputter));

        //misc
        $xrdsOutputter->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'version' => '2.0'));
        $xrdsOutputter->showXrdsService(OAUTH_DISCOVERY,
                            '#oauth');
        $xrdsOutputter->showXrdsService(OMB_VERSION,
                            '#omb');
        $xrdsOutputter->elementEnd('XRD');

        $xrdsOutputter->endXRDS();
    }
}
