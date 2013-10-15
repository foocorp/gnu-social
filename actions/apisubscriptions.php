<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for showing subscription information in the API
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
 * @package   StatusNet
 * @author    Dan Moore <dan@moore.cx>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * This class outputs a list of profiles as Twitter-style user and status objects.
 * It is used by the API methods /api/statuses/(friends|followers). To support the
 * social graph methods it also can output a simple list of IDs.
 *
 * @category API
 * @package  StatusNet
 * @author   Dan Moore <dan@moore.cx>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
abstract class ApiSubscriptionsAction extends ApiBareAuthAction
{
    var $profiles = null;
    var $tag      = null;
    var $lite     = null;
    var $ids_only = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare($args)
    {
        parent::prepare($args);

        $this->tag      = $this->arg('tag');

        // Note: Twitter no longer supports 'lite'
        $this->lite     = $this->arg('lite');

        $this->ids_only = $this->arg('ids_only');

        // If called as a social graph method, show 5000 per page, otherwise 100

        $this->count    = isset($this->ids_only) ?
            5000 : (int)$this->arg('count', 100);

        $this->target = $this->getTargetProfile($this->arg('id'));

        if (!($this->target instanceof Profile)) {
            // TRANS: Client error displayed when requesting a list of followers for a non-existing user.
            $this->clientError(_('No such user.'), 404);
        }

        $this->profiles = $this->getProfiles();

        return true;
    }

    /**
     * Handle the request
     *
     * Show the profiles
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        if (!in_array($this->format, array('xml', 'json'))) {
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), 404);
        }

        $this->initDocument($this->format);

        if (isset($this->ids_only)) {
            $this->showIds();
        } else {
            $this->showProfiles(isset($this->lite) ? false : true);
        }

        $this->endDocument($this->format);
    }

    /**
     * Get profiles related to the type of subscriber/subscription action
     *
     * @return array Profiles
     */
    abstract protected function getProfiles();

    /**
     * Is this action read only?
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * When was this feed last modified?
     *
     * @return string datestamp of the latest profile in the stream
     */
    function lastModified()
    {
        if (!empty($this->profiles) && (count($this->profiles) > 0)) {
            return strtotime($this->profiles[0]->created);
        }

        return null;
    }

    /**
     * An entity tag for this action
     *
     * Returns an Etag based on the action name, language, user ID, and
     * timestamps of the first and last profiles in the subscriptions list
     * There's also an indicator to show whether this action is being called
     * as /api/statuses/(friends|followers) or /api/(friends|followers)/ids
     *
     * @return string etag
     */
    function etag()
    {
        if (!empty($this->profiles) && (count($this->profiles) > 0)) {

            $last = count($this->profiles) - 1;

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_user_cache_hash($this->auth_user),
                      common_language(),
                      $this->target->id,
                      // Caching tags.
                      isset($this->ids_only) ? 'IDs' : 'Profiles',
                      strtotime($this->profiles[0]->created),
                      strtotime($this->profiles[$last]->created))
            )
            . '"';
        }

        return null;
    }

    /**
     * Show the profiles as Twitter-style useres and statuses
     *
     * @param boolean $include_statuses Whether to include the latest status
     *                                  with each user. Default true.
     *
     * @return void
     */
    function showProfiles($include_statuses = true)
    {
        switch ($this->format) {
        case 'xml':
            $this->elementStart('users', array('type' => 'array',
                                               'xmlns:statusnet' => 'http://status.net/schema/api/1/'));
            foreach ($this->profiles as $profile) {
                $this->showProfile(
                    $profile,
                    $this->format,
                    null,
                    $include_statuses
                );
            }
            $this->elementEnd('users');
            break;
        case 'json':
            $arrays = array();
            foreach ($this->profiles as $profile) {
                $arrays[] = $this->twitterUserArray(
                    $profile,
                    $include_statuses
                );
            }
            print json_encode($arrays);
            break;
        default:
            // TRANS: Client error displayed when requesting profiles of followers in an unsupported format.
            $this->clientError(_('Unsupported format.'));
            break;
        }
    }

    /**
     * Show the IDs of the profiles only. 5000 per page. To support
     * the 'social graph' methods: /api/(friends|followers)/ids
     *
     * @return void
     */
    function showIds()
    {
        switch ($this->format) {
        case 'xml':
            $this->elementStart('ids');
            foreach ($this->profiles as $profile) {
                $this->element('id', null, $profile->id);
            }
            $this->elementEnd('ids');
            break;
        case 'json':
            $ids = array();
            foreach ($this->profiles as $profile) {
                $ids[] = (int)$profile->id;
            }
            print json_encode($ids);
            break;
        default:
            // TRANS: Client error displayed when requesting IDs of followers in an unsupported format.
            $this->clientError(_('Unsupported format.'));
            break;
        }
    }
}
