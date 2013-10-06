<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action for showing Twitter-like JSON search results
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
 * @category  Search
 * @package   GNUSocial
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2010 StatusNet, Inc.
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Action handler for Twitter-compatible API search
 *
 * @category Search
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      ApiAction
 */
class ApiSearchJSONAction extends ApiPrivateAuthAction
{
    var $query;
    var $lang;
    var $rpp;
    var $page;
    var $since_id;
    var $limit;
    var $geocode;

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean true if nothing goes wrong
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->query = $this->trimmed('q');
        $this->lang  = $this->trimmed('lang');
        $this->rpp   = $this->trimmed('rpp');

        if (!$this->rpp) {
            $this->rpp = 15;
        }

        if ($this->rpp > 100) {
            $this->rpp = 100;
        }

        $this->page = $this->trimmed('page');

        if (!$this->page) {
            $this->page = 1;
        }

        // TODO: Suppport max_id -- we need to tweak the backend
        // Search classes to support it.

        $this->since_id = $this->trimmed('since_id');
        $this->geocode  = $this->trimmed('geocode');

        return true;
    }

    /**
     * Handle a request
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showResults();
    }

    /**
     * Show search results
     *
     * @return void
     */
    function showResults()
    {
        $q = strtolower($this->query);

        // TODO: Support search operators like from: and to:, boolean, etc.

        if (preg_match('/^#([\pL\pN_\-\.]{1,64})$/ue', $q)) {
            $stream = new TagNoticeStream(substr($q, 1), $this->scoped);
        } else if ($this->isAnURL($q)) {
            $canon = File_redirection::_canonUrl($q);
            $file = File::getKV('url', $canon);
            if (!empty($file)) {
                $stream = new FileNoticeStream($file, $this->scoped);
            }
        } else {
            $stream = new SearchNoticeStream($q, $this->scoped);
        }

        if (empty($stream)) {
            // XXX: This is hackish, but need some simple way to say "There's no results"
            $notice = new ArrayWrapper(array());
        } else {
            $notice = $stream->getNotices(($this->page - 1) * $this->rpp, $this->rpp + 1);
        }

        // TODO: max_id, lang, geocode

        $results = new JSONSearchResultsList($notice, $q, $this->rpp, $this->page, $this->since_id);

        $this->initDocument('json');
        $results->show();
        $this->endDocument('json');
    }

    function isAnURL($q) {
        $regex = '#^'.
            '(?:^|[\s\<\>\(\)\[\]\{\}\\\'\\\";]+)(?![\@\!\#])'.
            '('.
            '(?:'.
            '(?:'. //Known protocols
            '(?:'.
            '(?:(?:https?|ftps?|mms|rtsp|gopher|news|nntp|telnet|wais|file|prospero|webcal|irc)://)'.
            '|'.
            '(?:(?:mailto|aim|tel|xmpp):)'.
            ')'.
            '(?:[\pN\pL\-\_\+\%\~]+(?::[\pN\pL\-\_\+\%\~]+)?\@)?'. //user:pass@
            '(?:'.
            '(?:'.
            '\[[\pN\pL\-\_\:\.]+(?<![\.\:])\]'. //[dns]
            ')|(?:'.
            '[\pN\pL\-\_\:\.]+(?<![\.\:])'. //dns
            ')'.
            ')'.
            ')'.
            '|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)'. //IPv4
            '|(?:'. //IPv6
            '\[?(?:(?:(?:[0-9A-Fa-f]{1,4}:){7}(?:(?:[0-9A-Fa-f]{1,4})|:))|(?:(?:[0-9A-Fa-f]{1,4}:){6}(?::|(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})|(?::[0-9A-Fa-f]{1,4})))|(?:(?:[0-9A-Fa-f]{1,4}:){5}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){4}(?::[0-9A-Fa-f]{1,4}){0,1}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){3}(?::[0-9A-Fa-f]{1,4}){0,2}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){2}(?::[0-9A-Fa-f]{1,4}){0,3}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:)(?::[0-9A-Fa-f]{1,4}){0,4}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?::(?::[0-9A-Fa-f]{1,4}){0,5}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})))\]?(?<!:)'.
            ')|(?:'. //DNS
            '(?:[\pN\pL\-\_\+\%\~]+(?:\:[\pN\pL\-\_\+\%\~]+)?\@)?'. //user:pass@
            '[\pN\pL\-\_]+(?:\.[\pN\pL\-\_]+)*\.'.
            //tld list from http://data.iana.org/TLD/tlds-alpha-by-domain.txt, also added local, loc, and onion
            '(?:AC|AD|AE|AERO|AF|AG|AI|AL|AM|AN|AO|AQ|AR|ARPA|AS|ASIA|AT|AU|AW|AX|AZ|BA|BB|BD|BE|BF|BG|BH|BI|BIZ|BJ|BM|BN|BO|BR|BS|BT|BV|BW|BY|BZ|CA|CAT|CC|CD|CF|CG|CH|CI|CK|CL|CM|CN|CO|COM|COOP|CR|CU|CV|CX|CY|CZ|DE|DJ|DK|DM|DO|DZ|EC|EDU|EE|EG|ER|ES|ET|EU|FI|FJ|FK|FM|FO|FR|GA|GB|GD|GE|GF|GG|GH|GI|GL|GM|GN|GOV|GP|GQ|GR|GS|GT|GU|GW|GY|HK|HM|HN|HR|HT|HU|ID|IE|IL|IM|IN|INFO|INT|IO|IQ|IR|IS|IT|JE|JM|JO|JOBS|JP|KE|KG|KH|KI|KM|KN|KP|KR|KW|KY|KZ|LA|LB|LC|LI|LK|LR|LS|LT|LU|LV|LY|MA|MC|MD|ME|MG|MH|MIL|MK|ML|MM|MN|MO|MOBI|MP|MQ|MR|MS|MT|MU|MUSEUM|MV|MW|MX|MY|MZ|NA|NAME|NC|NE|NET|NF|NG|NI|NL|NO|NP|NR|NU|NZ|OM|ORG|PA|PE|PF|PG|PH|PK|PL|PM|PN|PR|PRO|PS|PT|PW|PY|QA|RE|RO|RS|RU|RW|SA|SB|SC|SD|SE|SG|SH|SI|SJ|SK|SL|SM|SN|SO|SR|ST|SU|SV|SY|SZ|TC|TD|TEL|TF|TG|TH|TJ|TK|TL|TM|TN|TO|TP|TR|TRAVEL|TT|TV|TW|TZ|UA|UG|UK|US|UY|UZ|VA|VC|VE|VG|VI|VN|VU|WF|WS|XN--0ZWM56D|测试|XN--11B5BS3A9AJ6G|परीक्षा|XN--80AKHBYKNJ4F|испытание|XN--9T4B11YI5A|테스트|XN--DEBA0AD|טעסט|XN--G6W251D|測試|XN--HGBK6AJ7F53BBA|آزمایشی|XN--HLCJ6AYA9ESC7A|பரிட்சை|XN--JXALPDLP|δοκιμή|XN--KGBECHTV|إختبار|XN--ZCKZAH|テスト|YE|YT|YU|ZA|ZM|ZW|local|loc|onion)'.
            ')(?![\pN\pL\-\_])'.
            ')'.
            '(?:'.
            '(?:\:\d+)?'. //:port
            '(?:/[\pN\pL$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'@]*)?'. // /path
            '(?:\?[\pN\pL\$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'@\/]*)?'. // ?query string
            '(?:\#[\pN\pL$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'\@/\?\#]*)?'. // #fragment
            ')(?<![\?\.\,\#\,])'.
            ')'.
            '$#ixu';
        return preg_match($regex, $q);
    }

    /**
     * Do we need to write to the database?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }
}
