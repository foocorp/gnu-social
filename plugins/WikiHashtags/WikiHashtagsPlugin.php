<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to show WikiHashtags content in the sidebar
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
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to use WikiHashtags
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Event
 */
class WikiHashtagsPlugin extends Plugin
{
    const VERSION = '0.1';

    function __construct($code=null)
    {
        parent::__construct();
    }

    function onStartShowSections(Action $action)
    {
        $name = $action->trimmed('action');

        if ($name == 'tag') {
            $taginput = $action->trimmed('tag');
            $tag = common_canonical_tag($taginput);

            if (!empty($tag)) {
                $url = sprintf('http://hashtags.wikia.com/index.php?title=%s&action=render',
                               urlencode($tag));
                $editurl = sprintf('http://hashtags.wikia.com/index.php?title=%s&action=edit',
                                   urlencode($tag));

                $request = HTTPClient::start();
                $response = $request->get($url);
                $html = $response->getBody();

                $action->elementStart('div', array('id' => 'wikihashtags', 'class' => 'section'));

                if ($response->isOk() && !empty($html)) {
                    $action->element('style', null,
                                     "span.editsection { display: none }\n".
                                     "table.toc { display: none }");
                    $action->raw($html);
                    $action->elementStart('p');
                    $action->element('a', array('href' => $editurl,
                                                // TRANS: Link title for editing an article on WikiHashTags.
                                                // TRANS: %s is the hash tag page to be edited.
                                                'title' => sprintf(_m('Edit the article for #%s on WikiHashtags'), $tag)),
                                     // TRANS: Link description for editing an article on WikiHashTags.
                                     _m('Edit'));
                    $action->element('a', array('href' => 'http://www.gnu.org/copyleft/fdl.html',
                                                // TRANS: Link title for viewing the GFDL.
                                                'title' => _m('Shared under the terms of the GNU Free Documentation License'),
                                                'rel' => 'license'),
                                     // TRANS: Link description for viewing the GFDL.
                                     _m('GNU FDL'));
                    $action->elementEnd('p');
                } else {
                    $action->element('a', array('href' => $editurl),
                                     // TRANS: Link description for editing an article on WikiHashTags.
                                     // TRANS: %s is the hash tag page to be created.
                                     sprintf(_m('Start the article for #%s on WikiHashtags'), $tag));
                }

                $action->elementEnd('div');
            }
        }

        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'WikiHashtags',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/WikiHashtags',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Gets hashtag descriptions from <a href="http://hashtags.wikia.com/">WikiHashtags</a>.'));
        return true;
    }
}
