<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @author    Zach Copley <zach@status.net>
 * @author    Julien C <chaumond@gmail.com>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';

/**
 * Encapsulation of the Twitter status -> notice incoming bridge import.
 * Is used by both the polling twitterstatusfetcher.php daemon, and the
 * in-progress streaming import.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Julien C <chaumond@gmail.com>
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @link     http://twitter.com/
 */
class TwitterImport
{
    public $avatarsizename = 'reasonably_small'; // a Twitter size name for 128x128 px
    public $avatarsize = 128;   // they're square...

    public function importStatus($status)
    {
        // Hacktastic: filter out stuff coming from this StatusNet
        $source = mb_strtolower(common_config('integration', 'source'));

        if (preg_match("/$source/", mb_strtolower($status->source))) {
            common_debug(__METHOD__ . ' - Skipping import of status ' .
                         twitter_id($status) . " with source {$source}");
            return null;
        }

        // Don't save it if the user is protected
        // FIXME: save it but treat it as private
        if ($status->user->protected) {
            return null;
        }

        $notice = $this->saveStatus($status);

        return $notice;
    }

    function name()
    {
        return get_class($this);
    }

    function saveStatus($status)
    {
        $profile = $this->ensureProfile($status->user);

        if (empty($profile)) {
            common_log(LOG_ERR, __METHOD__ . ' - Problem saving notice. No associated Profile.');
            return null;
        }

        $statusId = twitter_id($status);
        $statusUri = $this->makeStatusURI($status->user->screen_name, $statusId);

        // check to see if we've already imported the status
        $n2s = Notice_to_status::getKV('status_id', $statusId);

        if (!empty($n2s)) {
            common_log(
                LOG_INFO,
                __METHOD__ . " - Ignoring duplicate import: {$statusId}"
            );
            return Notice::getKV('id', $n2s->notice_id);
        }

        // If it's a retweet, save it as a repeat!
        if (!empty($status->retweeted_status)) {
            common_log(LOG_INFO, "Status {$statusId} is a retweet of " . twitter_id($status->retweeted_status) . ".");
            $original = $this->saveStatus($status->retweeted_status);
            if (empty($original)) {
                return null;
            } else {
                $author = $original->getProfile();
                // TRANS: Message used to repeat a notice. RT is the abbreviation of 'retweet'.
                // TRANS: %1$s is the repeated user's name, %2$s is the repeated notice.
                $content = sprintf(_m('RT @%1$s %2$s'),
                                   $author->nickname,
                                   $original->content);

                if (Notice::contentTooLong($content)) {
                    $contentlimit = Notice::maxContent();
                    $content = mb_substr($content, 0, $contentlimit - 4) . ' ...';
                }

                $repeat = Notice::saveNew($profile->id,
                                          $content,
                                          'twitter',
                                          array('repeat_of' => $original->id,
                                                'uri' => $statusUri,
                                                'is_local' => Notice::GATEWAY));
                common_log(LOG_INFO, "Saved {$repeat->id} as a repeat of {$original->id}");
                Notice_to_status::saveNew($repeat->id, $statusId);
                return $repeat;
            }
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

        $replyTo = twitter_id($status, 'in_reply_to_status_id');
        if (!empty($replyTo)) {
            common_log(LOG_INFO, "Status {$statusId} is a reply to status {$replyTo}");
            $n2s = Notice_to_status::getKV('status_id', $replyTo);
            if (empty($n2s)) {
                common_log(LOG_INFO, "Couldn't find local notice for status {$replyTo}");
            } else {
                $reply = Notice::getKV('id', $n2s->notice_id);
                if (empty($reply)) {
                    common_log(LOG_INFO, "Couldn't find local notice for status {$replyTo}");
                } else {
                    common_log(LOG_INFO, "Found local notice {$reply->id} for status {$replyTo}");
                    $notice->reply_to     = $reply->id;
                    $notice->conversation = $reply->conversation;
                }
            }
        }

        if (empty($notice->conversation)) {
            $conv = Conversation::create();
            $notice->conversation = $conv->id;
            common_log(LOG_INFO, "No known conversation for status {$statusId} so making a new one {$conv->id}.");
        }

        $notice->is_local   = Notice::GATEWAY;

        $notice->content  = html_entity_decode($this->linkify($status, FALSE), ENT_QUOTES, 'UTF-8');
        $notice->rendered = $this->linkify($status, TRUE);

        if (Event::handle('StartNoticeSave', array(&$notice))) {

            $id = $notice->insert();

            if (!$id) {
                common_log_db_error($notice, 'INSERT', __FILE__);
                common_log(LOG_ERR, __METHOD__ . ' - Problem saving notice.');
            }

            Event::handle('EndNoticeSave', array($notice));
        }

        Notice_to_status::saveNew($notice->id, $statusId);

        $this->saveStatusMentions($notice, $status);
        $this->saveStatusAttachments($notice, $status);

        $notice->blowOnInsert();

        return $notice;
    }

    /**
     * Make an URI for a status.
     *
     * @param object $status status object
     *
     * @return string URI
     */
    function makeStatusURI($username, $id)
    {
        return 'http://twitter.com/#!/'
          . $username
          . '/status/'
          . $id;
    }


    /**
     * Look up a Profile by profileurl field.  Profile::getKV() was
     * not working consistently.
     *
     * @param string $nickname   local nickname of the Twitter user
     * @param string $profileurl the profile url
     *
     * @return mixed value the first Profile with that url, or null
     */
    protected function getProfileByUrl($nickname, $profileurl)
    {
        $profile = new Profile();
        $profile->nickname = $nickname;
        $profile->profileurl = $profileurl;
        $profile->limit(1);

        if (!$profile->find(true)) {
            throw new NoResultException($profile);
        }
        return $profile;
    }

    protected function ensureProfile($twuser)
    {
        // check to see if there's already a profile for this user
        $profileurl = 'http://twitter.com/' . $twuser->screen_name;
        try {
            $profile = $this->getProfileByUrl($twuser->screen_name, $profileurl);
            $this->updateAvatar($twuser, $profile);
            return $profile;
        } catch (NoResultException $e) {
            common_debug(__METHOD__ . ' - Adding profile and remote profile ' .
                         "for Twitter user: $profileurl.");
        }

        $profile = new Profile();
        $profile->query("BEGIN");
        $profile->nickname   = $twuser->screen_name;
        $profile->fullname   = $twuser->name;
        $profile->homepage   = $twuser->url;
        $profile->bio        = $twuser->description;
        $profile->location   = $twuser->location;
        $profile->profileurl = $profileurl;
        $profile->created    = common_sql_now();

        try {
            $id = $profile->insert();   // insert _should_ throw exception on failure
            if (empty($id)) {
                throw new Exception('Failed insert');
            }
        } catch(Exception $e) {
            common_log(LOG_WARNING, __METHOD__ . " Couldn't insert profile: " . $e->getMessage());
            common_log_db_error($profile, 'INSERT', __FILE__);
            $profile->query("ROLLBACK");
            return false;
        }

        $profile->query("COMMIT");
        $this->updateAvatar($twuser, $profile);
        return $profile;
    }

    /*
     * Checks whether we have to update the profile's avatar
     *
     * @return true when updated, false on failure, null when no action taken
     */
    protected function updateAvatar($twuser, Profile $profile)
    {
        $path_parts = pathinfo($twuser->profile_image_url);
        $ext        = isset($path_parts['extension'])
                        ? '.'.$path_parts['extension']
                        : '';	// some lack extension
        $img_root   = basename($path_parts['basename'], '_normal'.$ext);	// cut off extension
        $filename   = "Twitter_{$twuser->id}_{$img_root}_{$this->avatarsizename}{$ext}";

        try {
            $avatar = Avatar::getUploaded($profile);
            if ($avatar->filename === $filename) {
                return null;
            }
            common_debug(__METHOD__ . " - Updating profile avatar (profile_id={$profile->id}) " .
			"from {$avatar->filename} to {$filename}");
            // else we continue with creating a new avatar
        } catch (NoAvatarException $e) {
            // Avatar was not found. We can catch NoAvatarException or FileNotFoundException
            // but generally we just want to continue creating a new avatar.
            common_debug(__METHOD__ . " - No avatar found for (profile_id={$profile->id})");
        }
        
        $url        = "{$path_parts['dirname']}/{$img_root}_{$this->avatarsizename}{$ext}";
        $mediatype  = $this->getMediatype(mb_substr($ext, 1));

        try {
            $this->newAvatar($profile, $url, $filename, $mediatype);
        } catch (Exception $e) {
            if (file_exists(Avatar::path($filename))) {
                unlink(Avatar::path($filename));
            }
            return false;
        }

        return true;
    }

    protected function getMediatype($ext)
    {
        $mediatype = null;

        switch (strtolower($ext)) {
        case 'jpeg':
        case 'jpg':
            $mediatype = 'image/jpeg';
            break;
        case 'gif':
            $mediatype = 'image/gif';
            break;
        default:
            $mediatype = 'image/png';
        }

        return $mediatype;
    }

    protected function newAvatar(Profile $profile, $url, $filename, $mediatype)
    {
        // Clear out old avatars, won't do anything if there are none
        Avatar::deleteFromProfile($profile);

        // throws exception if unable to fetch
        $this->fetchRemoteUrl($url, Avatar::path($filename));

        $avatar = new Avatar();
        $avatar->profile_id = $profile->id;
        $avatar->original   = 1; // this is an original/"uploaded" avatar
        $avatar->mediatype  = $mediatype;
        $avatar->filename   = $filename;
        $avatar->url        = Avatar::url($filename);
        $avatar->width      = $this->avatarsize;
        $avatar->height     = $this->avatarsize;

        $avatar->created = common_sql_now();

        $id = $avatar->insert();

        if (empty($id)) {
            common_log(LOG_WARNING, __METHOD__ . " Couldn't insert avatar - " . $e->getMessage());
            common_log_db_error($avatar, 'INSERT', __FILE__);
            throw new ServerException('Could not insert avatar');
        }

        common_debug(__METHOD__ . " - Saved new avatar for {$profile->id}.");

        return $avatar;
    }

    /**
     * Fetch a remote avatar image and save to local storage.
     *
     * @param string $url avatar source URL
     * @param string $filename bare local filename for download
     * @return bool true on success, false on failure
     */
    protected function fetchRemoteUrl($url, $filename)
    {
        common_debug(__METHOD__ . " - Fetching Twitter avatar: {$url} to {$filename}");
        $request = HTTPClient::start();
        $request->setConfig('connect_timeout', 3);  // I had problems with throttling
        $request->setConfig('timeout', 6);          // and locking the process sucks.
        $response = $request->get($url);
        if ($response->isOk()) {
            if (!file_put_contents($filename, $response->getBody())) {
                throw new ServerException('Failed saving fetched file');
            }
        } else {
            throw new Exception('Unexpected HTTP status code');
        }
        return true;
    }

    const URL = 1;
    const HASHTAG = 2;
    const MENTION = 3;

    function linkify($status, $html = FALSE)
    {
        $text = $status->text;

        if (empty($status->entities)) {
            $statusId = twitter_id($status);
            common_log(LOG_WARNING, "No entities data for {$statusId}; trying to fake up links ourselves.");
            $text = common_replace_urls_callback($text, 'common_linkify');
            $text = preg_replace_callback('/(^|\&quot\;|\'|\(|\[|\{|\s+)#([\pL\pN_\-\.]{1,64})/',
                        function ($m) { return $m[1].'#'.TwitterStatusFetcher::tagLink($m[2]); }, $text);
            $text = preg_replace_callback('/(^|\s+)@([a-z0-9A-Z_]{1,64})/',
                        function ($m) { return $m[1].'@'.TwitterStatusFetcher::atLink($m[2]); }, $text);
            return $text;
        }

        // Move all the entities into order so we can
        // replace them and escape surrounding plaintext
        // in order

        $toReplace = array();

        if (!empty($status->entities->urls)) {
            foreach ($status->entities->urls as $url) {
                $toReplace[$url->indices[0]] = array(self::URL, $url);
            }
        }

        if (!empty($status->entities->hashtags)) {
            foreach ($status->entities->hashtags as $hashtag) {
                $toReplace[$hashtag->indices[0]] = array(self::HASHTAG, $hashtag);
            }
        }

        if (!empty($status->entities->user_mentions)) {
            foreach ($status->entities->user_mentions as $mention) {
                $toReplace[$mention->indices[0]] = array(self::MENTION, $mention);
            }
        }

        // sort in forward order by key

        ksort($toReplace);

        $result = '';
        $cursor = 0;

        foreach ($toReplace as $part) {
            list($type, $object) = $part;
            $start = $object->indices[0];
            $end = $object->indices[1];
            if ($cursor < $start) {
                // Copy in the preceding plaintext
                $result .= $this->twitEscape(mb_substr($text, $cursor, $start - $cursor));
                $cursor = $start;
            }
            $orig = $this->twitEscape(mb_substr($text, $start, $end - $start));
            switch($type) {
            case self::URL:
                $linkText = $this->makeUrlLink($object, $orig, $html);
                break;
            case self::HASHTAG:
                if ($html) {
                    $linkText = $this->makeHashtagLink($object, $orig);
                }else{
                    $linkText = $orig;
                }
                break;
            case self::MENTION:
                if ($html) {
                    $linkText = $this->makeMentionLink($object, $orig);
                }else{
                    $linkText = $orig;
                }
                break;
            default:
                $linkText = $orig;
                continue;
            }
            $result .= $linkText;
            $cursor = $end;
        }
        $last = $this->twitEscape(mb_substr($text, $cursor));
        $result .= $last;

        return $result;
    }

    function twitEscape($str)
    {
        // Twitter seems to preemptive turn < and > into &lt; and &gt;
        // but doesn't for &, so while you may have some magic protection
        // against XSS by not bothing to escape manually, you still get
        // invalid XHTML. Thanks!
        //
        // Looks like their web interface pretty much sends anything
        // through intact, so.... to do equivalent, decode all entities
        // and then re-encode the special ones.
        return htmlspecialchars(html_entity_decode($str, ENT_COMPAT, 'UTF-8'));
    }

    function makeUrlLink($object, $orig, $html)
    {
        if ($html) {
            return '<a href="'.htmlspecialchars($object->expanded_url).'" class="extlink">'.htmlspecialchars($object->display_url).'</a>';
        }else{
            return htmlspecialchars($object->expanded_url);
        }
    }

    function makeHashtagLink($object, $orig)
    {
        return "#" . self::tagLink($object->text, substr($orig, 1));
    }

    function makeMentionLink($object, $orig)
    {
        return "@".self::atLink($object->screen_name, $object->name, substr($orig, 1));
    }

    static function tagLink($tag, $orig)
    {
        return "<a href='https://search.twitter.com/search?q=%23{$tag}' class='hashtag'>{$orig}</a>";
    }

    static function atLink($screenName, $fullName, $orig)
    {
        if (!empty($fullName)) {
            return "<a href='http://twitter.com/#!/{$screenName}' title='{$fullName}'>{$orig}</a>";
        } else {
            return "<a href='http://twitter.com/#!/{$screenName}'>{$orig}</a>";
        }
    }

    function saveStatusMentions($notice, $status)
    {
        $mentions = array();

        if (empty($status->entities) || empty($status->entities->user_mentions)) {
            return;
        }

        foreach ($status->entities->user_mentions as $mention) {
            $flink = Foreign_link::getByForeignID($mention->id, TWITTER_SERVICE);
            if (!empty($flink)) {
                $user = User::getKV('id', $flink->user_id);
                if (!empty($user)) {
                    $reply = new Reply();
                    $reply->notice_id  = $notice->id;
                    $reply->profile_id = $user->id;
                    $reply->modified   = $notice->created;
                    common_log(LOG_INFO, __METHOD__ . ": saving reply: notice {$notice->id} to profile {$user->id}");
                    $id = $reply->insert();
                }
            }
        }
    }

    /**
     * Record URL links from the notice. Needed to get thumbnail records
     * for referenced photo and video posts, etc.
     *
     * @param Notice $notice
     * @param object $status
     */
    function saveStatusAttachments($notice, $status)
    {
        if (common_config('attachments', 'process_links')) {
            if (!empty($status->entities) && !empty($status->entities->urls)) {
                foreach ($status->entities->urls as $url) {
                    File::processNew($url->url, $notice->id);
                }
            }
        }
    }
}
