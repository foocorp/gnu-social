<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * State machine for running through Yammer import.
 *
 * @package YammerImportPlugin
 * @author Brion Vibber <brion@status.net>
 */
class YammerRunner
{
    private $state;
    private $client;
    private $importer;

    /**
     * Normalize our singleton state and give us a YammerRunner object to play with!
     *
     * @return YammerRunner
     */
    public static function init()
    {
        $state = Yammer_state::staticGet('id', 1);
        if (!$state) {
            $state = self::initState();
        }
        return new YammerRunner($state);
    }

    private static function initState()
    {
        $state = new Yammer_state();
        $state->id = 1;
        $state->state = 'init';
        $state->created = common_sql_now();
        $state->modified = common_sql_now();
        $state->insert();
        return $state;
    }

    private function __construct($state)
    {
        $this->state = $state;

        $this->client = new SN_YammerClient(
            common_config('yammer', 'consumer_key'),
            common_config('yammer', 'consumer_secret'),
            $this->state->oauth_token,
            $this->state->oauth_secret);

        $this->importer = new YammerImporter($this->client);
    }

    /**
     * Check which state we're in
     *
     * @return string
     */
    public function state()
    {
        return $this->state->state;
    }

    /**
     * Is the import done, finished, complete, finito?
     *
     * @return boolean
     */
    public function isDone()
    {
        $workStates = array('import-users', 'import-groups', 'fetch-messages', 'save-messages');
        return ($this->state() == 'done');
    }

    /**
     * Check if we have work to do in iterate().
     *
     * @return boolean
     */
    public function hasWork()
    {
        $workStates = array('import-users', 'import-groups', 'fetch-messages', 'save-messages');
        return in_array($this->state(), $workStates);
    }

    /**
     * Blow away any current state!
     */
    public function reset()
    {
        $this->state->delete();
        $this->state = self::initState();
    }

    /**
     * Start the authentication process! If all goes well, we'll get back a URL.
     * Have the user visit that URL, log in on Yammer and verify the importer's
     * permissions. They'll get back a verification code, which needs to be passed
     * on to saveAuthToken().
     *
     * @return string URL
     */
    public function requestAuth()
    {
        if ($this->state->state != 'init') {
            // TRANS: Server exception thrown if a Yammer authentication request is already present.
            throw new ServerException(_m('Cannot request Yammer auth; already there!'));
        }

        $data = $this->client->requestToken();

        $old = clone($this->state);
        $this->state->state = 'requesting-auth';
        $this->state->oauth_token = $data['oauth_token'];
        $this->state->oauth_secret = $data['oauth_token_secret'];
        $this->state->modified = common_sql_now();
        $this->state->update($old);

        return $this->getAuthUrl();
    }

    /**
     * When already in requesting-auth state, grab the URL to send the user to
     * to complete OAuth setup.
     *
     * @return string URL
     */
    function getAuthUrl()
    {
        if ($this->state() == 'requesting-auth') {
            return $this->client->authorizeUrl($this->state->oauth_token);
        } else {
            // TRANS: Server exception thrown when requesting a Yammer authentication URL while in an incorrect state.
            throw new ServerException(_m('Cannot get Yammer auth URL when not in requesting-auth state!'));
        }
    }

    /**
     * Now that the user's given us this verification code from Yammer, we can
     * request a final OAuth token/secret pair which we can use to access the
     * API.
     *
     * After success here, we'll be ready to move on and run through iterate()
     * until the import is complete.
     *
     * @param string $verifier
     * @return boolean success
     */
    public function saveAuthToken($verifier)
    {
        if ($this->state->state != 'requesting-auth') {
            // TRANS: Server exception thrown if a Yammer authentication token could not be saved in a certain import state.
            // TRANS: %s is the import state in the which the error occurred.
            throw new ServerException(_m('Cannot save auth token in Yammer import state %s.',$this->state->state));
        }

        $data = $this->client->accessToken($verifier);

        $old = clone($this->state);
        $this->state->state = 'import-users';
        $this->state->oauth_token = $data['oauth_token'];
        $this->state->oauth_secret = $data['oauth_token_secret'];
        $this->state->modified = common_sql_now();
        $this->state->update($old);

        return true;
    }

    /**
     * Once authentication is complete, we need to call iterate() a bunch of times
     * until state() returns 'done'.
     *
     * @return boolean success
     */
    public function iterate()
    {
        switch($this->state())
        {
            case 'init':
            case 'requesting-auth':
                // Neither of these should reach our background state!
                common_log(LOG_ERR, "Non-background YammerImport state '$state->state' during import run!");
                return false;
            case 'import-users':
                return $this->iterateUsers();
            case 'import-groups':
                return $this->iterateGroups();
            case 'fetch-messages':
                return $this->iterateFetchMessages();
            case 'save-messages':
                return $this->iterateSaveMessages();
            default:
                common_log(LOG_ERR, "Invalid YammerImport state '$state->state' during import run!");
                return false;
        }
    }

    /**
     * Trundle through one 'page' return of up to 50 user accounts retrieved
     * from the Yammer API, importing them as we go.
     *
     * When we run out of users, move on to groups.
     *
     * @return boolean success
     */
    private function iterateUsers()
    {
        $old = clone($this->state);

        $page = intval($this->state->users_page) + 1;
        $data = $this->client->users(array('page' => $page));

        if (count($data) == 0) {
            common_log(LOG_INFO, "Finished importing Yammer users; moving on to groups.");
            $this->state->state = 'import-groups';
        } else {
            foreach ($data as $item) {
                $user = $this->importer->importUser($item);
                common_log(LOG_INFO, "Imported Yammer user " . $item['id'] . " as $user->nickname ($user->id)");
            }
            $this->state->users_page = $page;
        }
        $this->state->modified = common_sql_now();
        $this->state->update($old);
        return true;
    }

    /**
     * Trundle through one 'page' return of up to 20 user groups retrieved
     * from the Yammer API, importing them as we go.
     *
     * When we run out of groups, move on to messages.
     *
     * @return boolean success
     */
    private function iterateGroups()
    {
        $old = clone($this->state);

        $page = intval($this->state->groups_page) + 1;
        $data = $this->client->groups(array('page' => $page));

        if (count($data) == 0) {
            common_log(LOG_INFO, "Finished importing Yammer groups; moving on to messages.");
            $this->state->state = 'fetch-messages';
        } else {
            foreach ($data as $item) {
                $group = $this->importer->importGroup($item);
                common_log(LOG_INFO, "Imported Yammer group " . $item['id'] . " as $group->nickname ($group->id)");
            }
            $this->state->groups_page = $page;
        }
        $this->state->modified = common_sql_now();
        $this->state->update($old);
        return true;
    }

    /**
     * Trundle through one 'page' return of up to 20 public messages retrieved
     * from the Yammer API, saving them to our stub table for future import in
     * correct chronological order.
     *
     * When we run out of messages to fetch, move on to saving the messages.
     *
     * @return boolean success
     */
    private function iterateFetchMessages()
    {
        $old = clone($this->state);

        $oldest = intval($this->state->messages_oldest);
        if ($oldest) {
            $params = array('older_than' => $oldest);
        } else {
            $params = array();
        }
        $data = $this->client->messages($params);
        $messages = $data['messages'];

        if (count($messages) == 0) {
            common_log(LOG_INFO, "Finished fetching Yammer messages; moving on to save messages.");
            $this->state->state = 'save-messages';
        } else {
            foreach ($messages as $item) {
                $stub = Yammer_notice_stub::staticGet($item['id']);
                if (!$stub) {
                    Yammer_notice_stub::record($item['id'], $item);
                }
                $oldest = $item['id'];
            }
            $this->state->messages_oldest = $oldest;
        }
        $this->state->modified = common_sql_now();
        $this->state->update($old);
        return true;
    }

    private function iterateSaveMessages()
    {
        $old = clone($this->state);

        $newest = intval($this->state->messages_newest);

        $stub = new Yammer_notice_stub();
        if ($newest) {
            $stub->whereAdd('id > ' . $newest);
        }
        $stub->limit(20);
        $stub->orderBy('id');
        $stub->find();

        if ($stub->N == 0) {
            common_log(LOG_INFO, "Finished saving Yammer messages; import complete!");
            $this->state->state = 'done';
        } else {
            while ($stub->fetch()) {
                $item = $stub->getData();
                $notice = $this->importer->importNotice($item);
                common_log(LOG_INFO, "Imported Yammer notice " . $item['id'] . " as $notice->id");
                $newest = $item['id'];
            }
            $this->state->messages_newest = $newest;
        }
        $this->state->modified = common_sql_now();
        $this->state->update($old);
        return true;
    }

    /**
     * Count the number of Yammer users we've mapped into our system!
     *
     * @return int
     */
    public function countUsers()
    {
        $map = new Yammer_user();
        return $map->count();
    }

    /**
     * Count the number of Yammer groups we've mapped into our system!
     *
     * @return int
     */
    public function countGroups()
    {
        $map = new Yammer_group();
        return $map->count();
    }

    /**
     * Count the number of Yammer notices we've pulled down for pending import...
     *
     * @return int
     */
    public function countFetchedNotices()
    {
        $map = new Yammer_notice_stub();
        return $map->count();
    }

    /**
     * Count the number of Yammer notices we've mapped into our system!
     *
     * @return int
     */
    public function countSavedNotices()
    {
        $map = new Yammer_notice();
        return $map->count();
    }

    /**
     * Start running import work in the background queues...
     */
    public function startBackgroundImport()
    {
        $qm = QueueManager::get();
        $qm->enqueue('YammerImport', 'yammer');
    }

    /**
     * Record an error condition from a background run, which we should
     * display in progress state for the admin.
     *
     * @param string $msg
     */
    public function recordError($msg)
    {
        // HACK HACK HACK
        try {
            $temp = new Yammer_state();
            $temp->query('ROLLBACK');
        } catch (Exception $e) {
            common_log(LOG_ERR, 'Exception while confirming rollback while recording error: ' . $e->getMessage());
        }
        $old = clone($this->state);
        $this->state->last_error = $msg;
        $this->state->update($old);
    }

    /**
     * Clear the error state.
     */
    public function clearError()
    {
        $this->recordError('');
    }

    /**
     * Get the last recorded background error message, if any.
     *
     * @return string
     */
    public function lastError()
    {
        return $this->state->last_error;
    }
}
