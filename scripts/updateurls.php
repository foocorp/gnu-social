#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
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

$shortoptions = '';
$longoptions = array();

$helptext = <<<END_OF_UPDATEURLS_HELP
updateurls.php [options]
update stored URLs in the system

END_OF_UPDATEURLS_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

function main()
{
    updateUserUrls();
    updateGroupUrls();
}

function updateUserUrls()
{
    printfnq("Updating user URLs...\n");

    // XXX: only update user URLs where out-of-date

    $user = new User();
    if ($user->find()) {
        while ($user->fetch()) {
            printfv("Updating user {$user->nickname}...");
            try {
                $profile = $user->getProfile();

                updateProfileUrl($profile);
                updateAvatarUrls($profile);

                // Broadcast for remote users

                common_broadcast_profile($profile);

            } catch (Exception $e) {
                printv("Error updating URLs: " . $e->getMessage());
            }
            printfv("DONE.");
        }
    }
}

function updateProfileUrl($profile)
{
    $orig = clone($profile);
    $profile->profileurl = common_profile_url($profile->nickname);
    $profile->update($orig);
}

function updateAvatarUrls($profile)
{
    $avatar = new Avatar();

    $avatar->profile_id = $profile->id;
    if ($avatar->find()) {
        while ($avatar->fetch()) {
            $orig_url = $avatar->url;
            $avatar->url = Avatar::url($avatar->filename);
            if ($avatar->url != $orig_url) {
                $sql =
                  "UPDATE avatar SET url = '" . $avatar->url . "' ".
                  "WHERE profile_id = " . $avatar->profile_id . " ".
                  "AND width = " . $avatar->width . " " .
                  "AND height = " . $avatar->height . " ";

                if ($avatar->original) {
                    $sql .= "AND original = 1 ";
                }

                if (!$avatar->query($sql)) {
                    throw new Exception("Can't update avatar for user " . $profile->nickname . ".");
                } else {
                    $touched = true;
                }
            }
        }
    }
}

function updateGroupUrls()
{
    printfnq("Updating group URLs...\n");

    $group = new User_group();

    if ($group->find()) {
        while ($group->fetch()) {
            try {
                printfv("Updating group {$group->nickname}...");
                $orig = User_group::getKV('id', $group->id);
                if (!empty($group->original_logo)) {
                    $group->original_logo = Avatar::url(basename($group->original_logo));
                    $group->homepage_logo = Avatar::url(basename($group->homepage_logo));
                    $group->stream_logo = Avatar::url(basename($group->stream_logo));
                    $group->mini_logo = Avatar::url(basename($group->mini_logo));
                }
                // XXX: this is a hack to see if a group is local or not
                $localUri = common_local_url('groupbyid',
                                             array('id' => $group->id));
                if ($group->getUri() != $localUri) {
                    $group->mainpage = common_local_url('showgroup',
                                                        array('nickname' => $group->nickname));
                }
                $group->update($orig);
                printfv("DONE.");
            } catch (Exception $e) {
                printv("Can't update avatars for group " . $group->nickname . ": ". $e->getMessage());
            }
        }
    }
}

main();
