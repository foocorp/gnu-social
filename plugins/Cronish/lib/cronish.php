<?php
/**
 * GNU social cron-on-visit class
 *
 * Keeps track, through Config dataobject class, of relative time since the 
 * last run in order to to run event handlers with certain intervals.
 *
 * @category  Cron
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Cronish
{
    /**
     * Will call events as close as it gets to one hour. Event handlers
     * which use this MUST be as quick as possible, maybe only adding a
     * queue item to be handled later or something. Otherwise execution
     * will timeout for PHP - or at least cause unnecessary delays for
     * the unlucky user who visits the site exactly at one of these events.
     */
    public function callTimedEvents()
    {
        $timers = array('minutely' => 60,   // this is NOT guaranteed to run every minute (only on busy sites)
                        'hourly' => 3600,
                        'daily'  => 86400,
                        'weekly' => 604800);

        foreach($timers as $name=>$interval) {
            $run = false;

            $lastrun = new Config();
            $lastrun->section = 'cron';
            $lastrun->setting = 'last_' . $name;
            $found = $lastrun->find(true);

            if (!$found) {
                $lastrun->value = time();
                if ($lastrun->insert() === false) {
                    common_log(LOG_WARNING, "Could not save 'cron' setting '{$name}'");
                    continue;
                }
                $run = true;
            } elseif ($lastrun->value < time() - $interval) {
                $orig    = clone($lastrun);
                $lastrun->value = time();
                $lastrun->update($orig);
                $run = true;
            }

            if ($run === true) {
                // such as CronHourly, CronDaily, CronWeekly
                Event::handle('Cron' . ucfirst($name));
            }
        }
    }
}
