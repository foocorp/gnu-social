<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin that requires the user to have a validated email address before they can post notices
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class RequireValidatedEmailPlugin extends Plugin
{
    // Users created before this time will be grandfathered in
    // without the validation requirement.
    public $grandfatherCutoff=null;

    // If OpenID plugin is installed, users with a verified OpenID
    // association whose provider URL matches one of these regexes
    // will be considered to be sufficiently valid for our needs.
    //
    // For example, to trust WikiHow and Wikipedia OpenID users:
    //
    // addPlugin('RequireValidatedEmailPlugin', array(
    //    'trustedOpenIDs' => array(
    //        '!^http://\w+\.wikihow\.com/!',
    //        '!^http://\w+\.wikipedia\.org/!',
    //    ),
    // ));
    public $trustedOpenIDs=array();

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Event handler for notice saves; rejects the notice
     * if user's address isn't validated.
     *
     * @param Notice $notice
     * @return bool hook result code
     */
    function onStartNoticeSave($notice)
    {
        $user = User::staticGet('id', $notice->profile_id);
        if (!empty($user)) { // it's a remote notice
            if (!$this->validated($user)) {
                throw new ClientException(_m("You must validate your email address before posting."));
            }
        }
        return true;
    }

    /**
     * Event handler for registration attempts; rejects the registration
     * if email field is missing.
     *
     * @param RegisterAction $action
     * @return bool hook result code
     */
    function onStartRegistrationTry($action)
    {
        $email = $action->trimmed('email');

        if (empty($email)) {
            $action->showForm(_m('You must provide an email address to register.'));
            return false;
        }

        // Default form will run address format validation and reject if bad.

        return true;
    }

    /**
     * Check if a user has a validated email address or has been
     * otherwise grandfathered in.
     *
     * @param User $user
     * @return bool
     */
    protected function validated($user)
    {
        // The email field is only stored after validation...
        // Until then you'll find them in confirm_address.
        $knownGood = !empty($user->email) ||
                     $this->grandfathered($user) ||
                     $this->hasTrustedOpenID($user);

        // Give other plugins a chance to override, if they can validate
        // that somebody's ok despite a non-validated email.
        Event::handle('RequireValidatedEmailPlugin_Override', array($user, &$knownGood));

        return $knownGood;
    }

    /**
     * Check if a user was created before the grandfathering cutoff.
     * If so, we won't need to check for validation.
     *
     * @param User $user
     * @return bool
     */
    protected function grandfathered($user)
    {
        if ($this->grandfatherCutoff) {
            $created = strtotime($user->created . " GMT");
            $cutoff = strtotime($this->grandfatherCutoff);
            if ($created < $cutoff) {
                return true;
            }
        }
        return false;
    }

    /**
     * Override for RequireValidatedEmail plugin. If we have a user who's
     * not validated an e-mail, but did come from a trusted provider,
     * we'll consider them ok.
     */
    function hasTrustedOpenID($user)
    {
        if ($this->trustedOpenIDs && class_exists('User_openid')) {
            foreach ($this->trustedOpenIDs as $regex) {
                $oid = new User_openid();
                $oid->user_id = $user->id;
                $oid->find();
                while ($oid->fetch()) {
                    if (preg_match($regex, $oid->canonical)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Require Validated Email',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews, Evan Prodromou, Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:RequireValidatedEmail',
                            'rawdescription' =>
                            _m('The Require Validated Email plugin disables posting for accounts that do not have a validated email address.'));
        return true;
    }
}

