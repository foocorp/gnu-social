<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Cancel the subscription of a profile
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
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Cancel the subscription of a profile
 *
 * @category Subscription
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class CancelsubscriptionAction extends FormAction
{
    protected $needPost = true;

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $profile_id = $this->int('unsubscribeto');
        $this->target = Profile::getKV('id', $profile_id);
        if (!$this->target instanceof Profile) {
            throw new NoProfileException($profile_id);
        }

        return true;
    }

    protected function handlePost()
    {
        parent::handlePost();

        try {
            $request = Subscription_queue::pkeyGet(array('subscriber' => $this->scoped->id,
                                                         'subscribed' => $this->target->id));
            if ($request instanceof Subscription_queue) {
                $request->abort();
            }
        } catch (AlreadyFulfilledException $e) {
            common_debug('Tried to cancel a non-existing pending subscription');
        }

        if (StatusNet::isAjax()) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title after unsubscribing from a group.
            $this->element('title', null, _m('TITLE','Unsubscribed'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $subscribe = new SubscribeForm($this, $this->target);
            $subscribe->show();
            $this->elementEnd('body');
            $this->endHTML();
            exit();
        } else {
            common_redirect(common_local_url('subscriptions',
                                             array('nickname' => $this->scoped->nickname)),
                            303);
        }
    }
}
