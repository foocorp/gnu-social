<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Permalink for a peopletag
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
 * @category  Peopletag
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class ProfiletagbyidAction extends Action
{
    /** peopletag we're viewing. */
    var $peopletag = null;

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->arg('id');
        $tagger_id = $this->arg('tagger_id');

        if (!$id) {
            // TRANS: Client error displayed trying to perform an action without providing an ID.
            $this->clientError(_('No ID.'));
        }

        common_debug("Peopletag id $id by user id $tagger_id");

        $this->peopletag = Profile_list::getKV('id', $id);

        if (!$this->peopletag) {
            // TRANS: Client error displayed trying to reference a non-existing list.
            $this->clientError(_('No such list.'), 404);
        }

        $user = User::getKV('id', $tagger_id);
        if (!$user) {
            // remote peopletag, permanently redirect
            common_redirect($this->peopletag->permalink(), 301);
        }

        return true;
    }

    /**
     * Handle the request
     *
     * Shows a profile for the group, some controls, and a list of
     * group notices.
     *
     * @return void
     */
    function handle($args)
    {
        common_redirect($this->peopletag->homeUrl(), 303);
    }
}
