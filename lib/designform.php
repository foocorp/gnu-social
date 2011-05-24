<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for choosing a design
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
 * @category  Form
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Form for choosing a design
 *
 * Used for choosing a site design, user design, or group design.
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class DesignForm extends Form
{
    /**
     * Return-to args
     */

    var $design     = null;
    var $actionurl  = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out       output channel
     * @param Design        $design    initial design
     * @param Design        $actionurl url of action (for form posting)
     */
    function __construct($out, $design, $actionurl)
    {
        parent::__construct($out);

        $this->design     = $design;
        $this->actionurl = $actionurl;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'design';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_design';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return $this->actionurl;
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend of form for changing the page design.
        $this->out->element('legend', null, _('Change design'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->backgroundData();

        $this->out->elementEnd('fieldset');

        $this->out->elementStart('fieldset', array('id' => 'settings_design_color'));
        // TRANS: Fieldset legend on profile design page to change profile page colours.
        $this->out->element('legend', null, _('Change colours'));
        $this->colourData();
        $this->out->elementEnd('fieldset');

        $this->out->elementStart('fieldset');

        // TRANS: Button text on profile design page to immediately reset all colour settings to default.
        $this->out->submit('defaults', _('Use defaults'), 'submit form_action-default',
                           // TRANS: Title for button on profile design page to reset all colour settings to default.
                           'defaults', _('Restore default designs.'));

        $this->out->element('input', array('id' => 'settings_design_reset',
                                           'type' => 'reset',
                                           // TRANS: Button text on profile design page to reset all colour settings to default without saving.
                                           'value' => _m('BUTTON', 'Reset'),
                                           'class' => 'submit form_action-primary',
                                           // TRANS: Title for button on profile design page to reset all colour settings to default without saving.
                                           'title' => _('Reset back to default.')));
    }

    function backgroundData()
    {
        $this->out->elementStart('ul', 'form_data');
        $this->out->elementStart('li');
        $this->out->element('label', array('for' => 'design_background-image_file'),
                            // TRANS: Label in form on profile design page.
                            // TRANS: Field contains file name on user's computer that could be that user's custom profile background image.
                            _('Upload file'));
        $this->out->element('input', array('name' => 'design_background-image_file',
                                           'type' => 'file',
                                           'id' => 'design_background-image_file'));
        // TRANS: Instructions for form on profile design page.
        $this->out->element('p', 'form_guide', _('You can upload your personal ' .
                                                 'background image. The maximum file size is 2MB.'));
        $this->out->element('input', array('name' => 'MAX_FILE_SIZE',
                                           'type' => 'hidden',
                                           'id' => 'MAX_FILE_SIZE',
                                           'value' => ImageFile::maxFileSizeInt()));
        $this->out->elementEnd('li');

        if (!empty($this->design->backgroundimage)) {

            $this->out->elementStart('li', array('id' =>
                                                 'design_background-image_onoff'));

            $this->out->element('img', array('src' =>
                                             Design::url($this->design->backgroundimage)));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_on',
                           'class' => 'radio',
                           'value' => 'on');

            if ($this->design->disposition & BACKGROUND_ON) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);

            $this->out->element('label', array('for' => 'design_background-image_on',
                                               'class' => 'radio'),
                                // TRANS: Radio button on profile design page that will enable use of the uploaded profile image.
                                _m('RADIO', 'On'));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_off',
                           'class' => 'radio',
                           'value' => 'off');

            if ($this->design->disposition & BACKGROUND_OFF) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);

            $this->out->element('label', array('for' => 'design_background-image_off',
                                               'class' => 'radio'),
                                // TRANS: Radio button on profile design page that will disable use of the uploaded profile image.
                                _m('RADIO', 'Off'));
            // TRANS: Form guide for a set of radio buttons on the profile design page that will enable or disable
            // TRANS: use of the uploaded profile image.
            $this->out->element('p', 'form_guide', _('Turn background image on or off.'));
            $this->out->elementEnd('li');

            $this->out->elementStart('li');
            $this->out->checkbox('design_background-image_repeat',
                                 // TRANS: Checkbox label on profile design page that will cause the profile image to be tiled.
                                 _('Tile background image'),
                                 ($this->design->disposition & BACKGROUND_TILE) ? true : false);
            $this->out->elementEnd('li');
        }

        $this->out->elementEnd('ul');
    }

    function colourData()
    {
        $this->out->elementStart('ul', 'form_data');

        try {

            $bgcolor = new WebColor($this->design->backgroundcolor);

            $this->out->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page background colour.
            $this->out->element('label', array('for' => 'swatch-1'), _('Background'));
            $this->out->element('input', array('name' => 'design_background',
                                               'type' => 'text',
                                               'id' => 'swatch-1',
                                               'class' => 'swatch',
                                               'maxlength' => '7',
                                               'size' => '7',
                                               'value' => ''));
            $this->out->elementEnd('li');

            $ccolor = new WebColor($this->design->contentcolor);

            $this->out->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page content colour.
            $this->out->element('label', array('for' => 'swatch-2'), _('Content'));
            $this->out->element('input', array('name' => 'design_content',
                                               'type' => 'text',
                                               'id' => 'swatch-2',
                                               'class' => 'swatch',
                                               'maxlength' => '7',
                                               'size' => '7',
                                               'value' => ''));
            $this->out->elementEnd('li');

            $sbcolor = new WebColor($this->design->sidebarcolor);

            $this->out->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page sidebar colour.
            $this->out->element('label', array('for' => 'swatch-3'), _('Sidebar'));
            $this->out->element('input', array('name' => 'design_sidebar',
                                               'type' => 'text',
                                               'id' => 'swatch-3',
                                               'class' => 'swatch',
                                               'maxlength' => '7',
                                               'size' => '7',
                                               'value' => ''));
            $this->out->elementEnd('li');

            $tcolor = new WebColor($this->design->textcolor);

            $this->out->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page text colour.
            $this->out->element('label', array('for' => 'swatch-4'), _('Text'));
            $this->out->element('input', array('name' => 'design_text',
                                               'type' => 'text',
                                               'id' => 'swatch-4',
                                               'class' => 'swatch',
                                               'maxlength' => '7',
                                               'size' => '7',
                                               'value' => ''));
            $this->out->elementEnd('li');

            $lcolor = new WebColor($this->design->linkcolor);

            $this->out->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page links colour.
            $this->out->element('label', array('for' => 'swatch-5'), _('Links'));
            $this->out->element('input', array('name' => 'design_links',
                                               'type' => 'text',
                                               'id' => 'swatch-5',
                                               'class' => 'swatch',
                                               'maxlength' => '7',
                                               'size' => '7',
                                               'value' => ''));
            $this->out->elementEnd('li');

        } catch (WebColorException $e) {
            common_log(LOG_ERR, 'Bad color values in design ID: ' .$this->design->id);
        }

        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        // TRANS: Button text on profile design page to save settings.
        $this->out->submit('save', _m('BUTTON','Save'), 'submit form_action-secondary',
                           // TRANS: Title for button on profile design page to save settings.
                           'save', _('Save design.'));
    }
}
