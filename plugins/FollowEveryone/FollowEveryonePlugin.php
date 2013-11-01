<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * When a new user registers, all existing users follow them automatically.
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
 * @category  Community
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Plugin to make all users follow each other at registration
 *
 * Users can unfollow afterwards if they want.
 *
 * @category  Sample
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class FollowEveryonePlugin extends Plugin
{
    /**
     * Called when a new user is registered.
     *
     * We find all users, and try to subscribe them to the new user, and
     * the new user to them. Exceptions (like silenced users or whatever)
     * are caught, logged, and ignored.
     *
     * @param Profile $profile The new user's profile
     *
     * @return boolean hook value
     */
    public function onEndUserRegister(Profile $profile)
    {
        $otherUser = new User();
        $otherUser->whereAdd('id != ' . $profile->id);

        if ($otherUser->find()) {
            while ($otherUser->fetch()) {
                $otherProfile = $otherUser->getProfile();
                try {
                    if (User_followeveryone_prefs::followEveryone($otherUser->id)) {
                        Subscription::start($otherProfile, $profile);
                    }
                    Subscription::start($profile, $otherProfile);
                } catch (Exception $e) {
                    common_log(LOG_WARNING, $e->getMessage());
                    continue;
                }
            }
        }

        $ufep = new User_followeveryone_prefs();

        $ufep->user_id        = $profile->id;
        $ufep->followeveryone = true;

        $ufep->insert();

        return true;
    }

    /**
     * Database schema setup
     *
     * Plugins can add their own tables to the StatusNet database. Plugins
     * should use StatusNet's schema interface to add or delete tables. The
     * ensureTable() method provides an easy way to ensure a table's structure
     * and availability.
     *
     * By default, the schema is checked every time StatusNet is run (say, when
     * a Web page is hit). Admins can configure their systems to only check the
     * schema when the checkschema.php script is run, greatly improving performance.
     * However, they need to remember to run that script after installing or
     * upgrading a plugin!
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles
        $schema->ensureTable('user_followeveryone_prefs', User_followeveryone_prefs::schemaDef());

        return true;
    }

    /**
     * Show a checkbox on the profile form to ask whether to follow everyone
     *
     * @param Action $action The action being executed
     *
     * @return boolean hook value
     */
    function onEndProfileFormData($action)
    {
        $user = common_current_user();

        $action->elementStart('li');
        // TRANS: Checkbox label in form for profile settings.
        $action->checkbox('followeveryone', _m('Follow everyone'),
                          ($action->arg('followeveryone')) ?
                          $action->arg('followeveryone') :
                          User_followeveryone_prefs::followEveryone($user->id));
        $action->elementEnd('li');

        return true;
    }

    /**
     * Save checkbox value for following everyone
     *
     * @param Action $action The action being executed
     *
     * @return boolean hook value
     */
    function onEndProfileSaveForm($action)
    {
        $user = common_current_user();

        User_followeveryone_prefs::savePref($user->id,
                                            $action->boolean('followeveryone'));

        return true;
    }

    /**
     * Provide version information about this plugin.
     *
     * @param Array &$versions Array of version data
     *
     * @return boolean hook value
     *
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'FollowEveryone',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:FollowEveryone',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('New users follow everyone at registration and are followed in return.'));
        return true;
    }
}
