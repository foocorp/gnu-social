<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Design administration panel
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
 * @category  Settings
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Administer design settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class DesignadminpanelAction extends AdminPanelAction
{
    /* The default site design */
    var $design = null;

    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Message used as title for design settings for the site.
        return _('Design');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for design adminsitration panel.
        return _('Design settings for this StatusNet site');
    }

    /**
     * Get the default design and show the design admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $this->design = Design::siteDesign();
        $form = new DesignAdminPanelForm($this);
        $form->show();
        return;
    }

    /**
     * Save settings from the form
     *
     * @return void
     */
    function saveSettings()
    {
        if ($this->arg('save')) {
            $this->saveDesignSettings();
        } else if ($this->arg('defaults')) {
            $this->restoreDefaults();
        } else {
            // TRANS: Client error displayed when the submitted form contains unexpected data.
            $this->clientError(_('Unexpected form submission.'));
        }
    }

    /**
     * Save the new design settings
     *
     * @return void
     */
    function saveDesignSettings()
    {
        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
            // TRANS: Client error displayed when the number of bytes in a POST request exceeds a limit.
            // TRANS: %s is the number of bytes of the CONTENT_LENGTH.
            $msg = _m('The server was unable to handle that much POST data (%s byte) due to its current configuration.',
                      'The server was unable to handle that much POST data (%s bytes) due to its current configuration.',
                      intval($_SERVER['CONTENT_LENGTH']));
            $this->clientException(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        // check for file uploads

        $bgimage = $this->saveBackgroundImage();
        $customTheme = $this->saveCustomTheme();

        $oldtheme = common_config('site', 'theme');
        if ($customTheme) {
            // This feels pretty hacky :D
            $this->args['theme'] = $customTheme;
            $themeChanged = true;
        } else {
            $themeChanged = ($this->trimmed('theme') != $oldtheme);
        }

        static $settings = array('theme', 'logo', 'ssllogo');

        $values = array();

        foreach ($settings as $setting) {
            $values[$setting] = $this->trimmed($setting);
        }

        $this->validate($values);

        $config = new Config();

        $config->query('BEGIN');

        if ($themeChanged) {
            // If the theme has changed, reset custom colors and let them pick
            // up the new theme's defaults.
            $colors = array('background', 'content', 'sidebar', 'text', 'link');
            foreach ($colors as $colorKey) {
                // Clear from global config so we see defaults on this page...
                $GLOBALS['config']['design'][$colorKey . 'color'] = false;

                // And remove old settings from DB...
                $this->deleteSetting('design', $colorKey . 'color');
            }
        } else {
            // Only save colors from the form if the theme has not changed.
            //
            // @fixme a future more ajaxy form should allow theme switch
            // and color customization in one step.

            $bgcolor = new WebColor($this->trimmed('design_background'));
            $ccolor  = new WebColor($this->trimmed('design_content'));
            $sbcolor = new WebColor($this->trimmed('design_sidebar'));
            $tcolor  = new WebColor($this->trimmed('design_text'));
            $lcolor  = new WebColor($this->trimmed('design_links'));

            Config::save('design', 'backgroundcolor', $bgcolor->intValue());
            Config::save('design', 'contentcolor', $ccolor->intValue());
            Config::save('design', 'sidebarcolor', $sbcolor->intValue());
            Config::save('design', 'textcolor', $tcolor->intValue());
            Config::save('design', 'linkcolor', $lcolor->intValue());
        }

        $onoff = $this->arg('design_background-image_onoff');

        $on   = false;
        $off  = false;

        if ($onoff == 'on') {
            $on = true;
        } else {
            $off = true;
        }

        $tile = $this->boolean('design_background-image_repeat');

        // Hack to use Design's bit setter
        $scratch = new Design();
        $scratch->setDisposition($on, $off, $tile);

        Config::save('design', 'disposition', $scratch->disposition);

        foreach ($settings as $setting) {
            Config::save('site', $setting, $values[$setting]);
        }

        if (isset($bgimage)) {
            Config::save('design', 'backgroundimage', $bgimage);
        }

        if (common_config('custom_css', 'enabled')) {
            $css = $this->arg('css');
            if ($css != common_config('custom_css', 'css')) {
                Config::save('custom_css', 'css', $css);
            }
        }

        $config->query('COMMIT');
    }

    /**
     * Restore the default design
     *
     * @return void
     */
    function restoreDefaults()
    {
        $this->deleteSetting('site', 'logo');
        $this->deleteSetting('site', 'ssllogo');
        $this->deleteSetting('site', 'theme');

        $settings = array(
            'theme', 'backgroundimage', 'backgroundcolor', 'contentcolor',
            'sidebarcolor', 'textcolor', 'linkcolor', 'disposition'
        );

        foreach ($settings as $setting) {
            $this->deleteSetting('design', $setting);
        }

        // XXX: Should we restore the default dir settings, etc.? --Z

        // XXX: I can't get it to show the new settings without forcing
        // this terrible reload -- FIX ME!
        common_redirect(common_local_url('designadminpanel'), 303);
    }

    /**
     * Save the background image if the user uploaded one
     *
     * @return string $filename the filename of the image
     */
    function saveBackgroundImage()
    {
        $filename = null;
        if (isset($_FILES['design_background-image_file']['error']) &&
            $_FILES['design_background-image_file']['error'] ==
            UPLOAD_ERR_OK) {

            $filepath = null;

            try {
                $imagefile =
                    ImageFile::fromUpload('design_background-image_file');
            } catch (Exception $e) {
                $this->clientError('Unable to save background image.');
                return;
            }

            // Note: site design background image has a special filename

            $filename = Design::filename('site-design-background',
                image_type_to_extension($imagefile->type),
                    common_timestamp());

            $filepath = Design::path($filename);

            move_uploaded_file($imagefile->filepath, $filepath);

            // delete any old backround img laying around

            if (isset($this->design->backgroundimage)) {
                @unlink(Design::path($design->backgroundimage));
            }

            return $filename;
        }
    }

    /**
     * Save the custom theme if the user uploaded one.
     *
     * @return mixed custom theme name, if succesful, or null if no theme upload.
     * @throws ClientException for invalid theme archives
     * @throws ServerException if trouble saving the theme files
     */
    function saveCustomTheme()
    {
        if (common_config('theme_upload', 'enabled') &&
            $_FILES['design_upload_theme']['error'] == UPLOAD_ERR_OK) {

            $upload = ThemeUploader::fromUpload('design_upload_theme');
            $basedir = common_config('local', 'dir');
            if (empty($basedir)) {
                $basedir = INSTALLDIR . '/local';
            }
            $name = 'custom'; // @todo allow multiples, custom naming?
            $outdir = $basedir . '/theme/' . $name;
            $upload->extract($outdir);
            return $name;
        } else {
            return null;
        }
    }

    /**
     * Attempt to validate setting values
     *
     * @return void
     */
    function validate(&$values)
    {
        if (!empty($values['logo']) &&
            !Validate::uri($values['logo'], array('allowed_schemes' => array('http', 'https')))) {
            // TRANS: Client error displayed when a logo URL does is not valid.
            $this->clientError(_('Invalid logo URL.'));
        }

        if (!empty($values['ssllogo']) &&
            !Validate::uri($values['ssllogo'], array('allowed_schemes' => array('https')))) {
            // TRANS: Client error displayed when an SSL logo URL is invalid.
            $this->clientError(_('Invalid SSL logo URL.'));
        }

        if (!in_array($values['theme'], Theme::listAvailable())) {
            // TRANS: Client error displayed when a theme is submitted through the form that is not in the theme list.
            // TRANS: %s is the chosen unavailable theme.
            $this->clientError(sprintf(_('Theme not available: %s.'), $values['theme']));
        }
    }

    /**
     * Add the Farbtastic stylesheet
     *
     * @return void
     */
    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink('js/farbtastic/farbtastic.css',null,'screen, projection, tv');
    }

    /**
     * Add the Farbtastic scripts
     *
     * @return void
     */
    function showScripts()
    {
        parent::showScripts();

        $this->script('farbtastic/farbtastic.js');
        $this->script('userdesign.go.js');

        $this->autofocus('design_background-image_file');
    }

}

class DesignAdminPanelForm extends AdminForm
{

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'form_design_admin_panel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_settings';
    }

    /**
     * HTTP method used to submit the form
     *
     * For image data we need to send multipart/form-data
     * so we set that here too
     *
     * @return string the method to use for submitting
     */
    function method()
    {
        $this->enctype = 'multipart/form-data';

        return 'post';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('designadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->showLogo();
        $this->showTheme();
        $this->showBackground();
        $this->showColors();
        $this->showAdvanced();
    }

    function showLogo()
    {
        $this->out->elementStart('fieldset', array('id' => 'settings_design_logo'));
        // TRANS: Fieldset legend for form to change logo.
        $this->out->element('legend', null, _('Change logo'));

        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input('logo',
                     // TRANS: Field label for StatusNet site logo.
                     _('Site logo'),
                     // TRANS: Title for field label for StatusNet site logo.
                     'Logo for the site (full URL).');
        $this->unli();

        $this->li();
        $this->input('ssllogo',
                     // TRANS: Field label for SSL StatusNet site logo.
                     _('SSL logo'),
                     // TRANS: Title for field label for SSL StatusNet site logo.
                     'Logo to show on SSL pages.');
        $this->unli();

        $this->out->elementEnd('ul');

        $this->out->elementEnd('fieldset');

    }

    function showTheme()
    {
        $this->out->elementStart('fieldset', array('id' => 'settings_design_theme'));
        // TRANS: Fieldset legend for form change StatusNet site's theme.
        $this->out->element('legend', null, _('Change theme'));

        $this->out->elementStart('ul', 'form_data');

        $themes = Theme::listAvailable();

        // XXX: listAvailable() can return an empty list if you
        // screw up your settings, so just in case:

        if (empty($themes)) {
            $themes = array('default', 'default');
        }

        asort($themes);
        $themes = array_combine($themes, $themes);

        $this->li();
        // TRANS: Field label for dropdown to choose site theme.
        $this->out->dropdown('theme', _('Site theme'),
                             // TRANS: Title for field label for dropdown to choose site theme.
                             $themes, _('Theme for the site.'),
                             false, $this->value('theme'));
        $this->unli();

        if (common_config('theme_upload', 'enabled')) {
            $this->li();
            // TRANS: Field label for uploading a cutom theme.
            $this->out->element('label', array('for' => 'design_upload_theme'), _('Custom theme'));
            $this->out->element('input', array('id' => 'design_upload_theme',
                                               'name' => 'design_upload_theme',
                                               'type' => 'file'));
            // TRANS: Form instructions for uploading a cutom StatusNet theme.
            $this->out->element('p', 'form_guide', _('You can upload a custom StatusNet theme as a .ZIP archive.'));
            $this->unli();
        }

        $this->out->elementEnd('ul');

        $this->out->elementEnd('fieldset');
    }

    function showBackground()
    {
        $design = $this->out->design;

        $this->out->elementStart('fieldset', array('id' =>
            'settings_design_background-image'));
        // TRANS: Fieldset legend for theme background image.
        $this->out->element('legend', null, _('Change background image'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->out->element('input', array('name' => 'MAX_FILE_SIZE',
                                          'type' => 'hidden',
                                          'id' => 'MAX_FILE_SIZE',
                                          'value' => ImageFile::maxFileSizeInt()));
        $this->out->element('label', array('for' => 'design_background-image_file'),
                               // TRANS: Field label for background image on theme designer page.
                                _('Background'));
        $this->out->element('input', array('name' => 'design_background-image_file',
                                     'type' => 'file',
                                     'id' => 'design_background-image_file'));
        $this->out->element('p', 'form_guide',
            // TRANS: Form guide for background image upload form on theme designer page.
            sprintf(_('You can upload a background image for the site. ' .
              'The maximum file size is %1$s.'), ImageFile::maxFileSize()));
        $this->unli();

        if (!empty($design->backgroundimage)) {

            $this->out->elementStart('li', array('id' =>
                'design_background-image_onoff'));

            $this->out->element('img', array('src' =>
                Design::url($design->backgroundimage)));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_on',
                           'class' => 'radio',
                           'value' => 'on');

            if ($design->disposition & BACKGROUND_ON) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);

            $this->out->element('label', array('for' => 'design_background-image_on',
                                          'class' => 'radio'),
                                          // TRANS: Used as radio button label to add a background image.
                                          _('On'));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_off',
                           'class' => 'radio',
                           'value' => 'off');

            if ($design->disposition & BACKGROUND_OFF) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);

            $this->out->element('label', array('for' => 'design_background-image_off',
                                          'class' => 'radio'),
                                          // TRANS: Used as radio button label to not add a background image.
                                          _('Off'));
            // TRANS: Form guide for turning background image on or off on theme designer page.
            $this->out->element('p', 'form_guide', _('Turn background image on or off.'));
            $this->unli();

            $this->li();
            $this->out->checkbox('design_background-image_repeat',
                            // TRANS: Checkbox label to title background image on theme designer page.
                            _('Tile background image'),
                            ($design->disposition & BACKGROUND_TILE) ? true : false);
            $this->unli();
        }

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
    }

    function showColors()
    {
        $design = $this->out->design;

        $this->out->elementStart('fieldset', array('id' => 'settings_design_color'));
        // TRANS: Fieldset legend for theme colors.
        $this->out->element('legend', null, _('Change colors'));

        $this->out->elementStart('ul', 'form_data');

        try {
            // @fixme avoid loop unrolling in non-performance-critical contexts like this

            $bgcolor = new WebColor($design->backgroundcolor);

            $this->li();
            // TRANS: Field label for background color selector.
            $this->out->element('label', array('for' => 'swatch-1'), _('Background'));
            $this->out->element('input', array('name' => 'design_background',
                                          'type' => 'text',
                                          'id' => 'swatch-1',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => ''));
            $this->unli();

            $ccolor = new WebColor($design->contentcolor);

            $this->li();
            // TRANS: Field label for content color selector.
            $this->out->element('label', array('for' => 'swatch-2'), _('Content'));
            $this->out->element('input', array('name' => 'design_content',
                                          'type' => 'text',
                                          'id' => 'swatch-2',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => ''));
            $this->unli();

            $sbcolor = new WebColor($design->sidebarcolor);

            $this->li();
            // TRANS: Field label for sidebar color selector.
            $this->out->element('label', array('for' => 'swatch-3'), _('Sidebar'));
            $this->out->element('input', array('name' => 'design_sidebar',
                                        'type' => 'text',
                                        'id' => 'swatch-3',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => ''));
            $this->unli();

            $tcolor = new WebColor($design->textcolor);

            $this->li();
            // TRANS: Field label for text color selector.
            $this->out->element('label', array('for' => 'swatch-4'), _('Text'));
            $this->out->element('input', array('name' => 'design_text',
                                        'type' => 'text',
                                        'id' => 'swatch-4',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => ''));
            $this->unli();

            $lcolor = new WebColor($design->linkcolor);

            $this->li();
            // TRANS: Field label for link color selector.
            $this->out->element('label', array('for' => 'swatch-5'), _('Links'));
            $this->out->element('input', array('name' => 'design_links',
                                         'type' => 'text',
                                         'id' => 'swatch-5',
                                         'class' => 'swatch',
                                         'maxlength' => '7',
                                         'size' => '7',
                                         'value' => ''));
            $this->unli();

        } catch (WebColorException $e) {
            // @fixme normalize them individually!
            common_log(LOG_ERR, 'Bad color values in site design: ' .
                $e->getMessage());
        }

        $this->out->elementEnd('fieldset');

        $this->out->elementEnd('ul');
    }

    function showAdvanced()
    {
        if (common_config('custom_css', 'enabled')) {
            $this->out->elementStart('fieldset', array('id' => 'settings_design_advanced'));
            // TRANS: Fieldset legend for advanced theme design settings.
            $this->out->element('legend', null, _('Advanced'));
            $this->out->elementStart('ul', 'form_data');

            $this->li();
            // TRANS: Field label for custom CSS.
            $this->out->element('label', array('for' => 'css'), _('Custom CSS'));
            $this->out->element('textarea', array('name' => 'css',
                                            'id' => 'css',
                                            'cols' => '50',
                                            'rows' => '10'),
                                strval(common_config('custom_css', 'css')));
            $this->unli();

            $this->out->elementEnd('fieldset');
            $this->out->elementEnd('ul');
        }
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        // TRANS: Button text for resetting theme settings.
        $this->out->submit('defaults', _m('BUTTON','Use defaults'), 'submit form_action-default',
                // TRANS: Title for button for resetting theme settings.
                'defaults', _('Restore default designs.'));

        $this->out->element('input', array('id' => 'settings_design_reset',
                                         'type' => 'reset',
                                         // TRANS: Button text for resetting theme settings.
                                         'value' => 'Reset',
                                         'class' => 'submit form_action-primary',
                                         // TRANS: Title for button for resetting theme settings.
                                         'title' => _('Reset back to default.')));

        $this->out->submit('save',
                           // TRANS: Button text for saving theme settings.
                           _m('BUTTON','Save'),
                           'submit form_action-secondary',
                           'save',
                           // TRANS: Title for button for saving theme settings.
                           _('Save design.'));
    }
}
