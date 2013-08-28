<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * 'Sign in with Twitter' login page
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Login
 * @package   StatusNet
 * @author    Julien Chaumond <chaumond@gmail.com>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';

/**
 * Page for logging in with Twitter
 *
 * @category Login
 * @package  StatusNet
 * @author   Julien Chaumond <chaumond@gmail.com>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */
class TwitterloginAction extends Action
{
    function handle($args)
    {
        parent::handle($args);

        if (common_is_real_login()) {
            // TRANS: Client error displayed when trying to log in using Twitter while already logged in to StatusNet.
            $this->clientError(_m('Already logged in.'));
        }

        $this->showPage();
    }

    function title()
    {
        // TRANS: Title for login using Twitter page.
        return _m('TITLE','Twitter Login');
    }

    function getInstructions()
    {
        // TRANS: Instructions for login using Twitter page.
        return _m('Login with your Twitter account');
    }

    function showPageNotice()
    {
        $instr = $this->getInstructions();
        $output = common_markup_to_html($instr);
        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    function showContent()
    {
        $this->elementStart('a', array('href' => common_local_url('twitterauthorization',
                                                                  null,
                                                                  array('signin' => true))));
        $this->element('img', array('src' => Plugin::staticPath('TwitterBridge', 'Sign-in-with-Twitter-lighter.png'),
                                    // TRANS: Alternative text for "sign in with Twitter" image.
                                    'alt' => _m('Sign in with Twitter')));
        $this->elementEnd('a');
    }

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}
