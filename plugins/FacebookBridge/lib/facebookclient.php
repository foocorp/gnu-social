<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for communicating with Facebook
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class for communication with Facebook
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class Facebookclient
{
    protected $facebook      = null; // Facebook Graph client obj
    protected $flink         = null; // Foreign_link StatusNet -> Facebook
    protected $notice        = null; // The user's notice
    protected $user          = null; // Sender of the notice

    /**
     *
     * @param Notice $notice the notice to manipulate
     * @param Profile $profile local user to act as; if left empty, the notice's poster will be used.
     */
    function __construct($notice, $profile=null)
    {
        $this->facebook = self::getFacebook();

        if (empty($this->facebook)) {
            throw new FacebookApiException(
                "Could not create Facebook client! Bad application ID or secret?"
            );
        }

        $this->notice = $notice;

        $profile_id = $profile ? $profile->id : $notice->profile_id;
        $this->flink = Foreign_link::getByUserID(
            $profile_id,
            FACEBOOK_SERVICE
        );

        if (!empty($this->flink)) {
            $this->user = $this->flink->getUser();
        }
    }

    /*
     * Get an instance of the Facebook Graph SDK object
     *
     * @param string $appId     Application
     * @param string $secret    Facebook API secret
     *
     * @return Facebook A Facebook SDK obj
     */
    static function getFacebook($appId = null, $secret = null)
    {
        // Check defaults and configuration for application ID and secret
        if (empty($appId)) {
            $appId = common_config('facebook', 'appid');
        }

        if (empty($secret)) {
            $secret = common_config('facebook', 'secret');
        }

        // If there's no app ID and secret set in the local config, look
        // for a global one
        if (empty($appId) || empty($secret)) {
            $appId  = common_config('facebook', 'global_appid');
            $secret = common_config('facebook', 'global_secret');
        }

        if (empty($appId)) {
            common_log(
                LOG_WARNING,
                "Couldn't find Facebook application ID!",
                __FILE__
            );
        }

        if (empty($secret)) {
            common_log(
                LOG_WARNING,
                "Couldn't find Facebook application ID!",
                __FILE__
            );
        }

        return new Facebook(
            array(
               'appId'  => $appId,
               'secret' => $secret,
               'cookie' => true
            )
        );
    }

    /*
     * Broadcast a notice to Facebook
     *
     * @param Notice $notice    the notice to send
     */
    static function facebookBroadcastNotice($notice)
    {
        $client = new Facebookclient($notice);
        return $client->sendNotice();
    }

    /*
     * Should the notice go to Facebook?
     */
    function isFacebookBound() {

        if (empty($this->flink)) {
            // User hasn't setup bridging
            return false;
        }

        // Avoid a loop
        if ($this->notice->source == 'Facebook') {
            common_log(
                LOG_INFO,
                sprintf(
                    'Skipping notice %d because its source is Facebook.',
                    $this->notice->id
                ),
                __FILE__
            );
            return false;
        }

        // If the user does not want to broadcast to Facebook, move along
        if (!($this->flink->noticesync & FOREIGN_NOTICE_SEND == FOREIGN_NOTICE_SEND)) {
            common_log(
                LOG_INFO,
                sprintf(
                    'Skipping notice %d because user has FOREIGN_NOTICE_SEND bit off.',
                    $this->notice->id
                ),
                __FILE__
            );
            return false;
        }

        // If it's not a reply, or if the user WANTS to send @-replies,
        // then, yeah, it can go to Facebook.

        if (empty($this->notice->reply_to) ||
            ($this->flink->noticesync & FOREIGN_NOTICE_SEND_REPLY)) {
            return true;
        }

        return false;
    }

    /*
     * Determine whether we should send this notice using the Graph API or the
     * old REST API and then dispatch
     */
    function sendNotice()
    {
        // If there's nothing in the credentials field try to send via
        // the Old Rest API

        if ($this->isFacebookBound()) {
            common_debug("notice is facebook bound", __FILE__);
            if (empty($this->flink->credentials)) {
                return $this->sendOldRest();
            } else {

                // Otherwise we most likely have an access token
                return $this->sendGraph();
            }
        }

        // dequeue
        return true;
    }

    /*
     * Send a notice to Facebook using the Graph API
     */
    function sendGraph()
    {
        try {

            $fbuid = $this->flink->foreign_id;

            common_debug(
                sprintf(
                    "Attempting use Graph API to post notice %d as a stream item for %s (%d), fbuid %d",
                    $this->notice->id,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );

            $params = array(
                'access_token' => $this->flink->credentials,
                // XXX: Need to worrry about length of the message?
                'message'      => $this->notice->content
            );

            $attachments = $this->notice->attachments();

            if (!empty($attachments)) {

                // We can only send one attachment with the Graph API :(

                $first = array_shift($attachments);

                if (substr($first->mimetype, 0, 6) == 'image/'
                    || in_array(
                        $first->mimetype,
                        array('application/x-shockwave-flash', 'audio/mpeg' ))) {

                   $params['picture'] = $first->url;
                   $params['caption'] = 'Click for full size';
                   $params['source']  = $first->url;
                }

            }

            $result = $this->facebook->api(
                sprintf('/%s/feed', $fbuid), 'post', $params
            );

            // Save a mapping
            Notice_to_item::saveNew($this->notice->id, $result['id']);

            common_log(
                LOG_INFO,
                sprintf(
                    "Posted notice %d as a stream item for %s (%d), fbuid %d",
                    $this->notice->id,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );

        } catch (FacebookApiException $e) {
            return $this->handleFacebookError($e);
        }

        return true;
    }

    /*
     * Send a notice to Facebook using the deprecated Old REST API. We need this
     * for backwards compatibility. Users who signed up for Facebook bridging
     * using the old Facebook Canvas application do not have an OAuth 2.0
     * access token.
     */
    function sendOldRest()
    {
        try {

            $canPublish = $this->checkPermission('publish_stream');
            $canUpdate  = $this->checkPermission('status_update');

            // We prefer to use stream.publish, because it can handle
            // attachments and returns the ID of the published item

            if ($canPublish == 1) {
                $this->restPublishStream();
            } else if ($canUpdate == 1) {
                // as a last resort we can just update the user's "status"
                $this->restStatusUpdate();
            } else {

                $msg = 'Not sending notice %d to Facebook because user %s '
                     . '(%d), fbuid %d,  does not have \'status_update\' '
                     . 'or \'publish_stream\' permission.';

                common_log(
                    LOG_WARNING,
                    sprintf(
                        $msg,
                        $this->notice->id,
                        $this->user->nickname,
                        $this->user->id,
                        $this->flink->foreign_id
                    ),
                    __FILE__
                );
            }

        } catch (FacebookApiException $e) {
            return $this->handleFacebookError($e);
        }

        return true;
    }

    /*
     * Query Facebook to to see if a user has permission
     *
     *
     *
     * @param $permission the permission to check for - must be either
     *                    public_stream or status_update
     *
     * @return boolean result
     */
    function checkPermission($permission)
    {
        if (!in_array($permission, array('publish_stream', 'status_update'))) {
             // TRANS: Server exception thrown when permission check fails.
             throw new ServerException(_('No such permission!'));
        }

        $fbuid = $this->flink->foreign_id;

        common_debug(
            sprintf(
                'Checking for %s permission for user %s (%d), fbuid %d',
                $permission,
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );

        $hasPermission = $this->facebook->api(
            array(
                'method'   => 'users.hasAppPermission',
                'ext_perm' => $permission,
                'uid'      => $fbuid
            )
        );

        if ($hasPermission == 1) {

            common_debug(
                sprintf(
                    '%s (%d), fbuid %d has %s permission',
                    $permission,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );

            return true;

        } else {

            $logMsg = '%s (%d), fbuid $fbuid does NOT have %s permission.'
                    . 'Facebook returned: %s';

            common_debug(
                sprintf(
                    $logMsg,
                    $this->user->nickname,
                    $this->user->id,
                    $permission,
                    $fbuid,
                    var_export($result, true)
                ),
                __FILE__
            );

            return false;
        }
    }

    /*
     * Handle a Facebook API Exception
     *
     * @param FacebookApiException $e the exception
     *
     */
    function handleFacebookError($e)
    {
        $fbuid  = $this->flink->foreign_id;
        $errmsg = $e->getMessage();
        $code   = $e->getCode();

        // The Facebook PHP SDK seems to always set the code attribute
        // of the Exception to 0; they put the real error code in
        // the message. Gar!
        if ($code == 0) {
            preg_match('/^\(#(?<code>\d+)\)/', $errmsg, $matches);
            $code = $matches['code'];
        }

        // XXX: Check for any others?
        switch($code) {
         case 100: // Invalid parameter
            $msg = 'Facebook claims notice %d was posted with an invalid '
                 . 'parameter (error code 100 - %s) Notice details: '
                 . '[nickname=%s, user id=%d, fbuid=%d, content="%s"]. '
                 . 'Dequeing.';
            common_log(
                LOG_ERR, sprintf(
                    $msg,
                    $this->notice->id,
                    $errmsg,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid,
                    $this->notice->content
                ),
                __FILE__
            );
            return true;
            break;
         case 200: // Permissions error
         case 250: // Updating status requires the extended permission status_update
            $this->disconnect();
            return true; // dequeue
            break;
         case 341: // Feed action request limit reached
                $msg = '%s (userid=%d, fbuid=%d) has exceeded his/her limit '
                     . 'for posting notices to Facebook today. Dequeuing '
                     . 'notice %d';
                common_log(
                    LOG_INFO, sprintf(
                        $msg,
                        $user->nickname,
                        $user->id,
                        $fbuid,
                        $this->notice->id
                    ),
                    __FILE__
                );
            // @todo FIXME: We want to rety at a later time when the throttling has expired
            // instead of just giving up.
            return true;
            break;
         default:
            $msg = 'Facebook returned an error we don\'t know how to deal with '
                 . 'when posting notice %d. Error code: %d, error message: "%s"'
                 . ' Notice details: [nickname=%s, user id=%d, fbuid=%d, '
                 . 'notice content="%s"]. Dequeing.';
            common_log(
                LOG_ERR, sprintf(
                    $msg,
                    $this->notice->id,
                    $code,
                    $errmsg,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid,
                    $this->notice->content
                ),
                __FILE__
            );
            return true; // dequeue
            break;
        }
    }

    /*
     * Publish a notice to Facebook as a status update
     *
     * This is the least preferable way to send a notice to Facebook because
     * it doesn't support attachments and the API method doesn't return
     * the ID of the post on Facebook.
     *
     */
    function restStatusUpdate()
    {
        $fbuid = $this->flink->foreign_id;

        common_debug(
            sprintf(
                "Attempting to post notice %d as a status update for %s (%d), fbuid %d",
                $this->notice->id,
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );

        $result = $this->facebook->api(
            array(
                'method'               => 'users.setStatus',
                'status'               => $this->formatMessage(),
                'status_includes_verb' => true,
                'uid'                  => $fbuid
            )
        );

        if ($result == 1) { // 1 is success

            common_log(
                LOG_INFO,
                sprintf(
                    "Posted notice %s as a status update for %s (%d), fbuid %d",
                    $this->notice->id,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );

            // There is no item ID returned for status update so we can't
            // save a Notice_to_item mapping

        } else {

            $msg = sprintf(
                "Error posting notice %s as a status update for %s (%d), fbuid %d - error code: %s",
                $this->notice->id,
                $this->user->nickname,
                $this->user->id,
                $fbuid,
                $result // will contain 0, or an error
            );

            throw new FacebookApiException($msg, $result);
        }
    }

    /*
     * Publish a notice to a Facebook user's stream using the old REST API
     */
    function restPublishStream()
    {
        $fbuid = $this->flink->foreign_id;

        common_debug(
            sprintf(
                'Attempting to post notice %d as stream item for %s (%d) fbuid %d',
                $this->notice->id,
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );

        $fbattachment = $this->formatAttachments();

        $result = $this->facebook->api(
            array(
                'method'     => 'stream.publish',
                'message'    => $this->formatMessage(),
                'attachment' => $fbattachment,
                'uid'        => $fbuid
            )
        );

        if (!empty($result)) { // result will contain the item ID
            // Save a mapping
            Notice_to_item::saveNew($this->notice->id, $result);

            common_log(
                LOG_INFO,
                sprintf(
                    'Posted notice %d as a %s for %s (%d), fbuid %d',
                    $this->notice->id,
                    empty($fbattachment) ? 'stream item' : 'stream item with attachment',
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );
        } else {

            $msg = sprintf(
                'Could not post notice %d as a %s for %s (%d), fbuid %d - error code: %s',
                $this->notice->id,
                empty($fbattachment) ? 'stream item' : 'stream item with attachment',
                $this->user->nickname,
                $this->user->id,
                $result, // result will contain an error code
                $fbuid
            );

            throw new FacebookApiException($msg, $result);
        }
    }

    /*
     * Format the text message of a stream item so it's appropriate for
     * sending to Facebook. If the notice is too long, truncate it, and
     * add a linkback to the original notice at the end.
     *
     * @return String $txt the formated message
     */
    function formatMessage()
    {
        // Start with the plaintext source of this notice...
        $txt = $this->notice->content;

        // Facebook has a 420-char hardcoded max.
        if (mb_strlen($statustxt) > 420) {
            $noticeUrl = common_shorten_url($this->notice->getUrl());
            $urlLen = mb_strlen($noticeUrl);
            $txt = mb_substr($statustxt, 0, 420 - ($urlLen + 3)) . ' … ' . $noticeUrl;
        }

        return $txt;
    }

    /*
     * Format attachments for the old REST API stream.publish method
     *
     * Note: Old REST API supports multiple attachments per post
     *
     */
    function formatAttachments()
    {
        $attachments = $this->notice->attachments();

        $fbattachment          = array();
        $fbattachment['media'] = array();

        foreach($attachments as $attachment)
        {
            try {
                $enclosure = $attachment->getEnclosure();
                $fbmedia = $this->getFacebookMedia($enclosure);
            } catch (ServerException $e) {
                $fbmedia = $this->getFacebookMedia($attachment);
            }
            if($fbmedia){
                $fbattachment['media'][]=$fbmedia;
            }else{
                $fbattachment['name'] = ($attachment->title ?
                                      $attachment->title : $attachment->url);
                $fbattachment['href'] = $attachment->url;
            }
        }
        if(count($fbattachment['media'])>0){
            unset($fbattachment['name']);
            unset($fbattachment['href']);
        }
        return $fbattachment;
    }

    /**
     * given a File objects, returns an associative array suitable for Facebook media
     */
    function getFacebookMedia($attachment)
    {
        $fbmedia    = array();

        if (strncmp($attachment->mimetype, 'image/', strlen('image/')) == 0) {
            $fbmedia['type']         = 'image';
            $fbmedia['src']          = $attachment->url;
            $fbmedia['href']         = $attachment->url;
        } else if ($attachment->mimetype == 'audio/mpeg') {
            $fbmedia['type']         = 'mp3';
            $fbmedia['src']          = $attachment->url;
        }else if ($attachment->mimetype == 'application/x-shockwave-flash') {
            $fbmedia['type']         = 'flash';

            // http://wiki.developers.facebook.com/index.php/Attachment_%28Streams%29
            // says that imgsrc is required... but we have no value to put in it
            // $fbmedia['imgsrc']='';

            $fbmedia['swfsrc']       = $attachment->url;
        }else{
            return false;
        }
        return $fbmedia;
    }

    /*
     * Disconnect a user from Facebook by deleting his Foreign_link.
     * Notifies the user his account has been disconnected by email.
     */
    function disconnect()
    {
        $fbuid = $this->flink->foreign_id;

        common_log(
            LOG_INFO,
            sprintf(
                'Removing Facebook link for %s (%d), fbuid %d',
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );

        $result = $this->flink->delete();

        if (empty($result)) {
            common_log(
                LOG_ERR,
                sprintf(
                    'Could not remove Facebook link for %s (%d), fbuid %d',
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );
            common_log_db_error($flink, 'DELETE', __FILE__);
        }

        // Notify the user that we are removing their Facebook link
        if (!empty($this->user->email)) {
            $result = $this->mailFacebookDisconnect();

            if (!$result) {
                $msg = 'Unable to send email to notify %s (%d), fbuid %d '
                     . 'about his/her Facebook link being removed.';

                common_log(
                    LOG_WARNING,
                    sprintf(
                        $msg,
                        $this->user->nickname,
                        $this->user->id,
                        $fbuid
                    ),
                    __FILE__
                );
            }
        } else {
            $msg = 'Unable to send email to notify %s (%d), fbuid %d '
                 . 'about his/her Facebook link being removed because the '
                 . 'user has not set an email address.';

            common_log(
                LOG_WARNING,
                sprintf(
                    $msg,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );
        }
    }

    /**
     * Send a mail message to notify a user that her Facebook link
     * has been terminated.
     *
     * @return boolean success flag
     */
    function mailFacebookDisconnect()
    {
        $profile = $this->user->getProfile();

        $siteName = common_config('site', 'name');

        common_switch_locale($this->user->language);

        // TRANS: E-mail subject.
        $subject = _m('Your Facebook connection has been removed');

        // TRANS: E-mail body. %1$s is a username, %2$s is the StatusNet sitename.
        $msg = _m("Hi %1\$s,\n\n".
                  "We are sorry to inform you we are unable to publish your notice to\n".
                  "Facebook, and have removed the connection between your %2\$s account and\n".
                  "Facebook.\n\n".
                  "This may have happened because you have removed permission for %2\$s\n".
                  "to post on your behalf, or perhaps you have deactivated your Facebook\n".
                  "account. You can reconnect your %2\$s account to Facebook at any time by\n".
                  "logging in with Facebook again.\n\n".
                  "Sincerely,\n\n".
                  "%2\$s\n");

        $body = sprintf(
            $msg,
            $this->user->nickname,
            $siteName
        );

        common_switch_locale();

        $result = mail_to_user($this->user, $subject, $body);

        if (empty($this->user->password)) {
            $result = self::emailWarn($this->user);
        }

        return $result;
    }

    /*
     * Send the user an email warning that their account has been
     * disconnected and he/she has no way to login and must contact
     * the site administrator for help.
     *
     * @param User $user the deauthorizing user
     *
     */
    static function emailWarn($user)
    {
        $profile = $user->getProfile();

        $siteName  = common_config('site', 'name');
        $siteEmail = common_config('site', 'email');

        if (empty($siteEmail)) {
            common_log(
                LOG_WARNING,
                    "No site email address configured. Please set one."
            );
        }

        common_switch_locale($user->language);

        // TRANS: E-mail subject. %s is the StatusNet sitename.
        $subject = _m('Contact the %s administrator to retrieve your account');

        // TRANS: E-mail body. %1$s is a username,
        // TRANS: %2$s is the StatusNet sitename, %3$s is the site contact e-mail address.
        $msg = _m("Hi %1\$s,\n\n".
                  "We have noticed you have deauthorized the Facebook connection for your\n".
                  "%2\$s account.  You have not set a password for your %2\$s account yet, so\n".
                  "you will not be able to login. If you wish to continue using your %2\$s\n".
                  "account, please contact the site administrator (%3\$s) to set a password.\n\n".
                  "Sincerely,\n\n".
                  "%2\$s\n");

        $body = sprintf(
            $msg,
            $user->nickname,
            $siteName,
            $siteEmail
        );

        common_switch_locale();

        if (mail_to_user($user, $subject, $body)) {
            common_log(
                LOG_INFO,
                sprintf(
                    'Sent account lockout warning to %s (%d)',
                    $user->nickname,
                    $user->id
                ),
                __FILE__
            );
        } else {
            common_log(
                LOG_WARNING,
                sprintf(
                    'Unable to send account lockout warning to %s (%d)',
                    $user->nickname,
                    $user->id
                ),
                __FILE__
            );
        }
    }

    /*
     * Check to see if we have a mapping to a copy of this notice
     * on Facebook
     *
     * @param Notice $notice the notice to check
     *
     * @return mixed null if it can't find one, or the id of the Facebook
     *               stream item
     */
    static function facebookStatusId($notice)
    {
        $n2i = Notice_to_item::getKV('notice_id', $notice->id);

        if (empty($n2i)) {
            return null;
        } else {
            return $n2i->item_id;
        }
    }

    /*
     * Save a Foreign_user record of a Facebook user
     *
     * @param object $fbuser a Facebook Graph API user obj
     *                       See: http://developers.facebook.com/docs/reference/api/user
     * @return mixed $result Id or key
     *
     */
    static function addFacebookUser($fbuser)
    {
        // remove any existing, possibly outdated, record
        $luser = Foreign_user::getForeignUser($fbuser->id, FACEBOOK_SERVICE);

        if (!empty($luser)) {

            $result = $luser->delete();

            if ($result != false) {
                common_log(
                    LOG_INFO,
                    sprintf(
                        'Removed old Facebook user: %s, fbuid %d',
                        $fbuid->name,
                        $fbuid->id
                    ),
                    __FILE__
                );
            }
        }

        $fuser = new Foreign_user();

        $fuser->nickname = $fbuser->username;
        $fuser->uri      = $fbuser->link;
        $fuser->id       = $fbuser->id;
        $fuser->service  = FACEBOOK_SERVICE;
        $fuser->created  = common_sql_now();

        $result = $fuser->insert();

        if (empty($result)) {
            common_log(
                LOG_WARNING,
                    sprintf(
                        'Failed to add new Facebook user: %s, fbuid %d',
                        $fbuser->username,
                        $fbuser->id
                    ),
                    __FILE__
            );

            common_log_db_error($fuser, 'INSERT', __FILE__);
        } else {
            common_log(
                LOG_INFO,
                sprintf(
                    'Added new Facebook user: %s, fbuid %d',
                    $fbuser->name,
                    $fbuser->id
                ),
                __FILE__
            );
        }

        return $result;
    }

    /*
     * Remove an item from a Facebook user's feed if we have a mapping
     * for it.
     */
    function streamRemove()
    {
        $n2i = Notice_to_item::getKV('notice_id', $this->notice->id);

        if (!empty($this->flink) && !empty($n2i)) {
            try {
                $result = $this->facebook->api(
                    array(
                        'method'  => 'stream.remove',
                        'post_id' => $n2i->item_id,
                        'uid'     => $this->flink->foreign_id
                    )
                );

                if (!empty($result) && result == true) {
                    common_log(
                      LOG_INFO,
                        sprintf(
                            'Deleted Facebook item: %s for %s (%d), fbuid %d',
                            $n2i->item_id,
                            $this->user->nickname,
                            $this->user->id,
                            $this->flink->foreign_id
                        ),
                        __FILE__
                    );

                    $n2i->delete();

                } else {
                    throw new FaceboookApiException(var_export($result, true));
                }
            } catch (FacebookApiException $e) {
                common_log(
                  LOG_WARNING,
                    sprintf(
                        'Could not deleted Facebook item: %s for %s (%d), '
                            . 'fbuid %d - (API error: %s) item already deleted '
                            . 'on Facebook? ',
                        $n2i->item_id,
                        $this->user->nickname,
                        $this->user->id,
                        $this->flink->foreign_id,
                        $e
                    ),
                    __FILE__
                );
            }
        }
    }

    /*
     * Like an item in a Facebook user's feed if we have a mapping
     * for it.
     */
    function like()
    {
        $n2i = Notice_to_item::getKV('notice_id', $this->notice->id);

        if (!empty($this->flink) && !empty($n2i)) {
            try {
                $result = $this->facebook->api(
                    array(
                        'method'  => 'stream.addlike',
                        'post_id' => $n2i->item_id,
                        'uid'     => $this->flink->foreign_id
                    )
                );

                if (!empty($result) && result == true) {
                    common_log(
                      LOG_INFO,
                        sprintf(
                            'Added like for item: %s for %s (%d), fbuid %d',
                            $n2i->item_id,
                            $this->user->nickname,
                            $this->user->id,
                            $this->flink->foreign_id
                        ),
                        __FILE__
                    );
                } else {
                    throw new FacebookApiException(var_export($result, true));
                }
            } catch (FacebookApiException $e) {
                common_log(
                  LOG_WARNING,
                    sprintf(
                        'Could not like Facebook item: %s for %s (%d), '
                            . 'fbuid %d (API error: %s)',
                        $n2i->item_id,
                        $this->user->nickname,
                        $this->user->id,
                        $this->flink->foreign_id,
                        $e
                    ),
                    __FILE__
                );
            }
        }
    }

    /*
     * Unlike an item in a Facebook user's feed if we have a mapping
     * for it.
     */
    function unLike()
    {
        $n2i = Notice_to_item::getKV('notice_id', $this->notice->id);

        if (!empty($this->flink) && !empty($n2i)) {
            try {
                $result = $this->facebook->api(
                    array(
                        'method'  => 'stream.removeLike',
                        'post_id' => $n2i->item_id,
                        'uid'     => $this->flink->foreign_id
                    )
                );

                if (!empty($result) && result == true) {
                    common_log(
                      LOG_INFO,
                        sprintf(
                            'Removed like for item: %s for %s (%d), fbuid %d',
                            $n2i->item_id,
                            $this->user->nickname,
                            $this->user->id,
                            $this->flink->foreign_id
                        ),
                        __FILE__
                    );

                } else {
                    throw new FacebookApiException(var_export($result, true));
                }
            } catch (FacebookApiException $e) {
                  common_log(
                  LOG_WARNING,
                    sprintf(
                        'Could not remove like for Facebook item: %s for %s '
                          . '(%d), fbuid %d (API error: %s)',
                        $n2i->item_id,
                        $this->user->nickname,
                        $this->user->id,
                        $this->flink->foreign_id,
                        $e
                    ),
                    __FILE__
                );
            }
        }
    }
}
