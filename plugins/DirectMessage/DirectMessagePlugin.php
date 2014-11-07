<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2014, Free Software Foundation, Inc.
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * @maintainer  Mikael Nordfeldth <mmn@hethane.se>
 */
class DirectMessagePlugin extends Plugin
{
    public function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('message', Message::schemaDef());
        return true;
    }

    public function onRouterInitialized(URLMapper $m)
    {
        // web front-end actions
        $m->connect('message/new', array('action' => 'newmessage'));
        $m->connect('message/new?to=:to', array('action' => 'newmessage'), array('to' => Nickname::DISPLAY_FMT));
        $m->connect('message/:message',
                    array('action' => 'showmessage'),
                    array('message' => '[0-9]+'));

        // direct messages
        $m->connect('api/direct_messages.:format',
                    array('action' => 'ApiDirectMessage',
                          'format' => '(xml|json|rss|atom)'));
        $m->connect('api/direct_messages/sent.:format',
                    array('action' => 'ApiDirectMessage',
                          'format' => '(xml|json|rss|atom)',
                          'sent' => true));
        $m->connect('api/direct_messages/new.:format',
                    array('action' => 'ApiDirectMessageNew',
                          'format' => '(xml|json)'));

        return true;
    }

    public function onEndPersonalGroupNav(Menu $menu, Profile $target, Profile $scoped=null)
    {
        if ($scoped instanceof Profile && $scoped->id == $target->id
                && !common_config('singleuser', 'enabled')) {

            $menu->out->menuItem(common_local_url('inbox', array('nickname' =>
                                                                 $target->getNickname())),
                                 // TRANS: Menu item in personal group navigation menu.
                                 _m('MENU','Messages'),
                                 // TRANS: Menu item title in personal group navigation menu.
                                 _('Your incoming messages'),
                                 $scoped->id === $target->id && $menu->actionName =='inbox');
        }
    }

    public function onEndProfilePageActionsElements(HTMLOutputter $out, Profile $profile)
    {
        $scoped = Profile::current();
        if (!$scoped instanceof Profile) {
            return true;
        }

        if ($profile->isLocal() && $scoped->mutuallySubscribed($profile)) {
            $out->elementStart('li', 'entity_send-a-message');
            $out->element('a', array('href' => common_local_url('newmessage', array('to' => $profile->id)),
                                     // TRANS: Link title for link on user profile.
                                     'title' => _('Send a direct message to this user.')),
                                // TRANS: Link text for link on user profile.
                                _m('BUTTON','Message'));
            $out->elementEnd('li');
        }
        return true;
    }

    public function onProfileDeleteRelated(Profile $profile, &$related)
    {
        $msg = new Message();
        $msg->from_profile = $profile->id;
        $msg->delete();

        $msg = new Message();
        $msg->to_profile = $profile->id;
        $msg->delete();
        return true;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Direct Message',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://gnu.io/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Direct Message to other local users (broken out of core).'));

        return true;
    }
}
