#!/usr/bin/env php
<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

// Tune number of processes and how often to poll Twitter
// XXX: Should these things be in config.php?
define('MAXCHILDREN', 2);
define('POLL_INTERVAL', 60); // in seconds

$shortoptions = 'di::';
$longoptions = array('id::', 'debug');

$helptext = <<<END_OF_TRIM_HELP
Batch script for retrieving Twitter messages from foreign service.

  -i --id              Identity (default 'generic')
  -d --debug           Debug (lots of log output)

END_OF_TRIM_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';
require_once INSTALLDIR . '/lib/common.php';
require_once INSTALLDIR . '/lib/daemon.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitterbasicauthclient.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitteroauthclient.php';

/**
 * Fetch statuses from Twitter
 *
 * Fetches statuses from Twitter and inserts them as notices
 *
 * NOTE: an Avatar path MUST be set in config.php for this
 * script to work, e.g.:
 *     $config['avatar']['path'] = $config['site']['path'] . '/avatar/';
 *
 * @todo @fixme @gar Fix the above. For some reason $_path is always empty when
 * this script is run, so the default avatar path is always set wrong in
 * default.php. Therefore it must be set explicitly in config.php. --Z
 *
 * @category Twitter
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class TwitterStatusFetcher extends ParallelizingDaemon
{
    /**
     *  Constructor
     *
     * @param string  $id           the name/id of this daemon
     * @param int     $interval     sleep this long before doing everything again
     * @param int     $max_children maximum number of child processes at a time
     * @param boolean $debug        debug output flag
     *
     * @return void
     *
     **/
    function __construct($id = null, $interval = 60,
                         $max_children = 2, $debug = null)
    {
        parent::__construct($id, $interval, $max_children, $debug);
    }

    /**
     * Name of this daemon
     *
     * @return string Name of the daemon.
     */

    function name()
    {
        return ('twitterstatusfetcher.'.$this->_id);
    }

    /**
     * Find all the Twitter foreign links for users who have requested
     * importing of their friends' timelines
     *
     * @return array flinks an array of Foreign_link objects
     */

    function getObjects()
    {
        global $_DB_DATAOBJECT;

        $flink = new Foreign_link();
        $conn = &$flink->getDatabaseConnection();

        $flink->service = TWITTER_SERVICE;
        $flink->orderBy('last_noticesync');
        $flink->find();

        $flinks = array();

        while ($flink->fetch()) {

            if (($flink->noticesync & FOREIGN_NOTICE_RECV) ==
                FOREIGN_NOTICE_RECV) {
                $flinks[] = clone($flink);
                common_log(LOG_INFO, "sync: foreign id $flink->foreign_id");
            } else {
                common_log(LOG_INFO, "nothing to sync");
            }
        }

        $flink->free();
        unset($flink);

        $conn->disconnect();
        unset($_DB_DATAOBJECT['CONNECTIONS']);

        return $flinks;
    }

    function childTask($flink) {

        // Each child ps needs its own DB connection

        // Note: DataObject::getDatabaseConnection() creates
        // a new connection if there isn't one already

        $conn = &$flink->getDatabaseConnection();

        $this->getTimeline($flink);

        $flink->last_friendsync = common_sql_now();
        $flink->update();

        $conn->disconnect();

        // XXX: Couldn't find a less brutal way to blow
        // away a cached connection

        global $_DB_DATAOBJECT;
        unset($_DB_DATAOBJECT['CONNECTIONS']);
    }

    function getTimeline($flink)
    {
        if (empty($flink)) {
            common_log(LOG_WARNING, $this->name() .
                       " - Can't retrieve Foreign_link for foreign ID $fid");
            return;
        }

        common_debug($this->name() . ' - Trying to get timeline for Twitter user ' .
                     $flink->foreign_id);

        // XXX: Biggest remaining issue - How do we know at which status
        // to start importing?  How many statuses?  Right now I'm going
        // with the default last 20.

        $client = null;

        if (TwitterOAuthClient::isPackedToken($flink->credentials)) {
            $token = TwitterOAuthClient::unpackToken($flink->credentials);
            $client = new TwitterOAuthClient($token->key, $token->secret);
            common_debug($this->name() . ' - Grabbing friends timeline with OAuth.');
        } else {
            $client = new TwitterBasicAuthClient($flink);
            common_debug($this->name() . ' - Grabbing friends timeline with basic auth.');
        }

        $timeline = null;

        try {
            $timeline = $client->statusesHomeTimeline();
        } catch (Exception $e) {
            common_log(LOG_WARNING, $this->name() .
                       ' - Twitter client unable to get friends timeline for user ' .
                       $flink->user_id . ' - code: ' .
                       $e->getCode() . 'msg: ' . $e->getMessage());
        }

        if (empty($timeline)) {
            common_log(LOG_WARNING, $this->name() .  " - Empty timeline.");
            return;
        }

        common_debug(LOG_INFO, $this->name() . ' - Retrieved ' . sizeof($timeline) . ' statuses from Twitter.');

        // Reverse to preserve order

        foreach (array_reverse($timeline) as $status) {

            // Hacktastic: filter out stuff coming from this StatusNet

            $source = mb_strtolower(common_config('integration', 'source'));

            if (preg_match("/$source/", mb_strtolower($status->source))) {
                common_debug($this->name() . ' - Skipping import of status ' .
                             $status->id . ' with source ' . $source);
                continue;
            }

            $this->saveStatus($status, $flink);
        }

        // Okay, record the time we synced with Twitter for posterity

        $flink->last_noticesync = common_sql_now();
        $flink->update();
    }

    function saveStatus($status, $flink)
    {
        $profile = $this->ensureProfile($status->user);

        if (empty($profile)) {
            common_log(LOG_ERR, $this->name() .
                ' - Problem saving notice. No associated Profile.');
            return;
        }

        $statusUri = 'http://twitter.com/'
            . $status->user->screen_name
            . '/status/'
            . $status->id;

        // check to see if we've already imported the status

        $dupe = $this->checkDupe($profile, $statusUri);

        if (!empty($dupe)) {
            common_log(
                LOG_INFO,
                $this->name() .
                " - Ignoring duplicate import: $statusUri"
            );
            return;
        }

        $notice = new Notice();

        $notice->profile_id = $profile->id;
        $notice->uri        = $statusUri;
        $notice->url        = $statusUri;
        $notice->created    = strftime(
            '%Y-%m-%d %H:%M:%S',
            strtotime($status->created_at)
        );

        $notice->source     = 'twitter';
        $notice->reply_to   = null;
        $notice->is_local   = Notice::GATEWAY;

        $notice->content    = common_shorten_links($status->text);
        $notice->rendered   = common_render_content(
            $notice->content,
            $notice
        );

        if (Event::handle('StartNoticeSave', array(&$notice))) {

            $id = $notice->insert();

            if (!$id) {
                common_log_db_error($notice, 'INSERT', __FILE__);
                common_log(LOG_ERR, $this->name() .
                    ' - Problem saving notice.');
            }

            Event::handle('EndNoticeSave', array($notice));
        }

        $orig = clone($notice);
        $conv = Conversation::create();

        $notice->conversation = $conv->id;

        if (!$notice->update($orig)) {
            common_log_db_error($notice, 'UPDATE', __FILE__);
            common_log(LOG_ERR, $this->name() .
                ' - Problem saving notice.');
        }

        Inbox::insertNotice($flink->user_id, $notice->id);
        $notice->blowOnInsert();

        return $notice;
    }

    /**
     * Look up a Profile by profileurl field.  Profile::staticGet() was
     * not working consistently.
     *
     * @param string $nickname   local nickname of the Twitter user
     * @param string $profileurl the profile url
     *
     * @return mixed value the first Profile with that url, or null
     */

    function getProfileByUrl($nickname, $profileurl)
    {
        $profile = new Profile();
        $profile->nickname = $nickname;
        $profile->profileurl = $profileurl;
        $profile->limit(1);

        if ($profile->find()) {
            $profile->fetch();
            return $profile;
        }

        return null;
    }

    /**
     * Check to see if this Twitter status has already been imported
     *
     * @param Profile $profile   Twitter user's local profile
     * @param string  $statusUri URI of the status on Twitter
     *
     * @return mixed value a matching Notice or null
     */

    function checkDupe($profile, $statusUri)
    {
        $notice = new Notice();
        $notice->uri = $statusUri;
        $notice->profile_id = $profile->id;
        $notice->limit(1);

        if ($notice->find()) {
            $notice->fetch();
            return $notice;
        }

        return null;
    }

    function ensureProfile($user)
    {
        // check to see if there's already a profile for this user

        $profileurl = 'http://twitter.com/' . $user->screen_name;
        $profile = $this->getProfileByUrl($user->screen_name, $profileurl);

        if (!empty($profile)) {
            common_debug($this->name() .
                         " - Profile for $profile->nickname found.");

            // Check to see if the user's Avatar has changed

            $this->checkAvatar($user, $profile);
            return $profile;

        } else {

            common_debug($this->name() . ' - Adding profile and remote profile ' .
                         "for Twitter user: $profileurl.");

            $profile = new Profile();
            $profile->query("BEGIN");

            $profile->nickname = $user->screen_name;
            $profile->fullname = $user->name;
            $profile->homepage = $user->url;
            $profile->bio = $user->description;
            $profile->location = $user->location;
            $profile->profileurl = $profileurl;
            $profile->created = common_sql_now();

            try {
                $id = $profile->insert();
            } catch(Exception $e) {
                common_log(LOG_WARNING, $this->name . ' Couldn\'t insert profile - ' . $e->getMessage());
            }

            if (empty($id)) {
                common_log_db_error($profile, 'INSERT', __FILE__);
                $profile->query("ROLLBACK");
                return false;
            }

            // check for remote profile

            $remote_pro = Remote_profile::staticGet('uri', $profileurl);

            if (empty($remote_pro)) {

                $remote_pro = new Remote_profile();

                $remote_pro->id = $id;
                $remote_pro->uri = $profileurl;
                $remote_pro->created = common_sql_now();

                try {
                    $rid = $remote_pro->insert();
                } catch (Exception $e) {
                    common_log(LOG_WARNING, $this->name() . ' Couldn\'t save remote profile - ' . $e->getMessage());
                }

                if (empty($rid)) {
                    common_log_db_error($profile, 'INSERT', __FILE__);
                    $profile->query("ROLLBACK");
                    return false;
                }
            }

            $profile->query("COMMIT");

            $this->saveAvatars($user, $id);

            return $profile;
        }
    }

    function checkAvatar($twitter_user, $profile)
    {
        global $config;

        $path_parts = pathinfo($twitter_user->profile_image_url);

        $newname = 'Twitter_' . $twitter_user->id . '_' .
            $path_parts['basename'];

        $oldname = $profile->getAvatar(48)->filename;

        if ($newname != $oldname) {
            common_debug($this->name() . ' - Avatar for Twitter user ' .
                         "$profile->nickname has changed.");
            common_debug($this->name() . " - old: $oldname new: $newname");

            $this->updateAvatars($twitter_user, $profile);
        }

        if ($this->missingAvatarFile($profile)) {
            common_debug($this->name() . ' - Twitter user ' .
                         $profile->nickname .
                         ' is missing one or more local avatars.');
            common_debug($this->name() ." - old: $oldname new: $newname");

            $this->updateAvatars($twitter_user, $profile);
        }
    }

    function updateAvatars($twitter_user, $profile) {

        global $config;

        $path_parts = pathinfo($twitter_user->profile_image_url);

        $img_root = substr($path_parts['basename'], 0, -11);
        $ext = $path_parts['extension'];
        $mediatype = $this->getMediatype($ext);

        foreach (array('mini', 'normal', 'bigger') as $size) {
            $url = $path_parts['dirname'] . '/' .
                $img_root . '_' . $size . ".$ext";
            $filename = 'Twitter_' . $twitter_user->id . '_' .
                $img_root . "_$size.$ext";

            $this->updateAvatar($profile->id, $size, $mediatype, $filename);
            $this->fetchAvatar($url, $filename);
        }
    }

    function missingAvatarFile($profile) {
        foreach (array(24, 48, 73) as $size) {
            $filename = $profile->getAvatar($size)->filename;
            $avatarpath = Avatar::path($filename);
            if (file_exists($avatarpath) == FALSE) {
                return true;
            }
        }
        return false;
    }

    function getMediatype($ext)
    {
        $mediatype = null;

        switch (strtolower($ext)) {
        case 'jpg':
            $mediatype = 'image/jpg';
            break;
        case 'gif':
            $mediatype = 'image/gif';
            break;
        default:
            $mediatype = 'image/png';
        }

        return $mediatype;
    }

    function saveAvatars($user, $id)
    {
        global $config;

        $path_parts = pathinfo($user->profile_image_url);
        $ext = $path_parts['extension'];
        $end = strlen('_normal' . $ext);
        $img_root = substr($path_parts['basename'], 0, -($end+1));
        $mediatype = $this->getMediatype($ext);

        foreach (array('mini', 'normal', 'bigger') as $size) {
            $url = $path_parts['dirname'] . '/' .
                $img_root . '_' . $size . ".$ext";
            $filename = 'Twitter_' . $user->id . '_' .
                $img_root . "_$size.$ext";

            if ($this->fetchAvatar($url, $filename)) {
                $this->newAvatar($id, $size, $mediatype, $filename);
            } else {
                common_log(LOG_WARNING, $id() .
                           " - Problem fetching Avatar: $url");
            }
        }
    }

    function updateAvatar($profile_id, $size, $mediatype, $filename) {

        common_debug($this->name() . " - Updating avatar: $size");

        $profile = Profile::staticGet($profile_id);

        if (empty($profile)) {
            common_debug($this->name() . " - Couldn't get profile: $profile_id!");
            return;
        }

        $sizes = array('mini' => 24, 'normal' => 48, 'bigger' => 73);
        $avatar = $profile->getAvatar($sizes[$size]);

        // Delete the avatar, if present

        if ($avatar) {
            $avatar->delete();
        }

        $this->newAvatar($profile->id, $size, $mediatype, $filename);
    }

    function newAvatar($profile_id, $size, $mediatype, $filename)
    {
        global $config;

        $avatar = new Avatar();
        $avatar->profile_id = $profile_id;

        switch($size) {
        case 'mini':
            $avatar->width  = 24;
            $avatar->height = 24;
            break;
        case 'normal':
            $avatar->width  = 48;
            $avatar->height = 48;
            break;
        default:

            // Note: Twitter's big avatars are a different size than
            // StatusNet's (StatusNet's = 96)

            $avatar->width  = 73;
            $avatar->height = 73;
        }

        $avatar->original = 0; // we don't have the original
        $avatar->mediatype = $mediatype;
        $avatar->filename = $filename;
        $avatar->url = Avatar::url($filename);

        $avatar->created = common_sql_now();

        try {
            $id = $avatar->insert();
        } catch (Exception $e) {
            common_log(LOG_WARNING, $this->name() . ' Couldn\'t insert avatar - ' . $e->getMessage());
        }

        if (empty($id)) {
            common_log_db_error($avatar, 'INSERT', __FILE__);
            return null;
        }

        common_debug($this->name() .
                     " - Saved new $size avatar for $profile_id.");

        return $id;
    }

    /**
     * Fetch a remote avatar image and save to local storage.
     *
     * @param string $url avatar source URL
     * @param string $filename bare local filename for download
     * @return bool true on success, false on failure
     */
    function fetchAvatar($url, $filename)
    {
        common_debug($this->name() . " - Fetching Twitter avatar: $url");

        $request = HTTPClient::start();
        $response = $request->get($url);
        if ($response->isOk()) {
            $avatarfile = Avatar::path($filename);
            $ok = file_put_contents($avatarfile, $response->getBody());
            if (!$ok) {
                common_log(LOG_WARNING, $this->name() .
                           " - Couldn't open file $filename");
                return false;
            }
        } else {
            return false;
        }

        return true;
    }
}

$id    = null;
$debug = null;

if (have_option('i')) {
    $id = get_option_value('i');
} else if (have_option('--id')) {
    $id = get_option_value('--id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

if (have_option('d') || have_option('debug')) {
    $debug = true;
}

$fetcher = new TwitterStatusFetcher($id, 60, 2, $debug);
$fetcher->runOnce();

