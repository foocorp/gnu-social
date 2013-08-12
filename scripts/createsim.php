#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'b:g:j:n:t:u:w:x:z:';
$longoptions = array(
    'subscriptions=',
    'groups=',
    'joins=',
    'notices=',
    'tags=',
    'users=',
    'words=',
    'prefix=',
    'groupprefix=',
    'faves='
);

$helptext = <<<END_OF_CREATESIM_HELP
Creates a lot of test users and notices to (loosely) simulate a real server.

    -b --subscriptions Average subscriptions per user (default no. users/20)
    -g --groups        Number of groups (default 20)
    -j --joins         Number of groups per user (default 5)
    -f --faves         Number of faves per user (default notices/10)
    -n --notices       Average notices per user (default 100)
    -t --tags          Number of distinct hash tags (default 10000)
    -u --users         Number of users (default 100)
    -w --words         Words file (default '/usr/share/dict/words')
    -x --prefix        User name prefix (default 'testuser')
    -z --groupprefix   Group name prefix (default 'testgroup')

END_OF_CREATESIM_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

// XXX: make these command-line options

function newUser($i)
{
    global $userprefix;
    $user = User::register(array('nickname' => sprintf('%s%d', $userprefix, $i),
                                 'password' => sprintf('password%d', $i),
                                 'fullname' => sprintf('Test User %d', $i)));
    if (!empty($user)) {
        $user->free();
    }
}

function newGroup($i, $j)
{
    global $groupprefix;
    global $userprefix;

    // Pick a random user to be the admin

    $n = rand(0, max($j - 1, 0));
    $user = User::staticGet('nickname', sprintf('%s%d', $userprefix, $n));

    $group = User_group::register(array('nickname' => sprintf('%s%d', $groupprefix, $i),
                                        'local'    => true,
                                        'userid'   => $user->id,
                                        'fullname' => sprintf('Test Group %d', $i)));
}

function newNotice($i, $tagmax)
{
    global $userprefix;

    $options = array('scope' => Notice::defaultScope());

    $n = rand(0, $i - 1);
    $user = User::staticGet('nickname', sprintf('%s%d', $userprefix, $n));

    $is_reply = rand(0, 1);

    $content = testNoticeContent();

    if ($is_reply == 0) {
        $stream = new InboxNoticeStream($user, $user->getProfile());
        $notices = $stream->getNotices(0, 20);
        if ($notices->N > 0) {
            $nval = rand(0, $notices->N - 1);
            $notices->fetch(); // go to 0th
            for ($i = 0; $i < $nval; $i++) {
                $notices->fetch();
            }
            $options['reply_to'] = $notices->id;
            $dont_use_nickname = rand(0, 2);
            if ($dont_use_nickname != 0) {
                $rprofile = $notices->getProfile();
                $content = "@".$rprofile->nickname." ".$content;
            }
            $private_to_addressees = rand(0, 4);
            if ($private_to_addressees == 0) {
                $options['scope'] |= Notice::ADDRESSEE_SCOPE;
            }
        }
    } else {
        $is_directed = rand(0, 4);

        if ($is_directed == 0) {
            $subs = $user->getSubscriptions(0, 100)->fetchAll();
            if (count($subs) > 0) {
                $seen = array();
                $f = rand(0, 9);
                if ($f <= 6) {
                    $addrs = 1;
                } else if ($f <= 8) {
                    $addrs = 2;
                } else {
                    $addrs = 3;
                }
                for ($m = 0; $m < $addrs; $m++) {
                    $x = rand(0, count($subs) - 1);
                    if ($seen[$x]) {
                        continue;
                    }
                    if ($subs[$x]->id == $user->id) {
                        continue;
                    }
                    $seen[$x] = true;
                    $rprofile = $subs[$x];
                    $content = "@".$rprofile->nickname." ".$content;
                }
                $private_to_addressees = rand(0, 4);
                if ($private_to_addressees == 0) {
                    $options['scope'] |= Notice::ADDRESSEE_SCOPE;
                }
            }
        }
    }

    $has_hash = rand(0, 2);

    if ($has_hash == 0) {
        $hashcount = rand(0, 2);
        for ($j = 0; $j < $hashcount; $j++) {
            $h = rand(0, $tagmax);
            $content .= " #tag{$h}";
        }
    }

    $in_group = rand(0, 5);

    if ($in_group == 0) {
        $groups = $user->getGroups();
        if ($groups->N > 0) {
            $gval = rand(0, $groups->N - 1);
            $groups->fetch(); // go to 0th
            for ($i = 0; $i < $gval; $i++) {
                $groups->fetch();
            }
            $options['groups'] = array($groups->id);
            $content = "!".$groups->nickname." ".$content;
            $private_to_group = rand(0, 2);
            if ($private_to_group == 0) {
                $options['scope'] |= Notice::GROUP_SCOPE;
            }
        }
    }

    $private_to_site = rand(0, 4);

    if ($private_to_site == 0) {
        $options['scope'] |= Notice::SITE_SCOPE;
    }

    $notice = Notice::saveNew($user->id, $content, 'createsim', $options);
}

function newMessage($i)
{
    global $userprefix;

    $n = rand(0, $i - 1);
    $user = User::staticGet('nickname', sprintf('%s%d', $userprefix, $n));

    $content = testNoticeContent();

    $friends = $user->mutuallySubscribedUsers()->fetchAll();

    if (count($friends) == 0) {
        return;
    }

    $j = rand(0, count($friends) - 1);
    
    $other = $friends[$j];

    $message = Message::saveNew($user->id, $other->id, $content, 'createsim');
}

function newSub($i)
{
    global $userprefix;
    $f = rand(0, $i - 1);

    $fromnick = sprintf('%s%d', $userprefix, $f);

    $from = User::staticGet('nickname', $fromnick);

    if (empty($from)) {
        throw new Exception("Can't find user '$fromnick'.");
    }

    $t = rand(0, $i - 1);

    if ($t == $f) {
        $t++;
        if ($t > $i - 1) {
            $t = 0;
        }
    }

    $tunic = sprintf('%s%d', $userprefix, $t);

    $to = User::staticGet('nickname', $tunic);

    if (empty($to)) {
        throw new Exception("Can't find user '$tunic'.");
    }

    subs_subscribe_to($from, $to);

    $from->free();
    $to->free();
}

function newJoin($u, $g)
{
    global $userprefix;
    global $groupprefix;

    $userNumber = rand(0, $u - 1);

    $userNick = sprintf('%s%d', $userprefix, $userNumber);

    $user = User::staticGet('nickname', $userNick);

    if (empty($user)) {
        throw new Exception("Can't find user '$fromnick'.");
    }

    $groupNumber = rand(0, $g - 1);

    $groupNick = sprintf('%s%d', $groupprefix, $groupNumber);

    $group = User_group::staticGet('nickname', $groupNick);

    if (empty($group)) {
        throw new Exception("Can't find group '$groupNick'.");
    }

    if (!$user->isMember($group)) {
        $user->joinGroup($group);
    }
}

function newFave($u)
{
    global $userprefix;
    global $groupprefix;

    $userNumber = rand(0, $u - 1);

    $userNick = sprintf('%s%d', $userprefix, $userNumber);

    $user = User::staticGet('nickname', $userNick);

    if (empty($user)) {
        throw new Exception("Can't find user '$userNick'.");
    }

    // NB: it's OK to like your own stuff!

    $otherNumber = rand(0, $u - 1);

    $otherNick = sprintf('%s%d', $userprefix, $otherNumber);

    $other = User::staticGet('nickname', $otherNick);

    if (empty($other)) {
        throw new Exception("Can't find user '$otherNick'.");
    }

    $notices = $other->getNotices()->fetchAll();

    if (count($notices) == 0) {
        return;
    }

    $idx = rand(0, count($notices) - 1);

    $notice = $notices[$idx];

    if ($user->hasFave($notice)) {
        return;
    }

    Fave::addNew($user->getProfile(), $notice);
}

function testNoticeContent()
{
    global $words;

    if (is_null($words)) {
        return "test notice content";
    }

    $cnt = rand(3, 8);

    $ids = array_rand($words, $cnt);

    foreach ($ids as $id) {
        $parts[] = $words[$id];
    }

    $text = implode(' ', $parts);

    if (mb_strlen($text) > 80) {
        $text = substr($text, 0, 77) . "...";
    }

    return $text;
}

function main($usercount, $groupcount, $noticeavg, $subsavg, $joinsavg, $favesavg, $messageavg, $tagmax)
{
    global $config;
    $config['site']['dupelimit'] = -1;

    $n = 0;
    $g = 0;

    // Make users first

    $preuser = min($usercount, 5);

    for ($j = 0; $j < $preuser; $j++) {
        printfv("$i Creating user $n\n");
        newUser($n);
        $n++;
    }

    $pregroup = min($groupcount, 3);

    for ($k = 0; $k < $pregroup; $k++) {
        printfv("$i Creating group $g\n");
        newGroup($g, $n);
        $g++;
    }

    // # registrations + # notices + # subs

    $events = $usercount + $groupcount + ($usercount * ($noticeavg + $subsavg + $joinsavg + $favesavg + $messageavg));

    $events -= $preuser;
    $events -= $pregroup;

    $ut = $usercount;
    $gt = $ut + $groupcount;
    $nt = $gt + ($usercount * $noticeavg);
    $st = $nt + ($usercount * $subsavg);
    $jt = $st + ($usercount * $joinsavg);
    $ft = $jt + ($usercount * $favesavg);
    $mt = $ft + ($usercount * $messageavg);

    printfv("$events events ($ut, $gt, $nt, $st, $jt, $ft, $mt)\n");

    for ($i = 0; $i < $events; $i++)
    {
        $e = rand(0, $events);

        if ($e >= 0 && $e <= $ut) {
            printfv("$i Creating user $n\n");
            newUser($n);
            $n++;
        } else if ($e > $ut && $e <= $gt) {
            printfv("$i Creating group $g\n");
            newGroup($g, $n);
            $g++;
        } else if ($e > $gt && $e <= $nt) {
            printfv("$i Making a new notice\n");
            newNotice($n, $tagmax);
        } else if ($e > $nt && $e <= $st) {
            printfv("$i Making a new subscription\n");
            newSub($n);
        } else if ($e > $st && $e <= $jt) {
            printfv("$i Making a new group join\n");
            newJoin($n, $g);
        } else if ($e > $jt && $e <= $ft) {
            printfv("$i Making a new fave\n");
            newFave($n);
        } else if ($e > $ft && $e <= $mt) {
            printfv("$i Making a new message\n");
            newMessage($n);
        } else {
            printfv("No event for $i!");
        }
    }
}

$defaultWordsfile = '/usr/share/dict/words';

$usercount   = (have_option('u', 'users')) ? get_option_value('u', 'users') : 100;
$groupcount  = (have_option('g', 'groups')) ? get_option_value('g', 'groups') : 20;
$noticeavg   = (have_option('n', 'notices')) ? get_option_value('n', 'notices') : 100;
$subsavg     = (have_option('b', 'subscriptions')) ? get_option_value('b', 'subscriptions') : max($usercount/20, 10);
$joinsavg    = (have_option('j', 'joins')) ? get_option_value('j', 'joins') : 5;
$favesavg    = (have_option('f', 'faves')) ? get_option_value('f', 'faves') : max($noticeavg/10, 5);
$messageavg  = (have_option('m', 'messages')) ? get_option_value('m', 'messages') : max($noticeavg/10, 5);
$tagmax      = (have_option('t', 'tags')) ? get_option_value('t', 'tags') : 10000;
$userprefix  = (have_option('x', 'prefix')) ? get_option_value('x', 'prefix') : 'testuser';
$groupprefix = (have_option('z', 'groupprefix')) ? get_option_value('z', 'groupprefix') : 'testgroup';
$wordsfile   = (have_option('w', 'words')) ? get_option_value('w', 'words') : $defaultWordsfile;

if (is_readable($wordsfile)) {
    $words = file($wordsfile);
} else {
   if ($wordsfile != $defaultWordsfile) {
      // user specified words file couldn't be read
      throw new Exception("Couldn't read words file: {$wordsfile}.");
    }
    $words = null;
}

try {
    main($usercount, $groupcount, $noticeavg, $subsavg, $joinsavg, $favesavg, $messageavg, $tagmax);
} catch (Exception $e) {
    printfv("Got an exception: ".$e->getMessage());
}
