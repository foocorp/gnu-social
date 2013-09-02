<?php
/**
 * Form action extendable class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Form action extendable class
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Mikael Nordfeldth <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class FormAction extends Action
{
    protected $form = null;
    protected $type = null;
    protected $needLogin = true;

    protected function prepare(array $args=array()) {
        parent::prepare($args);

        $this->form = $this->form ?: $this->action;
        $this->args['form'] = $this->form;

        $this->type = !is_null($this->type) ? $this->type : $this->trimmed('type');
        $this->args['context'] = $this->trimmed('action');  // reply for notice for example

        if (!$this->type) {
            $this->type = null;
        }

        return true;
    }

    protected function handle()
    {
        parent::handle();

        if ($this->isPost()) {
            try {
                $msg = $this->handlePost();
                $this->showForm($msg, true);
            } catch (Exception $e) {
                $this->showForm($e->getMessage());
            }
        } else {
            $this->showForm();
        }
    }

    public function isReadOnly($args) {
        return !$this->isPost();
    }

    public function showPageNotice()
    {
        $this->showInstructions();
        if ($msg = $this->getError()) {
            $this->element('div', 'error', $msg);
        }
        if ($msg = $this->getInfo()) {
            $this->element('div', 'info', $msg);
        }
    }

    /**
     * Outputs the instructions for the form
     *
     * SHOULD overload
     */
    public function showInstructions()
    {
        // instructions are nice, so users know what to do
    }

    public function showForm($msg=null, $success=false)
    {
        if ($success) {
            $this->msg = $msg;
        } else {
            $this->error = $msg;
        }
        $this->showPage();
    }

    /**
     * Gets called from handle() if isPost() is true;
     * @return void
     */
    protected function handlePost()
    {
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
    }
}
