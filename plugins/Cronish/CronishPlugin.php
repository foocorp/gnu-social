<?php

class CronishPlugin extends Plugin {
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
