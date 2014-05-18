<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for all actions with automatic showPage() call in handle()
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ManagedAction extends Action
{
    /**
     * Handler method
     */
    protected function handle()
    {
        parent::handle();

        if ($this->canPost && $this->isPost()) {
            try {
                $this->msg = $this->handlePost();
            } catch (Exception $e) {
                $this->error = $e->getMessage();
            }
        }

        if (StatusNet::isAjax()) {
            $this->showAjax();
        } else {
            $this->showPage();
        }
    }

    protected function handlePost()
    {
        // This will only be run if the Action has the property canPost==true
    }

    public function showAjax()
    {
        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        // TRANS: Title for conversation page.
        $this->element('title', null, _m('TITLE','Notice'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $this->showContent();
        $this->elementEnd('body');
        $this->endHTML();
    }
}
