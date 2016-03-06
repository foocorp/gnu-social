<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Email-based registration, as on the StatusNet OnDemand service
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
 * @category  Email registration
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Email based registration plugin
 *
 * @category  Email registration
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EmailRegistrationPlugin extends Plugin
{
    const CONFIRMTYPE = 'register';

    function onArgsInitialize(&$args)
    {
        if (array_key_exists('action', $args) && $args['action'] == 'register') {
            // YOINK!
            $args['action'] = 'emailregister';
        }
        return true;
    }

    function onLoginAction($action, &$login)
    {
        if ($action == 'emailregister') {
            $login = true;
            return false;
        }
        return true;
    }

    function onStartLoadDoc(&$title, &$output)
    {
        $dir = dirname(__FILE__);

        // @todo FIXME: i18n issue.
        $docFile = DocFile::forTitle($title, $dir.'/doc-src/');

        if (!empty($docFile)) {
            $output = $docFile->toHTML();
            return false;
        }

        return true;
    }

    static function registerEmail($email)
    {
        $old = User::getKV('email', $email);

        if (!empty($old)) {
            // TRANS: Error text when trying to register with an already registered e-mail address.
            // TRANS: %s is the URL to recover password at.
            throw new ClientException(sprintf(_m('A user with that email address already exists. You can use the '.
                                                 '<a href="%s">password recovery</a> tool to recover a missing password.'),
                                              common_local_url('recoverpassword')));
        }

        $valid = false;

        if (Event::handle('StartValidateUserEmail', array(null, $email, &$valid))) {
            $valid = Validate::email($email, common_config('email', 'check_domain'));
            Event::handle('EndValidateUserEmail', array(null, $email, &$valid));
        }

        if (!$valid) {
            // TRANS: Error text when trying to register with an invalid e-mail address.
            throw new ClientException(_m('Not a valid email address.'));
        }

        try {
            $confirm = Confirm_address::getByAddress($email, self::CONFIRMTYPE);
        } catch (NoResultException $e) {
            $confirm = Confirm_address::saveNew(null, $email, 'register');
        }

        return $confirm;
    }

    static function nicknameFromEmail($email)
    {
        $parts = explode('@', $email);

        $nickname = $parts[0];

        $nickname = preg_replace('/[^A-Za-z0-9]/', '', $nickname);

        $nickname = Nickname::normalize($nickname);

        $original = $nickname;

        $n = 0;

        while (User::getKV('nickname', $nickname)) {
            $n++;
            $nickname = $original . $n;
        }

        return $nickname;
    }

    static function sendConfirmEmail($confirm, $title=null)
    {
        $sitename = common_config('site', 'name');

        $recipients = array($confirm->address);

        $headers['From'] = mail_notify_from();
        $headers['To'] = trim($confirm->address);
         // TRANS: Subject for confirmation e-mail.
         // TRANS: %s is the StatusNet sitename.
        $headers['Subject'] = sprintf(_m('Welcome to %s'), $sitename);
        $headers['Content-Type'] = 'text/html; charset=UTF-8';

        $confirmUrl = common_local_url('register', array('code' => $confirm->code));

        if (empty($title)) {
            $title = 'confirmemailreg';
        }

        $confirmTemplate = DocFile::forTitle($title, DocFile::mailPaths());

        $body = $confirmTemplate->toHTML(array('confirmurl' => $confirmUrl));

        mail_send($recipients, $headers, $body);
    }

    function onEndDocFileForTitle($title, $paths, &$filename)
    {
        if ($title == 'confirmemailreg' && empty($filename)) {
            $filename = dirname(__FILE__).'/mail-src/'.$title;
            return false;
        }
        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'EmailRegistration',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/EmailRegistration',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Use email only for registration.'));
        return true;
    }
}
