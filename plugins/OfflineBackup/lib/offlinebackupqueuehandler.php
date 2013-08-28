<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Offline backup queue handler
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
 * @category  Offline backup
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Offline backup queue handler
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class OfflineBackupQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'backoff';
    }

    function handle($object)
    {
        $userId = $object;

        $user = User::getKV($userId);

        common_log(LOG_INFO, "Making backup file for user ".$user->nickname);

        $fileName = $this->makeBackupFile($user);

        common_log(LOG_INFO, "Notifying user ".$user->nickname . " of their new backup file.");

        $this->notifyBackupFile($user, $fileName);

        return true;
    }

    function makeBackupFile($user)
    {
        // XXX: this is pretty lose-y;  try another way

        $tmpdir = sys_get_temp_dir() . '/offline-backup/' . $user->nickname . '/' . common_date_iso8601(common_sql_now());

        common_log(LOG_INFO, 'Writing backup data to ' . $tmpdir . ' for ' . $user->nickname);

        mkdir($tmpdir, 0700, true);

        $this->dumpNotices($user, $tmpdir);
        $this->dumpFaves($user, $tmpdir);
        $this->dumpSubscriptions($user, $tmpdir);
        $this->dumpSubscribers($user, $tmpdir);
        $this->dumpGroups($user, $tmpdir);

        $fileName = File::filename($user->getProfile(), "backup", "application/atom+xml");
        $fullPath = File::path($fileName);

        $this->makeActivityFeed($user, $tmpdir, $fullPath);

        $this->delTree($tmpdir);

        return $fileName;
    }

    function notifyBackupFile($user, $fileName)
    {
        $fileUrl = File::url($fileName);

        $body = sprintf(_m("The backup file you requested is ready for download.\n\n".
                           "%s\n".
                           "Thanks for your time,\n",
                           "%s\n"),
                        $fileUrl,
                        common_config('site', 'name'));

        $headers = _mail_prepare_headers('offlinebackup', $user->nickname, $user->nickname);

        mail_to_user($user, _('Backup file ready for download'), $body, $headers);
    }

    function dumpNotices($user, $dir)
    {
        common_log(LOG_INFO, 'dumping notices by ' . $user->nickname . ' to directory ' . $dir);

        $profile = $user->getProfile();

        $stream = new ProfileNoticeStream($profile, $profile);

        $page = 1;

        do {

            $notice = $stream->getNotices(($page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

            while ($notice->fetch()) {
                try {
                    $fname = $dir . '/'. common_date_iso8601($notice->created) . '-notice-' . $notice->id . '.atom'; 
                    $data  = $notice->asAtomEntry(false, false, false, null);
                    common_log(LOG_INFO, 'dumping notice ' . $notice->id . ' to file ' . $fname);
                    file_put_contents($fname, $data);
                    $data  = null;
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error backing up notice " . $notice->id . ": " . $e->getMessage());
                    continue;
                }
            }

            $page++;

        } while ($notice->N > NOTICES_PER_PAGE);
    }

    function dumpFaves($user, $dir)
    {
        common_log(LOG_INFO, 'dumping faves by ' . $user->nickname . ' to directory ' . $dir);
        
        $page = 1;

        do {
            $fave = Fave::byProfile($user->id, ($page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

            while ($fave->fetch()) {
                try {
                    $fname = $dir . '/'. common_date_iso8601($fave->modified) . '-fave-' . $fave->notice_id . '.atom'; 
                    $act   = $fave->asActivity();
                    $data  = $act->asString(false, false, false);
                    common_log(LOG_INFO, 'dumping fave of ' . $fave->notice_id . ' to file ' . $fname);
                    file_put_contents($fname, $data);
                    $data  = null;
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error backing up fave of " . $fave->notice_id . ": " . $e->getMessage());
                    continue;
                }
            }
            
            $page++;

        } while ($fave->N > NOTICES_PER_PAGE);
    }

    function dumpSubscriptions($user, $dir)
    {
        common_log(LOG_INFO, 'dumping subscriptions by ' . $user->nickname . ' to directory ' . $dir);
        
        $page = 1;

        do {
            $sub = Subscription::bySubscriber($user->id, ($page-1)*PROFILES_PER_PAGE, PROFILES_PER_PAGE + 1);

            while ($sub->fetch()) {
                try {
                    if ($sub->subscribed == $user->id) {
                        continue;
                    }
                    $fname = $dir . '/'. common_date_iso8601($sub->created) . '-subscription-' . $sub->subscribed . '.atom'; 
                    $act   = $sub->asActivity();
                    $data  = $act->asString(false, false, false);
                    common_log(LOG_INFO, 'dumping sub of ' . $sub->subscribed . ' to file ' . $fname);
                    file_put_contents($fname, $data);
                    $data  = null;
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error backing up subscription to " . $sub->subscribed . ": " . $e->getMessage());
                    continue;
                }
            }

            $page++;

        } while ($sub->N > PROFILES_PER_PAGE);
    }

    function dumpSubscribers($user, $dir)
    {
        common_log(LOG_INFO, 'dumping subscribers to ' . $user->nickname . ' to directory ' . $dir);
        
        $page = 1;

        do {
            $sub = Subscription::bySubscribed($user->id, ($page-1)*PROFILES_PER_PAGE, PROFILES_PER_PAGE + 1);

            while ($sub->fetch()) {
                try {
                    if ($sub->subscriber == $user->id) {
                        continue;
                    }
                    $fname = $dir . '/'. common_date_iso8601($sub->created) . '-subscriber-' . $sub->subscriber . '.atom'; 
                    $act   = $sub->asActivity();
                    $data  = $act->asString(false, true, false);
                    common_log(LOG_INFO, 'dumping sub by ' . $sub->subscriber . ' to file ' . $fname);
                    file_put_contents($fname, $data);
                    $data  = null;
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error backing up subscription from " . $sub->subscriber . ": " . $e->getMessage());
                    continue;
                }
            }

            $page++;

        } while ($sub->N > PROFILES_PER_PAGE);
    }

    function dumpGroups($user, $dir)
    {
        common_log(LOG_INFO, 'dumping memberships of ' . $user->nickname . ' to directory ' . $dir);
        
        $page = 1;

        do {

            $mem = Group_member::byMember($user->id, ($page-1)*GROUPS_PER_PAGE, GROUPS_PER_PAGE + 1);

            while ($mem->fetch()) {
                try {
                    $fname = $dir . '/'. common_date_iso8601($mem->created) . '-membership-' . $mem->group_id . '.atom'; 
                    $act   = $mem->asActivity();
                    $data  = $act->asString(false, false, false);
                    common_log(LOG_INFO, 'dumping membership in ' . $mem->group_id . ' to file ' . $fname);
                    file_put_contents($fname, $data);
                    $data  = null;
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error backing up membership in " . $mem->group_id . ": " . $e->getMessage());
                    continue;
                }
            }

            $page++;

        } while ($mem->N > GROUPS_PER_PAGE);
    }

    function makeActivityFeed($user, $tmpdir, $fullPath)
    {
        $handle = fopen($fullPath, 'c');

        $this->writeFeedHeader($user, $handle);

        $objects = scandir($tmpdir);

        rsort($objects);

        foreach ($objects as $object) {
            $objFull = $tmpdir . '/' . $object;
            if (!is_dir($objFull)) {
                $entry = file_get_contents($objFull);
                fwrite($handle, $entry);
                $entry = null;
            }
        }

        $this->writeFeedFooter($user, $handle);
        fclose($handle);
    }

    function writeFeedHeader($user, $handle)
    {
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>');
        fwrite($handle, "\n");
        fwrite($handle, '<feed xml:lang="en-US" xmlns="http://www.w3.org/2005/Atom" xmlns:thr="http://purl.org/syndication/thread/1.0" xmlns:georss="http://www.georss.org/georss" xmlns:activity="http://activitystrea.ms/spec/1.0/" xmlns:media="http://purl.org/syndication/atommedia" xmlns:poco="http://portablecontacts.net/spec/1.0" xmlns:ostatus="http://ostatus.org/schema/1.0" xmlns:statusnet="http://status.net/schema/api/1/">');
        fwrite($handle, "\n");

        $profile = $user->getProfile();

        $author = ActivityObject::fromProfile($profile);

        $xs = new XMLStringer();
        $author->outputTo($xs, 'author');
        fwrite($handle, $xs->getString());
        fwrite($handle, "\n");
    }

    function writeFeedFooter($user, $handle)
    {
        fwrite($handle, '</feed>');
    }

    function delTree($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") {
                        $this->delTree($dir."/".$object);
                    } else {
                        unlink($dir."/".$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
