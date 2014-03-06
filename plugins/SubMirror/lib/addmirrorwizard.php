<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @package   StatusNet
 * @copyright 2010-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class AddMirrorWizard extends Widget
{
    /**
     * Name of the form
     *
     * Sub-classes should overload this with the name of their form.
     *
     * @return void
     */
    function formLegend()
    {
    }

    /**
     * Visible or invisible data elements
     *
     * Display the form fields that make up the data of the form.
     * Sub-classes should overload this to show their data.
     *
     * @return void
     */
    function show()
    {
        $this->out->elementStart('div', array('id' => 'add-mirror-wizard'));

        $providers = $this->providers();
        $this->showProviders($providers);

        $this->out->elementEnd('div');
    }

    function providers()
    {
        return array(
            /*
            // We could accept hostname & username combos here, or
            // webfingery combinations as for remote users.
            array(
                'id' => 'gnusocial',
                'name' => _m('GNU social'),
            ),
             */
            /*
            // WordPress was on our list some whiles ago, but not sure
            // what we can actually do here. Search on Wordpress.com hosted
            // sites, or ?
            array(
                'id' => 'wordpress',
                'name' => _m('WordPress'),
            ),
             */
            array(
                'id' => 'feed',
                // TRANS: Name for possible feed provider.
                'name' => _m('RSS or Atom feed'),
            ),
        );
    }

    function showProviders(array $providers)
    {
        $out = $this->out;

        $out->elementStart('div', 'provider-list');
        // TRANS: Heading for feed mirroring selection form.
        $out->element('h2', null, _m('Select a feed provider'));
        $out->elementStart('table');
        foreach ($providers as $provider) {
            $icon = common_path('plugins/SubMirror/images/providers/' . $provider['id'] . '.png');
            $targetUrl = common_local_url('mirrorsettings', array('provider' => $provider['id']));

            $out->elementStart('tr', array('class' => 'provider'));
            $out->elementStart('td');

            $out->elementStart('div', 'provider-heading');
            $out->element('img', array('src' => $icon));
            $out->element('a', array('href' => $targetUrl), $provider['name']);
            $out->elementEnd('div');

            $out->elementEnd('td');
            $out->elementEnd('tr');
        }
        $out->elementEnd('table');
        $out->elementEnd('div');
    }

    /**
     * Buttons for form actions
     *
     * Submit and cancel buttons (or whatever)
     * Sub-classes should overload this to show their own buttons.
     *
     * @return void
     */
    function formActions()
    {
    }

    /**
     * ID of the form
     *
     * Should be unique on the page. Sub-classes should overload this
     * to show their own IDs.
     *
     * @return string ID of the form
     */
    function id()
    {
        return 'add-mirror-wizard';
    }

    /**
     * Action of the form.
     *
     * URL to post to. Should be overloaded by subclasses to give
     * somewhere to post to.
     *
     * @return string URL to post to
     */
    function action()
    {
        return common_local_url('addmirror');
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_settings';
    }
}
