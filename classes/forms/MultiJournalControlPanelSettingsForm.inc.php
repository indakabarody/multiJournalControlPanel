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

import('lib.pkp.classes.form.Form');

require_once dirname(__DIR__) . '/dao/MultiJournalControlPanelDAO.inc.php';

class MultiJournalControlPanelSettingsForm extends Form
{
    /** @var MultiJournalControlPanelPlugin */
    public $plugin;

    /** @var MultiJournalControlPanelDAO */
    public $dao;

    /**
     * Constructor.
     *
     * @param MultiJournalControlPanelPlugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin->getTemplateResource('settings/modal.tpl'));
        $this->plugin = $plugin;
        $this->dao = new MultiJournalControlPanelDAO();

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Load settings from database.
     *
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $thresholds = $this->dao->getStageThresholds(0);
        $paymentUsers = $this->dao->getPaymentAccessUsernames();
        $tokenExpDays = (int) $this->plugin->getSetting(0, 'tokenExpirationDays');
        if ($tokenExpDays <= 0) {
            $tokenExpDays = 30;
        }
        $maxUploadMb = (int) $this->plugin->getSetting(0, 'maxUploadSizeMb');
        if ($maxUploadMb <= 0) {
            $maxUploadMb = 1;
        }
        $loaValidationUrl = (string) $this->plugin->getSetting(0, 'loaValidationUrl');
        $houseName = (string) $this->plugin->getSetting(0, 'publishingHouseName');
        if ($houseName === '') {
            $request = Application::get()->getRequest();
            $site = $request && method_exists($request, 'getSite') ? $request->getSite() : null;
            $houseName = $site && method_exists($site, 'getLocalizedTitle') ? trim((string) $site->getLocalizedTitle()) : '';
        }
        if ($houseName === '') {
            $houseName = __('mjcp.common.defaultHouseName');
        }

        $this->setData('publishingHouseName', $houseName);
        $this->setData('round1Limit', $thresholds['round1Limit']);
        $this->setData('round2Limit', $thresholds['round2Limit']);
        $this->setData('copyeditingWarning', $thresholds['copyeditingWarning']);
        $this->setData('paymentAccessUsernames', implode(', ', $paymentUsers));
        $this->setData('tokenExpirationDays', $tokenExpDays);
        $this->setData('maxUploadSizeMb', $maxUploadMb);
        $this->setData('loaValidationUrl', $loaValidationUrl);

        parent::initData();
    }

    /**
     * Read submitted form values.
     *
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['publishingHouseName', 'round1Limit', 'round2Limit', 'copyeditingWarning', 'paymentAccessUsernames', 'tokenExpirationDays', 'maxUploadSizeMb', 'loaValidationUrl']);
        parent::readInputData();
    }

    /**
     * Render form template.
     *
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'fullSettingsUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'settings'),
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Save settings and write audit log.
     *
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $userId = $user ? (int) $user->getId() : 0;
        $username = $user ? (string) $user->getUsername() : '';

        $houseName = trim((string) $this->getData('publishingHouseName'));
        if ($houseName === '') {
            $site = $request && method_exists($request, 'getSite') ? $request->getSite() : null;
            $houseName = $site && method_exists($site, 'getLocalizedTitle') ? trim((string) $site->getLocalizedTitle()) : '';
        }
        if ($houseName === '') {
            $houseName = __('mjcp.common.defaultHouseName');
        }

        $round1Limit = max(14, min(21, (int) $this->getData('round1Limit')));
        $round2Limit = max(1, (int) $this->getData('round2Limit'));
        $ceWarning = max(1, (int) $this->getData('copyeditingWarning'));
        $paymentAccessUsernames = trim((string) $this->getData('paymentAccessUsernames'));
        $tokenExpirationDays = max(1, (int) $this->getData('tokenExpirationDays'));
        $maxUploadSizeMb = max(1, min(10, (int) $this->getData('maxUploadSizeMb')));
        $loaValidationUrl = trim((string) $this->getData('loaValidationUrl'));

        $oldThresholds = $this->dao->getStageThresholds(0);
        $oldValues = [
            'publishingHouseName' => (string) $this->plugin->getSetting(0, 'publishingHouseName'),
            'round1Limit' => $oldThresholds['round1Limit'],
            'round2Limit' => $oldThresholds['round2Limit'],
            'copyeditingWarning' => $oldThresholds['copyeditingWarning'],
            'paymentAccessUsernames' => implode(', ', $this->dao->getPaymentAccessUsernames()),
            'tokenExpirationDays' => (int) $this->plugin->getSetting(0, 'tokenExpirationDays'),
            'maxUploadSizeMb' => (int) $this->plugin->getSetting(0, 'maxUploadSizeMb'),
            'loaValidationUrl' => (string) $this->plugin->getSetting(0, 'loaValidationUrl'),
        ];

        $this->plugin->updateSetting(0, 'publishingHouseName', $houseName, 'string');
        $this->plugin->updateSetting(0, 'tokenExpirationDays', $tokenExpirationDays, 'int');
        $this->plugin->updateSetting(0, 'maxUploadSizeMb', $maxUploadSizeMb, 'int');
        $this->plugin->updateSetting(0, 'maxUploadSizeBytes', $maxUploadSizeMb * 1048576, 'int');
        if ($request && $request->getUserVar('loaValidationUrl') !== null) {
            $this->plugin->updateSetting(0, 'loaValidationUrl', $loaValidationUrl, 'string');
        }

        $this->dao->saveStageThresholds(
            0,
            [
                'round1Limit' => $round1Limit,
                'round2Limit' => $round2Limit,
                'copyeditingWarning' => $ceWarning,
            ],
            $userId,
        );

        $this->dao->setPaymentAccessUsernames($paymentAccessUsernames, $userId);

        $this->dao->logAudit(0, null, $userId, $username, 'plugin_settings_updated', 'plugin_settings', $oldValues, [
            'publishingHouseName' => $houseName,
            'round1Limit' => $round1Limit,
            'round2Limit' => $round2Limit,
            'copyeditingWarning' => $ceWarning,
            'paymentAccessUsernames' => $paymentAccessUsernames,
            'tokenExpirationDays' => $tokenExpirationDays,
            'maxUploadSizeMb' => $maxUploadSizeMb,
            'loaValidationUrl' => $request && $request->getUserVar('loaValidationUrl') !== null ? $loaValidationUrl : $oldValues['loaValidationUrl'],
        ]);

        import('classes.notification.NotificationManager');
        $notificationMgr = new NotificationManager();
        if ($userId > 0) {
            $notificationMgr->createTrivialNotification($userId, NOTIFICATION_TYPE_SUCCESS, ['contents' => __('common.changesSaved')]);
        }

        return parent::execute(...$functionArgs);
    }
}
