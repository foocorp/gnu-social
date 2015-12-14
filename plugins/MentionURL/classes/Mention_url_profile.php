<?php
/*
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Table Definition for mention_url_profile
 */

class Mention_url_profile extends Managed_DataObject
{
    public $__table = 'mention_url_profile'; // table name
    public $profile_id;                      // int(4) not_null
    public $profileurl;                      // varchar(191) primary_key not_null not 255 because utf8mb4 takes more space

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'matches exactly one profile id'),
                'profileurl' => array('type' => 'varchar', 'not null' => true, 'length' => 191, 'description' => 'URL of the profile'),
            ),
            'primary key' => array('profileurl'),
            'foreign keys' => array(
                'mention_url_profile_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
        );
    }

    public static function fromUrl($url, $depth=0) {
        common_debug('MentionURL: trying to find a profile for ' . $url);

        $url = preg_replace('#https?://#', 'https://', $url);
        try {
            $profile = Profile::fromUri($url);
        } catch(UnknownUriException $ex) {}

        if(!($profile instanceof Profile)) {
            $profile = self::findProfileByProfileURL($url);
        }

        $url = str_replace('https://', 'http://', $url);
        if(!($profile instanceof Profile)) {
            try {
                $profile = Profile::fromUri($url);
            } catch(UnknownUriException $ex) {}
        }

        if(!($profile instanceof Profile)) {
            $profile = self::findProfileByProfileURL($url);
        }

        if(!($profile instanceof Profile)) {
            $hcard = mention_url_representative_hcard($url);
            if(!$hcard) return null;

            $mention_profile = new Mention_url_profile();
            $mention_profile->query('BEGIN');

            $profile = new Profile();
            $profile->profileurl = $hcard['url'][0];
            $profile->fullname = $hcard['name'][0];
            preg_match('/\/([^\/]+)\/*$/', $profile->profileurl, $matches);
            if(!$hcard['nickname']) $hcard['nickname'] = array($matches[1]);
            $profile->nickname = $hcard['nickname'][0];
            $profile->created = common_sql_now();

            $mention_profile->profile_id = $profile->insert();
            if(!$mention_profile->profile_id) {
                $mention_profile->query('ROLLBACK');
                return null;
            }

            $mention_profile->profileurl = $profile->profileurl;
            if(!$mention_profile->insert()) {
                $mention_profile->query('ROLLBACK');
                if($depth > 0) {
                    return null;
                } else {
                    return self::fromUrl($url, $depth+1);
                }
            } else {
                $mention_profile->query('COMMIT');
            }
        }

        return $profile;
    }

    protected static function findProfileByProfileURL($url) {
        $profile = Profile::getKV('profileurl', $url);
        if($profile instanceof Profile) {
            $mention_profile = new Mention_url_profile();
            $mention_profile->profile_id = $profile->id;
            $mention_profile->profileurl = $profile->profileurl;
            $mention_profile->insert();
        }

        return $profile;
    }

    public function getProfile() {
        return Profile::getKV('id', $this->profile_id);
    }
}
