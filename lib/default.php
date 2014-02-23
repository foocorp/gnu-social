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
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-9 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

$default =
    array('site' =>
        array('name' => 'Just another GNU social node',
              'nickname' => 'gnusocial',
              'wildcard' => null,
              'server' => $_server,
              'theme' => 'neo',
              'path' => $_path,
              'logfile' => null,
              'logo' => null,
              'ssllogo' => null,
              'logdebug' => false,
              'logperf' => false, // Enable to dump performance counters to syslog
              'logperf_detail' => false, // Enable to dump every counter hit
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
              'inviteonly' => true,
              'private' => true,
              'ssl' => 'never',
              'sslserver' => null,
              'dupelimit' => 60, // default for same person saying the same thing
              'textlimit' => 0, // in chars; 0 == no limit
              'indent' => true,
              'use_x_sendfile' => false,
              'notice' => null, // site wide notice text
              'build' => 1, // build number, for code-dependent cache
              'minify' => false, // true to use the minified versions of JS files; false to use orig files. Can aid during development
              ),
        'db' =>
          array('database' => null, // must be set
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
              'log_slow_queries' => 0, // if set, log queries taking over N seconds
              'mysql_foreign_keys' => false), // if set, enables experimental foreign key support on MySQL
        'syslog' =>
        array('appname' => 'statusnet', # for syslog
              'priority' => 'debug', # XXX: currently ignored
              'facility' => LOG_USER),
        'queue' =>
        array('enabled' => true,
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
              'biolimit' => null,
              'backup' => true,
              'restore' => true,
              'delete' => false,
              'move' => true),
        'image' =>
        array('jpegquality' => 85),
        'avatar' =>
        array('server' => null,
              'dir' => INSTALLDIR . '/avatar/',
              'path' => $_path . '/avatar/',
              'ssl' => null,
              'maxsize' => 300),
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
        'usertheme' =>
        array('linkcolor' => 'black',
              'backgroundcolor' => 'black'),
        'theme_upload' =>
        array('enabled' => extension_loaded('zip')),
        'javascript' =>
        array('server' => null,
              'path'=> null,
              'ssl' => null,
              'bustframes' => true),
        'local' => // To override path/server for themes in 'local' dir (not currently applied to local plugins)
        array('server' => null,
              'dir' => null,
              'path' => null,
              'ssl' => null),
        'throttle' =>
        array('enabled' => false, // whether to throttle edits; false by default
              'count' => 20, // number of allowed messages in timespan
              'timespan' => 600), // timespan for throttling
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
        array('enabled' => false),
        'sms' =>
        array('enabled' => false),
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
        'attachments' =>
        array('server' => null,
              'dir' => INSTALLDIR . '/file/',
              'path' => $_path . '/file/',
              'sslserver' => null,
              'sslpath' => null,
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
              'show_thumbs' => true, // show thumbnails in notice lists for uploaded images, and photos and videos linked remotely that provide oEmbed info
              'thumb_width' => 100,
              'thumb_height' => 75,
              'process_links' => true, // check linked resources for embeddable photos and videos; this will hit referenced external web sites when processing new messages.
              ),
        'application' =>
        array('desclimit' => null),
        'group' =>
        array('maxaliases' => 3,
              'desclimit' => null,
              'addtag' => false),
        'peopletag' =>
        array('maxtags' => 100, // maximum number of tags a user can create.
              'maxpeople' => 500, // maximum no. of people with the same tag by the same user
              'allow_tagging' => array('all' => true), // equivalent to array('local' => true, 'remote' => true)
              'desclimit' => null),
        'oembed' =>
        array('endpoint' => 'https://noembed.com/embed/',
              'order' => array('built-in', 'well-known', 'service', 'discovery'),
        ),
        'search' =>
        array('type' => 'like'),
        'sessions' =>
        array('handle' => false,   // whether to handle sessions ourselves
              'debug' => false,    // debugging output for sessions
              'gc_limit' => 1000), // max sessions to expire at a time
        'notice' =>
        array('contentlimit' => null,
              'defaultscope' => null, // null means 1 if site/private, 0 otherwise
              'hidespam' => false), // Whether to hide silenced users from timelines
        'message' =>
        array('contentlimit' => null),
        'location' =>
        array('share' => 'user', // whether to share location; 'always', 'user', 'never'
              'sharedefault' => false),
        'logincommand' =>
        array('disabled' => true),
        'plugins' =>
        array('core' => array(
                            'AuthCrypt' => array(),
                            'Cronish' => array(),
                            'LRDD' => array(),
                            'OpportunisticQM' => array(),
                            'StrictTransportSecurity' => array(),
                        ),
              'default' => array(
                            'Activity' => array(),
                            'Bookmark' => array(),
                            'ClientSideShorten' => array(),
                            'Event' => array(),
                            'OpenID' => array(),
                            'Poll' => array(),
                            'QnA' => array(),
                            'SearchSub' => array(),
                            'TagSub' => array(),
                        ),
              'locale_path' => false, // Set to a path to use *instead of* each plugin's own locale subdirectories
              'server' => null,
              'sslserver' => null,
              'path' => null,
              'sslpath' => null,
              ),
        'pluginlist' => array(),
        'admin' =>
        array('panels' => array('site', 'user', 'paths', 'access', 'sessions', 'sitenotice', 'license', 'plugins')),
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
              'peopletag' => true,
              'external' => 'sometimes'), // Options: 'sometimes', 'never', default = 'sometimes'
        'url' =>
        array('shortener' => 'internal',
              'maxurllength' => 100,
              'maxnoticelength' => -1),
        'http' => // HTTP client settings when contacting other sites
        array('ssl_cafile' => false, // To enable SSL cert validation, point to a CA bundle (eg '/usr/lib/ssl/certs/ca-certificates.crt')
              'curl' => false, // Use CURL backend for HTTP fetches if available. (If not, PHP's socket streams will be used.)
              'proxy_host' => null,
              'proxy_port' => null,
              'proxy_user' => null,
              'proxy_password' => null,
              'proxy_auth_scheme' => null,
              ),
        'router' =>
        array('cache' => true), // whether to cache the router object. Defaults to true, turn off for devel
        'discovery' =>
          array('cors' => false), // Allow Cross-Origin Resource Sharing for service discovery (host-meta, XRD, etc.)
        'performance' => array('high' => false) // disable some features for higher performance; default false
    );
