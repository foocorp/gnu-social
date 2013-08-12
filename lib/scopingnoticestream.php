<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Filtering notice stream that recognizes notice scope
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
 * @category  Stream
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
 * Class comment
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ScopingNoticeStream extends FilteringNoticeStream
{
    protected $profile;

    function __construct($upstream, $profile = -1)
    {
        parent::__construct($upstream);

        // Invalid but not null
        if (is_int($profile) && $profile == -1) {
            $profile = Profile::current();
        }

        $this->profile = $profile;
    }

    /**
     * Only return notices where the profile is in scope
     *
     * @param Notice $notice The notice to check
     *
     * @return boolean whether to include the notice
     */

    function filter($notice)
    {
        return $notice->inScope($this->profile);
    }

    function prefill($notices)
    {
        // XXX: this should probably only be in the scoping one.
            
        Notice::fillGroups($notices);
        Notice::fillReplies($notices);

        if (common_config('notice', 'hidespam')) {

            $profiles = Notice::getProfiles($notices);

            foreach ($profiles as $profile) {
                $pids[] = $profile->id;
            }
            
            Memcached_DataObject::pivotGet('Profile_role',
                                           'profile_id',
                                           $pids,
                                           array('role' => Profile_role::SILENCED));
        }
    }
}
