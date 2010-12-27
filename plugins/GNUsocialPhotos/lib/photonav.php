<?php
/**
 * GNU Social
 * Copyright (C) 2010, Free Software Foundation, Inc.
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
 * @author    Max Shinn <trombonechamp@gmail.com>
 * @copyright 2010 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if(!defined('STATUSNET')) {
    exit(1);
}

class GNUsocialPhotoNav extends Widget {
    var $action = null;

    function __construct($action = null, $nickname = null)
    {
        parent::__construct($action);
        $this->action = $action;
        $this->nickname = $nickname;
    }

    function show()
    {
        if (empty($this->nickname)) 
            $this->nickname = $this->action->trimmed('nickname');
        
        $this->out->elementStart('ul', array('class' => 'nav'));

        $this->out->menuItem(common_local_url('showstream', array('nickname' => $this->nickname)), _('Profile'));

        $this->out->menuItem(common_local_url('photos', array('nickname' => $this->nickname)),
            _('Photos'));

        $user = common_current_user();
        if (!empty($user)) {
            $this->out->menuItem(common_local_url('photoupload', array()),
                _('Upload Photos'));
        }

        $this->out->elementEnd('ul');
    }
}
