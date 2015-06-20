Several example plugins are included in the plugins/ directory. You
can enable a plugin with the following line in config.php:

```php
    addPlugin('Example', array('param1' => 'value1',
                               'param2' => 'value2'));
```

This will look for and load files named 'ExamplePlugin.php' or
'Example/ExamplePlugin.php' either in the plugins/ directory (for
plugins that ship with StatusNet) or in the local/ directory (for
plugins you write yourself or that you get from somewhere else) or
local/plugins/.

Plugins are documented in their own directories.

Additional information on using and developing plugins can be found
at the following locations:

* [Plugin Development](doc/Plugin_development.md)
* [Community Plugins](http://www.skilledtests.com/wiki/GNU_social_plugins)

