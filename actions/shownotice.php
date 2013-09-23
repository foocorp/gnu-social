<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a single notice
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
 * @category  Personal
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/personalgroupnav.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

/**
 * Show a single notice
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ShownoticeAction extends Action
{
    /**
     * Notice object to show
     */
    var $notice = null;

    /**
     * Profile of the notice object
     */
    var $profile = null;

    /**
     * Avatar of the profile of the notice object
     */
    var $avatar = null;

    /**
     * Load attributes based on database arguments
     *
     * Loads all the DB stuff
     *
     * @param array $args $_REQUEST array
     *
     * @return success flag
     */
    function prepare($args)
    {
        parent::prepare($args);
        if ($this->boolean('ajax')) {
            StatusNet::setApi(true);
        }

        $this->notice = $this->getNotice();

        $cur = common_current_user();

        if (!empty($cur)) {
            $curProfile = $cur->getProfile();
        } else {
            $curProfile = null;
        }

        if (!$this->notice->inScope($curProfile)) {
            // TRANS: Client exception thrown when trying a view a notice the user has no access to.
            throw new ClientException(_('Not available.'), 403);
        }

        $this->profile = $this->notice->getProfile();

        if (empty($this->profile)) {
            // TRANS: Server error displayed trying to show a notice without a connected profile.
            $this->serverError(_('Notice has no profile.'), 500);
            return false;
        }

        $this->user = User::getKV('id', $this->profile->id);

        $this->avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);

        return true;
    }

    /**
     * Fetch the notice to show. This may be overridden by child classes to
     * customize what we fetch without duplicating all of the prepare() method.
     *
     * @return Notice
     */
    function getNotice()
    {
        $id = $this->arg('notice');

        $notice = Notice::getKV('id', $id);

        if (empty($notice)) {
            // Did we used to have it, and it got deleted?
            $deleted = Deleted_notice::getKV($id);
            if (!empty($deleted)) {
                // TRANS: Client error displayed trying to show a deleted notice.
                $this->clientError(_('Notice deleted.'), 410);
            } else {
                // TRANS: Client error displayed trying to show a non-existing notice.
                $this->clientError(_('No such notice.'), 404);
            }
            return false;
        }
        return $notice;
    }

    /**
     * Is this action read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Last-modified date for page
     *
     * When was the content of this page last modified? Based on notice,
     * profile, avatar.
     *
     * @return int last-modified date as unix timestamp
     */
    function lastModified()
    {
        return max(strtotime($this->notice->modified),
                   strtotime($this->profile->modified),
                   ($this->avatar) ? strtotime($this->avatar->modified) : 0);
    }

    /**
     * An entity tag for this page
     *
     * Shows the ETag for the page, based on the notice ID and timestamps
     * for the notice, profile, and avatar. It's weak, since we change
     * the date text "one hour ago", etc.
     *
     * @return string etag
     */
    function etag()
    {
        $avtime = ($this->avatar) ?
          strtotime($this->avatar->modified) : 0;

        return 'W/"' . implode(':', array($this->arg('action'),
                                          common_user_cache_hash(),
                                          common_language(),
                                          $this->notice->id,
                                          strtotime($this->notice->created),
                                          strtotime($this->profile->modified),
                                          $avtime)) . '"';
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */
    function title()
    {
        $base = $this->profile->getFancyName();

        // TRANS: Title of the page that shows a notice.
        // TRANS: %1$s is a user name, %2$s is the notice creation date/time.
        return sprintf(_('%1$s\'s status on %2$s'),
                       $base,
                       common_exact_date($this->notice->created));
    }

    /**
     * Handle input
     *
     * Only handles get, so just show the page.
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($this->boolean('ajax')) {
            $this->showAjax();
        } else {
            if ($this->notice->is_local == Notice::REMOTE) {
                if (!empty($this->notice->url)) {
                    $target = $this->notice->url;
                } else if (!empty($this->notice->uri) && preg_match('/^https?:/', $this->notice->uri)) {
                    // Old OMB posts saved the remote URL only into the URI field.
                    $target = $this->notice->uri;
                } else {
                    // Shouldn't happen.
                    $target = false;
                }
                if ($target && $target != $this->selfUrl()) {
                    common_redirect($target, 301);
                    return false;
                }
            }
            $this->showPage();
        }
    }

    /**
     * Fill the content area of the page
     *
     * Shows a single notice list item.
     *
     * @return void
     */
    function showContent()
    {
        $this->elementStart('ol', array('class' => 'notices xoxo'));
        $nli = new SingleNoticeItem($this->notice, $this);
        $nli->show();
        $this->elementEnd('ol');
    }

    function showAjax()
    {
        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        // TRANS: Title for page that shows a notice.
        $this->element('title', null, _m('TITLE','Notice'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $nli = new NoticeListItem($this->notice, $this);
        $nli->show();
        $this->elementEnd('body');
        $this->endHTML();
    }

    /**
     * Don't show page notice
     *
     * @return void
     */
    function showPageNoticeBlock()
    {
    }

    /**
     * Don't show aside
     *
     * @return void
     */
    function showAside() {
    }

    /**
     * Extra <head> content
     *
     * We show the microid(s) for the author, if any.
     *
     * @return void
     */
    function extraHead()
    {
        $user = User::getKV($this->profile->id);

        if (!$user) {
            return;
        }

        if ($user->emailmicroid && $user->email && $this->notice->uri) {
            $id = new Microid('mailto:'. $user->email,
                              $this->notice->uri);
            $this->element('meta', array('name' => 'microid',
                                         'content' => $id->toString()));
        }

        $this->element('link',array('rel'=>'alternate',
            'type'=>'application/json+oembed',
            'href'=>common_local_url(
                'oembed',
                array(),
                array('format'=>'json','url'=>$this->notice->uri)),
            'title'=>'oEmbed'),null);
        $this->element('link',array('rel'=>'alternate',
            'type'=>'text/xml+oembed',
            'href'=>common_local_url(
                'oembed',
                array(),
                array('format'=>'xml','url'=>$this->notice->uri)),
            'title'=>'oEmbed'),null);

        // Extras to aid in sharing notices to Facebook
        $avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);
        $avatarUrl = ($avatar) ?
                     $avatar->displayUrl() :
                     Avatar::defaultImage(AVATAR_PROFILE_SIZE);
        $this->element('meta', array('property' => 'og:image',
                                     'content' => $avatarUrl));
        $this->element('meta', array('property' => 'og:description',
                                     'content' => $this->notice->content));
    }
}

// @todo FIXME: Class documentation missing.
class SingleNoticeItem extends DoFollowListItem
{
    function avatarSize()
    {
        return AVATAR_STREAM_SIZE;
    }
}
