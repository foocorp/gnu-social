<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * Basic client class for Yammer's OAuth/JSON API.
 *
 * @package YammerImportPlugin
 * @author Brion Vibber <brion@status.net>
 */
class YammerImporter
{
    protected $client;

    function __construct(SN_YammerClient $client)
    {
        $this->client = $client;
    }

    /**
     * Load or create an imported profile from Yammer data.
     *
     * @param object $item loaded JSON data for Yammer importer
     * @return Profile
     */
    function importUser($item)
    {
        $data = $this->prepUser($item);
        $nickname = $data['options']['nickname'];

        $profileId = $this->findImportedUser($data['orig_id']);
        if ($profileId) {
            return Profile::getKV('id', $profileId);
        } else {
            $user = User::getKV('nickname', $nickname);
            if ($user) {
                common_log(LOG_WARN, "Copying Yammer profile info onto existing user $nickname");
                $profile = $user->getProfile();
                $this->savePropertiesOn($profile, $data['options'],
                        array('fullname', 'homepage', 'bio', 'location'));
            } else {
                $user = User::register($data['options']);
                $profile = $user->getProfile();
            }
            if ($data['avatar']) {
                try {
                    $this->saveAvatar($data['avatar'], $profile);
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error importing Yammer avatar: " . $e->getMessage());
                }
            }
            $this->recordImportedUser($data['orig_id'], $profile->id);
            return $profile;
        }
    }

    /**
     * Load or create an imported group from Yammer data.
     *
     * @param object $item loaded JSON data for Yammer importer
     * @return User_group
     */
    function importGroup($item)
    {
        $data = $this->prepGroup($item);
        $nickname = $data['options']['nickname'];

        $groupId = $this->findImportedGroup($data['orig_id']);
        if ($groupId) {
            return User_group::getKV('id', $groupId);
        } else {
            $local = Local_group::getKV('nickname', $nickname);
            if ($local) {
                common_log(LOG_WARN, "Copying Yammer group info onto existing group $nickname");
                $group = User_group::getKV('id', $local->group_id);
                $this->savePropertiesOn($group, $data['options'],
                        array('fullname', 'description'));
            } else {
                $group = User_group::register($data['options']);
            }
            if ($data['avatar']) {
                try {
                    $this->saveAvatar($data['avatar'], $group);
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error importing Yammer avatar: " . $e->getMessage());
                }
            }
            $this->recordImportedGroup($data['orig_id'], $group->id);
            return $group;
        }
    }

    private function savePropertiesOn($target, $options, $propList)
    {
        $changed = 0;
        $orig = clone($target);
        foreach ($propList as $prop) {
            if (!empty($options[$prop]) && $target->$prop != $options[$prop]) {
                $target->$prop = $options[$prop];
                $changed++;
            }
        }
        $target->update($orig);
    }

    /**
     * Load or create an imported notice from Yammer data.
     *
     * @param object $item loaded JSON data for Yammer importer
     * @return Notice
     */
    function importNotice($item)
    {
        $data = $this->prepNotice($item);

        $noticeId = $this->findImportedNotice($data['orig_id']);
        if ($noticeId) {
            return Notice::getKV('id', $noticeId);
        } else {
            $notice = Notice::getKV('uri', $data['options']['uri']);
            $content = $data['content'];
            $user = User::getKV($data['profile']);

            // Fetch file attachments and add the URLs...
            $uploads = array();
            foreach ($data['attachments'] as $url) {
                try {
                    $upload = $this->saveAttachment($url, $user);
                    $content .= ' ' . $upload->shortUrl();
                    $uploads[] = $upload;
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error importing Yammer attachment: " . $e->getMessage());
                }
            }

            // Here's the meat! Actually save the dang ol' notice.
            $notice = Notice::saveNew($user->id,
                                      $content,
                                      $data['source'],
                                      $data['options']);

            // Save "likes" as favorites...
            foreach ($data['faves'] as $nickname) {
                $user = User::getKV('nickname', $nickname);
                if ($user) {
                    Fave::addNew($user->getProfile(), $notice);
                }
            }

            // And finally attach the upload records...
            foreach ($uploads as $upload) {
                $upload->attachToNotice($notice);
            }
            $this->recordImportedNotice($data['orig_id'], $notice->id);
            return $notice;
        }
    }

    /**
     * Pull relevant info out of a Yammer data record for a user import.
     *
     * @param array $item
     * @return array
     */
    function prepUser($item)
    {
        if ($item['type'] != 'user') {
            // TRANS: Exception thrown when a non-user item type is used, but expected.
            throw new Exception(_m('Wrong item type sent to Yammer user import processing.'));
        }

        $origId = $item['id'];
        $origUrl = $item['url'];

        // @fixme check username rules?

        $options['nickname'] = $item['name'];
        $options['fullname'] = trim($item['full_name']);

        // Avatar... this will be the "_small" variant.
        // Remove that (pre-extension) suffix to get the orig-size image.
        $avatar = $item['mugshot_url'];

        // The following info is only available in full data, not in the reference version.

        // There can be extensive contact info, but for now we'll only pull the primary email.
        if (isset($item['contact'])) {
            foreach ($item['contact']['email_addresses'] as $addr) {
                if ($addr['type'] == 'primary') {
                    $options['email'] = $addr['address'];
                    $options['email_confirmed'] = true;
                    break;
                }
            }
        }

        // There can be multiple external URLs; for now pull the first one as home page.
        if (isset($item['external_urls'])) {
            foreach ($item['external_urls'] as $url) {
                if (common_valid_http_url($url)) {
                    $options['homepage'] = $url;
                    break;
                }
            }
        }

        // Combine a few bits into the bio...
        $bio = array();
        if (!empty($item['job_title'])) {
            $bio[] = $item['job_title'];
        }
        if (!empty($item['summary'])) {
            $bio[] = $item['summary'];
        }
        if (!empty($item['expertise'])) {
            // TRANS: Used as a prefix for the Yammer expertise field contents.
            $bio[] = _m('Expertise:') . ' ' . $item['expertise'];
        }
        $options['bio'] = implode("\n\n", $bio);

        // Pull raw location string, may be lookupable
        if (!empty($item['location'])) {
            $options['location'] = $item['location'];
        }

        // Timezone is in format like 'Pacific Time (US & Canada)'
        // We need to convert that to a zone id. :P
        // @fixme timezone not yet supported at registration time :)
        if (!empty($item['timezone'])) {
            $tz = $this->timezone($item['timezone']);
            if ($tz) {
                $options['timezone'] = $tz;
            }
        }

        return array('orig_id' => $origId,
                     'orig_url' => $origUrl,
                     'avatar' => $avatar,
                     'options' => $options);

    }

    /**
     * Pull relevant info out of a Yammer data record for a group import.
     *
     * @param array $item
     * @return array
     */
    function prepGroup($item)
    {
        if ($item['type'] != 'group') {
            // TRANS: Exception thrown when a non-group item type is used, but expected.
            throw new Exception(_m('Wrong item type sent to Yammer group import processing.'));
        }

        $origId = $item['id'];
        $origUrl = $item['url'];

        $privacy = $item['privacy']; // Warning! only public groups in SN so far

        $options['nickname'] = $item['name'];
        $options['fullname'] = $item['full_name'];
        $options['description'] = $item['description'];
        $options['created'] = $this->timestamp($item['created_at']);

        $avatar = $item['mugshot_url']; // as with user profiles...

        $options['mainpage'] = common_local_url('showgroup',
                                   array('nickname' => $options['nickname']));

        // Set some default vals or User_group::register will whine
        $options['homepage'] = '';
        $options['location'] = '';
        $options['aliases'] = array();
        // @todo FIXME: What about admin user for the group?

        $options['local'] = true;
        return array('orig_id' => $origId,
                     'orig_url' => $origUrl,
                     'options' => $options,
                     'avatar' => $avatar);
    }

    /**
     * Pull relevant info out of a Yammer data record for a notice import.
     *
     * @param array $item
     * @return array
     */
    function prepNotice($item)
    {
        if (isset($item['type']) && $item['type'] != 'message') {
            // TRANS: Exception thrown when a non-message item type is used, but expected.
            throw new Exception(_m('Wrong item type sent to Yammer message import processing.'));
        }

        $origId = $item['id'];
        $origUrl = $item['url'];

        $profile = $this->findImportedUser($item['sender_id']);
        $content = $item['body']['plain'];
        $source = 'yammer';
        $options = array();

        if ($item['replied_to_id']) {
            $replyTo = $this->findImportedNotice($item['replied_to_id']);
            if ($replyTo) {
                $options['reply_to'] = $replyTo;
            }
        }
        $options['created'] = $this->timestamp($item['created_at']);

        if (!empty($item['group_id'])) {
            $groupId = $this->findImportedGroup($item['group_id']);
            if ($groupId) {
                $options['groups'] = array($groupId);

                // @fixme if we see a group link inline, don't add this?
                $group = User_group::getKV('id', $groupId);
                if ($group) {
                    $content .= ' !' . $group->nickname;
                }
            }
        }

        $faves = array();
        foreach ($item['liked_by']['names'] as $liker) {
            // "permalink" is the username. wtf?
            $faves[] = $liker['permalink'];
        }

        $attachments = array();
        foreach ($item['attachments'] as $attach) {
            if ($attach['type'] == 'image' || $attach['type'] == 'file') {
                $attachments[] = $attach[$attach['type']]['url'];
            } else {
                common_log(LOG_WARNING, "Unrecognized Yammer attachment type: " . $attach['type']);
            }
        }

        return array('orig_id' => $origId,
                     'orig_url' => $origUrl,
                     'profile' => $profile,
                     'content' => $content,
                     'source' => $source,
                     'options' => $options,
                     'faves' => $faves,
                     'attachments' => $attachments);
    }

    private function findImportedUser($origId)
    {
        $map = Yammer_user::getKV('id', $origId);
        return $map ? $map->user_id : null;
    }

    private function findImportedGroup($origId)
    {
        $map = Yammer_group::getKV('id', $origId);
        return $map ? $map->group_id : null;
    }

    private function findImportedNotice($origId)
    {
        $map = Yammer_notice::getKV('id', $origId);
        return $map ? $map->notice_id : null;
    }

    private function recordImportedUser($origId, $userId)
    {
        Yammer_user::record($origId, $userId);
    }

    private function recordImportedGroup($origId, $groupId)
    {
        Yammer_group::record($origId, $groupId);
    }

    private function recordImportedNotice($origId, $noticeId)
    {
        Yammer_notice::record($origId, $noticeId);
    }

    /**
     * Normalize timestamp format.
     * @param string $ts
     * @return string
     */
    private function timestamp($ts)
    {
        return common_sql_date(strtotime($ts));
    }

    private function timezone($tz)
    {
        // Blaaaaaarf!
        $known = array('Pacific Time (US & Canada)' => 'America/Los_Angeles',
                       'Eastern Time (US & Canada)' => 'America/New_York');
        if (array_key_exists($tz, $known)) {
            return $known[$tz];
        } else {
            return false;
        }
    }

    /**
     * Download and update given avatar image
     *
     * @param string $url
     * @param mixed $dest either a Profile or User_group object
     * @throws Exception in various failure cases
     */
    private function saveAvatar($url, $dest)
    {
        // Yammer API data mostly gives us the small variant.
        // Try hitting the source image if we can!
        // @fixme no guarantee of this URL scheme I think.
        $url = preg_replace('/_small(\..*?)$/', '$1', $url);

        if (!common_valid_http_url($url)) {
            // TRANS: Server exception thrown when an avatar URL is invalid.
            // TRANS: %s is the invalid avatar URL.
            throw new ServerException(sprintf(_m('Invalid avatar URL %s.'), $url));
        }

        // @fixme this should be better encapsulated
        // ripped from oauthstore.php (for old OMB client)
        $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
        try {
            if (!copy($url, $temp_filename)) {
                // TRANS: Server exception thrown when an avatar could not be fetched.
                // TRANS: %s is the failed avatar URL.
                throw new ServerException(sprintf(_m('Unable to fetch avatar from %s.'), $url));
            }

            $id = $dest->id;
            // @fixme should we be using different ids?
            $imagefile = new ImageFile($id, $temp_filename);
            $filename = Avatar::filename($id,
                                         image_type_to_extension($imagefile->type),
                                         null,
                                         common_timestamp());
            rename($temp_filename, Avatar::path($filename));
        } catch (Exception $e) {
            unlink($temp_filename);
            throw $e;
        }
        // @fixme hardcoded chmod is lame, but seems to be necessary to
        // keep from accidentally saving images from command-line (queues)
        // that can't be read from web server, which causes hard-to-notice
        // problems later on:
        //
        // http://status.net/open-source/issues/2663
        chmod(Avatar::path($filename), 0644);

        $dest->setOriginal($filename);
    }

    /**
     * Fetch an attachment from Yammer and save it into our system.
     * Unlike avatars, the attachment URLs are guarded by authentication,
     * so we need to run the HTTP hit through our OAuth API client.
     *
     * @param string $url
     * @param User $user
     * @return MediaFile
     *
     * @throws Exception on low-level network or HTTP error
     */
    private function saveAttachment($url, User $user)
    {
        // Fetch the attachment...
        // WARNING: file must fit in memory here :(
        $body = $this->client->fetchUrl($url);

        // Save to a temporary file and shove it into our file-attachment space...
        $temp = tmpfile();
        fwrite($temp, $body);
        try {
            $upload = MediaFile::fromFileHandle($temp, $user);
            fclose($temp);
            return $upload;
        } catch (Exception $e) {
            fclose($temp);
            throw $e;
        }
    }
}
