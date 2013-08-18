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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$shortoptions = 'n:';
$longoptions = array('nick=','import','all','apiroot=');

$helptext = <<<ENDOFHELP
USAGE: streamtest.php -n <username>

  -n --nick=<username> Local user whose Twitter timeline to watch
     --import          Experimental: run incoming messages through import
     --all             Experimental: run multiuser; requires nick be the app owner
     --apiroot=<url>   Provide alternate streaming API root URL

Attempts a User Stream connection to Twitter as the given user, dumping
data as it comes.

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';
require_once dirname(dirname(__FILE__)) . '/jsonstreamreader.php';
require_once dirname(dirname(__FILE__)) . '/twitterstreamreader.php';

if (have_option('n')) {
    $nickname = get_option_value('n');
} else if (have_option('nick')) {
    $nickname = get_option_value('nickname');
} else {
    show_help($helptext);
    exit(0);
}

/**
 *
 * @param User $user 
 * @return TwitterOAuthClient
 */
function twitterAuthForUser(User $user)
{
    $flink = Foreign_link::getByUserID($user->id,
                                       TWITTER_SERVICE);
    if (!$flink) {
        throw new ServerException("No Twitter config for this user.");
    }

    $token = TwitterOAuthClient::unpackToken($flink->credentials);
    if (!$token) {
        throw new ServerException("No Twitter OAuth credentials for this user.");
    }

    return new TwitterOAuthClient($token->key, $token->secret);
}

function homeStreamForUser(User $user)
{
    $auth = twitterAuthForUser($user);
    return new TwitterUserStream($auth);
}

function siteStreamForOwner(User $user)
{
    // The user we auth as must be the owner of the application.
    $auth = twitterAuthForUser($user);

    if (have_option('apiroot')) {
        $stream = new TwitterSiteStream($auth, get_option_value('apiroot'));
    } else {
        $stream = new TwitterSiteStream($auth);
    }

    // Pull Twitter user IDs for all users we want to pull data for
    $userIds = array();

    $flink = new Foreign_link();
    $flink->service = TWITTER_SERVICE;
    $flink->find();

    while ($flink->fetch()) {
        if (($flink->noticesync & FOREIGN_NOTICE_RECV) ==
            FOREIGN_NOTICE_RECV) {
            $userIds[] = $flink->foreign_id;
        }
    }

    $stream->followUsers($userIds);
    return $stream;
}


$user = User::getKV('nickname', $nickname);
global $myuser;
$myuser = $user;

if (have_option('all')) {
    $stream = siteStreamForOwner($user);
} else {
    $stream = homeStreamForUser($user);
}


$stream->hookEvent('raw', function($data, $context) {
    common_log(LOG_INFO, json_encode($data) . ' for ' . json_encode($context));
});
$stream->hookEvent('friends', function($data, $context) {
    printf("Friend list: %s\n", implode(', ', $data->friends));
});
$stream->hookEvent('favorite', function($data, $context) {
    printf("%s favorited %s's notice: %s\n",
            $data->source->screen_name,
            $data->target->screen_name,
            $data->target_object->text);
});
$stream->hookEvent('unfavorite', function($data, $context) {
    printf("%s unfavorited %s's notice: %s\n",
            $data->source->screen_name,
            $data->target->screen_name,
            $data->target_object->text);
});
$stream->hookEvent('follow', function($data, $context) {
    printf("%s friended %s\n",
            $data->source->screen_name,
            $data->target->screen_name);
});
$stream->hookEvent('unfollow', function($data, $context) {
    printf("%s unfriended %s\n",
            $data->source->screen_name,
            $data->target->screen_name);
});
$stream->hookEvent('delete', function($data, $context) {
    printf("Deleted status notification: %s\n",
            $data->status->id);
});
$stream->hookEvent('scrub_geo', function($data, $context) {
    printf("Req to scrub geo data for user id %s up to status ID %s\n",
            $data->user_id,
            $data->up_to_status_id);
});
$stream->hookEvent('status', function($data, $context) {
    printf("Received status update from %s: %s\n",
            $data->user->screen_name,
            $data->text);

    if (have_option('import')) {
        $importer = new TwitterImport();
        printf("\timporting...");
        $notice = $importer->importStatus($data);
        if ($notice) {
            global $myuser;
            Inbox::insertNotice($myuser->id, $notice->id);
            printf(" %s\n", $notice->id);
        } else {
            printf(" FAIL\n");
        }
    }
});
$stream->hookEvent('direct_message', function($data) {
    printf("Direct message from %s to %s: %s\n",
            $data->sender->screen_name,
            $data->recipient->screen_name,
            $data->text);
});

class TwitterManager extends IoManager
{
    function __construct(TwitterStreamReader $stream)
    {
        $this->stream = $stream;
    }

    function getSockets()
    {
        return $this->stream->getSockets();
    }

    function handleInput($data)
    {
        $this->stream->handleInput($data);
        return true;
    }

    function start()
    {
        $this->stream->connect();
        return true;
    }

    function finish()
    {
        $this->stream->close();
        return true;
    }

    public static function get()
    {
        throw new Exception('not a singleton');
    }
}

class TwitterStreamMaster extends IoMaster
{
    function __construct($id, $ioManager)
    {
        parent::__construct($id);
        $this->ioManager = $ioManager;
    }

    /**
     * Initialize IoManagers which are appropriate to this instance.
     */
    function initManagers()
    {
        $this->instantiate($this->ioManager);
    }
}

$master = new TwitterStreamMaster('TwitterStream', new TwitterManager($stream));
$master->init();
$master->service();
