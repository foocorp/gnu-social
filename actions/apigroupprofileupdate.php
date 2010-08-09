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

require_once INSTALLDIR . '/lib/apiauth.php';

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
                _('This method requires a POST.'),
                400, $this->format
            );
            return;
        }

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(
                _('API method not found.'),
                404,
                $this->format
            );
            return;
        }

        if (empty($this->user)) {
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

        if (empty($this->group)) {
            $this->clientError(_('Group not found.'), 404, $this->format);
            return false;
        }

        if (!$this->user->isAdmin($this->group)) {
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
            $this->serverError(_('Could not create aliases.'));
        }

        if (!empty($this->nickname) && ($this->nickname != $orig->nickname)) {
            common_log(LOG_INFO, "Saving local group info.");
            $local = Local_group::staticGet('group_id', $this->group->id);
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
            $this->clientError(_('API method not found.'), 404, $this->format);
            break;
        }
    }

    function nicknameExists($nickname)
    {
        $group = Local_group::staticGet('nickname', $nickname);

        if (!empty($group) &&
            $group->group_id != $this->group->id) {
            return true;
        }

        $alias = Group_alias::staticGet('alias', $nickname);

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
                _(
                    'Nickname must have only lowercase letters ' .
                    'and numbers and no spaces.'
                )
            );
        } else if ($this->nicknameExists($this->nickname)) {
            throw new ApiValidationException(
                _('Nickname already in use. Try another one.')
            );
        } else if (!User_group::allowedNickname($this->nickname)) {
            throw new ApiValidationException(
                _('Not a valid nickname.')
            );
        }

		return true;
    }

    function validateHomepage()
    {
        if (!is_null($this->homepage)
        && (strlen($this->homepage) > 0)
        && !Validate::uri(
                $this->homepage,
                array('allowed_schemes' => array('http', 'https')
                )
            )
        ) {
            throw new ApiValidationException(
                _('Homepage is not a valid URL.')
            );
        }
    }

    function validateFullname()
    {
        if (!is_null($this->fullname) && mb_strlen($this->fullname) > 255) {
            throw new ApiValidationException(
                _('Full name is too long (max 255 chars).')
            );
        }
    }

    function validateDescription()
    {
        if (User_group::descriptionTooLong($this->description)) {
            throw new ApiValidationException(
                sprintf(
                    _('description is too long (max %d chars).'),
                    User_group::maxDescription()
                )
            );
        }
    }

    function validateLocation()
    {
        if (!is_null($this->location) && mb_strlen($this->location) > 255) {
            throw new ApiValidationException(
                _('Location is too long (max 255 chars).')
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
            throw new ApiValidationException(
                sprintf(
                    _('Too many aliases! Maximum %d.'),
                    common_config('group', 'maxaliases')
                )
            );
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
                        _('Invalid alias: "%s"'),
                        $alias
                    )
                );
            }

            if ($this->nicknameExists($alias)) {
                throw new ApiValidationException(
                    sprintf(
                        _('Alias "%s" already in use. Try another one.'),
                        $alias)
                );
            }

            // XXX assumes alphanum nicknames
            if (strcmp($alias, $this->nickname) == 0) {
                throw new ApiValidationException(
                    _('Alias can\'t be the same as nickname.')
                );
            }
        }

        return $aliases;
    }

}