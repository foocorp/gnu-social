<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * UAP (Universal Ad Package) plugin
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Abstract superclass for advertising plugins
 *
 * Plugins for showing ads should derive from this plugin.
 *
 * Outputs the following ad types (based on UAP):
 *
 * Medium Rectangle 300x250
 * Rectangle        180x150
 * Leaderboard      728x90
 * Wide Skyscraper  160x600
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
abstract class UAPPlugin extends Plugin
{
    public $mediumRectangle = null;
    public $rectangle       = null;
    public $leaderboard     = null;
    public $wideSkyscraper  = null;

    /**
     * Output our dedicated stylesheet
     *
     * @param Action $action Action being shown
     *
     * @return boolean hook flag
     */
    public function onEndShowStylesheets(Action $action)
    {
        // XXX: allow override by theme
        $action->cssLink('css/uap.css', 'base', 'screen, projection, tv');
        return true;
    }

    /**
     * Add a medium rectangle ad at the beginning of sidebar
     *
     * @param Action $action Action being shown
     *
     * @return boolean hook flag
     */
    function onStartShowAside(Action $action)
    {
        if (!is_null($this->mediumRectangle)) {

            $action->elementStart('div',
                                  array('id' => 'ad_medium-rectangle',
                                        'class' => 'ad'));

            $this->showMediumRectangle($action);

            $action->elementEnd('div');
        }

        // XXX: Hack to force ads to show on single-notice pages

        if (!is_null($this->rectangle) &&
            $action->trimmed('action') == 'shownotice') {

            $action->elementStart('div', array('id' => 'aside_primary',
                                               'class' => 'aside'));

            if (Event::handle('StartShowSections', array($action))) {
                $action->showSections();
                Event::handle('EndShowSections', array($action));
            }

            $action->elementEnd('div');

            return false;
        }

        return true;
    }

    /**
     * Add a leaderboard in the header
     *
     * @param Action $action Action being shown
     *
     * @return boolean hook flag
     */

    function onEndShowHeader($action)
    {
        if (!is_null($this->leaderboard)) {
            $action->elementStart('div',
                                  array('id' => 'ad_leaderboard',
                                        'class' => 'ad'));
            $this->showLeaderboard($action);
            $action->elementEnd('div');
        }

        return true;
    }

    /**
     * Add a rectangle before aside sections
     *
     * @param Action $action Action being shown
     *
     * @return boolean hook flag
     */
    function onStartShowSections(Action $action)
    {
        if (!is_null($this->rectangle)) {
            $action->elementStart('div',
                                  array('id' => 'ad_rectangle',
                                        'class' => 'ad'));
            $this->showRectangle($action);
            $action->elementEnd('div');
        }

        return true;
    }

    /**
     * Add a wide skyscraper after the aside
     *
     * @param Action $action Action being shown
     *
     * @return boolean hook flag
     */
    function onEndShowAside(Action $action)
    {
        if (!is_null($this->wideSkyscraper)) {
            $action->elementStart('div',
                                  array('id' => 'ad_wide-skyscraper',
                                        'class' => 'ad'));

            $this->showWideSkyscraper($action);

            $action->elementEnd('div');
        }
        return true;
    }

    /**
     * Show a medium rectangle ad
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    abstract protected function showMediumRectangle($action);

    /**
     * Show a rectangle ad
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    abstract protected function showRectangle($action);

    /**
     * Show a wide skyscraper ad
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    abstract protected function showWideSkyscraper($action);

    /**
     * Show a leaderboard ad
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    abstract protected function showLeaderboard($action);
}
