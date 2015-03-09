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
    protected function prepare(array $args=array())
    {
        if (!parent::prepare($args)) {
            return false;
        }
        $this->doPreparation();
        return true;
    }

    protected function doPreparation()
    {
        // pass by default
    }

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

        $this->showPage();
    }

    /**
     * If this is extended in child classes, they should
     * end with 'return parent::handlePost();' - and they
     * should only extend this function if what they do
     * cannot be handled in ->doPost()
     */
    protected function handlePost()
    {
        // This will only be run if the Action has the property canPost==true
        assert($this->canPost);

        // check for this before token since all POST and FILES data
        // is losts when size is exceeded
        if (empty($_POST) && $_SERVER['CONTENT_LENGTH']>0) {
            // TRANS: Client error displayed when the number of bytes in a POST request exceeds a limit.
            // TRANS: %s is the number of bytes of the CONTENT_LENGTH.
            $msg = _m('The server was unable to handle that much POST data (%s MiB) due to its current configuration.',
                      'The server was unable to handle that much POST data (%s MiB) due to its current configuration.',
                      round($_SERVER['CONTENT_LENGTH']/1024/1024,2));
            throw new ClientException($msg);
        }

        $this->checkSessionToken();
        return $this->doPost();
    }

    /**
     * Do Post stuff. Return a string if successful,
     * describing what has been done. Always throw an
     * exception on failure, with a descriptive message.
     */
    protected function doPost() {
        return false;
    }
}
