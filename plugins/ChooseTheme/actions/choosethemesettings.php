<?php
/**
 * ChooseTheme - GNU social plugin enabling user to select preferred theme
 * Copyright (C) 2015, kollektivet0x242.
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
 * @author   Knut Erik Hollund <knut.erik@unlike.no>
 * @copyright 2015 kollektivet0x242. http://www.kollektivet0x242.no
 *
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 */

if (!defined('STATUSNET') && !defined('GNUSOCIAL')) {
    exit(1);
}

class ChooseThemeSettingsAction extends SettingsAction {
	

    /**
     * Title of the page
     * @return string Page title
     */
    function title() {
        // TRANS: Page title.
        return _m('Choose theme settings');
    }

    /**
     * Instructions for use
     * @return string Instructions for use
     */
    function getInstructions() {
        // TRANS: Page instructions.
        return _m('Choose theme');
    }

    /**
     * Show the form for ChooseTheme
     * @return void
     */
    function showContent() {

	$site_theme = common_config('site','theme');
	$prefs = $this->scoped->getPref('chosen_theme', 'theme',$site_theme);
        if ($prefs === null) {
            common_debug('No chosen theme found in database for user.');
        }
	
	//Get a list of available themes on instance
	$available_themes = Theme::listAvailable();		
	$chosenone = array_search($prefs,$available_themes,true);
        $form = new ChooseThemeForm($this, $chosenone);
        $form->show();
    }


    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     * @return void
     */
    function handlePost() {
		
	//Get a list of available themes on instance
	$available_themes = Theme::listAvailable();
	$chosen_theme = $available_themes[(int)$this->arg('dwct','0')];
		
        $this->success = true;
        $this->msg = _m('Settings saved.');

	$this->success = $this->scoped->setPref('chosen_theme', 'theme', $chosen_theme);
        // TRANS: Confirmation shown when user profile settings are saved.        
	if(!$this->success) $this->msg = _('No valid theme chosen.');
 
        $this->showForm(_($this->msg), $this->success);
    }
}


class ChooseThemeForm extends Form {

    protected $prefs = null;
    

    function __construct($out, $prefs) {
        parent::__construct($out);

        if ($prefs!=null) {
			$this->prefs = $prefs;
	} else {
			$prefs = common_config('site','theme');
	}
	
}

    /**
     * Visible or invisible data elements
     *
     * Display the form fields that make up the data of the form.
     * Sub-classes should overload this to show their data.
     * @return void
     */

    function formData() {

	//Get a list of available themes on instance
	$available_themes = Theme::listAvailable();
		
	//Remove theme 'licenses' from selectable themes.
	//The 'licenses' theme is not an actual theme and
	//will just mess-up the gui.
	$key = array_search('licenses',$available_themes);
	if($key!=false){
		unset($available_themes[$key]);
	}		
		
        $this->elementStart('fieldset');
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->dropdown('dwct',_m('Themes'),$available_themes,_m('Select a theme'),false, $this->prefs);
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
      
    }

    /**
     * Buttons for form actions
     *
     * Submit and cancel buttons (or whatever)
     * Sub-classes should overload this to show their own buttons.
     * @return void
     */

    function formActions()
    {
        $this->submit('submit', _('Save'));
    }

    /**
     * ID of the form
     *
     * Should be unique on the page. Sub-classes should overload this
     * to show their own IDs.
     * @return int ID of the form
     */

    function id() {
        return 'form_choosetheme_prefs';
    }

    /**
     * Action of the form.
     *
     * URL to post to. Should be overloaded by subclasses to give
     * somewhere to post to.
     * @return string URL to post to
     */

    function action() {	
        return common_local_url('choosethemesettings');
    }

    /**
     * Class of the form. May include space-separated list of multiple classes.
     *
     * @return string the form's class
     */

    function formClass() {
        return 'form_settings';
    }
}
