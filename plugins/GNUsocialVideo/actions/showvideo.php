<?php
/**
 * GNU Social
 * Copyright (C) 2011, Free Software Foundation, Inc.
 *
 * PHP version 5
 *
 * LICENCE:
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
 * @package   GNU Social
 * @author    Ian Denhardt <ian@zenhack.net>
 * @copyright 2011 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if(!defined('STATUSNET')){
    exit(1);
}

class ShowvideoAction extends ShownoticeAction
{
    protected $id = null;
    protected $vid = null;

    function prepare($args)
    {
        OwnerDesignAction::prepare($args);
        $this->id = $this->trimmed('id');
        $this->vid = Video::getKV('id', $this->id);

        if (empty($this->vid)) {
            throw new ClientException(_('No such video.'), 404);
        }

        $this->notice = $this->vid->getNotice();

        if (empty($this->notice)) {
            throw new ClientException(_('No such video'), 404);
        }

        $this->user = User::getKV('id', $this->vid->profile_id);

        if (empty($this->user)) {
            throw new ClientException(_('No such user.'), 404);
        }

        $this->profile = $this->user->getProfile();

        if (empty($this->profile)) {
            throw new ServerException(_('User without a profile.'));
        }

        return true;
    }
}
