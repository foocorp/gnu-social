<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A plugin to add titles to notices
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
 * @category  NoticeTitle
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

define('NOTICE_TITLE_PLUGIN_VERSION', '0.1');

/**
 * NoticeTitle plugin to add an optional title to notices.
 *
 * Stores notice titles in a secondary table, notice_title.
 *
 * @category  NoticeTitle
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NoticeTitlePlugin extends Plugin
{

    // By default, notice-title widget will be available to all users.
    // With restricted on, only users who have been granted the
    // "richedit" role get it.
    public $restricted = false;

    /**
     * Database schema setup
     *
     * Add the notice_title helper table
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing titles for notices

        $schema->ensureTable('notice_title',
                             array(new ColumnDef('notice_id',
                                                 'integer',
                                                 null,
                                                 true,
                                                 'PRI'),
                                   new ColumnDef('title',
                                                 'varchar',
                                                 Notice_title::MAXCHARS,
                                                 false)));

        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'Notice_title':
            include_once $dir . '/'.$cls.'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Provide plugin version information.
     *
     * This data is used when showing the version page.
     *
     * @param array &$versions array of version data arrays; see EVENTS.txt
     *
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $url = 'http://status.net/wiki/Plugin:NoticeTitle';

        $versions[] = array('name' => 'NoticeTitle',
                            'version' => NOTICE_TITLE_PLUGIN_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => $url,
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Adds optional titles to notices.'));
        return true;
    }

    /**
     * Show title entry when showing notice form
     *
     * @param Form $form Form being shown
     *
     * @return boolean hook value
     */
    function onStartShowNoticeFormData($form)
    {
        if ($this->isAllowedRichEdit()) {
            $form->out->element('style',
                                null,
                                'label#notice_data-text-label { display: none }');
            $form->out->element('input', array('type' => 'text',
                                               'id' => 'notice_title',
                                               'name' => 'notice_title',
                                               'size' => 40,
                                               'maxlength' => Notice_title::MAXCHARS));
        }
        return true;
    }

    /**
     * Validate notice title before saving
     *
     * @param Action  $action    NewNoticeAction being executed
     * @param integer &$authorId Author ID
     * @param string  &$text     Text of the notice
     * @param array   &$options  Options array
     *
     * @return boolean hook value
     */
    function onStartNoticeSaveWeb($action, &$authorId, &$text, &$options)
    {
        $title = $action->trimmed('notice_title');
        if (!empty($title) && $this->isAllowedRichEdit()) {
            if (mb_strlen($title) > Notice_title::MAXCHARS) {
                // TRANS: Exception thrown when a notice title is too long.
                // TRANS: %d is the maximum number of characters allowed in a title (used for plural).
                throw new Exception(sprintf(_m('The notice title is too long (maximum %d character).',
                                               'The notice title is too long (maximum %d characters).',
                                               Notice_title::MAXCHARS),
                                            Notice_title::MAXCHARS));
            }
        }
        return true;
    }

    /**
     * Save notice title after notice is saved
     *
     * @param Action $action NewNoticeAction being executed
     * @param Notice $notice Notice that was saved
     *
     * @return boolean hook value
     */
    function onEndNoticeSaveWeb($action, $notice)
    {
        if (!empty($notice)) {

            $title = $action->trimmed('notice_title');

            if (!empty($title) && $this->isAllowedRichEdit()) {

                $nt = new Notice_title();

                $nt->notice_id = $notice->id;
                $nt->title     = $title;

                $nt->insert();
            }
        }

        return true;
    }

    /**
     * Show the notice title in lists
     *
     * @param NoticeListItem $nli NoticeListItem being shown
     *
     * @return boolean hook value
     */
    function onStartShowNoticeItem($nli)
    {
        $title = Notice_title::fromNotice($nli->notice);

        if (!empty($title)) {
            $nli->out->elementStart('h4', array('class' => 'notice_title'));
            $nli->out->element('a', array('href' => $nli->notice->bestUrl()), $title);
            $nli->out->elementEnd('h4');
        }

        return true;
    }

    /**
     * Show the notice title in RSS output
     *
     * @param Notice $notice Notice being shown
     * @param array  &$entry array of values used for RSS output
     *
     * @return boolean hook value
     */
    function onEndRssEntryArray($notice, &$entry)
    {
        $title = Notice_title::fromNotice($notice);

        if (!empty($title)) {
            $entry['title'] = $title;
        }

        return true;
    }

    /**
     * Show the notice title in Atom output
     *
     * @param Notice      &$notice Notice being shown
     * @param XMLStringer &$xs     output context
     * @param string      &$output string to be output as title
     *
     * @return boolean hook value
     */
    function onEndNoticeAsActivity($notice, &$activity)
    {
        $title = Notice_title::fromNotice($notice);

        if (!empty($title)) {
            foreach ($activity->objects as $obj) {
                if ($obj->id == $notice->uri) {
                    $obj->title = $title;
                    break;
                }
            }
        }

        return true;
    }

    /**
     * Remove title when the notice is deleted
     *
     * @param Notice $notice Notice being deleted
     *
     * @return boolean hook value
     */
    function onNoticeDeleteRelated($notice)
    {
        $nt = Notice_title::getKV('notice_id', $notice->id);

        if (!empty($nt)) {
            $nt->delete();
        }

        return true;
    }

    /**
     * If a notice has a title, show it in the <title> element
     *
     * @param Action $action Action being executed
     *
     * @return boolean hook value
     */
    function onStartShowHeadTitle($action)
    {
        $actionName = $action->trimmed('action');

        if ($actionName == 'shownotice') {
            $title = Notice_title::fromNotice($action->notice);
            if (!empty($title)) {
                $action->element('title', null,
                                 // TRANS: Page title. %1$s is the title, %2$s is the site name.
                                 sprintf(_m("%1\$s - %2\$s"),
                                         $title,
                                         common_config('site', 'name')));
            }
        }

        return true;
    }

    /**
     * If a notice has a title, show it in the <h1> element
     *
     * @param Action $action Action being executed
     *
     * @return boolean hook value
     */
    function onStartShowPageTitle($action)
    {
        $actionName = $action->trimmed('action');

        if ($actionName == 'shownotice') {
            $title = Notice_title::fromNotice($action->notice);
            if (!empty($title)) {
                $action->element('h1', null, $title);
                return false;
            }
        }

        return true;
    }

    /**
     * Does the current user have permission to use the notice-title widget?
     * Always true unless the plugin's "restricted" setting is on, in which
     * case it's limited to users with the "richedit" role.
     *
     * @todo FIXME: make that more sanely configurable :)
     *
     * @return boolean
     */
    private function isAllowedRichEdit()
    {
        if ($this->restricted) {
            $user = common_current_user();
            return !empty($user) && $user->hasRole('richedit');
        } else {
            return true;
        }
    }
}
