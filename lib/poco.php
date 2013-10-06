<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * An activity
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
 * @category  Feed
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PoCo
{
    const NS = 'http://portablecontacts.net/spec/1.0';

    const USERNAME     = 'preferredUsername';
    const DISPLAYNAME  = 'displayName';
    const NOTE         = 'note';

    public $preferredUsername;
    public $displayName;
    public $note;
    public $address;
    public $urls = array();

    function __construct($element = null)
    {
        if (empty($element)) {
            return;
        }

        $this->preferredUsername = ActivityUtils::childContent(
            $element,
            self::USERNAME,
            self::NS
        );

        $this->displayName = ActivityUtils::childContent(
            $element,
            self::DISPLAYNAME,
            self::NS
        );

        $this->note = ActivityUtils::childContent(
            $element,
            self::NOTE,
            self::NS
        );

        $this->address = $this->_getAddress($element);
        $this->urls = $this->_getURLs($element);
    }

    private function _getURLs($element)
    {
        $urlEls = $element->getElementsByTagnameNS(self::NS, PoCoURL::URLS);
        $urls = array();

        foreach ($urlEls as $urlEl) {

            $type = ActivityUtils::childContent(
                $urlEl,
                PoCoURL::TYPE,
                PoCo::NS
            );

            $value = ActivityUtils::childContent(
                $urlEl,
                PoCoURL::VALUE,
                PoCo::NS
            );

            $primary = ActivityUtils::childContent(
                $urlEl,
                PoCoURL::PRIMARY,
                PoCo::NS
            );

            $isPrimary = false;

            if (isset($primary) && $primary == 'true') {
                $isPrimary = true;
            }

            // @todo check to make sure a primary hasn't already been added

            array_push($urls, new PoCoURL($type, $value, $isPrimary));
        }
        return $urls;
    }

    private function _getAddress($element)
    {
        $addressEl = ActivityUtils::child(
            $element,
            PoCoAddress::ADDRESS,
            PoCo::NS
        );

        if (!empty($addressEl)) {
            $formatted = ActivityUtils::childContent(
                $addressEl,
                PoCoAddress::FORMATTED,
                self::NS
            );

            if (!empty($formatted)) {
                $address = new PoCoAddress();
                $address->formatted = $formatted;
                return $address;
            }
        }

        return null;
    }

    static function fromProfile(Profile $profile)
    {
        $poco = new PoCo();

        $poco->preferredUsername = $profile->nickname;
        $poco->displayName       = $profile->getBestName();

        $poco->note = $profile->bio;

        $paddy = new PoCoAddress();
        $paddy->formatted = $profile->location;
        $poco->address = $paddy;

        if (!empty($profile->homepage)) {
            array_push(
                $poco->urls,
                new PoCoURL(
                    'homepage',
                    $profile->homepage,
                    true
                )
            );
        }

        return $poco;
    }

    static function fromGroup(User_group $group)
    {
        $poco = new PoCo();

        $poco->preferredUsername = $group->nickname;
        $poco->displayName       = $group->getBestName();

        $poco->note = $group->description;

        $paddy = new PoCoAddress();
        $paddy->formatted = $group->location;
        $poco->address = $paddy;

        if (!empty($group->homepage)) {
            array_push(
                $poco->urls,
                new PoCoURL(
                    'homepage',
                    $group->homepage,
                    true
                )
            );
        }

        return $poco;
    }

    function getPrimaryURL()
    {
        foreach ($this->urls as $url) {
            if ($url->primary) {
                return $url;
            }
        }
    }

    function asString()
    {
        $xs = new XMLStringer(true);
        $this->outputTo($xs);
        return $xs->getString();
    }

    function outputTo($xo)
    {
        $xo->element(
            'poco:preferredUsername',
            null,
            $this->preferredUsername
        );

        $xo->element(
            'poco:displayName',
            null,
            $this->displayName
        );

        if (!empty($this->note)) {
            $xo->element('poco:note', null, common_xml_safe_str($this->note));
        }

        if (!empty($this->address)) {
            $this->address->outputTo($xo);
        }

        foreach ($this->urls as $url) {
            $url->outputTo($xo);
        }
    }

    /**
     * Output a Portable Contact as an array suitable for serializing
     * as JSON
     *
     * @return $array the PoCo array
     */

    function asArray()
    {
        $poco = array();

        $poco['preferredUsername'] = $this->preferredUsername;
        $poco['displayName']       = $this->displayName;

        if (!empty($this->note)) {
            $poco['note'] = $this->note;
        }

        if (!empty($this->address)) {
            $poco['addresses'] = $this->address->asArray();
        }

        if (!empty($this->urls)) {

            $urls = array();

            foreach ($this->urls as $url) {
                $urls[] = $url->asArray();
            }

            $poco['urls'] = $urls;
        }

        return $poco;
    }

}

