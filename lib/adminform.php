<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for administrative forms
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
 * @category  Widget
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for Administrative forms
 *
 * Just a place holder for some utility methods to simply some
 * repetitive form building code
 *
 * @category Widget
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Form
 */
class AdminForm extends Form
{
    /**
     * Utility to simplify some of the duplicated code around
     * params and settings.
     *
     * @param string $setting      Name of the setting
     * @param string $title        Title to use for the input
     * @param string $instructions Instructions for this field
     * @param string $section      config section, default = 'site'
     *
     * @return void
     */
    function input($setting, $title, $instructions, $section='site')
    {
        $this->out->input($setting, $title, $this->value($setting, $section), $instructions);
    }

    /**
     * Utility to simplify getting the posted-or-stored setting value
     *
     * @param string $setting Name of the setting
     * @param string $main    configuration section, default = 'site'
     *
     * @return string param value if posted, or current config value
     */
    function value($setting, $main='site')
    {
        $value = $this->out->trimmed($setting);
        if (empty($value)) {
            $value = common_config($main, $setting);
        }
        return $value;
    }
}
