<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a list of peopletags
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
 * @category  Public
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';

define('PEOPLETAGS_PER_PAGE', 20);

/**
 * Widget to show a list of peopletags
 *
 * @category Public
 * @package  StatusNet
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class PeopletagList extends Widget
{
    /** Current peopletag, peopletag query. */
    var $peopletag = null;
    /** current user **/
    var $user = null;

    function __construct($peopletag, $action=null)
    {
        parent::__construct($action);

        $this->peopletag = $peopletag;

        if (!empty($owner)) {
            $this->user = $owner;
        } else {
            $this->user = common_current_user();
        }
    }

    function show()
    {
        $this->out->elementStart('ul', 'peopletags xoxo hfeed');

        $cnt = 0;

        while ($this->peopletag->fetch()) {
            $cnt++;
            if($cnt > PEOPLETAGS_PER_PAGE) {
                break;
            }
            $this->showPeopletag();
        }

        $this->out->elementEnd('ul');

        return $cnt;
    }

    function showPeopletag()
    {
        $ptag = new PeopletagListItem($this->peopletag, $this->user, $this->out);
        $ptag->show();
    }
}

class PeopletagListItem extends Widget
{
    var $peopletag = null;
    var $current = null;
    var $profile = null;

    /**
     * constructor
     *
     * Also initializes the owner attribute.
     *
     * @param Notice $notice The notice we'll display
     */
    function __construct($peopletag, $current, $out=null)
    {
        parent::__construct($out);
        $this->peopletag  = $peopletag;
        $this->current = $current;
        $this->profile = Profile::getKV('id', $this->peopletag->tagger);
    }

    /**
     * recipe function for displaying a single peopletag.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */
    function url()
    {
        return $this->peopletag->homeUrl();
    }

    function show()
    {
        if (empty($this->peopletag)) {
            common_log(LOG_WARNING, "Trying to show missing peopletag; skipping.");
            return;
        }

        if (Event::handle('StartShowPeopletagItem', array($this))) {
            $this->showStart();
            $this->showPeopletag();
            $this->showStats();
            $this->showEnd();
            Event::handle('EndShowPeopletagItem', array($this));
        }
    }

    function showStart()
    {
        $mode = ($this->peopletag->private) ? 'private' : 'public';
        $this->out->elementStart('li', array('class' => 'hentry peopletag mode-' . $mode,
                                             'id' => 'peopletag-' . $this->peopletag->id));
    }

    function showEnd()
    {
        $this->out->elementEnd('li');
    }

    function showPeopletag()
    {
        $this->showCreator();
        $this->showTag();
        $this->showPrivacy();
        $this->showUpdated();
        $this->showActions();
        $this->showDescription();
    }

    function showStats()
    {
        $this->out->elementStart('div', 'entry-summary entity_statistics');
        $this->out->elementStart('span', 'tagged-count');
        $this->out->element('a',
            array('href' => common_local_url('peopletagged',
                                              array('tagger' => $this->profile->nickname,
                                                    'tag' => $this->peopletag->tag))),
            // TRANS: Link description for link to list of users tagged with a tag (so part of a list).
            _('Listed'));
        $this->out->raw($this->peopletag->taggedCount());
        $this->out->elementEnd('span');

        $this->out->elementStart('span', 'subscriber-count');
        $this->out->element('a',
            array('href' => common_local_url('peopletagsubscribers',
                                              array('tagger' => $this->profile->nickname,
                                                    'tag' => $this->peopletag->tag))),
            // TRANS: Link description for link to list of users subscribed to a tag.
            _('Subscribers'));
        $this->out->raw($this->peopletag->subscriberCount());
        $this->out->elementEnd('span');
        $this->out->elementEnd('div');
    }

    function showOwnerOptions()
    {
        $this->out->elementStart('li', 'entity_edit');
        $this->out->element('a', array('href' =>
                    common_local_url('editpeopletag', array('tagger' => $this->profile->nickname,
                                                    'tag' => $this->peopletag->tag)),
                                  // TRANS: Title for link to edit list settings.
                                  'title' => _('Edit list settings.')),
                       // TRANS: Text for link to edit list settings.
                       _('Edit'));
        $this->out->elementEnd('li');
    }

    function showSubscribeForm()
    {
        $this->out->elementStart('li');

        if (Event::handle('StartSubscribePeopletagForm', array($this->out, $this->peopletag))) {
            if ($this->current) {
                if ($this->peopletag->hasSubscriber($this->current->id)) {
                    $form = new UnsubscribePeopletagForm($this->out, $this->peopletag);
                    $form->show();
                } else {
                    $form = new SubscribePeopletagForm($this->out, $this->peopletag);
                    $form->show();
                }
            }
            Event::handle('EndSubscribePeopletagForm', array($this->out, $this->peopletag));
        }

        $this->out->elementEnd('li');
    }

    function showCreator()
    {
        $this->out->elementStart('span', 'author vcard');
        $attrs = array();
        $attrs['href'] = $this->profile->profileurl;
        $attrs['class'] = 'url';
        $attrs['rel'] = 'contact';

        if (!empty($this->profile->fullname)) {
            $attrs['title'] = $this->profile->fullname . ' (' . $this->profile->nickname . ')';
        }
        $this->out->elementStart('a', $attrs);
        $this->showAvatar();
        $this->out->text(' ');
        $this->out->element('span', 'nickname fn',
                        htmlspecialchars($this->profile->nickname));

        $this->out->elementEnd('a');
        $this->out->elementEnd('span');
    }

    function showUpdated()
    {
        if (!empty($this->peopletag->modified)) {
            $this->out->element('abbr',
                array('title' => common_date_w3dtf($this->peopletag->modified),
                      'class' => 'updated'),
                common_date_string($this->peopletag->modified));
        }
    }

    function showPrivacy()
    {
        if ($this->peopletag->private) {
            $this->out->elementStart('a',
                array('href' => common_local_url('peopletagsbyuser',
                    array('nickname' => $this->profile->nickname, 'private' => 1))));
            // TRANS: Privacy mode text in list list item for private list.
            $this->out->element('span', 'privacy_mode', _m('MODE','Private'));
            $this->out->elementEnd('a');
        }
    }

    function showTag()
    {
        $this->out->elementStart('span', 'entry-title tag');
        $this->out->element('a',
            array('rel'   => 'bookmark',
                  'href'  => $this->url()),
            htmlspecialchars($this->peopletag->tag));
        $this->out->elementEnd('span');
    }

    /**
     * show the avatar of the peopletag's creator
     *
     * This will use the default avatar if no avatar is assigned for the author.
     * It makes a link to the author's profile.
     *
     * @return void
     */

    function showAvatar($size=AVATAR_STREAM_SIZE)
    {
        $avatarUrl = $this->profile->avatarUrl($size);

        $this->out->element('img', array('src' => $avatarUrl,
                                         'class' => 'avatar photo',
                                         'width' => $size,
                                         'height' => $size,
                                         'alt' =>
                                         ($this->profile->fullname) ?
                                         $this->profile->fullname :
                                         $this->profile->nickname));
    }

    function showActions()
    {
        $this->out->elementStart('div', 'entity_actions');
        $this->out->elementStart('ul');

        if (!$this->peopletag->private) {
            $this->showSubscribeForm();
        }

        if (!empty($this->current) && $this->profile->id == $this->current->id) {
            $this->showOwnerOptions();
        }
        $this->out->elementEnd('ul');
        $this->out->elementEnd('div');
    }

    function showDescription()
    {
        $this->out->element('div', 'entry-content description',
                            $this->peopletag->description);
    }
}
