<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Dump of configuration variables
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
 * @category  API
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Gives a full dump of configuration variables for this instance
 * of GNU social, minus variables that may be security-sensitive (like
 * passwords).
 * URL: https://example.com/api/gnusocial/config.(xml|json)
 * Formats: xml, json
 *
 * @category API
 * @package  GNUsocial
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://www.gnu.org/software/social/
 */
class ApiGNUsocialConfigAction extends ApiAction
{
    var $keys = array(
        'site' => array('name', 'server', 'theme', 'path', 'logo', 'fancy', 'language',
                        'email', 'broughtby', 'broughtbyurl', 'timezone', 'closed',
                        'inviteonly', 'private', 'textlimit', 'ssl', 'sslserver'),
        'license' => array('type', 'owner', 'url', 'title', 'image'),
        'nickname' => array('featured'),
        'profile' => array('biolimit'),
        'group' => array('desclimit'),
        'notice' => array('contentlimit'),
        'throttle' => array('enabled', 'count', 'timespan'),
        'xmpp' => array('enabled', 'server', 'port', 'user'),
        'integration' => array('source'),
        'attachments' => array('uploads', 'file_quota'),
        'url' => array('maxurllength', 'maxnoticelength'),
    );

    protected function handle()
    {
        parent::handle();

        switch ($this->format) {
        case 'xml':
            $this->initDocument('xml');
            $this->elementStart('config');

            // XXX: check that all sections and settings are legal XML elements

            foreach ($this->keys as $section => $settings) {
                $this->elementStart($section);
                foreach ($settings as $setting) {
                    $value = $this->setting($section, $setting);
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    } else if ($value === false || $value == '0') {
                        $value = 'false';
                    } else if ($value === true || $value == '1') {
                        $value = 'true';
                    }

                    // return theme logo if there's no site specific one
                    if (empty($value)) {
                        if ($section == 'site' && $setting == 'logo') {
                            $value = Theme::path('logo.png');
                        }
                    }

                    $this->element($setting, null, $value);
                }
                $this->elementEnd($section);
            }
            $this->elementEnd('config');
            $this->endDocument('xml');
            break;
        case 'json':
            $result = array();
            foreach ($this->keys as $section => $settings) {
                $result[$section] = array();
                foreach ($settings as $setting) {
                    $result[$section][$setting]
                        = $this->setting($section, $setting);
                }
            }
            $this->initDocument('json');
            $this->showJsonObjects($result);
            $this->endDocument('json');
            break;
        default:
            $this->clientError(
                // TRANS: Client error displayed when coming across a non-supported API method.
                _('API method not found.'),
                404,
                $this->format
            );
            break;
        }
    }

    function setting($section, $key) {
        $result = common_config($section, $key);
        if ($key == 'file_quota') {
            // hack: adjust for the live upload limit
            if (common_config($section, 'uploads')) {
                $max = ImageFile::maxFileSizeInt();
            } else {
                $max = 0;
            }
            return min($result, $max);
        }
        return $result;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }
}
