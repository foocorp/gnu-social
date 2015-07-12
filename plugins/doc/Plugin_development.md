Plugin Development
=======================

SamplePlugin.php
-----------------------

Each plugin requires a main class to interact with the GNU social system.

The main class usually extends the Plugin class that comes with GNU social.

The class has standard-named methods that will be called when certain events
happen in the code base. These methods have names like 'onX' where X is an
event name (see EVENTS.txt for the list of available events). Event handlers
have pre-defined arguments, based on which event they're handling. A typical
event handler:

```php
function onSomeEvent($paramA, &$paramB)
{
    if ($paramA == 'jed') {
        throw new Exception(sprintf(_m("Invalid parameter %s"), $paramA));
    }
    $paramB = 'spock';
    return true;
}
```

Event Handlers
-----------------------

Event handlers must return a Boolean value.

If they return false, all other event handlers for this event (in other plug-in)
will be skipped, and in some cases the default processing for that event would
be skipped. This is great for replacing the default action of an event.

If the handler returns true, processing of other event handlers and the default
processing will continue. This is great for extending existing functionality.

If the handler throws an exception, processing will stop, and the exception's
error will be shown to the user.

Installation
------------------

To install a plugin (like this one), site admins add the following code to their
config.php file:

```php
addPlugin('Sample');
```

Plugins must be installed in one of the following directories:

* local/plugins/{$pluginclass}.php
* local/plugins/{$name}/{$pluginclass}.php
* local/{$pluginclass}.php
* local/{$name}/{$pluginclass}.php
* plugins/{$pluginclass}.php
* plugins/{$name}/{$pluginclass}.php

Here, `{$name}` is the name of the plugin, like 'Sample', and `{$pluginclass}`
is the name of the main class, like 'SamplePlugin'. Plugins that are part of
the main GNU social distribution go in 'plugins' and third-party or local ones
go in 'local'.

Simple plugins can be implemented as a single module. Others are more complex
and require additional modules; these should use their own directory, like
'local/plugins/{$name}/'. All files related to the plugin, including images,
JavaScript, CSS, external libraries or PHP modules should go in the plugin
directory.

Plugin Configuration
------------------

Plugins are configured using public instance attributes. To set their values,
site administrators use this syntax:

```php
addPlugin('Sample', array('attr1' => 'foo', 'attr2' => 'bar'));
```

The same plugin class can be initialized multiple times with different arguments:

```php
addPlugin('EmailNotify', array('sendTo' => 'evan@status.net'));
addPlugin('EmailNotify', array('sendTo' => 'brionv@status.net'));
```

```php
class SamplePlugin extends Plugin
{
    public $attr1 = null;
    public $attr2 = null;
}
```

Initialization
------------------

Plugins overload this method to do any initialization they need, like connecting
to remote servers or creating paths or so on. @return boolean hook value; true
means continue processing, false means stop.

```php
function initialize()
{
    return true;
}
```

Clean Up
------------------

Plugins overload this method to do any cleanup they need, like disconnecting from
remote servers or deleting temp files or so on.

```php
function cleanup()
{
    return true;
}
```

Database schema setup
------------------

Plugins can add their own tables to the GNU social database. Plugins should use
GNU social's schema interface to add or delete tables. The ensureTable() method
provides an easy way to ensure a table's structure and availability.

By default, the schema is checked every time GNU social is run (say, when a Web
page is hit). Admins can configure their systems to only check the schema when
the checkschema.php script is run, greatly improving performance. However, they
need to remember to run that script after installing or upgrading a plugin!

```php
function onCheckSchema()
{
    $schema = Schema::get();

    // '''For storing user-submitted flags on profiles'''

    $schema->ensureTable('user_greeting_count',
                          array(new ColumnDef('user_id', 'integer', null,
                                              true, 'PRI'),
                                new ColumnDef('greeting_count', 'integer')));

    return true;
}
```

Load related modules when needed
------------------

Most non-trivial plugins will require extra modules to do their work. Typically
these include data classes, action classes, widget classes, or external libraries.

This method receives a class name and loads the PHP file related to that class.
By tradition, action classes typically have files named for the action, all
lower-case. Data classes are in files with the data class name, initial letter
capitalized.

Note that this method will be called for *all* overloaded classes, not just ones
in this plugin! So, make sure to return true by default to let other plugins,
and the core code, get a chance.

```php
function onAutoload($cls)
{
    $dir = dirname(__FILE__);

    switch ($cls)
    {
    case 'HelloAction':
        include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
        return false;
    case 'User_greeting_count':
        include_once $dir . '/'.$cls.'.php';
        return false;
    default:
        return true;
    }
}
```

Map URLs to actions
------------------

This event handler lets the plugin map URLs on the site to actions (and thus an
action handler class). Note that the action handler class for an action will be
named 'FoobarAction', where action = 'foobar'. The class must be loaded in the
onAutoload() method.

```php
function onRouterInitialized($m)
{
    $m->connect('main/hello',
                array('action' => 'hello'));
    return true;
}
```

Modify the default menu to link to our custom action
------------------

Using event handlers, it's possible to modify the default UI for pages almost
without limit. In this method, we add a menu item to the default primary menu
for the interface to link to our action.

Action Class
------------------

The Action class provides a rich set of events to hook, as well as output methods.

```php
function onEndPrimaryNav($action)
{
    // '''common_local_url()''' gets the correct URL for the action name we provide

    $action->menuItem(common_local_url('hello'),
                      _m('Hello'), _m('A warm greeting'), false, 'nav_hello');
    return true;
}

function onPluginVersion(&$versions)
{
    $versions[] = array('name' => 'Sample',
                        'version' => STATUSNET_VERSION,
                        'author' => 'Brion Vibber, Evan Prodromou',
                        'homepage' => 'http://example.org/plugin',
                        'rawdescription' =>
                        _m('A sample plugin to show basics of development for new hackers.'));
    return true;
}
```

hello.php
------------------

This section is taken directly from the 'hello.php'. ( plugins/Sample/hello.php )

Give a warm greeting to our friendly user.

This sample action shows some basic ways of doing output in an action class.

Action classes have several output methods that they override from the parent class.

```php
class HelloAction extends Action
{
    var $user = null;
    var $gc   = null;
}
```

Take arguments for running
------------------

This method is called first, and it lets the action class get all its arguments
and validate them. It's also the time to fetch any relevant data from the database.

Action classes should run parent::prepare($args) as the first line of this
method to make sure the default argument-processing happens.

```php     
function prepare($args)
{
    parent::prepare($args);

    $this->user = common_current_user();

    if (!empty($this->user)) {
        $this->gc = User_greeting_count::inc($this->user->id);
    }

    return true;
}
```

Handle request
------------------

This is the main method for handling a request. Note that most preparation
should be done in the prepare() method; by the time handle() is called the
action should be more or less ready to go.

```php
function handle($args)
{
    parent::handle($args);

    $this->showPage();
}
```

Title of this page
------------------

Override this method to show a custom title.

```php
function title()
{
    if (empty($this->user)) {
        return _m('Hello');
    } else {
        return sprintf(_m('Hello, %s'), $this->user->nickname);
    }
}
```

Show content in the content area
------------------

The default GNU social page has a lot of decorations: menus, logos, tabs, all
that jazz. This method is used to show content in the content area of the
page; it's the main thing you want to overload. This method also demonstrates
use of a plural localized string.

```php
function showContent()
{
    if (empty($this->user)) {
        $this->element('p', array('class' => 'greeting'),
                       _m('Hello, stranger!'));
    } else {
        $this->element('p', array('class' => 'greeting'),
                       sprintf(_m('Hello, %s'), $this->user->nickname));
        $this->element('p', array('class' => 'greeting_count'),
                       sprintf(_m('I have greeted you %d time.',
                                  'I have greeted you %d times.',
                                  $this->gc->greeting_count),
                                  $this->gc->greeting_count));
    }
}
```

Return true if read only.
------------------

Some actions only read from the database; others read and write. The simple
database load-balancer built into GNU social will direct read-only actions to
database mirrors (if they are configured) and read-write actions to the master database.

This defaults to false to avoid data integrity issues, but you should make sure
to overload it for performance gains.

```php
function isReadOnly($args)
{
    return false;
}
```

