<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A plugin to allow anonymous users to favorite notices
 *
 * If you want to keep certain users from having anonymous faving for their
 * notices initialize the plugin with the restricted array, e.g.:
 *
 * addPlugin(
 *     'AnonymousFave',
 *     array('restricted' => array('spock', 'kirk', 'bones'))
 * );
 *
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
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

define('ANONYMOUS_FAVE_PLUGIN_VERSION', '0.1');

/**
 * Anonymous Fave plugin to allow anonymous (not logged in) users
 * to favorite notices
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class AnonymousFavePlugin extends Plugin
{
    // Array of users who should not have anon faving. The default is
    // that anonymous faving is allowed for all users.
    public $restricted = array();

    function onArgsInitialize() {
        // We always want a session because we're tracking anon users
        common_ensure_session();
    }

    /**
     * Hook for ensuring our tables are created
     *
     * Ensures the fave_tally table is there and has the right columns
     *
     * @return boolean hook return
     */

    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing total number of times a notice has been faved

        $schema->ensureTable('fave_tally',
            array(
                new ColumnDef('notice_id', 'integer', null,  false, 'PRI'),
                new ColumnDef('count', 'integer', null, false),
                new ColumnDef(
                    'modified',
                    'timestamp',
                    null,
                    false,
                    null,
                    'CURRENT_TIMESTAMP',
                    'on update CURRENT_TIMESTAMP'
                )
            )
        );

        return true;
    }

    function onEndShowHTML($action)
    {
        if (!common_logged_in()) {
            // Set a place to return to when submitting forms
            common_set_returnto($action->selfUrl());
        }
    }

    function onEndShowScripts($action)
    {
        // Setup ajax calls for favoriting. Usually this is only done when
        // a user is logged in.
        $action->inlineScript('SN.U.NoticeFavor();');
    }

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls) {
            case 'Fave_tally':
                include_once $dir . '/' . $cls . '.php';
                return false;
            case 'AnonFavorAction':
                include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
                return false;
            case 'AnonDisFavorAction':
                include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
                return false;
            case 'AnonFavorForm':
                include_once $dir . '/anonfavorform.php';
                return false;
            case 'AnonDisFavorForm':
                include_once $dir . '/anondisfavorform.php';
                return false;
            default:
                return true;
        }
    }

    function onStartInitializeRouter($m)
    {
        $m->connect('main/anonfavor', array('action' => 'AnonFavor'));
        $m->connect('main/anondisfavor', array('action' => 'AnonDisFavor'));

        return true;
    }

    function onStartShowNoticeOptions($item)
    {
        if (!common_logged_in()) {
            $item->out->elementStart('div', 'notice-options');
            $item->showFaveForm();
            $item->out->elementEnd('div');
        }

        return true;
    }

    function onStartShowFaveForm($item)
    {
        if (!common_logged_in() && $this->hasAnonFaving($item)) {

            $profile = AnonymousFavePlugin::getAnonProfile();
            if (!empty($profile)) {
                if ($profile->hasFave($item->notice)) {
                    $disfavor = new AnonDisFavorForm($item->out, $item->notice);
                    $disfavor->show();
                } else {
                    $favor = new AnonFavorForm($item->out, $item->notice);
                    $favor->show();
                }
            }
        }

        return true;
    }

    function onEndFavorNoticeForm($form, $notice)
    {
        $this->showTally($form->out, $notice);
    }

    function onEndDisFavorNoticeForm($form, $notice)
    {
        $this->showTally($form->out, $notice);
    }

    function showTally($out, $notice)
    {
        $tally = Fave_tally::ensureTally($notice->id);

        if (!empty($tally)) {
            $out->elementStart(
                'div',
                array(
                    'id' => 'notice-' . $notice->id . '-tally',
                    'class' => 'notice-tally'
                )
            );
            $out->elementStart('span', array('class' => 'fave-tally-title'));
            // TRANS: Label for tally for number of times a notice was favored.
            $out->raw(sprintf(_m("Favored")));
            $out->elementEnd('span');
            $out->elementStart('span', array('class' => 'fave-tally'));
            $out->raw($tally->count);
            $out->elementEnd('span');
            $out->elementEnd('div');
        }
    }

    function onEndFavorNotice($profile, $notice)
    {
        $tally = Fave_tally::increment($notice->id);
    }

    function onEndDisfavorNotice($profile, $notice)
    {
        $tally = Fave_tally::decrement($notice->id);
    }

    static function createAnonProfile()
    {
        // Get the anon user's IP, and turn it into a nickname
        list($proxy, $ip) = common_client_ip();

        // IP + time + random number should help to avoid collisions
        $baseNickname = $ip . '-' . time() . '-' . common_good_rand(5);

        $profile = new Profile();
        $profile->nickname = $baseNickname;
        $id = $profile->insert();

        if (!$id) {
            // TRANS: Server exception.
            throw new ServerException(_m("Could not create anonymous user session."));
        }

        // Stick the Profile ID into the nickname
        $orig = clone($profile);

        $profile->nickname = 'anon-' . $id . '-' . $baseNickname;
        $result = $profile->update($orig);

        if (!$result) {
            // TRANS: Server exception.
            throw new ServerException(_m("Could not create anonymous user session."));
        }

        common_log(
            LOG_INFO,
            "AnonymousFavePlugin - created profile for anonymous user from IP: "
            . $ip
            . ', nickname = '
            . $profile->nickname
        );

        return $profile;
    }

    static function getAnonProfile()
    {

        $token = $_SESSION['anon_token'];
        $anon = base64_decode($token);

        $profile = null;

        if (!empty($anon) && substr($anon, 0, 5) == 'anon-') {
            $parts = explode('-', $anon);
            $id = $parts[1];
            // Do Profile lookup by ID instead of nickname for safety/performance
            $profile = Profile::getKV('id', $id);
        } else {
            $profile = AnonymousFavePlugin::createAnonProfile();
            // Obfuscate so it's hard to figure out the Profile ID
            $_SESSION['anon_token'] = base64_encode($profile->nickname);
        }

        return $profile;
    }

    /**
     * Determine whether a given NoticeListItem should have the
     * anonymous fave/disfave form
     *
     * @param NoticeListItem $item
     *
     * @return boolean false if the profile associated with the notice is
     *                       in the list of restricted profiles, otherwise
     *                       return true
     */
    function hasAnonFaving($item)
    {
        $profile = Profile::getKV('id', $item->notice->profile_id);
        if (in_array($profile->nickname, $this->restricted)) {
            return false;
        }

        return true;
    }

    /**
     * Provide plugin version information.
     *
     * This data is used when showing the version page.
     *
     * @param array &$versions array of version data arrays; see EVENTS.txt
     *
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $url = 'http://status.net/wiki/Plugin:AnonymousFave';

        $versions[] = array('name' => 'AnonymousFave',
            'version' => ANONYMOUS_FAVE_PLUGIN_VERSION,
            'author' => 'Zach Copley',
            'homepage' => $url,
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('Allow anonymous users to favorite notices.'));

        return true;
    }
}
