<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Update a group's profile
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * API analog to the group edit page
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiGroupProfileUpdateAction extends ApiAuthAction
{
    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->nickname    = common_canonical_nickname($this->trimmed('nickname'));

        $this->fullname    = $this->trimmed('fullname');
        $this->homepage    = $this->trimmed('homepage');
        $this->description = $this->trimmed('description');
        $this->location    = $this->trimmed('location');
        $this->aliasstring = $this->trimmed('aliases');

        $this->user  = $this->auth_user;
        $this->group = $this->getTargetGroup($this->arg('id'));

        return true;
    }

    /**
     * Handle the request
     *
     * See which request params have been set, and update the profile
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                // TRANS: Client error message. POST is a HTTP command. It should not be translated.
                _('This method requires a POST.'),
                400, $this->format
            );
            return;
        }

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(
                // TRANS: Client error displayed when coming across a non-supported API method.
                _('API method not found.'),
                404,
                $this->format
            );
            return;
        }

        if (empty($this->user)) {
            // TRANS: Client error displayed when not providing a user or an invalid user.
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

        if (empty($this->group)) {
            // TRANS: Client error displayed when not providing a group or an invalid group.
            $this->clientError(_('Group not found.'), 404, $this->format);
            return false;
        }

        if (!$this->user->isAdmin($this->group)) {
            // TRANS: Client error displayed when trying to edit a group without being an admin.
            $this->clientError(_('You must be an admin to edit the group.'), 403);
            return false;
        }

        $this->group->query('BEGIN');

        $orig = clone($this->group);

        try {

            if (!empty($this->nickname)) {
                if ($this->validateNickname()) {
                    $this->group->nickname = $this->nickname;
                    $this->group->mainpage = common_local_url(
                        'showgroup',
                        array('nickname' => $this->nickname)
                    );
                }
            }

            if (!empty($this->fullname)) {
                $this->validateFullname();
                $this->group->fullname = $this->fullname;
            }

            if (!empty($this->homepage)) {
                $this->validateHomepage();
                $this->group->homepage = $this->hompage;
            }

            if (!empty($this->description)) {
                $this->validateDescription();
                $this->group->description = $this->decription;
            }

            if (!empty($this->location)) {
                $this->validateLocation();
                $this->group->location = $this->location;
            }

        } catch (ApiValidationException $ave) {
            $this->clientError(
                $ave->getMessage(),
                403,
                $this->format
            );
            return;
        }

        $result = $this->group->update($orig);

        if (!$result) {
            common_log_db_error($this->group, 'UPDATE', __FILE__);
            // TRANS: Server error displayed when group update fails.
            $this->serverError(_('Could not update group.'));
        }

        $aliases = array();

        try {
            if (!empty($this->aliasstring)) {
                $aliases = $this->validateAliases();
            }

        } catch (ApiValidationException $ave) {
            $this->clientError(
                $ave->getMessage(),
                403,
                $this->format
            );
            return;
        }

        $result = $this->group->setAliases($aliases);

        if (!$result) {
            // TRANS: Server error displayed when adding group aliases fails.
            $this->serverError(_('Could not create aliases.'));
        }

        if (!empty($this->nickname) && ($this->nickname != $orig->nickname)) {
            common_log(LOG_INFO, "Saving local group info.");
            $local = Local_group::getKV('group_id', $this->group->id);
            $local->setNickname($this->nickname);
        }

        $this->group->query('COMMIT');

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlGroup($this->group);
            break;
        case 'json':
            $this->showSingleJsonGroup($this->group);
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), 404, $this->format);
            break;
        }
    }

    function nicknameExists($nickname)
    {
        $group = Local_group::getKV('nickname', $nickname);

        if (!empty($group) &&
            $group->group_id != $this->group->id) {
            return true;
        }

        $alias = Group_alias::getKV('alias', $nickname);

        if (!empty($alias) &&
            $alias->group_id != $this->group->id) {
            return true;
        }

        return false;
    }

    function validateNickname()
    {
        if (!Validate::string(
            $this->nickname, array(
                'min_length' => 1,
                'max_length' => 64,
                'format' => NICKNAME_FMT
                )
            )
        ) {
            throw new ApiValidationException(
                // TRANS: API validation exception thrown when nickname does not validate.
                _('Nickname must have only lowercase letters and numbers and no spaces.')
            );
        } else if ($this->nicknameExists($this->nickname)) {
            throw new ApiValidationException(
                // TRANS: API validation exception thrown when nickname is already used.
                _('Nickname already in use. Try another one.')
            );
        } else if (!User_group::allowedNickname($this->nickname)) {
            throw new ApiValidationException(
                // TRANS: API validation exception thrown when nickname does not validate.
                _('Not a valid nickname.')
            );
        }

		return true;
    }

    function validateHomepage()
    {
        if (!is_null($this->homepage)
                && (strlen($this->homepage) > 0)
                && !common_valid_http_url($this->homepage)) {
            throw new ApiValidationException(
                // TRANS: API validation exception thrown when homepage URL does not validate.
                _('Homepage is not a valid URL.')
            );
        }
    }

    function validateFullname()
    {
        if (!is_null($this->fullname) && mb_strlen($this->fullname) > 255) {
            throw new ApiValidationException(
                // TRANS: API validation exception thrown when full name does not validate.
                _('Full name is too long (maximum 255 characters).')
            );
        }
    }

    function validateDescription()
    {
        if (User_group::descriptionTooLong($this->description)) {
            // TRANS: API validation exception thrown when description does not validate.
            // TRANS: %d is the maximum description length and used for plural.
            throw new ApiValidationException(sprintf(_m('Description is too long (maximum %d character).',
                                                        'Description is too long (maximum %d characters).',
                                                        User_group::maxDescription()),
                                                     User_group::maxDescription()));
        }
    }

    function validateLocation()
    {
        if (!is_null($this->location) && mb_strlen($this->location) > 255) {
            throw new ApiValidationException(
                // TRANS: API validation exception thrown when location does not validate.
                _('Location is too long (maximum 255 characters).')
            );
        }
    }

    function validateAliases()
    {
        $aliases = array_map(
            'common_canonical_nickname',
            array_unique(
                preg_split('/[\s,]+/',
                $this->aliasstring
                )
            )
        );

        if (count($aliases) > common_config('group', 'maxaliases')) {
            // TRANS: API validation exception thrown when aliases do not validate.
            // TRANS: %d is the maximum number of aliases and used for plural.
            throw new ApiValidationException(sprintf(_m('Too many aliases! Maximum %d allowed.',
                                                        'Too many aliases! Maximum %d allowed.',
                                                        common_config('group', 'maxaliases')),
                                                     common_config('group', 'maxaliases')));
        }

        foreach ($aliases as $alias) {
            if (!Validate::string(
                $alias, array(
                    'min_length' => 1,
                    'max_length' => 64,
                    'format' => NICKNAME_FMT)
                )
            ) {
                throw new ApiValidationException(
                    sprintf(
                        // TRANS: API validation exception thrown when aliases does not validate.
                        // TRANS: %s is the invalid alias.
                        _('Invalid alias: "%s".'),
                        $alias
                    )
                );
            }

            if ($this->nicknameExists($alias)) {
                throw new ApiValidationException(
                    sprintf(
                        // TRANS: API validation exception thrown when aliases is already used.
                        // TRANS: %s is the already used alias.
                        _('Alias "%s" already in use. Try another one.'),
                        $alias)
                );
            }

            // XXX assumes alphanum nicknames
            if (strcmp($alias, $this->nickname) == 0) {
                throw new ApiValidationException(
                    // TRANS: API validation exception thrown when alias is the same as nickname.
                    _('Alias cannot be the same as nickname.')
                );
            }
        }

        return $aliases;
    }
}
