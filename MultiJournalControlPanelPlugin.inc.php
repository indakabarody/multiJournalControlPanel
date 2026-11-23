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

import('lib.pkp.classes.plugins.GenericPlugin');

use Illuminate\Database\Capsule\Manager as Capsule;

class MultiJournalControlPanelPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            // Register DAO in DAORegistry
            $this->import('classes.dao.MultiJournalControlPanelDAO');
            $mjcpDao = new MultiJournalControlPanelDAO();
            DAORegistry::registerDAO('MultiJournalControlPanelDAO', $mjcpDao);

            // Route requests for /multiJournalControlPanel/*
            HookRegistry::register('LoadHandler', [$this, 'callbackLoadHandler']);

            // Add navigation item to OJS 3.3 backend sidebar menu
            HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'callbackSetupBackendPage']);

            // Add link in Site Administration > Site Management
            HookRegistry::register('Templates::Admin::Index::SiteManagement', [$this, 'callbackAdminSiteManagement']);

            // Register scheduled task with AcronPlugin
            HookRegistry::register('AcronPlugin::parseCronTab', [$this, 'callbackParseCronTab']);

            // Automatically sync enabled setting when a new journal is created
            HookRegistry::register('Context::add', [$this, 'callbackContextAdd']);

            // Workflow payment warning notification when an article in Copyediting/Production is unpaid
            HookRegistry::register('TemplateManager::display', [$this, 'callbackWorkflowPaymentWarning']);
        }

        return $success;
    }

    /**
     * Load locale files for OJS 3.3.0-24 compatibility.
     *
     * @copydoc Plugin::addLocaleData()
     */
    public function addLocaleData($locale = null)
    {
        $currentLocale = $locale ? $locale : AppLocale::getLocale();
        $result = parent::addLocaleData($currentLocale);
        foreach (['en_US', 'id_ID'] as $loc) {
            $poPath = $this->getPluginPath() . '/locale/' . $loc . '/locale.po';
            if (file_exists($poPath)) {
                AppLocale::registerLocaleFile($loc, $poPath);
            }
        }
        return $result;
    }

    /**
     * Declare this plugin as a site-wide plugin (matching `<sitewide>1</sitewide>` in `version.xml`).
     * Ensures OJS 3.3 `PluginAccessPolicy` restricts plugin enable/disable/manage actions to Site Administrators.
     *
     * @copydoc Plugin::isSitePlugin()
     */
    public function isSitePlugin()
    {
        return true;
    }

    /**
     * Determine whether or not this plugin is currently enabled.
     * When enabled in any journal or site-wide, synchronize across all contexts.
     *
     * @copydoc LazyLoadPlugin::getEnabled()
     */
    public function getEnabled($contextId = null)
    {
        if ($contextId === null) {
            $contextId = $this->getCurrentContextId();
            if ($this->isSitePlugin()) {
                $contextId = 0;
            }
        }

        $direct = parent::getEnabled($contextId);
        if (!empty($direct)) {
            return true;
        }

        if ($contextId !== 0) {
            $siteEnabled = parent::getEnabled(0);
            if (!empty($siteEnabled)) {
                return true;
            }
        }

        try {
            $enabledAnywhere = Capsule::table('plugin_settings')->where('plugin_name', '=', strtolower($this->getName()))->where('setting_name', '=', 'enabled')->where('setting_value', '=', '1')->exists();

            if ($enabledAnywhere) {
                $this->_syncEnabledAcrossAllContexts(true);
                return true;
            }
        } catch (Exception $e) {
        }

        return false;
    }

    /**
     * Set enabled state across all journals and site-level context.
     *
     * @copydoc LazyLoadPlugin::setEnabled()
     */
    public function setEnabled($enabled)
    {
        parent::setEnabled($enabled);
        $this->_syncEnabledAcrossAllContexts((bool) $enabled);
        if ($enabled) {
            $this->install();
        }
    }

    /**
     * Retrieve a plugin setting with fallback to direct `plugin_settings` query when PluginSettingsDAO is not registered.
     *
     * @copydoc Plugin::getSetting()
     */
    public function getSetting($contextId, $name)
    {
        if (DAORegistry::getDAO('PluginSettingsDAO')) {
            return parent::getSetting($contextId, $name);
        }
        try {
            $row = Capsule::table('plugin_settings')->where('context_id', '=', (int) $contextId)->where('plugin_name', '=', strtolower($this->getName()))->where('setting_name', '=', (string) $name)->first();
            if (!$row) {
                return null;
            }
            if ($row->setting_type === 'int') {
                return (int) $row->setting_value;
            }
            if ($row->setting_type === 'bool') {
                return (bool) $row->setting_value;
            }
            return $row->setting_value;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Update a plugin setting with fallback to direct `plugin_settings` upsert when PluginSettingsDAO is not registered.
     *
     * @copydoc Plugin::updateSetting()
     */
    public function updateSetting($contextId, $name, $value, $type = null)
    {
        if (DAORegistry::getDAO('PluginSettingsDAO')) {
            parent::updateSetting($contextId, $name, $value, $type);
            return;
        }
        try {
            $sType = $type ? (string) $type : 'string';
            $sVal = $sType === 'bool' ? ($value ? '1' : '0') : (string) $value;
            $exists = Capsule::table('plugin_settings')->where('context_id', '=', (int) $contextId)->where('plugin_name', '=', strtolower($this->getName()))->where('setting_name', '=', (string) $name)->exists();
            if ($exists) {
                Capsule::table('plugin_settings')
                    ->where('context_id', '=', (int) $contextId)
                    ->where('plugin_name', '=', strtolower($this->getName()))
                    ->where('setting_name', '=', (string) $name)
                    ->update(['setting_value' => $sVal, 'setting_type' => $sType]);
            } else {
                Capsule::table('plugin_settings')->insert([
                    'context_id' => (int) $contextId,
                    'plugin_name' => strtolower($this->getName()),
                    'setting_name' => (string) $name,
                    'setting_value' => $sVal,
                    'setting_type' => $sType,
                ]);
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Synchronize enabled setting across context 0 and all journals.
     *
     * @param bool $enabled
     * @return void
     */
    protected function _syncEnabledAcrossAllContexts($enabled)
    {
        $this->updateSetting(0, 'enabled', $enabled, 'bool');
        try {
            $journalIds = Capsule::table('journals')->pluck('journal_id');
            foreach ($journalIds as $jid) {
                $this->updateSetting((int) $jid, 'enabled', $enabled, 'bool');
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Lifecycle method: install plugin database tables and default seed data.
     *
     * @return bool
     */
    public function install()
    {
        try {
            $migration = $this->getInstallMigration();
            if ($migration) {
                $migration->up();
            }
            return true;
        } catch (Exception $e) {
            error_log('MultiJournalControlPanelPlugin::install error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lifecycle method: upgrade plugin schema and seed missing defaults without losing data.
     *
     * @return bool
     */
    public function upgrade()
    {
        return $this->install();
    }

    /**
     * Lifecycle method: uninstall plugin safely.
     * Never deletes OJS editorial data and never drops plugin tables automatically without confirmation.
     *
     * @param bool $confirmDropPluginTables
     * @return bool
     */
    public function uninstall($confirmDropPluginTables = false)
    {
        try {
            if ($confirmDropPluginTables) {
                $migration = $this->getInstallMigration();
                if ($migration) {
                    $migration->down(true);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log('MultiJournalControlPanelPlugin::uninstall error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.multiJournalControlPanel.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.multiJournalControlPanel.description');
    }

    /**
     * Return the path to this plugin's directory, falling back to __DIR__ if called before register().
     *
     * @copydoc Plugin::getPluginPath()
     */
    public function getPluginPath()
    {
        $path = parent::getPluginPath();
        return $path ? $path : __DIR__;
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     */
    public function getInstallMigration()
    {
        require_once __DIR__ . '/MultiJournalControlPanelSchemaMigration.inc.php';
        return new MultiJournalControlPanelSchemaMigration();
    }

    /**
     * Hook callback: route `multiJournalControlPanel` page requests to `MultiJournalControlPanelHandler`.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackLoadHandler($hookName, $args)
    {
        $page = &$args[0];

        if ($page === 'multiJournalControlPanel') {
            define('HANDLER_CLASS', 'MultiJournalControlPanelHandler');
            $this->import('pages.MultiJournalControlPanelHandler');
            MultiJournalControlPanelHandler::setPlugin($this);
            return true;
        }

        return false;
    }

    /**
     * Hook callback: inject "Control Panel" link into OJS 3.3 backend sidebar navigation for authorized users.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackSetupBackendPage($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $user = $request ? $request->getUser() : null;
        if (!$user) {
            return false;
        }

        $this->import('classes.services.DashboardService');
        $dashboardService = new DashboardService($this);
        $context = $request->getContext();
        $ctxId = $context ? (int) $context->getId() : 0;
        $perms = $dashboardService->resolveUserPermissions($user, $ctxId);

        if (!$perms['canViewDashboard'] && !$perms['canViewPayment']) {
            return false;
        }

        $templateMgr = TemplateManager::getManager($request);
        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();

        $requestedPage = $router->getRequestedPage($request);
        $contextPath = $context ? $context->getPath() : 'index';
        $defaultOp = $perms['canViewDashboard'] ? 'index' : 'payments';
        $panelUrl = $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', $defaultOp);

        $menu = (array) $templateMgr->getState('menu');
        $navItem = [
            'name' => __('mjcp.nav.sidebarTitle'),
            'url' => $panelUrl,
            'isCurrent' => $requestedPage === 'multiJournalControlPanel',
        ];

        $newMenu = [];
        $inserted = false;
        foreach ($menu as $k => $v) {
            $newMenu[$k] = $v;
            if ($k === 'submissions') {
                $newMenu['multiJournalControlPanel'] = $navItem;
                $inserted = true;
            }
        }
        if (!$inserted) {
            $newMenu['multiJournalControlPanel'] = $navItem;
        }

        $templateMgr->setState(['menu' => $newMenu]);

        // Load plugin script on OJS management/admin pages so Settings AjaxModal binds AjaxFormHandler
        if ($requestedPage === 'management' || $requestedPage === 'admin') {
            $baseUrl = $request->getBaseUrl();
            $pluginUrl = $baseUrl . '/' . $this->getPluginPath();
            $templateMgr->addJavaScript('mjcpScript', $pluginUrl . '/js/multiJournalControlPanel.js', ['contexts' => ['backend']]);
        }

        return false;
    }

    /**
     * Hook callback: add link to Multi-Journal Control Panel inside Administration > Site Management.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackAdminSiteManagement($hookName, $args)
    {
        if (!Validation::isSiteAdmin()) {
            return false;
        }
        $output = &$args[2];
        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        $url = $dispatcher->url($request, ROUTE_PAGE, 'index', 'multiJournalControlPanel');
        $label = htmlspecialchars(__('plugins.generic.multiJournalControlPanel.displayName'), ENT_QUOTES, 'UTF-8');

        $output .= '<li><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a></li>';
        return false;
    }

    /**
     * Hook callback: register `scheduledTasks.xml` with `AcronPlugin`.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackParseCronTab($hookName, $args)
    {
        $taskFilesPath = &$args[0];
        $taskFilesPath[] = $this->getPluginPath() . '/scheduledTasks.xml';
        return false;
    }

    /**
     * Hook callback: enable plugin automatically for newly created journals.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackContextAdd($hookName, $args)
    {
        $context = isset($args[0]) ? $args[0] : null;
        if ($context && $this->getEnabled()) {
            $this->updateSetting((int) $context->getId(), 'enabled', true, 'bool');
        }
        return false;
    }

    /**
     * Display a non-blocking warning notification when a Journal Manager opens the OJS editorial
     * workflow for a submission in Copyediting (stage 4) or Production (stage 5) whose payment status is unpaid.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackWorkflowPaymentWarning($hookName, $args)
    {
        $templateMgr = isset($args[0]) ? $args[0] : null;
        $template = isset($args[1]) ? (string) $args[1] : '';

        if ($template !== 'workflow/workflow.tpl' || !$templateMgr) {
            return false;
        }

        try {
            $submission = $templateMgr->getTemplateVars('submission');
            if (!$submission || !is_object($submission) || !method_exists($submission, 'getId')) {
                return false;
            }
            $stageId = (int) $submission->getStageId();
            if ($stageId < 4) {
                return false;
            }

            $subId = (int) $submission->getId();
            $payRow = Capsule::table('mjcp_payments')->where('submission_id', '=', $subId)->first();
            $payStatus = $payRow ? strtolower((string) $payRow->payment_status) : 'unpaid';

            if ($payStatus !== 'paid') {
                $request = Application::get()->getRequest();
                $user = $request ? $request->getUser() : null;
                if ($user) {
                    // Enforce access rules: only registered Journal Managers / payment-authorized users see unpaid warnings.
                    // Directors, copyeditors, reviewers, and unregistered users must never see payment status.
                    $this->import('classes.services.DashboardService');
                    $dashboardService = new DashboardService($this);
                    $context = $request->getContext();
                    $ctxId = $context ? (int) $context->getId() : 0;
                    $perms = $dashboardService->resolveUserPermissions($user, $ctxId);
                    if (!$perms['canViewPayment'] || $perms['isStrictDirector']) {
                        return false;
                    }

                    import('classes.notification.NotificationManager');
                    $notificationMgr = new NotificationManager();
                    $notificationMgr->createTrivialNotification((int) $user->getId(), NOTIFICATION_TYPE_WARNING, ['contents' => __('mjcp.payment.workflowWarningUnpaid', ['id' => $subId])]);
                    // Ensure SiteHandler.js displays the trivial notification immediately on this page load
                    $templateMgr->assign('hasSystemNotifications', true);
                }
            }
        } catch (Exception $e) {
        }

        return false;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $dispatcher = $request->getDispatcher();
        $router = $request->getRouter();
        $context = $request->getContext();
        $contextPath = $context ? $context->getPath() : 'index';

        import('lib.pkp.classes.linkAction.request.AjaxModal');
        import('lib.pkp.classes.linkAction.request.RedirectAction');

        $openPanelAction = new LinkAction('openControlPanel', new RedirectAction($dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel')), __('mjcp.action.openControlPanel'), null);

        $user = $request->getUser();
        $canManageSettings = false;
        if ($user) {
            $this->import('classes.services.DashboardService');
            $dashboardService = new DashboardService($this);
            $perms = $dashboardService->resolveUserPermissions($user, 0);
            $canManageSettings = !empty($perms['canManageSettings']);
        }

        if ($canManageSettings) {
            $settingsAction = new LinkAction('settings', new AjaxModal($router->url($request, null, null, 'manage', null, array_merge((array) $actionArgs, ['verb' => 'settings'])), $this->getDisplayName()), __('manager.plugins.settings'), null);
            array_unshift($actions, $openPanelAction, $settingsAction);
        } else {
            array_unshift($actions, $openPanelAction);
        }
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        $user = $request->getUser();
        if (!$user) {
            return parent::manage($args, $request);
        }

        $this->import('classes.services.DashboardService');
        $dashboardService = new DashboardService($this);
        $perms = $dashboardService->resolveUserPermissions($user, 0);
        if (empty($perms['canManageSettings'])) {
            $dao = $dashboardService->getDAO();
            $ipHash = hash('sha256', (string) $request->getRemoteAddr() . '|mjcp_privacy_salt_2026');
            $dao->logAudit(0, null, (int) $user->getId(), (string) $user->getUsername(), 'unauthorized_plugin_manage_settings', 'security', null, 'Access Denied', $ipHash);
            return new JSONMessage(false, __('mjcp.error.accessDenied'));
        }

        switch ($request->getUserVar('verb')) {
            case 'settings':
                $this->import('classes.forms.MultiJournalControlPanelSettingsForm');
                $form = new MultiJournalControlPanelSettingsForm($this);

                if (!$request->getUserVar('save')) {
                    $form->initData();
                    return new JSONMessage(true, $form->fetch($request));
                }

                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    return new JSONMessage(true);
                }
                return new JSONMessage(true, $form->fetch($request));
        }

        return parent::manage($args, $request);
    }
}
