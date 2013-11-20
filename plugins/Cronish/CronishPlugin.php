<?php
/**
 * GNU social cronish plugin, to imitate cron actions
 *
 * @category  Cron
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://www.gnu.org/software/social/
 */

class CronishPlugin extends Plugin {
    public function onCronMinutely()
    {
        common_debug('CRON: Running minutely cron job!');
    }

    public function onCronHourly()
    {
        common_debug('CRON: Running hourly cron job!');
    }

    public function onCronDaily()
    {
        common_debug('CRON: Running daily cron job!');
    }

    public function onCronWeekly()
    {
        common_debug('CRON: Running weekly cron job!');
    }

    /**
     * When the page has finished rendering, let's do some cron jobs
     * if we have the time.
     */
    public function onEndActionExecute($status, Action $action)
    {
        $cron = new Cronish(); 
        $cron->callTimedEvents();

        return true;
    }

    public function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Cronish',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://www.gnu.org/software/social/',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Cronish plugin that executes events on a near-hour/day/week basis.'));
        return true;
    }
}
