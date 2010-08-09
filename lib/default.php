<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Default settings for core configuration
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
 * @category  Config
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-9 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

$default =
  array('site' =>
        array('name' => 'Just another StatusNet microblog',
              'nickname' => 'statusnet',
              'wildcard' => null,
              'server' => $_server,
              'theme' => 'default',
              'path' => $_path,
              'logfile' => null,
              'logo' => null,
              'logdebug' => false,
              'fancy' => false,
              'locale_path' => INSTALLDIR.'/locale',
              'language' => 'en',
              'langdetect' => true,
              'languages' => get_all_languages(),
              'email' =>
              array_key_exists('SERVER_ADMIN', $_SERVER) ? $_SERVER['SERVER_ADMIN'] : null,
              'broughtby' => null,
              'timezone' => 'UTC',
              'broughtbyurl' => null,
              'closed' => false,
              'inviteonly' => false,
              'private' => false,
              'ssl' => 'never',
              'sslserver' => null,
              'shorturllength' => 30,
              'dupelimit' => 60, // default for same person saying the same thing
              'textlimit' => 140,
              'indent' => true,
              'use_x_sendfile' => false,
              'notice' => null // site wide notice text
              ),
        'db' =>
        array('database' => 'YOU HAVE TO SET THIS IN config.php',
              'schema_location' => INSTALLDIR . '/classes',
              'class_location' => INSTALLDIR . '/classes',
              'require_prefix' => 'classes/',
              'class_prefix' => '',
              'mirror' => null,
              'utf8' => true,
              'db_driver' => 'DB', # XXX: JanRain libs only work with DB
              'quote_identifiers' => false,
              'type' => 'mysql',
              'schemacheck' => 'runtime', // 'runtime' or 'script'
              'annotate_queries' => false, // true to add caller comments to queries, eg /* POST Notice::saveNew */
              'log_queries' => false, // true to log all DB queries
              'log_slow_queries' => 0), // if set, log queries taking over N seconds
        'syslog' =>
        array('appname' => 'statusnet', # for syslog
              'priority' => 'debug', # XXX: currently ignored
              'facility' => LOG_USER),
        'queue' =>
        array('enabled' => false,
              'subsystem' => 'db', # default to database, or 'stomp'
              'stomp_server' => null,
              'queue_basename' => '/queue/statusnet/',
              'control_channel' => '/topic/statusnet/control', // broadcasts to all queue daemons
              'stomp_username' => null,
              'stomp_password' => null,
              'stomp_persistent' => true, // keep items across queue server restart, if persistence is enabled
              'stomp_transactions' => true, // use STOMP transactions to aid in detecting failures (supported by ActiveMQ, but not by all)
              'stomp_acks' => true, // send acknowledgements after successful processing (supported by ActiveMQ, but not by all)
              'stomp_manual_failover' => true, // if multiple servers are listed, treat them as separate (enqueue on one randomly, listen on all)
              'monitor' => null, // URL to monitor ping endpoint (work in progress)
              'softlimit' => '90%', // total size or % of memory_limit at which to restart queue threads gracefully
              'spawndelay' => 1, // Wait at least N seconds between (re)spawns of child processes to avoid slamming the queue server with subscription startup
              'debug_memory' => false, // true to spit memory usage to log
              'inboxes' => true, // true to do inbox distribution & output queueing from in background via 'distrib' queue
              'breakout' => array(), // List queue specifiers to break out when using Stomp queue.
                                     // Default will share all queues for all sites within each group.
                                     // Specify as <group>/<queue> or <group>/<queue>/<site>,
                                     // using nickname identifier as site.
                                     //
                                     // 'main/distrib' separate "distrib" queue covering all sites
                                     // 'xmpp/xmppout/mysite' separate "xmppout" queue covering just 'mysite'
              'max_retries' => 10, // drop messages after N failed attempts to process (Stomp)
              'dead_letter_dir' => false, // set to directory to save dropped messages into (Stomp)
              ),
        'license' =>
        array('type' => 'cc', # can be 'cc', 'allrightsreserved', 'private'
              'owner' => null, # can be name of content owner e.g. for enterprise
              'url' => 'http://creativecommons.org/licenses/by/3.0/',
              'title' => 'Creative Commons Attribution 3.0',
              'image' => 'http://i.creativecommons.org/l/by/3.0/80x15.png'),
        'mail' =>
        array('backend' => 'mail',
              'params' => null,
              'domain_check' => true),
        'nickname' =>
        array('blacklist' => array(),
              'featured' => array()),
        'profile' =>
        array('banned' => array(),
              'biolimit' => null),
        'avatar' =>
        array('server' => null,
              'dir' => INSTALLDIR . '/avatar/',
              'path' => $_path . '/avatar/',
              'ssl' => null),
        'background' =>
        array('server' => null,
              'dir' => INSTALLDIR . '/background/',
              'path' => $_path . '/background/',
              'ssl' => null),
        'public' =>
        array('localonly' => true,
              'blacklist' => array(),
              'autosource' => array()),
        'theme' =>
        array('server' => null,
              'dir' => null,
              'path'=> null,
              'ssl' => null),
        'theme_upload' =>
        array('enabled' => extension_loaded('zip')),
        'javascript' =>
        array('server' => null,
              'path'=> null,
              'ssl' => null),
        'local' => // To override path/server for themes in 'local' dir (not currently applied to local plugins)
        array('server' => null,
              'dir' => null,
              'path' => null,
              'ssl' => null),
        'throttle' =>
        array('enabled' => false, // whether to throttle edits; false by default
              'count' => 20, // number of allowed messages in timespan
              'timespan' => 600), // timespan for throttling
        'xmpp' =>
        array('enabled' => false,
              'server' => 'INVALID SERVER',
              'port' => 5222,
              'user' => 'update',
              'encryption' => true,
              'resource' => 'uniquename',
              'password' => 'blahblahblah',
              'host' => null, # only set if != server
              'debug' => false, # print extra debug info
              'public' => array()), # JIDs of users who want to receive the public stream
        'invite' =>
        array('enabled' => true),
        'tag' =>
        array('dropoff' => 864000.0,   # controls weighting based on age
              'cutoff' => 86400 * 90), # only look at notices posted in last 90 days
        'popular' =>
        array('dropoff' => 864000.0,   # controls weighting based on age
              'cutoff' => 86400 * 90), # only look at notices favorited in last 90 days
        'daemon' =>
        array('piddir' => '/var/run',
              'user' => false,
              'group' => false),
        'emailpost' =>
        array('enabled' => true),
        'sms' =>
        array('enabled' => true),
        'twitterimport' =>
        array('enabled' => false),
        'integration' =>
        array('source' => 'StatusNet', # source attribute for Twitter
              'taguri' => null), # base for tag URIs
        'twitter' =>
        array('signin' => true,
              'consumer_key' => null,
              'consumer_secret' => null),
        'cache' =>
        array('base' => null),
        'ping' =>
        array('notify' => array(),
              'timeout' => 2),
        'inboxes' =>
        array('enabled' => true), # ignored after 0.9.x
        'newuser' =>
        array('default' => null,
              'welcome' => null),
        'snapshot' =>
        array('run' => 'web',
              'frequency' => 10000,
              'reporturl' => 'http://status.net/stats/report'),
        'attachments' =>
        array('server' => null,
              'dir' => INSTALLDIR . '/file/',
              'path' => $_path . '/file/',
              'ssl' => null,
              'supported' => array('image/png',
                                   'image/jpeg',
                                   'image/gif',
                                   'image/svg+xml',
                                   'audio/mpeg',
                                   'audio/x-speex',
                                   'application/ogg',
                                   'application/pdf',
                                   'application/vnd.oasis.opendocument.text',
                                   'application/vnd.oasis.opendocument.text-template',
                                   'application/vnd.oasis.opendocument.graphics',
                                   'application/vnd.oasis.opendocument.graphics-template',
                                   'application/vnd.oasis.opendocument.presentation',
                                   'application/vnd.oasis.opendocument.presentation-template',
                                   'application/vnd.oasis.opendocument.spreadsheet',
                                   'application/vnd.oasis.opendocument.spreadsheet-template',
                                   'application/vnd.oasis.opendocument.chart',
                                   'application/vnd.oasis.opendocument.chart-template',
                                   'application/vnd.oasis.opendocument.image',
                                   'application/vnd.oasis.opendocument.image-template',
                                   'application/vnd.oasis.opendocument.formula',
                                   'application/vnd.oasis.opendocument.formula-template',
                                   'application/vnd.oasis.opendocument.text-master',
                                   'application/vnd.oasis.opendocument.text-web',
                                   'application/x-zip',
                                   'application/zip',
                                   'text/plain',
                                   'video/mpeg',
                                   'video/mp4',
                                   'video/quicktime',
                                   'video/mpeg'),
              'file_quota' => 5000000,
              'user_quota' => 50000000,
              'monthly_quota' => 15000000,
              'uploads' => true,
              'filecommand' => '/usr/bin/file',
              ),
        'application' =>
        array('desclimit' => null),
        'group' =>
        array('maxaliases' => 3,
              'desclimit' => null),
        'oohembed' => array('endpoint' => 'http://oohembed.com/oohembed/'),
        'search' =>
        array('type' => 'fulltext'),
        'sessions' =>
        array('handle' => false, // whether to handle sessions ourselves
              'debug' => false), // debugging output for sessions
        'design' =>
        array('backgroundcolor' => null, // null -> 'use theme default'
              'contentcolor' => null,
              'sidebarcolor' => null,
              'textcolor' => null,
              'linkcolor' => null,
              'backgroundimage' => null,
              'disposition' => null),
        'custom_css' =>
        array('enabled' => true,
              'css' => ''),
        'notice' =>
        array('contentlimit' => null),
        'message' =>
        array('contentlimit' => null),
        'location' =>
        array('share' => 'user', // whether to share location; 'always', 'user', 'never'
              'sharedefault' => true),
        'omb' =>
        array('timeout' => 5), // HTTP request timeout in seconds when contacting remote hosts for OMB updates
        'logincommand' =>
        array('disabled' => true),
        'plugins' =>
        array('default' => array('LilUrl' => array('shortenerName'=>'ur1.ca',
                                                   'freeService' => true,
                                                   'serviceUrl'=>'http://ur1.ca/'),
                                 'PtitUrl' => array('shortenerName' => 'ptiturl.com',
                                                    'serviceUrl' => 'http://ptiturl.com/?creer=oui&action=Reduire&url=%1$s'),
                                 'SimpleUrl' => array(array('shortenerName' => 'is.gd', 'serviceUrl' => 'http://is.gd/api.php?longurl=%1$s'),
                                                      array('shortenerName' => 'snipr.com', 'serviceUrl' => 'http://snipr.com/site/snip?r=simple&link=%1$s'),
                                                      array('shortenerName' => 'metamark.net', 'serviceUrl' => 'http://metamark.net/api/rest/simple?long_url=%1$s'),
                                                      array('shortenerName' => 'tinyurl.com', 'serviceUrl' => 'http://tinyurl.com/api-create.php?url=%1$s')),
                                 'TightUrl' => array('shortenerName' => '2tu.us', 'freeService' => true,'serviceUrl'=>'http://2tu.us/?save=y&url=%1$s'),
                                 'Geonames' => null,
                                 'Mapstraction' => null,
                                 'OStatus' => null,
                                 'WikiHashtags' => null,
                                 'RSSCloud' => null,
                                 'ClientSideShorten' => null,
                                 'OpenID' => null),
              ),
        'pluginlist' => array(),
        'admin' =>
        array('panels' => array('design', 'site', 'user', 'paths', 'access', 'sessions', 'sitenotice', 'plugins')),
        'singleuser' =>
        array('enabled' => false,
              'nickname' => null),
        'robotstxt' =>
        array('crawldelay' => 0,
              'disallow' => array('main', 'settings', 'admin', 'search', 'message')
              ),
        'api' =>
        array('realm' => null),
        'nofollow' =>
        array('subscribers' => true,
              'members' => true,
              'peopletag' => true),
        'url' =>
        array('shortener' => 'ur1.ca',
              'maxlength' => 25,
              'maxnoticelength' => -1),
        'http' => // HTTP client settings when contacting other sites
        array('ssl_cafile' => false // To enable SSL cert validation, point to a CA bundle (eg '/usr/lib/ssl/certs/ca-certificates.crt')
              ),
        );
