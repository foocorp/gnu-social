<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * An item in a notice list
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
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * widget for displaying a single notice
 *
 * This widget has the core smarts for showing a single notice: what to display,
 * where, and under which circumstances. Its key method is show(); this is a recipe
 * that calls all the other show*() methods to build up a single notice. The
 * ProfileNoticeListItem subclass, for example, overrides showAuthor() to skip
 * author info (since that's implicit by the data in the page).
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      NoticeList
 * @see      ProfileNoticeListItem
 */
class NoticeListItem extends Widget
{
    /** The notice this item will show. */
    var $notice = null;

    /** The notice that was repeated. */
    var $repeat = null;

    /** The profile of the author of the notice, extracted once for convenience. */
    var $profile = null;

    /**
     * constructor
     *
     * Also initializes the profile attribute.
     *
     * @param Notice $notice The notice we'll display
     */
    function __construct($notice, $out=null)
    {
        parent::__construct($out);
        if (!empty($notice->repeat_of)) {
            $original = Notice::getKV('id', $notice->repeat_of);
            if (empty($original)) { // could have been deleted
                $this->notice = $notice;
            } else {
                $this->notice = $original;
                $this->repeat = $notice;
            }
        } else {
            $this->notice  = $notice;
        }
        $this->profile = $this->notice->getProfile();
    }

    /**
     * recipe function for displaying a single notice.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */
    function show()
    {
        if (empty($this->notice)) {
            common_log(LOG_WARNING, "Trying to show missing notice; skipping.");
            return;
        } else if (empty($this->profile)) {
            common_log(LOG_WARNING, "Trying to show missing profile (" . $this->notice->profile_id . "); skipping.");
            return;
        }

        $this->showStart();
        if (Event::handle('StartShowNoticeItem', array($this))) {
            $this->showNotice();
            $this->showNoticeAttachments();
            $this->showNoticeInfo();
            $this->showNoticeOptions();
            Event::handle('EndShowNoticeItem', array($this));
        }
        $this->showEnd();
    }

    function showNotice()
    {
        $this->out->elementStart('div', 'entry-title');
        $this->showAuthor();
        $this->showContent();
        $this->out->elementEnd('div');
    }

    function showNoticeInfo()
    {
        $this->out->elementStart('div', 'entry-content');
        if (Event::handle('StartShowNoticeInfo', array($this))) {
            $this->showNoticeLink();
            $this->showNoticeSource();
            $this->showNoticeLocation();
            $this->showContext();
            $this->showRepeat();
            Event::handle('EndShowNoticeInfo', array($this));
        }

        $this->out->elementEnd('div');
    }

    function showNoticeOptions()
    {
        if (Event::handle('StartShowNoticeOptions', array($this))) {
            $user = common_current_user();
            if ($user) {
                $this->out->elementStart('div', 'notice-options');
                if (Event::handle('StartShowNoticeOptionItems', array($this))) {
                    $this->showFaveForm();
                    $this->showReplyLink();
                    $this->showRepeatForm();
                    $this->showDeleteLink();
                    Event::handle('EndShowNoticeOptionItems', array($this));
                }
                $this->out->elementEnd('div');
            }
            Event::handle('EndShowNoticeOptions', array($this));
        }
    }

    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        if (Event::handle('StartOpenNoticeListItemElement', array($this))) {
            $id = (empty($this->repeat)) ? $this->notice->id : $this->repeat->id;
            $class = 'hentry notice';
            if ($this->notice->scope != 0 && $this->notice->scope != 1) {
                $class .= ' limited-scope';
            }
            if (!empty($this->notice->source)) {
                $class .= ' notice-source-'.$this->notice->source;
            }
            $this->out->elementStart('li', array('class' => $class,
                                                 'id' => 'notice-' . $id));
            Event::handle('EndOpenNoticeListItemElement', array($this));
        }
    }

    /**
     * show the "favorite" form
     *
     * @return void
     */
    function showFaveForm()
    {
        if (Event::handle('StartShowFaveForm', array($this))) {
            $user = common_current_user();
            if ($user) {
                if ($user->hasFave($this->notice)) {
                    $disfavor = new DisfavorForm($this->out, $this->notice);
                    $disfavor->show();
                } else {
                    $favor = new FavorForm($this->out, $this->notice);
                    $favor->show();
                }
            }
            Event::handle('EndShowFaveForm', array($this));
        }
    }

    /**
     * show the author of a notice
     *
     * By default, this shows the avatar and (linked) nickname of the author.
     *
     * @return void
     */

    function showAuthor()
    {
        $this->out->elementStart('div', 'author');

        $this->out->elementStart('span', 'vcard author');

        $attrs = array('href' => $this->profile->profileurl,
                       'class' => 'url',
                       'title' => $this->profile->nickname);

        $this->out->elementStart('a', $attrs);
        $this->showAvatar();
        $this->out->text(' ');
        $user = common_current_user();
        if (!empty($user) && $user->streamNicknames()) {
            $this->out->element('span',array('class' => 'fn'),
                                $this->profile->nickname);
        } else {
            $this->out->element('span',array('class' => 'fn'),
                                $this->profile->getBestName());
        }
        $this->out->elementEnd('a');

        $this->out->elementEnd('span');

        $this->showAddressees();

        $this->out->elementEnd('div');
    }

    function showAddressees()
    {
        $ga = $this->getGroupAddressees();
        $pa = $this->getProfileAddressees();

        $a = array_merge($ga, $pa);

        if (!empty($a)) {
            $this->out->elementStart('span', 'addressees');
            $first = true;
            foreach ($a as $addr) {
                if (!$first) {
                    // TRANS: Separator in profile addressees list.
                    $this->out->text(_m('SEPARATOR',', '));
                } else {
                    // Start of profile addressees list.
                    $first = false;
                }
                $text = $addr['text'];
                unset($addr['text']);
                $this->out->element('a', $addr, $text);
            }
            $this->out->elementEnd('span', 'addressees');
        }
    }

    function getGroupAddressees()
    {
        $ga = array();

        $groups = $this->getGroups();

        $user = common_current_user();

        $streamNicknames = !empty($user) && $user->streamNicknames();

        foreach ($groups as $group) {
            $ga[] = array('href' => $group->homeUrl(),
                          'title' => $group->nickname,
                          'class' => 'addressee group',
                          'text' => ($streamNicknames) ? $group->nickname : $group->getBestName());
        }

        return $ga;
    }

    function getGroups()
    {
        return $this->notice->getGroups();
    }

    function getProfileAddressees()
    {
        $pa = array();

        $replies = $this->getReplyProfiles();

        $user = common_current_user();

        $streamNicknames = !empty($user) && $user->streamNicknames();

        foreach ($replies as $reply) {
            $pa[] = array('href' => $reply->profileurl,
                          'title' => $reply->nickname,
                          'class' => 'addressee account',
                          'text' => ($streamNicknames) ? $reply->nickname : $reply->getBestName());
        }

        return $pa;
    }

    function getReplyProfiles()
    {
        return $this->notice->getReplyProfiles();
    }

    /**
     * show the avatar of the notice's author
     *
     * This will use the default avatar if no avatar is assigned for the author.
     * It makes a link to the author's profile.
     *
     * @return void
     */
    function showAvatar()
    {
        $avatar_size = $this->avatarSize();

        $avatarUrl = $this->profile->avatarUrl($avatar_size);

        $this->out->element('img', array('src' => $avatarUrl,
                                         'class' => 'avatar photo',
                                         'width' => $avatar_size,
                                         'height' => $avatar_size,
                                         'alt' =>
                                         ($this->profile->fullname) ?
                                         $this->profile->fullname :
                                         $this->profile->nickname));
    }

    function avatarSize()
    {
        return AVATAR_STREAM_SIZE;
    }

    /**
     * show the nickname of the author
     *
     * Links to the author's profile page
     *
     * @return void
     */
    function showNickname()
    {
        $this->out->raw('<span class="nickname fn">' .
                        htmlspecialchars($this->profile->nickname) .
                        '</span>');
    }

    /**
     * show the content of the notice
     *
     * Shows the content of the notice. This is pre-rendered for efficiency
     * at save time. Some very old notices might not be pre-rendered, so
     * they're rendered on the spot.
     *
     * @return void
     */
    function showContent()
    {
        // FIXME: URL, image, video, audio
        $this->out->elementStart('p', array('class' => 'entry-content'));
        if ($this->notice->rendered) {
            $this->out->raw($this->notice->rendered);
        } else {
            // XXX: may be some uncooked notices in the DB,
            // we cook them right now. This should probably disappear in future
            // versions (>> 0.4.x)
            $this->out->raw(common_render_content($this->notice->content, $this->notice));
        }
        $this->out->elementEnd('p');
    }

    function showNoticeAttachments() {
        if (common_config('attachments', 'show_thumbs')) {
            $al = new InlineAttachmentList($this->notice, $this->out);
            $al->show();
        }
    }

    /**
     * show the link to the main page for the notice
     *
     * Displays a link to the page for a notice, with "relative" time. Tries to
     * get remote notice URLs correct, but doesn't always succeed.
     *
     * @return void
     */
    function showNoticeLink()
    {
        $noticeurl = $this->notice->bestUrl();

        // above should always return an URL

        assert(!empty($noticeurl));

        $this->out->elementStart('a', array('rel' => 'bookmark',
                                            'class' => 'timestamp',
                                            'href' => $noticeurl));
        $dt = common_date_iso8601($this->notice->created);
        $this->out->element('abbr', array('class' => 'published',
                                          'title' => $dt),
                            common_date_string($this->notice->created));
        $this->out->elementEnd('a');
    }

    /**
     * show the notice location
     *
     * shows the notice location in the correct language.
     *
     * If an URL is available, makes a link. Otherwise, just a span.
     *
     * @return void
     */
    function showNoticeLocation()
    {
        $id = $this->notice->id;

        $location = $this->notice->getLocation();

        if (empty($location)) {
            return;
        }

        $name = $location->getName();

        $lat = $this->notice->lat;
        $lon = $this->notice->lon;
        $latlon = (!empty($lat) && !empty($lon)) ? $lat.';'.$lon : '';

        if (empty($name)) {
            $latdms = $this->decimalDegreesToDMS(abs($lat));
            $londms = $this->decimalDegreesToDMS(abs($lon));
            // TRANS: Used in coordinates as abbreviation of north.
            $north = _('N');
            // TRANS: Used in coordinates as abbreviation of south.
            $south = _('S');
            // TRANS: Used in coordinates as abbreviation of east.
            $east = _('E');
            // TRANS: Used in coordinates as abbreviation of west.
            $west = _('W');
            $name = sprintf(
                // TRANS: Coordinates message.
                // TRANS: %1$s is lattitude degrees, %2$s is lattitude minutes,
                // TRANS: %3$s is lattitude seconds, %4$s is N (north) or S (south) depending on lattitude,
                // TRANS: %5$s is longitude degrees, %6$s is longitude minutes,
                // TRANS: %7$s is longitude seconds, %8$s is E (east) or W (west) depending on longitude,
                _('%1$u°%2$u\'%3$u"%4$s %5$u°%6$u\'%7$u"%8$s'),
                $latdms['deg'],$latdms['min'], $latdms['sec'],($lat>0? $north:$south),
                $londms['deg'],$londms['min'], $londms['sec'],($lon>0? $east:$west));
        }

        $url  = $location->getUrl();

        $this->out->text(' ');
        $this->out->elementStart('span', array('class' => 'location'));
        // TRANS: Followed by geo location.
        $this->out->text(_('at'));
        $this->out->text(' ');
        if (empty($url)) {
            $this->out->element('abbr', array('class' => 'geo',
                                              'title' => $latlon),
                                $name);
        } else {
            $xstr = new XMLStringer(false);
            $xstr->elementStart('a', array('href' => $url,
                                           'rel' => 'external'));
            $xstr->element('abbr', array('class' => 'geo',
                                         'title' => $latlon),
                           $name);
            $xstr->elementEnd('a');
            $this->out->raw($xstr->getString());
        }
        $this->out->elementEnd('span');
    }

    /**
     * @param number $dec decimal degrees
     * @return array split into 'deg', 'min', and 'sec'
     */
    function decimalDegreesToDMS($dec)
    {
        $deg = intval($dec);
        $tempma = abs($dec) - abs($deg);

        $tempma = $tempma * 3600;
        $min = floor($tempma / 60);
        $sec = $tempma - ($min*60);

        return array("deg"=>$deg,"min"=>$min,"sec"=>$sec);
    }

    /**
     * Show the source of the notice
     *
     * Either the name (and link) of the API client that posted the notice,
     * or one of other other channels.
     *
     * @return void
     */
    function showNoticeSource()
    {
        $ns = $this->notice->getSource();

        if ($ns) {
            // TRANS: A possible notice source (web interface).
            $source_name = (empty($ns->name)) ? ($ns->code ? _($ns->code) : _m('SOURCE','web')) : _($ns->name);
            $this->out->text(' ');
            $this->out->elementStart('span', 'source');
            // @todo FIXME: probably i18n issue. If "from" is followed by text, that should be a parameter to "from" (from %s).
            // TRANS: Followed by notice source.
            $this->out->text(_('from'));
            $this->out->text(' ');

            $name  = $source_name;
            $url   = $ns->url;
            $title = null;

            if (Event::handle('StartNoticeSourceLink', array($this->notice, &$name, &$url, &$title))) {
                $name = $source_name;
                $url  = $ns->url;
            }
            Event::handle('EndNoticeSourceLink', array($this->notice, &$name, &$url, &$title));

            // if $ns->name and $ns->url are populated we have
            // configured a source attr somewhere
            if (!empty($name) && !empty($url)) {
                $this->out->elementStart('span', 'device');

                $attrs = array(
                    'href' => $url,
                    'rel' => 'external'
                );

                if (!empty($title)) {
                    $attrs['title'] = $title;
                }

                $this->out->element('a', $attrs, $name);
                $this->out->elementEnd('span');
            } else {
                $this->out->element('span', 'device', $name);
            }

            $this->out->elementEnd('span');
        }
    }

    /**
     * show link to notice this notice is a reply to
     *
     * If this notice is a reply, show a link to the notice it is replying to. The
     * heavy lifting for figuring out replies happens at save time.
     *
     * @return void
     */
    function showContext()
    {
        if ($this->notice->hasConversation()) {
            $conv = Conversation::getKV(
                'id',
                $this->notice->conversation
            );
            $convurl = $conv->uri;
            if (!empty($convurl)) {
                $this->out->text(' ');
                $this->out->element(
                    'a',
                    array(
                    'href' => $convurl.'#notice-'.$this->notice->id,
                    'class' => 'response'),
                    // TRANS: Addition in notice list item if notice is part of a conversation.
                    _('in context')
                );
            } else {
                $msg = sprintf(
                    "Couldn't find Conversation ID %d to make 'in context'"
                    . "link for Notice ID %d",
                    $this->notice->conversation,
                    $this->notice->id
                );
                common_log(LOG_WARNING, $msg);
            }
        }
    }

    /**
     * show a link to the author of repeat
     *
     * @return void
     */
    function showRepeat()
    {
        if (!empty($this->repeat)) {

            $repeater = Profile::getKV('id', $this->repeat->profile_id);

            $attrs = array('href' => $repeater->profileurl,
                           'class' => 'url');

            if (!empty($repeater->fullname)) {
                $attrs['title'] = $repeater->fullname . ' (' . $repeater->nickname . ')';
            }

            $this->out->elementStart('span', 'repeat vcard');

            // TRANS: Addition in notice list item if notice was repeated. Followed by a span with a nickname.
            $this->out->raw(_('Repeated by'));
            $this->out->raw(' ');

            $this->out->elementStart('a', $attrs);
            $this->out->element('span', 'fn nickname', $repeater->nickname);
            $this->out->elementEnd('a');

            $this->out->elementEnd('span');
        }
    }

    /**
     * show a link to reply to the current notice
     *
     * Should either do the reply in the current notice form (if available), or
     * link out to the notice-posting form. A little flakey, doesn't always work.
     *
     * @return void
     */
    function showReplyLink()
    {
        if (common_logged_in()) {
            $this->out->text(' ');
            $reply_url = common_local_url('newnotice',
                                          array('replyto' => $this->profile->nickname, 'inreplyto' => $this->notice->id));
            $this->out->elementStart('a', array('href' => $reply_url,
                                                'class' => 'notice_reply',
                                                // TRANS: Link title in notice list item to reply to a notice.
                                                'title' => _('Reply to this notice.')));
            // TRANS: Link text in notice list item to reply to a notice.
            $this->out->text(_('Reply'));
            $this->out->text(' ');
            $this->out->element('span', 'notice_id', $this->notice->id);
            $this->out->elementEnd('a');
        }
    }

    /**
     * if the user is the author, let them delete the notice
     *
     * @return void
     */
    function showDeleteLink()
    {
        $user = common_current_user();

        $todel = (empty($this->repeat)) ? $this->notice : $this->repeat;

        if (!empty($user) &&
            ($todel->profile_id == $user->id || $user->hasRight(Right::DELETEOTHERSNOTICE))) {
            $this->out->text(' ');
            $deleteurl = common_local_url('deletenotice',
                                          array('notice' => $todel->id));
            $this->out->element('a', array('href' => $deleteurl,
                                           'class' => 'notice_delete',
                                           // TRANS: Link title in notice list item to delete a notice.
                                           'title' => _('Delete this notice from the timeline.')),
                                           // TRANS: Link text in notice list item to delete a notice.
                                           _('Delete'));
        }
    }

    /**
     * show the form to repeat a notice
     *
     * @return void
     */
    function showRepeatForm()
    {
        if ($this->notice->scope == Notice::PUBLIC_SCOPE ||
            $this->notice->scope == Notice::SITE_SCOPE) {
            $user = common_current_user();
            if (!empty($user) &&
                $user->id != $this->notice->profile_id) {
                $this->out->text(' ');
                $profile = $user->getProfile();
                if ($profile->hasRepeated($this->notice->id)) {
                    $this->out->element('span', array('class' => 'repeated',
                                                      // TRANS: Title for repeat form status in notice list when a notice has been repeated.
                                                      'title' => _('Notice repeated.')),
                                        // TRANS: Repeat form status in notice list when a notice has been repeated.
                                        _('Repeated'));
                } else {
                    $rf = new RepeatForm($this->out, $this->notice);
                    $rf->show();
                }
            }
        }
    }

    /**
     * finish the notice
     *
     * Close the last elements in the notice list item
     *
     * @return void
     */
    function showEnd()
    {
        if (Event::handle('StartCloseNoticeListItemElement', array($this))) {
            $this->out->elementEnd('li');
            Event::handle('EndCloseNoticeListItemElement', array($this));
        }
    }

    /**
     * Get the notice in question
     *
     * For hooks, etc., this may be useful
     *
     * @return Notice The notice we're showing
     */

    function getNotice()
    {
        return $this->notice;
    }
}
