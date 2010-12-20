<?php
/**
 * Plugin to render old skool templates
 *
 * Captures rendered parts from the output buffer, passes them through a template file: tpl/index.html
 * Adds an API method at index.php/template/update which lets you overwrite the template file
 * Requires username/password and a single POST parameter called "template"
 * The method is disabled unless the user is #1, the first user of the system
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Brian Hendrickson <brian@megapump.com>
 * @copyright 2009 Megapump, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://megapump.com/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define('TEMPLATEPLUGIN_VERSION', '0.1');

class TemplatePlugin extends Plugin {

  var $blocks = array();

  function __construct() {
    parent::__construct();
  }

  // capture the RouterInitialized event
  // and connect a new API method
  // for updating the template
  function onRouterInitialized( $m ) {
    $m->connect( 'template/update', array(
      'action'      => 'template',
    ));
  }

  // <%styles%>
  // <%scripts%>
  // <%search%>
  // <%feeds%>
  // <%description%>
  // <%head%>
  function onStartShowHead( &$act ) {
    $this->clear_xmlWriter($act);
    $act->extraHead();
    $this->blocks['head'] = $act->xw->flush();
    $act->showStylesheets();
    $this->blocks['styles'] = $act->xw->flush();
    $act->showScripts();
    $this->blocks['scripts'] = $act->xw->flush();
    $act->showFeeds();
    $this->blocks['feeds'] = $act->xw->flush();
    $act->showOpenSearch();
    $this->blocks['search'] = $act->xw->flush();
    $act->showDescription();
    $this->blocks['description'] = $act->xw->flush();
    return false;
  }

  // <%bodytext%>
  function onStartShowContentBlock( &$act ) {
    $this->clear_xmlWriter($act);
    return true;
  }
  function onEndShowContentBlock( &$act ) {
    $this->blocks['bodytext'] = $act->xw->flush();
  }

  // <%localnav%>
  function onStartShowLocalNavBlock( &$act ) {
    $this->clear_xmlWriter($act);
    return true;
  }
  function onEndShowLocalNavBlock( &$act ) {
    $this->blocks['localnav'] = $act->xw->flush();
  }

  // <%export%>
  function onStartShowExportData( &$act ) {
    $this->clear_xmlWriter($act);
    return true;
  }
  function onEndShowExportData( &$act ) {
    $this->blocks['export'] = $act->xw->flush();
  }

  // <%subscriptions%>
  // <%subscribers%>
  // <%groups%>
  // <%statistics%>
  // <%cloud%>
  // <%groupmembers%>
  // <%groupstatistics%>
  // <%groupcloud%>
  // <%popular%>
  // <%groupsbyposts%>
  // <%featuredusers%>
  // <%groupsbymembers%>
  function onStartShowSections( &$act ) {
    global $action;
    $this->clear_xmlWriter($act);
    switch ($action) {
      case "showstream":
        $act->showSubscriptions();
        $this->blocks['subscriptions'] = $act->xw->flush();
        $act->showSubscribers();
        $this->blocks['subscribers'] = $act->xw->flush();
        $act->showGroups();
        $this->blocks['groups'] = $act->xw->flush();
        $act->showStatistics();
        $this->blocks['statistics'] = $act->xw->flush();
        $cloud = new PersonalTagCloudSection($act, $act->user);
        $cloud->show();
        $this->blocks['cloud'] = $act->xw->flush();
        break;
      case "showgroup":
        $act->showMembers();
        $this->blocks['groupmembers'] = $act->xw->flush();
        $act->showStatistics();
        $this->blocks['groupstatistics'] = $act->xw->flush();
        $cloud = new GroupTagCloudSection($act, $act->group);
        $cloud->show();
        $this->blocks['groupcloud'] = $act->xw->flush();
        break;
      case "public":
        $pop = new PopularNoticeSection($act);
        $pop->show();
        $this->blocks['popular'] = $act->xw->flush();
        $gbp = new GroupsByPostsSection($act);
        $gbp->show();
        $this->blocks['groupsbyposts'] = $act->xw->flush();
        $feat = new FeaturedUsersSection($act);
        $feat->show();
        $this->blocks['featuredusers'] = $act->xw->flush();
        break;
      case "groups":
        $gbp = new GroupsByPostsSection($act);
        $gbp->show();
        $this->blocks['groupsbyposts'] = $act->xw->flush();
        $gbm = new GroupsByMembersSection($act);
        $gbm->show();
        $this->blocks['groupsbymembers'] = $act->xw->flush();
        break;
    }
    return false;
  }

  // <%logo%>
  // <%nav%>
  // <%notice%>
  // <%noticeform%>
  function onStartShowHeader( &$act ) {
    $this->clear_xmlWriter($act);
    $act->showLogo();
    $this->blocks['logo'] = $act->xw->flush();
    $act->showPrimaryNav();
    $this->blocks['nav'] = $act->xw->flush();
    $act->showSiteNotice();
    $this->blocks['notice'] = $act->xw->flush();
    if (common_logged_in()) {
        $act->showNoticeForm();
    } else {
        $act->showAnonymousMessage();
    }
    $this->blocks['noticeform'] = $act->xw->flush();
    return false;
  }

  // <%secondarynav%>
  // <%licenses%>
  function onStartShowFooter( &$act ) {
    $this->clear_xmlWriter($act);
    $act->showSecondaryNav();
    $this->blocks['secondarynav'] = $act->xw->flush();
    $act->showLicenses();
    $this->blocks['licenses'] = $act->xw->flush();
    return false;
  }

  // capture the EndHTML event
  // and include the template
  function onEndEndHTML($act) {

    global $action, $tags;

    // set the action and title values
    $vars = array(
      'action'=>$action,
      'title'=>$act->title(). " - ". common_config('site', 'name')
    );

    // use the PHP template
    // unless statusnet config:
    //   $config['template']['mode'] = 'html';
    if (!(common_config('template', 'mode') == 'html')) {
      $tpl_file = $this->templateFolder() . '/index.php';
      $tags = array_merge($vars,$this->blocks);
      include $tpl_file;
      return;
    }

    $tpl_file = $this->templateFolder() . '/index.html';

    // read the static template
    $output = file_get_contents( $tpl_file );

    $tags = array();

    // get a list of the <%tags%> in the template
    $pattern='/<%([a-z]+)%>/';

    if ( 1 <= preg_match_all( $pattern, $output, $found ))
      $tags[] = $found;

    // for each found tag, set its value from the rendered blocks
    foreach( $tags[0][1] as $pos=>$tag ) {
      if (isset($this->blocks[$tag]))
        $vars[$tag] = $this->blocks[$tag];

      // didn't find a block for the tag
      elseif (!isset($vars[$tag]))
        $vars[$tag] = '';
    }

    // replace the tags in the template
    foreach( $vars as $key=>$val )
      $output = str_replace( '<%'.$key.'%>', $val, $output );

    echo $output;

    return true;

  }
  function templateFolder() {
    return 'tpl';
  }

  // catching the StartShowHTML event to halt the rendering
  function onStartShowHTML( &$act ) {
    $this->clear_xmlWriter($act);
    return true;
  }

  // clear the xmlWriter
  function clear_xmlWriter( &$act ) {
    $act->xw->openMemory();
    $act->xw->setIndent(true);
  }

}

/**
 * Action for updating the template remotely
 *
 * "template/update" -- a POST method that requires a single
 * parameter "template", containing the new template code
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Brian Hendrickson <brian@megapump.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://megapump.com/
 *
 */

class TemplateAction extends Action
{

  function prepare($args) {
    parent::prepare($args);
    return true;
  }

  function handle($args) {

    parent::handle($args);

    if (!isset($_SERVER['PHP_AUTH_USER'])) {

      // not authenticated, show login form
      header('WWW-Authenticate: Basic realm="StatusNet API"');

      // cancelled the browser login form
      $this->clientError(_('Authentication error!'), $code = 401);

    } else {

      $nick = $_SERVER['PHP_AUTH_USER'];
      $pass = $_SERVER['PHP_AUTH_PW'];

      // check username and password
      $user = common_check_user($nick,$pass);

      if ($user) {

        // verify that user is admin
        if (!($user->id == 1))
          $this->clientError(_('Only User #1 can update the template.'), $code = 401);

        // open the old template
        $tpl_file = $this->templateFolder() . '/index.html';
        $fp = fopen( $tpl_file, 'w+' );

        // overwrite with the new template
        fwrite($fp, $this->arg('template'));
        fclose($fp);

        header('HTTP/1.1 200 OK');
        header('Content-type: text/plain');
        print "Template Updated!";

      } else {

        // bad username and password
        $this->clientError(_('Authentication error!'), $code = 401);

      }

    }
  }
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Template',
                            'version' => TEMPLATEPLUGIN_VERSION,
                            'author' => 'Brian Hendrickson',
                            'homepage' => 'http://status.net/wiki/Plugin:Template',
                            'rawdescription' =>
                            _m('Use an HTML template for Web output.'));
        return true;
    }

}

/**
 * Function for retrieving a statusnet display section
 *
 * requires one parameter, the name of the section
 * section names are listed in the comments of the TemplatePlugin class
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Brian Hendrickson <brian@megapump.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://megapump.com/
 *
 */

function section($tagname) {
  global $tags;
  if (isset($tags[$tagname]))
    return $tags[$tagname];
}

