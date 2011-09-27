<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010-2011, StatusNet, Inc.
 *
 * An action that handles deauthorize callbacks from Facebook
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

/*
 * Action class for handling deauthorize callbacks from Facebook. If the user
 * doesn't have a password let her know she'll need to contact the site
 * admin to get back into her account (if possible).
 */
class FacebookdeauthorizeAction extends Action
{
    private $facebook;

    /**
     * For initializing members of the class.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     */
    function prepare($args)
    {
        $this->facebook = Facebookclient::getFacebook();

        return true;
    }

    /**
     * Handler method
     *
     * @param array $args is ignored since it's now passed in in prepare()
     */
    function handle($args)
    {
        parent::handle($args);

        $data = $this->facebook->getSignedRequest();

        if (isset($data['user_id'])) {

            $fbuid = $data['user_id'];

            $flink = Foreign_link::getByForeignID($fbuid, FACEBOOK_SERVICE);
            $user = $flink->getUser();

            // Remove the link to Facebook
            $result = $flink->delete();

            if (!$result) {
                common_log_db_error($flink, 'DELETE', __FILE__);
                common_log(
                    LOG_WARNING,
                    sprintf(
                        'Unable to delete Facebook foreign link '
                            . 'for %s (%d), fbuid %d',
                        $user->nickname,
                        $user->id,
                        $fbuid
                    ),
                    __FILE__
                );
                return;
            }

            common_log(
                LOG_INFO,
                sprintf(
                    'Facebook callback: %s (%d), fbuid %d has deauthorized '
                        . 'the Facebook application.',
                    $user->nickname,
                    $user->id,
                    $fbuid
                ),
                __FILE__
            );

            // Warn the user about being locked out of their account
            // if we can.
            if (empty($user->password) && !empty($user->email)) {
                Facebookclient::emailWarn($user);
            } else {
                common_log(
                    LOG_WARNING,
                    sprintf(
                        '%s (%d), fbuid %d has deauthorized his/her Facebook '
                        . 'connection but hasn\'t set a password so s/he '
                        . 'is locked out.',
                        $user->nickname,
                        $user->id,
                        $fbuid
                    ),
                    __FILE__
                );
            }

        } else {
            if (!empty($data)) {
                common_log(
                    LOG_WARNING,
                    sprintf(
                        'Facebook called the deauthorize callback '
                        . ' but didn\'t provide a user ID.'
                    ),
                    __FILE__
                );
            } else {
                // It probably wasn't Facebook that hit this action,
                // so redirect to the public timeline
                common_redirect(common_local_url('public'), 303);
            }
        }
    }
}
