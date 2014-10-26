<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A plugin to enable social-bookmarking functionality
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
 * @category  PollPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Poll plugin main class
 *
 * @category  PollPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class PollPlugin extends MicroAppPlugin
{
    const VERSION         = '0.1';

    // @fixme which domain should we use for these namespaces?
    const POLL_OBJECT          = 'http://activityschema.org/object/poll';
    const POLL_RESPONSE_OBJECT = 'http://activityschema.org/object/poll-response';

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
        $schema->ensureTable('poll', Poll::schemaDef());
        $schema->ensureTable('poll_response', Poll_response::schemaDef());
        $schema->ensureTable('user_poll_prefs', User_poll_prefs::schemaDef());
        return true;
    }

    /**
     * Show the CSS necessary for this plugin
     *
     * @param Action $action the action being run
     *
     * @return boolean hook value
     */
    function onEndShowStyles($action)
    {
        $action->cssLink($this->path('css/poll.css'));
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
        $m->connect('main/poll/new',
                    array('action' => 'newpoll'));

        $m->connect('main/poll/:id',
                    array('action' => 'showpoll'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));

        $m->connect('main/poll/response/:id',
                    array('action' => 'showpollresponse'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));

        $m->connect('main/poll/:id/respond',
                    array('action' => 'respondpoll'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));

        $m->connect('settings/poll',
                    array('action' => 'pollsettings'));

        return true;
    }

    /**
     * Plugin version data
     *
     * @param array &$versions array of version data
     *
     * @return value
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Poll',
                            'version' => self::VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:Poll',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Simple extension for supporting basic polls.'));
        return true;
    }

    function types()
    {
        return array(self::POLL_OBJECT, self::POLL_RESPONSE_OBJECT);
    }

    /**
     * When a notice is deleted, delete the related Poll
     *
     * @param Notice $notice Notice being deleted
     *
     * @return boolean hook value
     */
    function deleteRelated(Notice $notice)
    {
        $p = Poll::getByNotice($notice);

        if (!empty($p)) {
            $p->delete();
        }

        return true;
    }

    /**
     * Save a poll from an activity
     *
     * @param Profile  $profile  Profile to use as author
     * @param Activity $activity Activity to save
     * @param array    $options  Options to pass to bookmark-saving code
     *
     * @return Notice resulting notice
     */
    function saveNoticeFromActivity(Activity $activity, Profile $profile, array $options=array())
    {
        // @fixme
        common_log(LOG_DEBUG, "XXX activity: " . var_export($activity, true));
        common_log(LOG_DEBUG, "XXX profile: " . var_export($profile, true));
        common_log(LOG_DEBUG, "XXX options: " . var_export($options, true));

        // Ok for now, we can grab stuff from the XML entry directly.
        // This won't work when reading from JSON source
        if ($activity->entry) {
            $pollElements = $activity->entry->getElementsByTagNameNS(self::POLL_OBJECT, 'poll');
            $responseElements = $activity->entry->getElementsByTagNameNS(self::POLL_OBJECT, 'response');
            if ($pollElements->length) {
                $question = '';
                $opts = array();

                $data = $pollElements->item(0);
                foreach ($data->getElementsByTagNameNS(self::POLL_OBJECT, 'question') as $node) {
                    $question = $node->textContent;
                }
                foreach ($data->getElementsByTagNameNS(self::POLL_OBJECT, 'option') as $node) {
                    $opts[] = $node->textContent;
                }
                try {
                    $notice = Poll::saveNew($profile, $question, $opts, $options);
                    common_log(LOG_DEBUG, "Saved Poll from ActivityStream data ok: notice id " . $notice->id);
                    return $notice;
                } catch (Exception $e) {
                    common_log(LOG_DEBUG, "Poll save from ActivityStream data failed: " . $e->getMessage());
                }
            } else if ($responseElements->length) {
                $data = $responseElements->item(0);
                $pollUri = $data->getAttribute('poll');
                $selection = intval($data->getAttribute('selection'));

                if (!$pollUri) {
                    // TRANS: Exception thrown trying to respond to a poll without a poll reference.
                    throw new Exception(_m('Invalid poll response: No poll reference.'));
                }
                $poll = Poll::getKV('uri', $pollUri);
                if (!$poll) {
                    // TRANS: Exception thrown trying to respond to a non-existing poll.
                    throw new Exception(_m('Invalid poll response: Poll is unknown.'));
                }
                try {
                    $notice = Poll_response::saveNew($profile, $poll, $selection, $options);
                    common_log(LOG_DEBUG, "Saved Poll_response ok, notice id: " . $notice->id);
                    return $notice;
                } catch (Exception $e) {
                    common_log(LOG_DEBUG, "Poll response  save fail: " . $e->getMessage());
                }
            } else {
                common_log(LOG_DEBUG, "YYY no poll data");
            }
        }
    }

    function activityObjectFromNotice(Notice $notice)
    {
        assert($this->isMyNotice($notice));

        switch ($notice->object_type) {
        case self::POLL_OBJECT:
            return $this->activityObjectFromNoticePoll($notice);
        case self::POLL_RESPONSE_OBJECT:
            return $this->activityObjectFromNoticePollResponse($notice);
        default:
            // TRANS: Exception thrown when performing an unexpected action on a poll.
            // TRANS: %s is the unexpected object type.
            throw new Exception(sprintf(_m('Unexpected type for poll plugin: %s.'), $notice->object_type));
        }
    }

    function activityObjectFromNoticePollResponse(Notice $notice)
    {
        $object = new ActivityObject();
        $object->id      = $notice->uri;
        $object->type    = self::POLL_RESPONSE_OBJECT;
        $object->title   = $notice->content;
        $object->summary = $notice->content;
        $object->link    = $notice->getUrl();

        $response = Poll_response::getByNotice($notice);
        if ($response) {
            $poll = $response->getPoll();
            if ($poll) {
                // Stash data to be formatted later by
                // $this->activityObjectOutputAtom() or
                // $this->activityObjectOutputJson()...
                $object->pollSelection = intval($response->selection);
                $object->pollUri = $poll->uri;
            }
        }
        return $object;
    }

    function activityObjectFromNoticePoll(Notice $notice)
    {
        $object = new ActivityObject();
        $object->id      = $notice->uri;
        $object->type    = self::POLL_OBJECT;
        $object->title   = $notice->content;
        $object->summary = $notice->content;
        $object->link    = $notice->getUrl();

        $poll = Poll::getByNotice($notice);
        if ($poll) {
            // Stash data to be formatted later by
            // $this->activityObjectOutputAtom() or
            // $this->activityObjectOutputJson()...
            $object->pollQuestion = $poll->question;
            $object->pollOptions = $poll->getOptions();
        }

        return $object;
    }

    /**
     * Called when generating Atom XML ActivityStreams output from an
     * ActivityObject belonging to this plugin. Gives the plugin
     * a chance to add custom output.
     *
     * Note that you can only add output of additional XML elements,
     * not change existing stuff here.
     *
     * If output is already handled by the base Activity classes,
     * you can leave this base implementation as a no-op.
     *
     * @param ActivityObject $obj
     * @param XMLOutputter $out to add elements at end of object
     */
    function activityObjectOutputAtom(ActivityObject $obj, XMLOutputter $out)
    {
        if (isset($obj->pollQuestion)) {
            /**
             * <poll:poll xmlns:poll="http://apinamespace.org/activitystreams/object/poll">
             *   <poll:question>Who wants a poll question?</poll:question>
             *   <poll:option>Option one</poll:option>
             *   <poll:option>Option two</poll:option>
             *   <poll:option>Option three</poll:option>
             * </poll:poll>
             */
            $data = array('xmlns:poll' => self::POLL_OBJECT);
            $out->elementStart('poll:poll', $data);
            $out->element('poll:question', array(), $obj->pollQuestion);
            foreach ($obj->pollOptions as $opt) {
                $out->element('poll:option', array(), $opt);
            }
            $out->elementEnd('poll:poll');
        }
        if (isset($obj->pollSelection)) {
            /**
             * <poll:response xmlns:poll="http://apinamespace.org/activitystreams/object/poll">
             *                poll="http://..../poll/...."
             *                selection="3" />
             */
            $data = array('xmlns:poll' => self::POLL_OBJECT,
                          'poll'       => $obj->pollUri,
                          'selection'  => $obj->pollSelection);
            $out->element('poll:response', $data, '');
        }
    }

    /**
     * Called when generating JSON ActivityStreams output from an
     * ActivityObject belonging to this plugin. Gives the plugin
     * a chance to add custom output.
     *
     * Modify the array contents to your heart's content, and it'll
     * all get serialized out as JSON.
     *
     * If output is already handled by the base Activity classes,
     * you can leave this base implementation as a no-op.
     *
     * @param ActivityObject $obj
     * @param array &$out JSON-targeted array which can be modified
     */
    public function activityObjectOutputJson(ActivityObject $obj, array &$out)
    {
        common_log(LOG_DEBUG, 'QQQ: ' . var_export($obj, true));
        if (isset($obj->pollQuestion)) {
            /**
             * "poll": {
             *   "question": "Who wants a poll question?",
             *   "options": [
             *     "Option 1",
             *     "Option 2",
             *     "Option 3"
             *   ]
             * }
             */
            $data = array('question' => $obj->pollQuestion,
                          'options' => array());
            foreach ($obj->pollOptions as $opt) {
                $data['options'][] = $opt;
            }
            $out['poll'] = $data;
        }
        if (isset($obj->pollSelection)) {
            /**
             * "pollResponse": {
             *   "poll": "http://..../poll/....",
             *   "selection": 3
             * }
             */
            $data = array('poll'       => $obj->pollUri,
                          'selection'  => $obj->pollSelection);
            $out['pollResponse'] = $data;
        }
    }

    function entryForm($out)
    {
        return new NewPollForm($out);
    }

    // @fixme is this from parent?
    function tag()
    {
        return 'poll';
    }

    function appTitle()
    {
        // TRANS: Application title.
        return _m('APPTITLE','Poll');
    }

    function onStartAddNoticeReply($nli, $parent, $child)
    {
        // Filter out any poll responses
        if ($parent->object_type == self::POLL_OBJECT &&
            $child->object_type == self::POLL_RESPONSE_OBJECT) {
            return false;
        }
        return true;
    }

    // Hide poll responses for @chuck

    function onEndNoticeWhoGets($notice, &$ni) {
        if ($notice->object_type == self::POLL_RESPONSE_OBJECT) {
            foreach ($ni as $id => $source) {
                $user = User::getKV('id', $id);
                if (!empty($user)) {
                    $pollPrefs = User_poll_prefs::getKV('user_id', $user->id);
                    if (!empty($pollPrefs) && ($pollPrefs->hide_responses)) {
                        unset($ni[$id]);
                    }
                }
            }
        }
        return true;
    }

    /**
     * Menu item for personal subscriptions/groups area
     *
     * @param Action $action action being executed
     *
     * @return boolean hook return
     */

    function onEndAccountSettingsNav($action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('pollsettings'),
                          // TRANS: Poll plugin menu item on user settings page.
                          _m('MENU', 'Polls'),
                          // TRANS: Poll plugin tooltip for user settings menu item.
                          _m('Configure poll behavior'),
                          $action_name === 'pollsettings');

        return true;
    }

    protected function showNoticeContent(Notice $stored, HTMLOutputter $out, Profile $scoped=null)
    {
        if ($stored->object_type == self::POLL_RESPONSE_OBJECT) {
            parent::showNoticeContent($stored, $out, $scoped);
            return;
        }

        // If the stored notice is a POLL_OBJECT
        $poll = Poll::getByNotice($stored);
        if ($poll instanceof Poll) {
            if (!$scoped instanceof Profile || $poll->getResponse($scoped) instanceof Poll_response) {
                // Either the user is not logged in or it has already responded; show the results.
                $form = new PollResultForm($poll, $out);
            } else {
                $form = new PollResponseForm($poll, $out);
            }
            $form->show();
        } else {
            // TRANS: Error text displayed if no poll data could be found.
            $out->text(_m('Poll data is missing'));
        }
    }
}
