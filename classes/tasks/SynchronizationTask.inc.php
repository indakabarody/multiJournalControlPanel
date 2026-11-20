<?php
/**
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');

class SynchronizationTask extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('mjcp.sync.taskName');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions()
    {
        $plugin = PluginRegistry::getPlugin('generic', 'multijournalcontrolpanelplugin');
        if (!$plugin) {
            $plugin = PluginRegistry::loadPlugin('generic', 'multiJournalControlPanel');
        }
        if (!$plugin) {
            return false;
        }

        $plugin->addLocaleData();
        require_once dirname(__DIR__) . '/services/SynchronizationService.inc.php';
        $syncService = new SynchronizationService($plugin);
        $result = $syncService->synchronizeJournals(null, 'scheduled', null, 'en_US');

        return !empty($result['ok']);
    }
}
