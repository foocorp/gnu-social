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
 * @copyright 2010-2011 StatusNet, Inc.
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
            // TRANS: Client error displayed when trying to login while already logged in.
            $this->clientError(_m('Already logged in.'));
        } else {
            $this->showPage();
        }
    }

    function getInstructions()
    {
        // TRANS: Form instructions.
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

        $params = array(
          'scope' => 'read_stream,publish_stream,offline_access,user_status,user_location,user_website,email',
          'redirect_uri' => common_local_url('facebookfinishlogin')
        );

        // Degrade to plain link if JavaScript is not available
        $this->elementStart(
            'a',
            array(
                'href' => $facebook->getLoginUrl($params),
                'id'    => 'facebook_button'
            )
        );

        $attrs = array(
            'src' => Plugin::staticPath('FacebookBridge', 'images/login-button.png'),
            // TRANS: Alt text for "Login with Facebook" image.
            'alt'   => _m('Login with Facebook'),
            // TRANS: Title for "Login with Facebook" image.
            'title' => _m('Login with Facebook.')
        );

        $this->element('img', $attrs);

        $this->elementEnd('a');

        $this->elementEnd('fieldset');
    }

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}
