<?php
/**
 * GNU social - a federating social network
 *
 * Plugin that produces some of the default layouts of a GNU social site.
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
 * @category  Plugin
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2014 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class DefaultLayoutPlugin extends Plugin
{
    public $prerender_replyforms = false;

    public function onEndShowThreadedNoticeTail(NoticeListItem $nli, Notice $notice, array $notices) {
        if ($this->prerender_replyforms) {
            $nli->out->elementStart('li', array('class'=>'notice-reply', 'style'=>'display: none;'));
            $replyForm = new NoticeForm($nli->out, array('inreplyto' => $notice->getID()));
            $replyForm->show();
            $nli->out->elementEnd('li');
        }
        return true;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Default Layout',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://www.gnu.org/software/social/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Optional default layout elements.'));
        return true;
    }
}
