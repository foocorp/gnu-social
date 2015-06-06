<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2015, Free Software Foundation, Inc.
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
 * Diaspora federation protocol plugin for GNU Social
 *
 * Depends on:
 *  - OStatus plugin
 *  - WebFinger plugin
 *
 * @package ProtocolDiasporaPlugin
 * @maintainer Mikael Nordfeldth <mmn@hethane.se>
 */

class DiasporaPlugin extends Plugin
{
    const REL_SEED_LOCATION = 'http://joindiaspora.com/seed_location';
    const REL_GUID          = 'http://joindiaspora.com/guid';
    const REL_PUBLIC_KEY    = 'diaspora-public-key';

    public function onEndAttachPubkeyToUserXRD(Magicsig $magicsig, XML_XRD $xrd, Profile $target)
    {
        $xrd->links[] = new XML_XRD_Element_Link(self::REL_PUBLIC_KEY,
                                    base64_encode($magicsig->exportPublicKey()));
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Diaspora',
                            'version' => '0.1',
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://gnu.io/social',
                            // TRANS: Plugin description.
                            'rawdescription' => _m('Follow people across social networks that implement '.
                               'the <a href="https://diasporafoundation.org/">Diaspora</a> federation protocol.'));

        return true;
    }
}
