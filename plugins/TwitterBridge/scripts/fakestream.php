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
$longoptions = array('nick=','import','all');

$helptext = <<<ENDOFHELP
USAGE: fakestream.php -n <username>

  -n --nick=<username> Local user whose Twitter timeline to watch
     --import          Experimental: run incoming messages through import
     --all             Experimental: run multiuser; requires nick be the app owner

Attempts a User Stream connection to Twitter as the given user, dumping
data as it comes.

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (have_option('n')) {
    $nickname = get_option_value('n');
} else if (have_option('nick')) {
    $nickname = get_option_value('nickname');
} else if (have_option('all')) {
    $nickname = null;
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
    $flink = Foreign_link::getByUserID($user->id, TWITTER_SERVICE);
    $token = TwitterOAuthClient::unpackToken($flink->credentials);
    if (!$token) {
        throw new ServerException("No Twitter OAuth credentials for this user.");
    }

    return new TwitterOAuthClient($token->key, $token->secret);
}

/**
 * Emulate the line-by-line output...
 *
 * @param Foreign_link $flink
 * @param mixed $data
 */
function dumpMessage($flink, $data)
{
    $msg = prepMessage($flink, $data);
    print json_encode($msg) . "\r\n";
}

function prepMessage($flink, $data)
{
    $msg->for_user = $flink->foreign_id;
    $msg->message = $data;
    return $msg;
}

if (have_option('all')) {
    $users = array();

    $flink = new Foreign_link();
    $flink->service = TWITTER_SERVICE;
    $flink->find();

    while ($flink->fetch()) {
        if (($flink->noticesync & FOREIGN_NOTICE_RECV) ==
            FOREIGN_NOTICE_RECV) {
            $users[] = $flink->user_id;
        }
    }
} else {
    $user = User::getKV('nickname', $nickname);
    $users = array($user->id);
}

$output = array();
foreach ($users as $id) {
    $user = User::getKV('id', $id);
    if (!$user) {
        throw new Exception("No user for id $id");
    }
    $auth = twitterAuthForUser($user);
    $flink = Foreign_link::getByUserID($user->id,
                                       TWITTER_SERVICE);

    $friends->friends = $auth->friendsIds();
    dumpMessage($flink, $friends);

    $timeline = $auth->statusesHomeTimeline();
    foreach ($timeline as $status) {
        $output[] = prepMessage($flink, $status);
    }
}

usort($output, function($a, $b) {
    if ($a->message->id < $b->message->id) {
        return -1;
    } else if ($a->message->id == $b->message->id) {
        return 0;
    } else {
        return 1;
    }
});

foreach ($output as $msg) {
    print json_encode($msg) . "\r\n";
}
