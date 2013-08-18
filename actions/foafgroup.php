<?php
/*
 * StatusNet the distributed open-source microblogging tool
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
 *
 * @category  Mail
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Toby Inkster <mail@tobyinkster.co.uk>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

// @todo XXX: Documentation missing.
class FoafGroupAction extends Action
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
            // TRANS: Client error displayed when requesting Friends of a Friend feed without providing a group nickname.
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        $this->nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $this->nickname) {
            common_redirect(common_local_url('foafgroup',
                                             array('nickname' => $this->nickname)),
                            301);
            return false;
        }

        $local = Local_group::getKV('nickname', $this->nickname);

        if (!$local) {
            // TRANS: Client error displayed when requesting Friends of a Friend feed for a non-local group.
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        $this->group = User_group::getKV('id', $local->group_id);

        if (!$this->group) {
            // TRANS: Client error displayed when requesting Friends of a Friend feed for a nickname that is not a group.
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        common_set_returnto($this->selfUrl());

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        header('Content-Type: application/rdf+xml');

        $this->startXML();
        $this->elementStart('rdf:RDF', array('xmlns:rdf' =>
                                              'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                                              'xmlns:dcterms' =>
                                              'http://purl.org/dc/terms/',
                                              'xmlns:sioc' =>
                                              'http://rdfs.org/sioc/ns#',
                                              'xmlns:foaf' =>
                                              'http://xmlns.com/foaf/0.1/',
                                              'xmlns:statusnet' =>
                                              'http://status.net/ont/',
                                              'xmlns' => 'http://xmlns.com/foaf/0.1/'));

        $this->showPpd(common_local_url('foafgroup', array('nickname' => $this->nickname)), $this->group->permalink());

        $this->elementStart('Group', array('rdf:about' =>
                                             $this->group->permalink()));
        if ($this->group->fullname) {
            $this->element('name', null, $this->group->fullname);
        }
        if ($this->group->description) {
            $this->element('dcterms:description', null, $this->group->description);
        }
        if ($this->group->nickname) {
            $this->element('dcterms:identifier', null, $this->group->nickname);
            $this->element('nick', null, $this->group->nickname);
        }
        foreach ($this->group->getAliases() as $alias) {
            $this->element('nick', null, $alias);
        }
        if ($this->group->homeUrl()) {
            $this->element('weblog', array('rdf:resource' => $this->group->homeUrl()));
        }
        if ($this->group->homepage) {
            $this->element('page', array('rdf:resource' => $this->group->homepage));
        }
        if ($this->group->homepage_logo) {
            $this->element('depiction', array('rdf:resource' => $this->group->homepage_logo));
        }

        $members = $this->group->getMembers();
        $member_details = array();
        while ($members->fetch()) {
            $member_uri = common_local_url('userbyid', array('id'=>$members->id));
            $member_details[$member_uri] = array(
                                        'nickname' => $members->nickname,
                                        'is_admin' => false,
                                        );
            $this->element('member', array('rdf:resource' => $member_uri));
        }

        $admins = $this->group->getAdmins();
        while ($admins->fetch()) {
            $admin_uri = common_local_url('userbyid', array('id'=>$admins->id));
            $member_details[$admin_uri]['is_admin'] = true;
            $this->element('statusnet:groupAdmin', array('rdf:resource' => $admin_uri));
        }

        $this->elementEnd('Group');

        ksort($member_details);
        foreach ($member_details as $uri => $details) {
            if ($details['is_admin'])
            {
                $this->elementStart('Agent', array('rdf:about' => $uri));
                $this->element('nick', null, $details['nickname']);
                $this->elementStart('account');
                $this->elementStart('sioc:User', array('rdf:about'=>$uri.'#acct'));
                $this->elementStart('sioc:has_function');
                $this->elementStart('statusnet:GroupAdminRole');
                $this->element('sioc:scope', array('rdf:resource' => $this->group->permalink()));
                $this->elementEnd('statusnet:GroupAdminRole');
                $this->elementEnd('sioc:has_function');
                $this->elementEnd('sioc:User');
                $this->elementEnd('account');
                $this->elementEnd('Agent');
            }
            else
            {
                $this->element('Agent', array(
                                        'foaf:nick' => $details['nickname'],
                                        'rdf:about' => $uri,
                                        ));
            }
        }

        $this->elementEnd('rdf:RDF');
        $this->endXML();
    }

    function showPpd($foaf_url, $person_uri)
    {
        $this->elementStart('Document', array('rdf:about' => $foaf_url));
        $this->element('primaryTopic', array('rdf:resource' => $person_uri));
        $this->elementEnd('Document');
    }

}
