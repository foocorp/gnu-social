<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for all actions (~views)
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
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Base class for all actions
 *
 * This is the base class for all actions in the package. An action is
 * more or less a "view" in an MVC framework.
 *
 * Actions are responsible for extracting and validating parameters; using
 * model classes to read and write to the database; and doing ouput.
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */
class Action extends HTMLOutputter // lawsuit
{
    // This should be protected/private in the future
    public $args = array();

    // Action properties, set per-class
    protected $action = false;
    protected $ajax   = false;
    protected $menus  = true;
    protected $needLogin = false;
    protected $needPost = false;    // implies canPost if true
    protected $canPost = false;     // can this action handle POST method?

    // The currently scoped profile (normally Profile::current; from $this->auth_user for API)
    protected $scoped = null;

    // Related to front-end user representation
    protected $format = null;
    protected $error  = null;
    protected $msg    = null;

    /**
     * Constructor
     *
     * Just wraps the HTMLOutputter constructor.
     *
     * @param string  $output URI to output to, default = stdout
     * @param boolean $indent Whether to indent output, default true
     *
     * @see XMLOutputter::__construct
     * @see HTMLOutputter::__construct
     */
    function __construct($output='php://output', $indent=null)
    {
        parent::__construct($output, $indent);
    }

    function getError()
    {
        return $this->error;
    }

    function getInfo()
    {
        return $this->msg;
    }

    public function handleError($e)
    {
        if ($e instanceof ClientException) {
            $this->clientError($e->getMessage(), $e->getCode());
        } elseif ($e instanceof ServerException) {
            $this->serverError($e->getMessage(), $e->getCode());
        } else {
            // If it wasn't specified more closely which kind of exception it was
            $this->serverError($e->getMessage(), 500);
        }
    }

    static public function run(array $args=array(), $output='php://output', $indent=null) {
        $class = get_called_class();
        $action = new $class($output, $indent);
        set_exception_handler(array($action, 'handleError'));
        $action->execute($args);
        return $action;
    }

    public function execute(array $args=array()) {
        // checkMirror stuff
        if (common_config('db', 'mirror') && $this->isReadOnly($args)) {
            if (is_array(common_config('db', 'mirror'))) {
                // "load balancing", ha ha
                $arr = common_config('db', 'mirror');
                $k = array_rand($arr);
                $mirror = $arr[$k];
            } else {
                $mirror = common_config('db', 'mirror');
            }

            // everyone else uses the mirror
            common_config_set('db', 'database', $mirror);
        }

        if (Event::handle('StartActionExecute', array($this, &$args))) {
            $prepared = $this->prepare($args);
            if ($prepared) {
                $this->handle($args);
            } else {
                common_debug('Prepare failed for Action.');
            }

            $this->flush();
            Event::handle('EndActionExecute', array($this));
        }
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    protected function prepare(array $args=array())
    {
        if ($this->needPost && !$this->isPost()) {
            // TRANS: Client error. POST is a HTTP command. It should not be translated.
            $this->clientError(_('This method requires a POST.'), 405);
        }

        // needPost, of course, overrides canPost if true
        if (!$this->canPost) {
            $this->canPost = $this->needPost;
        }

        $this->args = common_copy_args($args);

        // This could be set with get_called_action and then
        // chop off 'Action' from the class name. In lower case.
        $this->action = strtolower($this->trimmed('action'));

        if ($this->ajax || $this->boolean('ajax')) {
            // check with GNUsocial::isAjax()
            GNUsocial::setAjax(true);
        }

        if ($this->needLogin) {
            $this->checkLogin(); // if not logged in, this redirs/excepts
        }

        $this->updateScopedProfile();

        return true;
    }

    public function updateScopedProfile()
    {
        $this->scoped = Profile::current();
        return $this->scoped;
    }

    public function getScoped()
    {
        return ($this->scoped instanceof Profile) ? $this->scoped : null;
    }

    // Must be run _after_ prepare
    public function getActionName()
    {
        return $this->action;
    }

    public function isAction(array $names)
    {
        foreach ($names as $class) {
            // PHP is case insensitive, and we have stuff like ApiUpperCaseAction,
            // but we at least make a point out of wanting to do stuff case-sensitive.
            $class = ucfirst($class) . 'Action';
            if ($this instanceof $class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Show page, a template method.
     *
     * @return nothing
     */
    public function showPage()
    {
        if (GNUsocial::isAjax()) {
            self::showAjax();
            return;
        }
        if (Event::handle('StartShowHTML', array($this))) {
            $this->startHTML();
            $this->flush();
            Event::handle('EndShowHTML', array($this));
        }
        if (Event::handle('StartShowHead', array($this))) {
            $this->showHead();
            $this->flush();
            Event::handle('EndShowHead', array($this));
        }
        if (Event::handle('StartShowBody', array($this))) {
            $this->showBody();
            Event::handle('EndShowBody', array($this));
        }
        if (Event::handle('StartEndHTML', array($this))) {
            $this->endHTML();
            Event::handle('EndEndHTML', array($this));
        }
    }

    public function showAjax()
    {
        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        // TRANS: Title for conversation page.
        $this->element('title', null, $this->title());
        $this->elementEnd('head');
        $this->elementStart('body');
        if ($this->getError()) {
            $this->element('p', array('id'=>'error'), $this->getError());
        } else {
            $this->showContent();
        }
        $this->elementEnd('body');
        $this->endHTML();
    }

    function endHTML()
    {
        global $_startTime;

        if (isset($_startTime)) {
            $endTime = microtime(true);
            $diff = round(($endTime - $_startTime) * 1000);
            $this->raw("<!-- ${diff}ms -->");
        }

        return parent::endHTML();
    }

    /**
     * Show head, a template method.
     *
     * @return nothing
     */
    function showHead()
    {
        // XXX: attributes (profile?)
        $this->elementStart('head');
        if (Event::handle('StartShowHeadElements', array($this))) {
            if (Event::handle('StartShowHeadTitle', array($this))) {
                $this->showTitle();
                Event::handle('EndShowHeadTitle', array($this));
            }
            $this->showShortcutIcon();
            $this->showStylesheets();
            $this->showOpenSearch();
            $this->showFeeds();
            $this->showDescription();
            $this->extraHead();
            Event::handle('EndShowHeadElements', array($this));
        }
        $this->elementEnd('head');
    }

    /**
     * Show title, a template method.
     *
     * @return nothing
     */
    function showTitle()
    {
        $this->element('title', null,
                       // TRANS: Page title. %1$s is the title, %2$s is the site name.
                       sprintf(_('%1$s - %2$s'),
                               $this->title(),
                               common_config('site', 'name')));
    }

    /**
     * Returns the page title
     *
     * SHOULD overload
     *
     * @return string page title
     */

    function title()
    {
        // TRANS: Page title for a page without a title set.
        return _('Untitled page');
    }

    /**
     * Show themed shortcut icon
     *
     * @return nothing
     */
    function showShortcutIcon()
    {
        if (is_readable(INSTALLDIR . '/theme/' . common_config('site', 'theme') . '/favicon.ico')) {
            $this->element('link', array('rel' => 'shortcut icon',
                                         'href' => Theme::path('favicon.ico')));
        } else {
            // favicon.ico should be HTTPS if the rest of the page is
            $this->element('link', array('rel' => 'shortcut icon',
                                         'href' => common_path('favicon.ico', GNUsocial::isHTTPS())));
        }

        if (common_config('site', 'mobile')) {
            if (is_readable(INSTALLDIR . '/theme/' . common_config('site', 'theme') . '/apple-touch-icon.png')) {
                $this->element('link', array('rel' => 'apple-touch-icon',
                                             'href' => Theme::path('apple-touch-icon.png')));
            } else {
                $this->element('link', array('rel' => 'apple-touch-icon',
                                             'href' => common_path('apple-touch-icon.png')));
            }
        }
    }

    /**
     * Show stylesheets
     *
     * @return nothing
     */
    function showStylesheets()
    {
        if (Event::handle('StartShowStyles', array($this))) {

            // Use old name for StatusNet for compatibility on events

            if (Event::handle('StartShowStylesheets', array($this))) {
                $this->primaryCssLink(null, 'screen, projection, tv, print');
                Event::handle('EndShowStylesheets', array($this));
            }

            $this->cssLink('js/extlib/jquery-ui/css/smoothness/jquery-ui.css');

            if (Event::handle('StartShowUAStyles', array($this))) {
                Event::handle('EndShowUAStyles', array($this));
            }

            Event::handle('EndShowStyles', array($this));

            if (common_config('custom_css', 'enabled')) {
                $css = common_config('custom_css', 'css');
                if (Event::handle('StartShowCustomCss', array($this, &$css))) {
                    if (trim($css) != '') {
                        $this->style($css);
                    }
                    Event::handle('EndShowCustomCss', array($this));
                }
            }
        }
    }

    function primaryCssLink($mainTheme=null, $media=null)
    {
        $theme = new Theme($mainTheme);

        // Some themes may have external stylesheets
        foreach ($theme->getExternals() as $url) {
            $this->cssLink($url, $mainTheme, $media);
        }

        // If the currently-selected theme has dependencies on other themes,
        // we'll need to load their display.css files as well in order.
        $baseThemes = $theme->getDeps();
        foreach ($baseThemes as $baseTheme) {
            $this->cssLink('css/display.css', $baseTheme, $media);
        }
        $this->cssLink('css/display.css', $mainTheme, $media);

        // Additional styles for RTL languages
        if (is_rtl(common_language())) {
            if (file_exists(Theme::file('css/rtl.css'))) {
                $this->cssLink('css/rtl.css', $mainTheme, $media);
            }
        }
    }

    /**
     * Show javascript headers
     *
     * @return nothing
     */
    function showScripts()
    {
        if (Event::handle('StartShowScripts', array($this))) {
            if (Event::handle('StartShowJQueryScripts', array($this))) {
                $this->script('extlib/jquery.js');
                $this->script('extlib/jquery.form.js');
                $this->script('extlib/jquery-ui/jquery-ui.js');
                $this->script('extlib/jquery.cookie.js');

                Event::handle('EndShowJQueryScripts', array($this));
            }
            if (Event::handle('StartShowStatusNetScripts', array($this))) {
                $this->script('util.js');
                $this->script('xbImportNode.js');

                // This route isn't available in single-user mode.
                // Not sure why, but it causes errors here.
                $this->inlineScript('var _peopletagAC = "' .
                                    common_local_url('peopletagautocomplete') . '";');
                $this->showScriptMessages();
                $this->showScriptVariables();
                // Anti-framing code to avoid clickjacking attacks in older browsers.
                // This will show a blank page if the page is being framed, which is
                // consistent with the behavior of the 'X-Frame-Options: SAMEORIGIN'
                // header, which prevents framing in newer browser.
                if (common_config('javascript', 'bustframes')) {
                    $this->inlineScript('if (window.top !== window.self) { document.write = ""; window.top.location = window.self.location; setTimeout(function () { document.body.innerHTML = ""; }, 1); window.self.onload = function () { document.body.innerHTML = ""; }; }');
                }
                Event::handle('EndShowStatusNetScripts', array($this));
            }
            Event::handle('EndShowScripts', array($this));
        }
    }

    /**
     * Exports a map of localized text strings to JavaScript code.
     *
     * Plugins can add to what's exported by hooking the StartScriptMessages or EndScriptMessages
     * events and appending to the array. Try to avoid adding strings that won't be used, as
     * they'll be added to HTML output.
     */
    function showScriptMessages()
    {
        $messages = array();

        if (Event::handle('StartScriptMessages', array($this, &$messages))) {
            // Common messages needed for timeline views etc...

            // TRANS: Localized tooltip for '...' expansion button on overlong remote messages.
            $messages['showmore_tooltip'] = _m('TOOLTIP', 'Show more');
            $messages['popup_close_button'] = _m('TOOLTIP', 'Close popup');

            $messages = array_merge($messages, $this->getScriptMessages());

            Event::handle('EndScriptMessages', array($this, &$messages));
        }

        if (!empty($messages)) {
            $this->inlineScript('SN.messages=' . json_encode($messages));
        }

        return $messages;
    }

    protected function showScriptVariables()
    {
        $vars = array();

        if (Event::handle('StartScriptVariables', array($this, &$vars))) {
            $vars['urlNewNotice'] = common_local_url('newnotice');
        }
        if (!empty($vars)) {
            $this->inlineScript('SN.V = ' . json_encode($vars));
        }
        return $vars;
    }

    /**
     * If the action will need localizable text strings, export them here like so:
     *
     * return array('pool_deepend' => _('Deep end'),
     *              'pool_shallow' => _('Shallow end'));
     *
     * The exported map will be available via SN.msg() to JS code:
     *
     *   $('#pool').html('<div class="deepend"></div><div class="shallow"></div>');
     *   $('#pool .deepend').text(SN.msg('pool_deepend'));
     *   $('#pool .shallow').text(SN.msg('pool_shallow'));
     *
     * Exports a map of localized text strings to JavaScript code.
     *
     * Plugins can add to what's exported on any action by hooking the StartScriptMessages or
     * EndScriptMessages events and appending to the array. Try to avoid adding strings that won't
     * be used, as they'll be added to HTML output.
     */
    function getScriptMessages()
    {
        return array();
    }

    /**
     * Show OpenSearch headers
     *
     * @return nothing
     */
    function showOpenSearch()
    {
        $this->element('link', array('rel' => 'search',
                                     'type' => 'application/opensearchdescription+xml',
                                     'href' =>  common_local_url('opensearch', array('type' => 'people')),
                                     'title' => common_config('site', 'name').' People Search'));
        $this->element('link', array('rel' => 'search', 'type' => 'application/opensearchdescription+xml',
                                     'href' =>  common_local_url('opensearch', array('type' => 'notice')),
                                     'title' => common_config('site', 'name').' Notice Search'));
    }

    /**
     * Show feed headers
     *
     * MAY overload
     *
     * @return nothing
     */
    function showFeeds()
    {
        foreach ($this->getFeeds() as $feed) {
            $this->element('link', array('rel' => $feed->rel(),
                                         'href' => $feed->url,
                                         'type' => $feed->mimeType(),
                                         'title' => $feed->title));
        }
    }

    /**
     * Show description.
     *
     * SHOULD overload
     *
     * @return nothing
     */
    function showDescription()
    {
        // does nothing by default
    }

    /**
     * Show extra stuff in <head>.
     *
     * MAY overload
     *
     * @return nothing
     */
    function extraHead()
    {
        // does nothing by default
    }

    /**
     * Show body.
     *
     * Calls template methods
     *
     * @return nothing
     */
    function showBody()
    {
        $params = array('id' => $this->getActionName());
        if ($this->scoped instanceof Profile) {
            $params['class'] = 'user_in';
        }
        $this->elementStart('body', $params);
        $this->elementStart('div', array('id' => 'wrap'));
        if (Event::handle('StartShowHeader', array($this))) {
            $this->showHeader();
            $this->flush();
            Event::handle('EndShowHeader', array($this));
        }
        $this->showCore();
        $this->flush();
        if (Event::handle('StartShowFooter', array($this))) {
            $this->showFooter();
            $this->flush();
            Event::handle('EndShowFooter', array($this));
        }
        $this->elementEnd('div');
        $this->showScripts();
        $this->elementEnd('body');
    }

    /**
     * Show header of the page.
     *
     * Calls template methods
     *
     * @return nothing
     */
    function showHeader()
    {
        $this->elementStart('div', array('id' => 'header'));
        $this->showLogo();
        $this->showPrimaryNav();
        if (Event::handle('StartShowSiteNotice', array($this))) {
            $this->showSiteNotice();

            Event::handle('EndShowSiteNotice', array($this));
        }

        $this->elementEnd('div');
    }

    /**
     * Show configured logo.
     *
     * @return nothing
     */
    function showLogo()
    {
        $this->elementStart('address', array('id' => 'site_contact', 'class' => 'h-card'));
        if (Event::handle('StartAddressData', array($this))) {
            if (common_config('singleuser', 'enabled')) {
                $user = User::singleUser();
                $url = common_local_url('showstream',
                                        array('nickname' => $user->nickname));
            } else if (common_logged_in()) {
                $cur = common_current_user();
                $url = common_local_url('all', array('nickname' => $cur->nickname));
            } else {
                $url = common_local_url('public');
            }

            $this->elementStart('a', array('class' => 'home bookmark',
                                           'href' => $url));

            if (GNUsocial::isHTTPS()) {
                $logoUrl = common_config('site', 'ssllogo');
                if (empty($logoUrl)) {
                    // if logo is an uploaded file, try to fall back to HTTPS file URL
                    $httpUrl = common_config('site', 'logo');
                    if (!empty($httpUrl)) {
                        $f = File::getKV('url', $httpUrl);
                        if (!empty($f) && !empty($f->filename)) {
                            // this will handle the HTTPS case
                            $logoUrl = File::url($f->filename);
                        }
                    }
                }
            } else {
                $logoUrl = common_config('site', 'logo');
            }

            if (empty($logoUrl) && file_exists(Theme::file('logo.png'))) {
                // This should handle the HTTPS case internally
                $logoUrl = Theme::path('logo.png');
            }

            if (!empty($logoUrl)) {
                $this->element('img', array('class' => 'logo u-photo p-name',
                                            'src' => $logoUrl,
                                            'alt' => common_config('site', 'name')));
            }

            $this->elementEnd('a');

            Event::handle('EndAddressData', array($this));
        }
        $this->elementEnd('address');
    }

    /**
     * Show primary navigation.
     *
     * @return nothing
     */
    function showPrimaryNav()
    {
        $this->elementStart('div', array('id' => 'site_nav_global_primary'));

        $user = common_current_user();

        if (!empty($user) || !common_config('site', 'private')) {
            $form = new SearchForm($this);
            $form->show();
        }

        $pn = new PrimaryNav($this);
        $pn->show();
        $this->elementEnd('div');
    }

    /**
     * Show site notice.
     *
     * @return nothing
     */
    function showSiteNotice()
    {
        // Revist. Should probably do an hAtom pattern here
        $text = common_config('site', 'notice');
        if ($text) {
            $this->elementStart('div', array('id' => 'site_notice',
                                            'class' => 'system_notice'));
            $this->raw($text);
            $this->elementEnd('div');
        }
    }

    /**
     * Show notice form.
     *
     * MAY overload if no notice form needed... or direct message box????
     *
     * @return nothing
     */
    function showNoticeForm()
    {
        // TRANS: Tab on the notice form.
        $tabs = array('status' => array('title' => _m('TAB','Status'),
                                        'href'  => common_local_url('newnotice')));

        $this->elementStart('div', 'input_forms');

        $this->element('label', array('for'=>'input_form_nav'), _m('TAB', 'Share your:'));

        if (Event::handle('StartShowEntryForms', array(&$tabs))) {
            $this->elementStart('ul', array('class' => 'nav',
                                            'id' => 'input_form_nav'));

            foreach ($tabs as $tag => $data) {
                $tag = htmlspecialchars($tag);
                $attrs = array('id' => 'input_form_nav_'.$tag,
                               'class' => 'input_form_nav_tab');

                if ($tag == 'status') {
                    $attrs['class'] .= ' current';
                }
                $this->elementStart('li', $attrs);

                $this->element('a',
                               array('onclick' => 'return SN.U.switchInputFormTab("'.$tag.'");',
                                     'href' => $data['href']),
                               $data['title']);
                $this->elementEnd('li');
            }

            $this->elementEnd('ul');

            foreach ($tabs as $tag => $data) {
                $attrs = array('class' => 'input_form',
                               'id' => 'input_form_'.$tag);
                if ($tag == 'status') {
                    $attrs['class'] .= ' current';
                }

                $this->elementStart('div', $attrs);

                $form = null;

                if (Event::handle('StartMakeEntryForm', array($tag, $this, &$form))) {
                    if ($tag == 'status') {
                        $options = $this->noticeFormOptions();
                        $form = new NoticeForm($this, $options);
                    }
                    Event::handle('EndMakeEntryForm', array($tag, $this, $form));
                }

                if (!empty($form)) {
                    $form->show();
                }

                $this->elementEnd('div');
            }
        }

        $this->elementEnd('div');
    }

    function noticeFormOptions()
    {
        return array();
    }

    /**
     * Show anonymous message.
     *
     * SHOULD overload
     *
     * @return nothing
     */
    function showAnonymousMessage()
    {
        // needs to be defined by the class
    }

    /**
     * Show core.
     *
     * Shows local navigation, content block and aside.
     *
     * @return nothing
     */
    function showCore()
    {
        $this->elementStart('div', array('id' => 'core'));
        $this->elementStart('div', array('id' => 'aside_primary_wrapper'));
        $this->elementStart('div', array('id' => 'content_wrapper'));
        $this->elementStart('div', array('id' => 'site_nav_local_views_wrapper'));
        if (Event::handle('StartShowLocalNavBlock', array($this))) {
            $this->showLocalNavBlock();
            $this->flush();
            Event::handle('EndShowLocalNavBlock', array($this));
        }
        if (Event::handle('StartShowContentBlock', array($this))) {
            $this->showContentBlock();
            $this->flush();
            Event::handle('EndShowContentBlock', array($this));
        }
        if (Event::handle('StartShowAside', array($this))) {
            $this->showAside();
            $this->flush();
            Event::handle('EndShowAside', array($this));
        }
        $this->elementEnd('div');
        $this->elementEnd('div');
        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    /**
     * Show local navigation block.
     *
     * @return nothing
     */
    function showLocalNavBlock()
    {
        // Need to have this ID for CSS; I'm too lazy to add it to
        // all menus
        $this->elementStart('div', array('id' => 'site_nav_local_views'));
        // Cheat cheat cheat!
        $this->showLocalNav();
        $this->elementEnd('div');
    }

    /**
     * If there's a logged-in user, show a bit of login context
     *
     * @return nothing
     */
    function showProfileBlock()
    {
        if (common_logged_in()) {
            $block = new DefaultProfileBlock($this);
            $block->show();
        }
    }

    /**
     * Show local navigation.
     *
     * SHOULD overload
     *
     * @return nothing
     */
    function showLocalNav()
    {
        $nav = new DefaultLocalNav($this);
        $nav->show();
    }

    /**
     * Show menu for an object (group, profile)
     *
     * This block will only show if a subclass has overridden
     * the showObjectNav() method.
     *
     * @return nothing
     */
    function showObjectNavBlock()
    {
        $rmethod = new ReflectionMethod($this, 'showObjectNav');
        $dclass = $rmethod->getDeclaringClass()->getName();

        if ($dclass != 'Action') {
            // Need to have this ID for CSS; I'm too lazy to add it to
            // all menus
            $this->elementStart('div', array('id' => 'site_nav_object',
                                             'class' => 'section'));
            $this->showObjectNav();
            $this->elementEnd('div');
        }
    }

    /**
     * Show object navigation.
     *
     * If there are things to do with this object, show it here.
     *
     * @return nothing
     */
    function showObjectNav()
    {
        /* Nothing here. */
    }

    /**
     * Show content block.
     *
     * @return nothing
     */
    function showContentBlock()
    {
        $this->elementStart('div', array('id' => 'content'));
        if (common_logged_in()) {
            if (Event::handle('StartShowNoticeForm', array($this))) {
                $this->showNoticeForm();
                Event::handle('EndShowNoticeForm', array($this));
            }
        }
        if (Event::handle('StartShowPageTitle', array($this))) {
            $this->showPageTitle();
            Event::handle('EndShowPageTitle', array($this));
        }
        $this->showPageNoticeBlock();
        $this->elementStart('div', array('id' => 'content_inner'));
        // show the actual content (forms, lists, whatever)
        $this->showContent();
        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    /**
     * Show page title.
     *
     * @return nothing
     */
    function showPageTitle()
    {
        $this->element('h1', null, $this->title());
    }

    /**
     * Show page notice block.
     *
     * Only show the block if a subclassed action has overrided
     * Action::showPageNotice(), or an event handler is registered for
     * the StartShowPageNotice event, in which case we assume the
     * 'page_notice' definition list is desired.  This is to prevent
     * empty 'page_notice' definition lists from being output everywhere.
     *
     * @return nothing
     */
    function showPageNoticeBlock()
    {
        $rmethod = new ReflectionMethod($this, 'showPageNotice');
        $dclass = $rmethod->getDeclaringClass()->getName();

        if ($dclass != 'Action' || Event::hasHandler('StartShowPageNotice')) {

            $this->elementStart('div', array('id' => 'page_notice',
                                            'class' => 'system_notice'));
            if (Event::handle('StartShowPageNotice', array($this))) {
                $this->showPageNotice();
                Event::handle('EndShowPageNotice', array($this));
            }
            $this->elementEnd('div');
        }
    }

    /**
     * Show page notice.
     *
     * SHOULD overload (unless there's not a notice)
     *
     * @return nothing
     */
    function showPageNotice()
    {
    }

    /**
     * Show content.
     *
     * MUST overload (unless there's not a notice)
     *
     * @return nothing
     */
    protected function showContent()
    {
    }

    /**
     * Show Aside.
     *
     * @return nothing
     */
    function showAside()
    {
        $this->elementStart('div', array('id' => 'aside_primary',
                                         'class' => 'aside'));
        $this->showProfileBlock();
        if (Event::handle('StartShowObjectNavBlock', array($this))) {
            $this->showObjectNavBlock();
            Event::handle('EndShowObjectNavBlock', array($this));
        }
        if (Event::handle('StartShowSections', array($this))) {
            $this->showSections();
            Event::handle('EndShowSections', array($this));
        }
        if (Event::handle('StartShowExportData', array($this))) {
            $this->showExportData();
            Event::handle('EndShowExportData', array($this));
        }
        $this->elementEnd('div');
    }

    /**
     * Show export data feeds.
     *
     * @return void
     */
    function showExportData()
    {
        $feeds = $this->getFeeds();
        if (!empty($feeds)) {
            $fl = new FeedList($this, $feeds);
            $fl->show();
        }
    }

    /**
     * Show sections.
     *
     * SHOULD overload
     *
     * @return nothing
     */
    function showSections()
    {
        // for each section, show it
    }

    /**
     * Show footer.
     *
     * @return nothing
     */
    function showFooter()
    {
        $this->elementStart('div', array('id' => 'footer'));
        if (Event::handle('StartShowInsideFooter', array($this))) {
            $this->showSecondaryNav();
            $this->showLicenses();
            Event::handle('EndShowInsideFooter', array($this));
        }
        $this->elementEnd('div');
    }

    /**
     * Show secondary navigation.
     *
     * @return nothing
     */
    function showSecondaryNav()
    {
        $sn = new SecondaryNav($this);
        $sn->show();
    }

    /**
     * Show licenses.
     *
     * @return nothing
     */
    function showLicenses()
    {
        $this->showGNUsocialLicense();
        $this->showContentLicense();
    }

    /**
     * Show GNU social license.
     *
     * @return nothing
     */
    function showGNUsocialLicense()
    {
        if (common_config('site', 'broughtby')) {
            // TRANS: First sentence of the GNU social site license. Used if 'broughtby' is set.
            // TRANS: Text between [] is a link description, text between () is the link itself.
            // TRANS: Make sure there is no whitespace between "]" and "(".
            // TRANS: "%%site.broughtby%%" is the value of the variable site.broughtby
            $instr = _('**%%site.name%%** is a social network, courtesy of [%%site.broughtby%%](%%site.broughtbyurl%%).');
        } else {
            // TRANS: First sentence of the GNU social site license. Used if 'broughtby' is not set.
            $instr = _('**%%site.name%%** is a social network.');
        }
        $instr .= ' ';
        // TRANS: Second sentence of the GNU social site license. Mentions the GNU social source code license.
        // TRANS: Make sure there is no whitespace between "]" and "(".
        // TRANS: [%1$s](%2$s) is a link description followed by the link itself
        // TRANS: %3$s is the version of GNU social that is being used.
        $instr .= sprintf(_('It runs on [%1$s](%2$s), version %3$s, available under the [GNU Affero General Public License](http://www.fsf.org/licensing/licenses/agpl-3.0.html).'), GNUSOCIAL_ENGINE, GNUSOCIAL_ENGINE_URL, GNUSOCIAL_VERSION);
        $output = common_markup_to_html($instr);
        $this->raw($output);
        // do it
    }

    /**
     * Show content license.
     *
     * @return nothing
     */
    function showContentLicense()
    {
        if (Event::handle('StartShowContentLicense', array($this))) {
            switch (common_config('license', 'type')) {
            case 'private':
                // TRANS: Content license displayed when license is set to 'private'.
                // TRANS: %1$s is the site name.
                $this->element('p', null, sprintf(_('Content and data of %1$s are private and confidential.'),
                                                  common_config('site', 'name')));
                // fall through
            case 'allrightsreserved':
                if (common_config('license', 'owner')) {
                    // TRANS: Content license displayed when license is set to 'allrightsreserved'.
                    // TRANS: %1$s is the copyright owner.
                    $this->element('p', null, sprintf(_('Content and data copyright by %1$s. All rights reserved.'),
                                                      common_config('license', 'owner')));
                } else {
                    // TRANS: Content license displayed when license is set to 'allrightsreserved' and no owner is set.
                    $this->element('p', null, _('Content and data copyright by contributors. All rights reserved.'));
                }
                break;
            case 'cc': // fall through
            default:
                $this->elementStart('p');

                $image    = common_config('license', 'image');
                $sslimage = common_config('license', 'sslimage');

                if (GNUsocial::isHTTPS()) {
                    if (!empty($sslimage)) {
                        $url = $sslimage;
                    } else if (preg_match('#^http://i.creativecommons.org/#', $image)) {
                        // CC support HTTPS on their images
                        $url = preg_replace('/^http/', 'https', $image, 1);
                    } else {
                        // Better to show mixed content than no content
                        $url = $image;
                    }
                } else {
                    $url = $image;
                }

                $this->element('img', array('id' => 'license_cc',
                                            'src' => $url,
                                            'alt' => common_config('license', 'title'),
                                            'width' => '80',
                                            'height' => '15'));
                $this->text(' ');
                // TRANS: license message in footer.
                // TRANS: %1$s is the site name, %2$s is a link to the license URL, with a licence name set in configuration.
                $notice = _('All %1$s content and data are available under the %2$s license.');
                $link = sprintf('<a class="license" rel="external license" href="%1$s">%2$s</a>',
                                htmlspecialchars(common_config('license', 'url')),
                                htmlspecialchars(common_config('license', 'title')));
                $this->raw(@sprintf(htmlspecialchars($notice),
                                   htmlspecialchars(common_config('site', 'name')),
                                   $link));
                $this->elementEnd('p');
                break;
            }

            Event::handle('EndShowContentLicense', array($this));
        }
    }

    /**
     * Return last modified, if applicable.
     *
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null
        return null;
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */
    function etag()
    {
        return null;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return false;
    }

    /**
     * Returns query argument or default value if not found
     *
     * @param string $key requested argument
     * @param string $def default value to return if $key is not provided
     *
     * @return boolean is read only action?
     */
    function arg($key, $def=null)
    {
        if (array_key_exists($key, $this->args)) {
            return $this->args[$key];
        } else {
            return $def;
        }
    }

    /**
     * Returns trimmed query argument or default value if not found
     *
     * @param string $key requested argument
     * @param string $def default value to return if $key is not provided
     *
     * @return boolean is read only action?
     */
    function trimmed($key, $def=null)
    {
        $arg = $this->arg($key, $def);
        return is_string($arg) ? trim($arg) : $arg;
    }

    /**
     * Handler method
     *
     * @return boolean is read only action?
     */
    protected function handle()
    {
        header('Vary: Accept-Encoding,Cookie');

        $lm   = $this->lastModified();
        $etag = $this->etag();

        if ($etag) {
            header('ETag: ' . $etag);
        }

        if ($lm) {
            header('Last-Modified: ' . date(DATE_RFC1123, $lm));
            if ($this->isCacheable()) {
                header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', 0 ) . ' GMT' );
                header( "Cache-Control: private, must-revalidate, max-age=0" );
                header( "Pragma:");
            }
        }

        $checked = false;
        if ($etag) {
            $if_none_match = (array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER)) ?
              $_SERVER['HTTP_IF_NONE_MATCH'] : null;
            if ($if_none_match) {
                // If this check fails, ignore the if-modified-since below.
                $checked = true;
                if ($this->_hasEtag($etag, $if_none_match)) {
                    header('HTTP/1.1 304 Not Modified');
                    // Better way to do this?
                    exit(0);
                }
            }
        }

        if (!$checked && $lm && array_key_exists('HTTP_IF_MODIFIED_SINCE', $_SERVER)) {
            $if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
            $ims = strtotime($if_modified_since);
            if ($lm <= $ims) {
                header('HTTP/1.1 304 Not Modified');
                // Better way to do this?
                exit(0);
            }
        }
    }

    /**
     * Is this action cacheable?
     *
     * If the action returns a last-modified
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return boolean is read only action?
     */
    function isCacheable()
    {
        return true;
    }

    /**
     * Has etag? (private)
     *
     * @param string $etag          etag http header
     * @param string $if_none_match ifNoneMatch http header
     *
     * @return boolean
     */
    function _hasEtag($etag, $if_none_match)
    {
        $etags = explode(',', $if_none_match);
        return in_array($etag, $etags) || in_array('*', $etags);
    }

    /**
     * Boolean understands english (yes, no, true, false)
     *
     * @param string $key query key we're interested in
     * @param string $def default value
     *
     * @return boolean interprets yes/no strings as boolean
     */
    function boolean($key, $def=false)
    {
        $arg = strtolower($this->trimmed($key));

        if (is_null($arg)) {
            return $def;
        } else if (in_array($arg, array('true', 'yes', '1', 'on'))) {
            return true;
        } else if (in_array($arg, array('false', 'no', '0'))) {
            return false;
        } else {
            return $def;
        }
    }

    /**
     * This is a cheap hack to avoid a bug in DB_DataObject
     * where '' is non-type-aware compared to 0, which means it
     * will always be true for values like false and 0 too...
     *
     * Upstream bug is::
     * https://pear.php.net/bugs/bug.php?id=20291
     */
    function booleanintstring($key, $def=false)
    {
        return $this->boolean($key, $def) ? '1' : '0';
    }

    /**
     * Integer value of an argument
     *
     * @param string $key      query key we're interested in
     * @param string $defValue optional default value (default null)
     * @param string $maxValue optional max value (default null)
     * @param string $minValue optional min value (default null)
     *
     * @return integer integer value
     */
    function int($key, $defValue=null, $maxValue=null, $minValue=null)
    {
        $arg = intval($this->arg($key));

        if (!is_numeric($this->arg($key)) || $arg != $this->arg($key)) {
            return $defValue;
        }

        if (!is_null($maxValue)) {
            $arg = min($arg, $maxValue);
        }

        if (!is_null($minValue)) {
            $arg = max($arg, $minValue);
        }

        return $arg;
    }

    /**
     * Server error
     *
     * @param string  $msg  error message to display
     * @param integer $code http error code, 500 by default
     *
     * @return nothing
     */
    function serverError($msg, $code=500, $format=null)
    {
        if ($format === null) {
            $format = $this->format;
        }

        common_debug("Server error '{$code}' on '{$this->action}': {$msg}", __FILE__);

        if (!array_key_exists($code, ServerErrorAction::$status)) {
            $code = 500;
        }

        $status_string = ServerErrorAction::$status[$code];

        switch ($format) {
        case 'xml':
            header("HTTP/1.1 {$code} {$status_string}");
            $this->initDocument('xml');
            $this->elementStart('hash');
            $this->element('error', null, $msg);
            $this->element('request', null, $_SERVER['REQUEST_URI']);
            $this->elementEnd('hash');
            $this->endDocument('xml');
            break;
        case 'json':
            if (!isset($this->callback)) {
                header("HTTP/1.1 {$code} {$status_string}");
            }
            $this->initDocument('json');
            $error_array = array('error' => $msg, 'request' => $_SERVER['REQUEST_URI']);
            print(json_encode($error_array));
            $this->endDocument('json');
            break;
        default:
            common_log(LOG_ERR, 'Handled serverError ('._ve($code).') but cannot output into desired format ('._ve($this->format).'): '._ve($msg));
            $action = new ServerErrorAction($msg, $code);
            $action->execute();
        }

        exit((int)$code);
    }

    /**
     * Client error
     *
     * @param string  $msg    error message to display
     * @param integer $code   http error code, 400 by default
     * @param string  $format error format (json, xml, text) for ApiAction
     *
     * @return nothing
     * @throws ClientException always
     */
    function clientError($msg, $code=400, $format=null)
    {
        // $format is currently only relevant for an ApiAction anyway
        if ($format === null) {
            $format = $this->format;
        }

        common_debug("User error '{$code}' on '{$this->action}': {$msg}", __FILE__);

        if (!array_key_exists($code, ClientErrorAction::$status)) {
            $code = 400;
        }

        $status_string = ClientErrorAction::$status[$code];

        switch ($format) {
        case 'xml':
            header("HTTP/1.1 {$code} {$status_string}");
            $this->initDocument('xml');
            $this->elementStart('hash');
            $this->element('error', null, $msg);
            $this->element('request', null, $_SERVER['REQUEST_URI']);
            $this->elementEnd('hash');
            $this->endDocument('xml');
            break;
        case 'json':
            if (!isset($this->callback)) {
                header("HTTP/1.1 {$code} {$status_string}");
            }
            $this->initDocument('json');
            $error_array = array('error' => $msg, 'request' => $_SERVER['REQUEST_URI']);
            print(json_encode($error_array));
            $this->endDocument('json');
            break;
        case 'text':
            header("HTTP/1.1 {$code} {$status_string}");
            header('Content-Type: text/plain; charset=utf-8');
            echo $msg;
            break;
        default:
            common_log(LOG_ERR, 'Handled clientError ('._ve($code).') but cannot output into desired format ('._ve($this->format).'): '._ve($msg));
            $action = new ClientErrorAction($msg, $code);
            $action->execute();
        }
        exit((int)$code);
    }

    /**
     * If not logged in, take appropriate action (redir or exception)
     *
     * @param boolean $redir Redirect to login if not logged in
     *
     * @return boolean true if logged in (never returns if not)
     */
    public function checkLogin($redir=true)
    {
        if (common_logged_in()) {
            return true;
        }

        if ($redir==true) {
            common_set_returnto($_SERVER['REQUEST_URI']);
            common_redirect(common_local_url('login'));
        }

        // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
        $this->clientError(_('Not logged in.'), 403);
    }

    /**
     * Returns the current URL
     *
     * @return string current URL
     */
    function selfUrl()
    {
        list($action, $args) = $this->returnToArgs();
        return common_local_url($action, $args);
    }

    /**
     * Returns arguments sufficient for re-constructing URL
     *
     * @return array two elements: action, other args
     */
    function returnToArgs()
    {
        $action = $this->getActionName();
        $args   = $this->args;
        unset($args['action']);
        if (common_config('site', 'fancy')) {
            unset($args['p']);
        }
        if (array_key_exists('submit', $args)) {
            unset($args['submit']);
        }
        foreach (array_keys($_COOKIE) as $cookie) {
            unset($args[$cookie]);
        }
        return array($action, $args);
    }

    /**
     * Generate a menu item
     *
     * @param string  $url         menu URL
     * @param string  $text        menu name
     * @param string  $title       title attribute, null by default
     * @param boolean $is_selected current menu item, false by default
     * @param string  $id          element id, null by default
     *
     * @return nothing
     */
    function menuItem($url, $text, $title=null, $is_selected=false, $id=null, $class=null)
    {
        // Added @id to li for some control.
        // XXX: We might want to move this to htmloutputter.php
        $lattrs  = array();
        $classes = array();
        if ($class !== null) {
            $classes[] = trim($class);
        }
        if ($is_selected) {
            $classes[] = 'current';
        }

        if (!empty($classes)) {
            $lattrs['class'] = implode(' ', $classes);
        }

        if (!is_null($id)) {
            $lattrs['id'] = $id;
        }

        $this->elementStart('li', $lattrs);
        $attrs['href'] = $url;
        if ($title) {
            $attrs['title'] = $title;
        }
        $this->element('a', $attrs, $text);
        $this->elementEnd('li');
    }

    /**
     * Generate pagination links
     *
     * @param boolean $have_before is there something before?
     * @param boolean $have_after  is there something after?
     * @param integer $page        current page
     * @param string  $action      current action
     * @param array   $args        rest of query arguments
     *
     * @return nothing
     */
    // XXX: The messages in this pagination method only tailor to navigating
    //      notices. In other lists, "Previous"/"Next" type navigation is
    //      desirable, but not available.
    function pagination($have_before, $have_after, $page, $action, $args=null)
    {
        // Does a little before-after block for next/prev page
        if ($have_before || $have_after) {
            $this->elementStart('ul', array('class' => 'nav',
                                            'id' => 'pagination'));
        }
        if ($have_before) {
            $pargs   = array('page' => $page-1);
            $this->elementStart('li', array('class' => 'nav_prev'));
            $this->element('a', array('href' => common_local_url($action, $args, $pargs),
                                      'rel' => 'prev'),
                           // TRANS: Pagination message to go to a page displaying information more in the
                           // TRANS: present than the currently displayed information.
                           _('After'));
            $this->elementEnd('li');
        }
        if ($have_after) {
            $pargs   = array('page' => $page+1);
            $this->elementStart('li', array('class' => 'nav_next'));
            $this->element('a', array('href' => common_local_url($action, $args, $pargs),
                                      'rel' => 'next'),
                           // TRANS: Pagination message to go to a page displaying information more in the
                           // TRANS: past than the currently displayed information.
                           _('Before'));
            $this->elementEnd('li');
        }
        if ($have_before || $have_after) {
            $this->elementEnd('ul');
        }
    }

    /**
     * An array of feeds for this action.
     *
     * Returns an array of potential feeds for this action.
     *
     * @return array Feed object to show in head and links
     */
    function getFeeds()
    {
        return array();
    }

    /**
     * Check the session token.
     *
     * Checks that the current form has the correct session token,
     * and throw an exception if it does not.
     *
     * @return void
     */
    // XXX: Finding this type of check with the same message about 50 times.
    //      Possible to refactor?
    function checkSessionToken()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (empty($token) || $token != common_session_token()) {
            // TRANS: Client error text when there is a problem with the session token.
            $this->clientError(_('There was a problem with your session token.'));
        }
    }

    /**
     * Check if the current request is a POST
     *
     * @return boolean true if POST; otherwise false.
     */

    function isPost()
    {
        return ($_SERVER['REQUEST_METHOD'] == 'POST');
    }
}
