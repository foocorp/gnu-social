<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a list of profiles
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
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ProfileListItem extends Widget
{
    /** Current profile. */
    var $profile = null;
    /** Action object using us. */
    var $action = null;

    function __construct($profile, $action)
    {
        parent::__construct($action);

        $this->profile = $profile;
        $this->action  = $action;
    }

    function show()
    {
        if (Event::handle('StartProfileListItem', array($this))) {
            $this->startItem();
            if (Event::handle('StartProfileListItemProfile', array($this))) {
                $this->showProfile();
                Event::handle('EndProfileListItemProfile', array($this));
            }
            if (Event::handle('StartProfileListItemActions', array($this))) {
                $this->showActions();
                Event::handle('EndProfileListItemActions', array($this));
            }
            $this->endItem();
            Event::handle('EndProfileListItem', array($this));
        }
    }

    function startItem()
    {
        $this->out->elementStart('li', array('class' => 'profile',
                                             'id' => 'profile-' . $this->profile->id));
    }

    function showProfile()
    {
        $this->startProfile();
        if (Event::handle('StartProfileListItemProfileElements', array($this))) {
            if (Event::handle('StartProfileListItemAvatar', array($this))) {
                $aAttrs = $this->linkAttributes();
                $this->out->elementStart('a', $aAttrs);
                $this->showAvatar($this->profile);
                $this->out->elementEnd('a');
                Event::handle('EndProfileListItemAvatar', array($this));
            }
            if (Event::handle('StartProfileListItemNickname', array($this))) {
                $this->showNickname();
                Event::handle('EndProfileListItemNickname', array($this));
            }
            if (Event::handle('StartProfileListItemFullName', array($this))) {
                $this->showFullName();
                Event::handle('EndProfileListItemFullName', array($this));
            }
            if (Event::handle('StartProfileListItemLocation', array($this))) {
                $this->showLocation();
                Event::handle('EndProfileListItemLocation', array($this));
            }
            if (Event::handle('StartProfileListItemHomepage', array($this))) {
                $this->showHomepage();
                Event::handle('EndProfileListItemHomepage', array($this));
            }
            if (Event::handle('StartProfileListItemBio', array($this))) {
                $this->showBio();
                Event::handle('EndProfileListItemBio', array($this));
            }
            if (Event::handle('StartProfileListItemTags', array($this))) {
                $this->showTags();
                Event::handle('EndProfileListItemTags', array($this));
            }
            Event::handle('EndProfileListItemProfileElements', array($this));
        }
        $this->endProfile();
    }

    function startProfile()
    {
        $this->out->elementStart('div', 'entity_profile h-card');
    }

    function showNickname()
    {
        $this->out->element('a', array('href'=>$this->profile->getUrl(),
                                       'class'=>'p-nickname'),
                            $this->profile->getNickname());
    }

    function showFullName()
    {
        if (!empty($this->profile->fullname)) {
            $this->out->element('span', 'p-name', $this->profile->fullname);
        }
    }

    function showLocation()
    {
        if (!empty($this->profile->location)) {
            $this->out->element('span', 'label p-locality', $this->profile->location);
        }
    }

    function showHomepage()
    {
        if (!empty($this->profile->homepage)) {
            $this->out->text(' ');
            $aAttrs = $this->homepageAttributes();
            $this->out->elementStart('a', $aAttrs);
            $this->out->raw($this->highlight($this->profile->homepage));
            $this->out->elementEnd('a');
        }
    }

    function showBio()
    {
        if (!empty($this->profile->bio)) {
            $this->out->elementStart('p', 'note');
            $this->out->raw($this->highlight($this->profile->bio));
            $this->out->elementEnd('p');
        }
    }

    function showTags()
    {
        $user = common_current_user();
        if (!empty($user)) {
            if ($user->id == $this->profile->id) {
                $tags = new SelftagsWidget($this->out, $user, $this->profile);
                $tags->show();
            } else if ($user->getProfile()->canTag($this->profile)) {
                $tags = new PeopletagsWidget($this->out, $user, $this->profile);
                $tags->show();
            }
        }
    }

    function endProfile()
    {
        $this->out->elementEnd('div');
    }

    function showActions()
    {
        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->showSubscribeButton();
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    function startActions()
    {
        $this->out->elementStart('div', 'entity_actions');
        $this->out->elementStart('ul');
    }

    function showSubscribeButton()
    {
        // Is this a logged-in user, looking at someone else's
        // profile?

        $user = common_current_user();

        if (!empty($user) && $this->profile->id != $user->id) {
            $this->out->elementStart('li', 'entity_subscribe');
            if ($user->isSubscribed($this->profile)) {
                $usf = new UnsubscribeForm($this->out, $this->profile);
                $usf->show();
            } else {
                if (Event::handle('StartShowProfileListSubscribeButton', array($this))) {
                    $sf = new SubscribeForm($this->out, $this->profile);
                    $sf->show();
                    Event::handle('EndShowProfileListSubscribeButton', array($this));
                }
            }
            $this->out->elementEnd('li');
        }
    }

    function endActions()
    {
        $this->out->elementEnd('ul');
        $this->out->elementEnd('div');
    }

    function endItem()
    {
        $this->out->elementEnd('li');
    }

    function highlight($text)
    {
        return htmlspecialchars($text);
    }

    function linkAttributes()
    {
        return array('href' => $this->profile->profileurl,
                     'class' => 'u-url',
                     'rel' => 'contact');
    }

    function homepageAttributes()
    {
        return array('href' => $this->profile->homepage,
                     'class' => 'u-url');
    }
}
