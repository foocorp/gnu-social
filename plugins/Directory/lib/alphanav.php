<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to display an alphabet menu
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
 * @category  Widget
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Outputs a fancy alphabet letter navigation menu
 *
 * @category Widget
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */
class AlphaNav extends Widget
{
    protected $action  = null;
    protected $filters = array();

    /**
     * Prepare the widget for use
     *
     * @param Action  $action  the current action
     * @param boolean $numbers whether to output 0..9
     * @param Array   $prepend array of filters to prepend
     * @param Array   $append  array of filters to append
     */
    function __construct(
            $action  = null,
            $numbers = false,
            $prepend = false,
            $append  = false
    )
    {
        parent::__construct($action);

        $this->action  = $action;

        if ($prepend) {
            $this->filters = array_merge($prepend, $this->filters);
        }

        if ($numbers) {
            $this->filters = array_merge($this->filters, range(0, 9));
        }

        $this->filters = array_merge($this->filters, range('A', 'Z'));

        if ($append) {
            $this->filters = array_merge($this->filters, $append);
        }
    }

    /**
     * Show the widget
     *
     * Emit the HTML for the widget, using the configured outputter.
     *
     * @return void
     */
    function show()
    {
        $actionName = $this->action->trimmed('action');

        $this->action->elementStart('div', array('class' => 'alpha_nav'));

        for ($i = 0, $size = sizeof($this->filters); $i < $size; $i++) {

            $filter = $this->filters[$i];
            $classes = '';

            // Add some classes for styling
            if ($i == 0) {
                $classes .= 'first '; // first filter in the list
            } elseif ($i == $size - 1) {
                $classes .= 'last ';  // last filter in the list
            }

            // hack to get around $m->connect(array('action' => 'all, 'nickname' => $nickname));
            if (strtolower($filter) == 'all') {
                $href = common_local_url($actionName);
            } else {
                $href = common_local_url(
                    $actionName,
                    array('filter' => strtolower($filter))
                );
            }

            $params  = array('href' => $href);

            // sort column
            if (!empty($this->action->sort)) {
                $params['sort'] = $this->action->sort;
            }

            // sort order
            if ($this->action->reverse) {
                $params['reverse'] = 'true';
            }

            $current = $this->action->arg('filter');

            // Highlight the selected filter. If there is no selected
            // filter, highlight the last filter in the list (all)
            if (!isset($current) && $i == ($size - 1)
                || $current === strtolower($filter)) {
                $classes .= 'current ';
            }

            if (!empty($classes)) {
                $params['class'] = trim($classes);
            }

            $this->action->element('a', $params, $filter);
        }

        $this->action->elementEnd('div');
    }
}
