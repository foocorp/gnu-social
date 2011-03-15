<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * An action for logging in with Facebook
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class FacebookloginAction extends Action
{

    function handle($args)
    {
        parent::handle($args);

        if (common_is_real_login()) {
            $this->clientError(_m('Already logged in.'));
        } else {
            $this->showPage();
        }
    }

    function getInstructions()
    {
        // TRANS: Instructions.
        return _m('Login with your Facebook Account');
    }

    function showPageNotice()
    {
        $instr = $this->getInstructions();
        $output = common_markup_to_html($instr);
        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    function title()
    {
        // TRANS: Page title.
        return _m('Login with Facebook');
    }

    function showContent() {

        $this->elementStart('fieldset');

        $facebook = Facebookclient::getFacebook();

        // Degrade to plain link if JavaScript is not available
        $this->elementStart(
            'a',
            array(
                'href' => $facebook->getLoginUrl(
                    array(
                        'next'       => common_local_url('facebookfinishlogin'),
                        'cancel'     => common_local_url('facebooklogin'),
                        'req_perms'  => 'read_stream,publish_stream,offline_access,user_status,user_location,user_website,email'
                    )
                 ),
                'id'    => 'facebook_button'
            )
        );

        $attrs = array(
            'src' => Plugin::staticPath('FacebookBridge', 'images/login-button.png'),
            'alt'   => 'Login with Facebook',
            'title' => 'Login with Facebook'
        );

        $this->element('img', $attrs);

        $this->elementEnd('a');

        /*
        $this->element('div', array('id' => 'fb-root'));
        $this->script(
            sprintf(
                'http://connect.facebook.net/en_US/all.js#appId=%s&xfbml=1',
                common_config('facebook', 'appid')
            )
        );
        $this->element('fb:facepile', array('max-rows' => '2', 'width' =>'300'));
        */
        $this->elementEnd('fieldset');
    }

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}

