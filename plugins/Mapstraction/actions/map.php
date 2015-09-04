<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a map of user's notices
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
 * @category  Mapstraction
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Show a map of notices
 *
 * @category Mapstraction
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class MapAction extends Action
{
    var $profile = null;
    var $page    = null;
    var $notices = null;

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');
        $nickname     = Nickname::normalize($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url($this->trimmed('action'), $args), 301);
        }

        $this->user = User::getKV('nickname', $nickname);

        if (!$this->user) {
            // TRANS: Client error displayed when referring to a non-existing user.
            $this->clientError(_m('No such user.'), 404);
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_m('User has no profile.'));
        }

        $page = $this->trimmed('page');

        if (!empty($page) && Validate::number($page)) {
            $this->page = $page+0;
        } else {
            $this->page = 1;
        }

        $this->notices = empty($this->tag)
          ? $this->user->getNotices(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1)
            : $this->user->getTaggedNotices($this->tag, ($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1, 0, 0, null);

        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showContent()
    {
        $this->element('div', array('id' => 'map_canvas',
                                    'class' => 'gray smallmap',
                                    'style' => "width: 100%; height: 400px"));
    }

    /**
     * Hook for adding extra JavaScript
     *
     * @param Action $action Action object for the page
     *
     * @return boolean event handler return
     */
    function showScripts()
    {
        parent::showScripts();
        $jsonArray = array();

        while ($this->notice->fetch()) {
            try {
                $notloc = Notice_location::locFromStored($this->notice);
                $jsonArray[] = $this->noticeAsJson($this->notice);
            } catch (ServerException $e) {
                // no location data
            }
        }

        $this->inlineScript('$(document).ready(function() { '.
                            ' var _notices = ' . json_encode($jsonArray).'; ' .
                            'showMapstraction($("#map_canvas"), _notices); });');

        return true;
    }

    function noticeAsJson($notice)
    {
        // FIXME: this code should be abstracted to a neutral third
        // party, like Notice::asJson(). I'm not sure of the ethics
        // of refactoring from within a plugin, so I'm just abusing
        // the ApiAction method. Don't do this unless you're me!

        $act = new ApiAction('/dev/null');

        $arr = $act->twitterStatusArray($notice, true);
        $arr['url'] = $notice->getUrl(true);
        $arr['html'] = $notice->rendered;
        $arr['source'] = $arr['source'];

        if (!empty($notice->reply_to)) {
            $reply_to = Notice::getKV('id', $notice->reply_to);
            if (!empty($reply_to)) {
                $arr['in_reply_to_status_url'] = $reply_to->getUrl(true);
            }
            $reply_to = null;
        }

        $profile = $notice->getProfile();
        $arr['user']['profile_url'] = $profile->profileurl;

        return $arr;
    }
}
