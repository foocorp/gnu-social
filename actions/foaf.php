<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

define('LISTENER', 1);
define('LISTENEE', -1);
define('BOTH', 0);

// @todo XXX: Documentation missing.
class FoafAction extends Action
{
    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');

        if (empty($nickname_arg)) {
            // TRANS: Client error displayed when requesting Friends of a Friend feed without providing a user nickname.
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $this->nickname) {
            common_redirect(common_local_url('foaf',
                                             array('nickname' => $this->nickname)),
                            301);
            return false;
        }

        $this->user = User::getKV('nickname', $this->nickname);

        if (!$this->user) {
            // TRANS: Client error displayed when requesting Friends of a Friend feed for an object that is not a user.
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_('User has no profile.'), 500);
            return false;
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        header('Content-Type: application/rdf+xml');

        $this->startXML();
        $this->elementStart('rdf:RDF', array('xmlns:rdf' =>
                                              'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                                              'xmlns:rdfs' =>
                                              'http://www.w3.org/2000/01/rdf-schema#',
                                              'xmlns:geo' =>
                                              'http://www.w3.org/2003/01/geo/wgs84_pos#',
                                              'xmlns:bio' =>
                                              'http://purl.org/vocab/bio/0.1/',
                                              'xmlns:sioc' =>
                                              'http://rdfs.org/sioc/ns#',
                                              'xmlns' => 'http://xmlns.com/foaf/0.1/'));

        // This is the document about the user

        $this->showPpd('', $this->user->uri);

        // Would be nice to tell if they were a Person or not (e.g. a #person usertag?)
        $this->elementStart('Agent', array('rdf:about' =>
                                             $this->user->uri));
        if ($this->user->email) {
            $this->element('mbox_sha1sum', null, sha1('mailto:' . $this->user->email));
        }
        if ($this->profile->fullname) {
            $this->element('name', null, $this->profile->fullname);
        }
        if ($this->profile->homepage) {
            $this->element('homepage', array('rdf:resource' => $this->profile->homepage));
        }
        if ($this->profile->profileurl) {
            $this->element('weblog', array('rdf:resource' => $this->profile->profileurl));
        }
        if ($this->profile->bio) {
            $this->element('bio:olb', null, $this->profile->bio);
        }

        $location = $this->profile->getLocation();
        if ($location) {
            $attr = array();
            if ($location->getRdfURL()) {
                $attr['rdf:about'] = $location->getRdfURL();
            }
            $location_name = $location->getName();

            $this->elementStart('based_near');
            $this->elementStart('geo:SpatialThing', $attr);
            if ($location_name) {
                $this->element('name', null, $location_name);
            }
            if ($location->lat) {
                $this->element('geo:lat', null, $location->lat);
            }
            if ($location->lon) {
                $this->element('geo:long', null, $location->lon);
            }
            if ($location->getURL()) {
                $this->element('page', array('rdf:resource'=>$location->getURL()));
            }
            $this->elementEnd('geo:SpatialThing');
            $this->elementEnd('based_near');
        }

        $avatar = $this->profile->getOriginalAvatar();
        if ($avatar) {
            $this->elementStart('img');
            $this->elementStart('Image', array('rdf:about' => $avatar->url));
            foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
                $scaled = $this->profile->getAvatar($size);
                if (!$scaled->original) { // sometimes the original has one of our scaled sizes
                    $this->elementStart('thumbnail');
                    $this->element('Image', array('rdf:about' => $scaled->url));
                    $this->elementEnd('thumbnail');
                }
            }
            $this->elementEnd('Image');
            $this->elementEnd('img');
        }

        $person = $this->showMicrobloggingAccount($this->profile,
                                     common_root_url(), $this->user->uri,
                                     /*$fetchSubscriptions*/true,
                                     /*$isSubscriber*/false);

        // Get people who subscribe to user

        $sub = new Subscription();
        $sub->subscribed = $this->profile->id;
        $sub->whereAdd('subscriber != subscribed');

        if ($sub->find()) {
            while ($sub->fetch()) {
                $profile = Profile::getKV('id', $sub->subscriber);
                if (empty($profile)) {
                    common_debug('Got a bad subscription: '.print_r($sub,true));
                    continue;
                }
                $other_uri = $profile->getUri();
                if (array_key_exists($other_uri, $person)) {
                    $person[$other_uri][0] = BOTH;
                } else {
                    $person[$other_uri] = array(LISTENER,
                                                $profile->id,
                                                $profile->nickname,
                                                $profile->isLocal() ? 'local' : 'remote');
                }
                unset($profile);
            }
        }

        unset($sub);

        foreach ($person as $uri => $p) {
            list($type, $id, $nickname, $local) = $p;
            if ($type == BOTH) {
                $this->element('knows', array('rdf:resource' => $uri));
            }
        }

        $this->elementEnd('Agent');


        foreach ($person as $uri => $p) {
            $foaf_url = null;
            list($type, $id, $nickname, $local) = $p;
            if ($local == 'local') {
                $foaf_url = common_local_url('foaf', array('nickname' => $nickname));
            }
            $profile = Profile::getKV($id);
            $this->elementStart('Agent', array('rdf:about' => $uri));
            if ($type == BOTH) {
                $this->element('knows', array('rdf:resource' => $this->user->uri));
            }
            $this->showMicrobloggingAccount($profile,
                                   ($local == 'local') ? common_root_url() : null,
                                   $uri,
                                   /*$fetchSubscriptions*/false,
                                   /*$isSubscriber*/($type == LISTENER || $type == BOTH));
            if ($foaf_url) {
                $this->element('rdfs:seeAlso', array('rdf:resource' => $foaf_url));
            }
            $this->elementEnd('Agent');
            if ($foaf_url) {
                $this->showPpd($foaf_url, $uri);
            }
            $profile->free();
            $profile = null;
            unset($profile);
        }

        $this->elementEnd('rdf:RDF');
        $this->endXML();
    }

    function showPpd($foaf_url, $person_uri)
    {
        $this->elementStart('PersonalProfileDocument', array('rdf:about' => $foaf_url));
        $this->element('maker', array('rdf:resource' => $person_uri));
        $this->element('primaryTopic', array('rdf:resource' => $person_uri));
        $this->elementEnd('PersonalProfileDocument');
    }

    /**
     * Output FOAF <account> bit for the given profile.
     *
     * @param Profile $profile
     * @param mixed $service Root URL of this StatusNet instance for a local
     *                       user, otherwise null.
     * @param mixed $useruri URI string for the referenced profile..
     * @param boolean $fetchSubscriptions Should we load and list all their subscriptions?
     * @param boolean $isSubscriber if not fetching subs, we can still mark the user as following the current page.
     *
     * @return array if $fetchSubscribers is set, return a list of info on those
     *               subscriptions.
     */
    function showMicrobloggingAccount($profile, $service=null, $useruri=null, $fetchSubscriptions=false, $isSubscriber=false)
    {
        $attr = array();
        if ($useruri) {
            $attr['rdf:about'] = $useruri . '#acct';
        }

        // Their account
        $this->elementStart('account');
        $this->elementStart('OnlineAccount', $attr);
        if ($service) {
            $this->element('accountServiceHomepage', array('rdf:resource' =>
                                                           $service));
        }
        $this->element('accountName', null, $profile->nickname);
        $this->element('accountProfilePage', array('rdf:resource' => $profile->profileurl));
        if ($useruri) {
            $this->element('sioc:account_of', array('rdf:resource'=>$useruri));
        }

        $person = array();

        if ($fetchSubscriptions) {
            // Get people user is subscribed to
            $sub = new Subscription();
            $sub->subscriber = $profile->id;
            $sub->whereAdd('subscriber != subscribed');

            if ($sub->find()) {
                while ($sub->fetch()) {
                    $profile = Profile::getKV('id', $sub->subscribed);
                    if (empty($profile)) {
                        common_debug('Got a bad subscription: '.print_r($sub,true));
                        continue;
                    }
                    $other_uri = $profile->getUri();
                    $this->element('sioc:follows', array('rdf:resource' => $other_uri.'#acct'));
                    $person[$other_uri] = array(LISTENEE,
                                                $profile->id,
                                                $profile->nickname,
                                                $profile->isLocal() ? 'local' : 'remote');
                    unset($profile);
                }
            }

            unset($sub);
        } else if ($isSubscriber) {
            // Just declare that they follow the user whose FOAF we're showing.
            $this->element('sioc:follows', array('rdf:resource' => $this->user->uri . '#acct'));
        }

        $this->elementEnd('OnlineAccount');
        $this->elementEnd('account');

        return $person;
    }
}
