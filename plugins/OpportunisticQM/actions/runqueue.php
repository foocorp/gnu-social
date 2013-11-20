<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class RunqueueAction extends Action
{
    protected $qm = null;

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $args = array();

        foreach (array('qmkey') as $key) {
            if ($this->arg($key) !== null) {
                $args[$key] = $this->arg($key);
            }
        }

        try {
            $this->qm = new OpportunisticQueueManager($args);
        } catch (RunQueueBadKeyException $e) {
            return false;
        }

        header('Content-type: text/plain; charset=utf-8');

        return true;
    }

    protected function handle() {
        // We don't need any of the parent functionality from parent::handle() here.

        // runQueue is a loop that works until limits have passed or there is no more work
        if ($this->qm->runQueue() === true) {
            // We don't have any more work
            $this->text('0');
        } else {
            // There were still items left in queue when we aborted
            $this->text('1');
        }
    }
}
