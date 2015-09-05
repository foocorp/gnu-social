<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2012, StatusNet, Inc.
 *
 * ModLogPlugin.php
 *
 * PHP version 5
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
 *
 * @category  Moderation
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Moderation logging
 *
 * Shows a history of moderation for this user in the sidebar
 *
 * @category  Moderation
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ModLogPlugin extends Plugin
{
    const VIEWMODLOG = 'ModLogPlugin::VIEWMODLOG';

    /**
     * Database schema setup
     *
     * We keep a moderation log table
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('mod_log', ModLog::schemaDef());

        return true;
    }

    function onEndGrantRole($profile, $role)
    {
        $modlog = new ModLog();

        $modlog->id         = UUID::gen();
        $modlog->profile_id = $profile->id;

        $cur = common_current_user();
        
        if (!empty($cur)) {
            $modlog->moderator_id = $cur->id;
        }

        $modlog->role     = $role;
        $modlog->is_grant = 1;
        $modlog->created  = common_sql_now();

        $modlog->insert();

        return true;
    }

    function onEndRevokeRole($profile, $role)
    {
        $modlog = new ModLog();

        $modlog->id = UUID::gen();

        $modlog->profile_id = $profile->id;

        $scoped = Profile::current();
        
        if ($scoped instanceof Profile) {
            $modlog->moderator_id = $scoped->getID();
        }

        $modlog->role     = $role;
        $modlog->is_grant = 0;
        $modlog->created  = common_sql_now();

        $modlog->insert();

        return true;
    }

    function onEndShowSections(Action $action)
    {
        if (!$action instanceof ShowstreamAction) {
            // early return for actions we're not interested in
            return true;
        }

        $scoped = $action->getScoped();
        if (!$scoped instanceof Profile || !$scoped->hasRight(self::VIEWMODLOG)) {
            // only continue if we are allowed to VIEWMODLOG
            return true;
        }

        $profile = $action->getTarget();

        $ml = new ModLog();

        $ml->profile_id = $profile->getID();
        $ml->orderBy("created");

        $cnt = $ml->find();

        if ($cnt > 0) {

            $action->elementStart('div', array('id' => 'entity_mod_log',
                                               'class' => 'section'));

            $action->element('h2', null, _('Moderation'));

            $action->elementStart('table');

            while ($ml->fetch()) {
                $action->elementStart('tr');
                $action->element('td', null, strftime('%y-%m-%d', strtotime($ml->created)));
                $action->element('td', null, sprintf(($ml->is_grant) ? _('+%s') : _('-%s'), $ml->role));
                $action->elementStart('td');
                if ($ml->moderator_id) {
                    $mod = Profile::getByID($ml->moderator_id);
                    if (empty($mod)) {
                        $action->text(_('[unknown]'));
                    } else {
                        $action->element('a', array('href' => $mod->getUrl(),
                                                    'title' => $mod->getFullname()),
                                         $mod->getNickname());
                    }
                } else {
                    $action->text(_('[unknown]'));
                }
                $action->elementEnd('td');
                $action->elementEnd('tr');
            }

            $action->elementEnd('table');

            $action->elementEnd('div');
        }
    }

    function onUserRightsCheck($profile, $right, &$result) {
        switch ($right) {
        case self::VIEWMODLOG:
            $result = ($profile->hasRole(Profile_role::MODERATOR) || $profile->hasRole('modhelper'));
            return false;
        default:
            return true;
        }
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'ModLog',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:ModLog',
                            'description' =>
                            _m('Show the moderation history for a profile in the sidebar'));
        return true;
    }
}
