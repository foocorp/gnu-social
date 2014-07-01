<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A microapp to implement lite blogging
 *
 * PHP version 5
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
 * @category  Blog
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Blog plugin
 *
 * Many social systems have a way to write and share long-form texts with
 * your network. This microapp plugin lets users post blog entries.
 *
 * @category  Blog
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class BlogPlugin extends MicroAppPlugin
{

    var $oldSaveNew = true;

    /**
     * Database schema setup
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('blog_entry', Blog_entry::schemaDef());

        return true;
    }

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onRouterInitialized($m)
    {
        $m->connect('blog/new',
                    array('action' => 'newblogentry'));
        $m->connect('blog/:id',
                    array('action' => 'showblogentry'),
                    array('id' => UUID::REGEX));
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Blog',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Blog',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Let users write and share long-form texts.'));
        return true;
    }

    function appTitle()
    {
        // TRANS: Blog application title.
        return _m('TITLE','Blog');
    }

    function tag()
    {
        return 'blog';
    }

    function types()
    {
        return array(Blog_entry::TYPE);
    }

    function saveNoticeFromActivity(Activity $activity, Profile $actor, array $options=array())
    {
        if (count($activity->objects) != 1) {
            // TRANS: Exception thrown when there are too many activity objects.
            throw new ClientException(_m('Too many activity objects.'));
        }

        $entryObj = $activity->objects[0];

        if ($entryObj->type != Blog_entry::TYPE) {
            // TRANS: Exception thrown when blog plugin comes across a non-blog entry type object.
            throw new ClientException(_m('Wrong type for object.'));
        }

        $notice = null;

        switch ($activity->verb) {
        case ActivityVerb::POST:
            $notice = Blog_entry::saveNew($actor,
                                         $entryObj->title,
                                         $entryObj->content,
                                         $options);
            break;
        default:
            // TRANS: Exception thrown when blog plugin comes across a undefined verb.
            throw new ClientException(_m('Unknown verb for blog entries.'));
        }

        return $notice;
    }

    function activityObjectFromNotice(Notice $notice)
    {
        $entry = Blog_entry::fromNotice($notice);

        if (empty($entry)) {
            // TRANS: Exception thrown when requesting a non-existing blog entry for notice.
            throw new ClientException(sprintf(_m('No blog entry for notice %s.'),
                        $notice->id));
        }

        return $entry->asActivityObject();
    }

    function entryForm($out)
    {
        return new BlogEntryForm($out);
    }

    function deleteRelated(Notice $notice)
    {
        if ($notice->object_type == Blog_entry::TYPE) {
            $entry = Blog_entry::fromNotice($notice);
            if (!empty($entry)) {
                $entry->delete();
            }
        }
    }

    function adaptNoticeListItem($nli)
    {
        $notice = $nli->notice;

        if ($notice->object_type == Blog_entry::TYPE) {
            return new BlogEntryListItem($nli);
        }

        return null;
    }

    function onEndShowScripts($action)
    {
        $action->script(common_path('plugins/TinyMCE/js/jquery.tinymce.js'));
        $action->inlineScript('var _tinymce_path = "'.common_path('plugins/TinyMCE/js/tiny_mce.js').'";'."\n".
                              'var _tinymce_placeholder = "'.common_path('plugins/TinyMCE/icons/placeholder.png').'";'."\n");
        $action->script($this->path('blog.js'));
        return true;
    }
}
