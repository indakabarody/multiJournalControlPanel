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

import('classes.handler.Handler');

require_once dirname(__DIR__) . '/classes/services/DashboardService.inc.php';
require_once dirname(__DIR__) . '/classes/services/PaymentService.inc.php';
require_once dirname(__DIR__) . '/classes/services/ExportService.inc.php';
require_once dirname(__DIR__) . '/classes/services/SynchronizationService.inc.php';

use Illuminate\Database\Capsule\Manager as Capsule;

class MultiJournalControlPanelHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /** @var MultiJournalControlPanelPlugin */
    protected static $_plugin;

    /** @var array Publicly accessible operations that do not require an OJS account */
    protected $_publicOps = ['confirm', 'submitConfirmation', 'downloadLoa', 'validateLoa'];

    /**
     * Set the plugin instance.
     *
     * @param MultiJournalControlPanelPlugin $plugin
     * @return void
     */
    public static function setPlugin($plugin)
    {
        self::$_plugin = $plugin;
    }

    /**
     * Get the plugin instance.
     *
     * @return MultiJournalControlPanelPlugin
     */
    public static function getPlugin()
    {
        return self::$_plugin;
    }

    /**
     * Authorize request.
     * Public operations (`confirm`, `submitConfirmation`, `downloadLoa`, `validateLoa`) validate
     * their cryptographic token or LOA number server-side without requiring an OJS login.
     * All internal panel operations enforce server-side authentication and role-based authorization.
     *
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $router = $request->getRouter();
        $op = (string) $router->getRequestedOp($request);

        if (in_array($op, $this->_publicOps, true)) {
            $this->_isBackendPage = false;
            return parent::authorize($request, $args, $roleAssignments);
        }

        $user = $request->getUser();
        if (!$user) {
            Validation::redirectLogin();
            return false;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $context = $request->getContext();
        $ctxId = $context ? (int) $context->getId() : 0;
        $perms = $dashboardService->resolveUserPermissions($user, $ctxId);

        if (!$perms['canViewDashboard'] && !$perms['canViewPayment']) {
            return false;
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * 1. Dashboard ("index" / "dashboard") - Summary Cards + Editorial Pipeline Table.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function index($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewDashboard', true);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $summary = $dashboardService->getDashboardSummary($filters, $locale);
        $pipeline = $dashboardService->getSubmissionPipeline($filters, $locale);

        $drilldownItems = [];
        $drilldownBucket = trim((string) $request->getUserVar('drillBucket'));
        $drilldownCard = trim((string) $request->getUserVar('drillCard'));
        $drilldownContextId = trim((string) $request->getUserVar('drillContextId'));

        if ($drilldownBucket !== '' || $drilldownCard !== '') {
            $drillFilters = $filters;
            if ($drilldownContextId !== '') {
                $drillFilters['contextId'] = (int) $drilldownContextId;
            }
            $drillFilters['bucket'] = $drilldownBucket;
            $drillFilters['cardType'] = $drilldownCard;
            $drilldownItems = $dashboardService->getDrilldownSubmissions($drillFilters, $request, $locale);
        }

        $this->_assignCommonPanelVars($request, $perms, 'dashboard', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'summary' => $summary,
            'pipeline' => $pipeline,
            'drilldownItems' => $drilldownItems,
            'drilldownBucket' => $drilldownBucket,
            'drilldownCard' => $drilldownCard,
        ]);

        $templateMgr->display($plugin->getTemplateResource('dashboard/index.tpl'));
    }

    /**
     * Alias for `index()`.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function dashboard($args, $request)
    {
        $this->index($args, $request);
    }

    /**
     * AJAX / JSON endpoint for drill-down list when a summary card or pipeline number is clicked.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function drilldown($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewDashboard', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $filters['bucket'] = trim((string) $request->getUserVar('bucket'));
        $filters['cardType'] = trim((string) $request->getUserVar('cardType'));
        if ($request->getUserVar('drillContextId') !== null && $request->getUserVar('drillContextId') !== '') {
            $filters['contextId'] = (int) $request->getUserVar('drillContextId');
        }

        $items = $dashboardService->getDrilldownSubmissions($filters, $request, $locale);
        $this->_sendJson(['ok' => true, 'items' => $items]);
    }

    /**
     * 2. Bottlenecks ("bottlenecks") - Overdue & At-Risk Submissions.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function bottlenecks($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewDashboard', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $bottleneckItems = $dashboardService->getBottleneckSubmissions($filters, $request, $locale);

        $this->_assignCommonPanelVars($request, $perms, 'bottlenecks', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'bottleneckItems' => $bottleneckItems,
        ]);

        $templateMgr->display($plugin->getTemplateResource('dashboard/index.tpl'));
    }

    /**
     * 3. Connected Journals ("journals") - Journals & Sync Status.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function journals($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewConnectedJournals', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $connectedJournals = $dashboardService->getConnectedJournals($request, $locale);

        $this->_assignCommonPanelVars($request, $perms, 'journals', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'connectedJournals' => $connectedJournals,
        ]);

        $templateMgr->display($plugin->getTemplateResource('dashboard/index.tpl'));
    }

    /**
     * 4. Issue Health ("issues") - Issue Readiness & Quotas.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function issues($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewDashboard', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $issueHealthCards = $dashboardService->getIssueHealthList($filters, $locale);

        $this->_assignCommonPanelVars($request, $perms, 'issues', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'issueHealthCards' => $issueHealthCards,
        ]);

        $templateMgr->display($plugin->getTemplateResource('dashboard/index.tpl'));
    }

    /**
     * 5. Journal Performance ("performance") - Monthly Trend Chart & KPIs.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function performance($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewDashboard', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $performance = $dashboardService->getJournalPerformance($filters, $locale);

        $this->_assignCommonPanelVars($request, $perms, 'performance', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'performance' => $performance,
        ]);

        $templateMgr->display($plugin->getTemplateResource('dashboard/index.tpl'));
    }

    /**
     * 6. Payment Management ("payments") - Payment Status & Bulk Import.
     * Strictly restricted to registered Journal Managers (read-only) and registered Finance Admins (read/write).
     * Directors are strictly denied access.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function payments($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewPayment', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $qf = trim((string) $request->getUserVar('quickFilter'));
        $filters['quickFilter'] = $qf !== '' ? $qf : 'all';

        $paymentData = $paymentService->getPaymentsDashboardData($filters, $locale);

        $this->_assignCommonPanelVars($request, $perms, 'payments', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'paymentSummary' => $paymentData['summary'],
            'paymentItems' => $paymentData['items'],
            'paymentRecapByJournal' => $paymentData['recapByJournal'],
            'activeQuickFilter' => $filters['quickFilter'],
        ]);

        $templateMgr->display($plugin->getTemplateResource('payments/index.tpl'));
    }

    /**
     * 7. Submission Payment & LOA Detail ("paymentDetail") - Author Form Detail, Payment Verification (Finance Admin), and LOA Upload (Journal Manager).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function paymentDetail($args, $request)
    {
        $user = $request->getUser();
        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $context = $request->getContext();
        $perms = $dashboardService->resolveUserPermissions($user, $context ? (int) $context->getId() : 0);

        // Allow registered payment users (Journal Manager / Finance Admin) or Journal Manager uploading LOA
        if (!$perms['canViewPayment'] && !$perms['canManageLoa']) {
            $this->_logUnauthorizedAttempt($request, 'view_payment_detail');
            $this->_renderAccessDenied($request);
            return;
        }

        $submissionId = !empty($args[0]) ? (int) $args[0] : (int) $request->getUserVar('submissionId');
        if ($submissionId <= 0) {
            $request->redirect(null, 'multiJournalControlPanel', $perms['canViewPayment'] ? 'payments' : 'authorLinks');
            return;
        }

        $paymentService = new PaymentService($plugin);
        $locale = $this->_resolveActiveLocale($request);
        $detail = $paymentService->getSubmissionPaymentAndLoaDetail($submissionId, $locale);

        if (!$detail) {
            $request->redirect(null, 'multiJournalControlPanel', $perms['canViewPayment'] ? 'payments' : 'authorLinks');
            return;
        }

        $filters = $this->_readCommonFilters($request);
        $this->_assignCommonPanelVars($request, $perms, 'payments', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'detail' => $detail,
        ]);

        $templateMgr->display($plugin->getTemplateResource('payments/form.tpl'));
    }

    /**
     * POST endpoint: Save Payment Verification (Finance Admin only).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function verifyPayment($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canEditPayment', false, 'verify_payment_attempt');
        if (!$perms) {
            return;
        }

        $submissionId = (int) $request->getUserVar('submissionId');
        $paymentStatus = trim((string) $request->getUserVar('paymentStatus'));
        $amount = $request->getUserVar('amount');
        $notes = $request->getUserVar('notes');
        $uploadedFile = isset($_FILES['proofFile']) ? $_FILES['proofFile'] : null;

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $result = $paymentService->verifyPayment($submissionId, $paymentStatus, $amount, $notes, $uploadedFile, $request->getUser());

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson($result, $result['ok'] ? 200 : 400);
            return;
        }

        $this->_notifyUser($request, $result['ok'] ? NOTIFICATION_TYPE_SUCCESS : NOTIFICATION_TYPE_ERROR, $result['message']);
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', 'paymentDetail', [$submissionId]);
    }

    /**
     * POST endpoint: Preview or commit Bulk Payment Import from CSV or XLSX (Finance Admin only).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function importPayments($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canEditPayment', false, 'bulk_payment_import_attempt');
        if (!$perms) {
            return;
        }

        $commit = (int) $request->getUserVar('commit') === 1;
        $rawCsvText = trim((string) $request->getUserVar('csvText'));
        $parsedRows = [];

        if (isset($_FILES['importFile']) && !empty($_FILES['importFile']['tmp_name']) && is_uploaded_file($_FILES['importFile']['tmp_name'])) {
            $ext = strtolower(pathinfo((string) $_FILES['importFile']['name'], PATHINFO_EXTENSION));
            if ($ext === 'xlsx') {
                $parsedRows = $this->_parseXlsxRows($_FILES['importFile']['tmp_name']);
            } else {
                $rawCsvText = (string) file_get_contents($_FILES['importFile']['tmp_name']);
            }
        }

        if (empty($parsedRows) && $rawCsvText !== '') {
            // Strip UTF-8 BOM if present
            $rawCsvText = preg_replace('/^\xEF\xBB\xBF/', '', $rawCsvText);
            $lines = preg_split('/\r\n|\r|\n/', $rawCsvText);
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $delimiter = strpos($line, ';') !== false && strpos($line, ',') === false ? ';' : ',';
                $cols = str_getcsv($line, $delimiter);
                if (!empty($cols)) {
                    $parsedRows[] = $cols;
                }
            }
        }

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $result = $paymentService->processBulkPaymentRows($parsedRows, $commit, $request->getUser());

        $this->_sendJson($result);
    }

    /**
     * Parse rows from the first worksheet of an OpenXML (.xlsx) file using PHP's built-in ZipArchive + SimpleXML.
     *
     * @param string $filePath
     * @return array
     */
    protected function _parseXlsxRows($filePath)
    {
        if (!class_exists('ZipArchive')) {
            return [];
        }
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $sharedStrings = [];
        $ssXmlStr = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXmlStr !== false) {
            $ssXml = @simplexml_load_string($ssXmlStr);
            if ($ssXml) {
                foreach ($ssXml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $sharedStrings[] = $text;
                    }
                }
            }
        }

        $sheetXmlStr = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXmlStr === false) {
            return [];
        }

        $sheetXml = @simplexml_load_string($sheetXmlStr);
        if (!$sheetXml || !isset($sheetXml->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($sheetXml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $colLetters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
                $colIdx = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $colIdx = ($colIdx * 26) + (ord($colLetters[$i]) - 64);
                }
                $colIdx = max(0, $colIdx - 1);

                $type = (string) $cell['t'];
                if ($type === 's') {
                    $ssIdx = (int) $cell->v;
                    $val = isset($sharedStrings[$ssIdx]) ? $sharedStrings[$ssIdx] : '';
                } elseif ($type === 'inlineStr') {
                    $val = isset($cell->is->t) ? (string) $cell->is->t : '';
                } else {
                    $val = isset($cell->v) ? (string) $cell->v : '';
                }
                $cells[$colIdx] = $val;
            }
            if (!empty($cells)) {
                $maxCol = max(array_keys($cells));
                $ordered = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $ordered[] = isset($cells[$c]) ? $cells[$c] : '';
                }
                $rows[] = $ordered;
            }
        }
        return $rows;
    }

    /**
     * POST endpoint: Upload or replace LOA PDF & LOA Number (Journal Manager only).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function uploadLoa($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canManageLoa', false, 'upload_loa_attempt');
        if (!$perms) {
            return;
        }

        $submissionId = (int) $request->getUserVar('submissionId');
        $loaNumber = trim((string) $request->getUserVar('loaNumber'));
        $uploadedFile = isset($_FILES['loaFile']) ? $_FILES['loaFile'] : null;

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $result = $paymentService->uploadLoa($submissionId, $loaNumber, $uploadedFile, $request->getUser());

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson($result, $result['ok'] ? 200 : 400);
            return;
        }

        $this->_notifyUser($request, $result['ok'] ? NOTIFICATION_TYPE_SUCCESS : NOTIFICATION_TYPE_ERROR, $result['message']);
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', 'paymentDetail', [$submissionId]);
    }

    /**
     * Access-controlled download handler for payment proof files.
     * Only accessible to registered payment users (`canViewPayment`).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function downloadProof($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewPayment', false, 'download_proof_attempt');
        if (!$perms) {
            return;
        }

        $submissionId = !empty($args[0]) ? (int) $args[0] : (int) $request->getUserVar('submissionId');
        $payment = Capsule::table('mjcp_payments')->where('submission_id', '=', $submissionId)->first();
        $authorForm = Capsule::table('mjcp_author_forms')->where('submission_id', '=', $submissionId)->first();

        $filePath = $payment && !empty($payment->proof_file_path) ? (string) $payment->proof_file_path : ($authorForm && !empty($authorForm->transfer_proof_path) ? (string) $authorForm->transfer_proof_path : '');
        $origName = $payment && !empty($payment->proof_original_name) ? (string) $payment->proof_original_name : ($authorForm && !empty($authorForm->transfer_proof_original_name) ? (string) $authorForm->transfer_proof_original_name : 'payment-proof');
        $mimeType = $payment && !empty($payment->proof_mime_type) ? (string) $payment->proof_mime_type : ($authorForm && !empty($authorForm->transfer_proof_mime) ? (string) $authorForm->transfer_proof_mime : 'application/octet-stream');

        if ($filePath === '' || !is_readable($filePath)) {
            $this->_renderAccessDenied($request, 'mjcp.upload.error.fileNotFound');
            return;
        }

        import('lib.pkp.classes.file.FileManager');
        $fileMgr = new FileManager();
        $fileMgr->downloadByPath($filePath, $mimeType, false, basename($origName));
        exit();
    }

    /**
     * 8. Author Form Links ("authorLinks") - Generate, copy, export, and revoke unique author links.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function authorLinks($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canManageAuthorLinks', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $filters['unfilledOnly'] = (int) $request->getUserVar('unfilledOnly') === 1;

        $linksData = $paymentService->getAuthorLinksList($filters, $request, $locale);

        $this->_assignCommonPanelVars($request, $perms, 'authorLinks', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'authorLinks' => $linksData['items'],
            'unfilledLinksCount' => $linksData['unfilledCount'],
            'unfilledOnly' => $filters['unfilledOnly'],
        ]);

        $templateMgr->display($plugin->getTemplateResource('dashboard/index.tpl'));
    }

    /**
     * POST endpoint: Generate one or multiple author tokens in bulk.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function generateToken($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canManageAuthorLinks', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $expDays = (int) $plugin->getSetting(0, 'tokenExpirationDays');
        if ($expDays <= 0) {
            $expDays = 30;
        }

        $submissionIds = [];
        if ($request->getUserVar('submissionIds')) {
            $rawIds = $request->getUserVar('submissionIds');
            if (is_array($rawIds)) {
                $submissionIds = array_map('intval', $rawIds);
            } else {
                $submissionIds = array_map('intval', preg_split('/[\s,]+/', (string) $rawIds, -1, PREG_SPLIT_NO_EMPTY));
            }
        } elseif ($request->getUserVar('submissionId')) {
            $submissionIds = [(int) $request->getUserVar('submissionId')];
        }

        $userId = (int) $request->getUser()->getId();
        $generated = [];
        foreach ($submissionIds as $subId) {
            if ($subId > 0) {
                $res = $paymentService->generateAuthorToken($subId, $expDays, $userId);
                if (!empty($res['ok'])) {
                    $generated[] = $res;
                }
            }
        }

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson([
                'ok' => true,
                'count' => count($generated),
                'tokens' => $generated,
                'message' => __('mjcp.links.generatedSuccess', ['count' => count($generated)]),
            ]);
            return;
        }

        $this->_notifyUser($request, NOTIFICATION_TYPE_SUCCESS, __('mjcp.links.generatedSuccess', ['count' => count($generated)]));
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', 'authorLinks');
    }

    /**
     * POST endpoint: Revoke (deactivate) an author token.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function revokeToken($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canManageAuthorLinks', false);
        if (!$perms) {
            return;
        }

        $submissionId = (int) $request->getUserVar('submissionId');
        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $paymentService->revokeAuthorToken($submissionId, (int) $request->getUser()->getId());

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson(['ok' => true, 'message' => __('mjcp.links.revokedSuccess')]);
            return;
        }

        $this->_notifyUser($request, NOTIFICATION_TYPE_SUCCESS, __('mjcp.links.revokedSuccess'));
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', 'authorLinks');
    }

    /**
     * 9. Author Testimonials ("testimonials") - Testimonial List & Consent Management.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function testimonials($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canViewDashboard', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $ctxId = $filters['contextId'] !== 'all' && (int) $filters['contextId'] > 0 ? (int) $filters['contextId'] : 0;
        $testimonials = $paymentService->getTestimonials(false, $ctxId, $locale);

        $this->_assignCommonPanelVars($request, $perms, 'testimonials', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'testimonials' => $testimonials,
        ]);

        $templateMgr->display($plugin->getTemplateResource('dashboard/index.tpl'));
    }

    /**
     * POST endpoint: Toggle testimonial consent withdrawal.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function toggleTestimonialConsent($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canViewDashboard', false);
        if (!$perms) {
            return;
        }

        $testimonialId = (int) $request->getUserVar('testimonialId');
        $withdraw = (int) $request->getUserVar('withdraw') === 1;

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $paymentService->setTestimonialConsentWithdrawn($testimonialId, $withdraw, (int) $request->getUser()->getId());

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson(['ok' => true, 'message' => __('common.changesSaved')]);
            return;
        }

        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', 'testimonials');
    }

    /**
     * 10. Settings ("settings") - Full Configuration Page.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function settings($args, $request)
    {
        $perms = $this->_requireInternalPermission($request, 'canManageSettings', false);
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $dao = $dashboardService->getDAO();
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $thresholds = $dao->getStageThresholds(0);
        $holidays = $dao->getHolidays(0);
        $journals = $dao->getAllJournals($locale);
        $defaultQuotas = $dao->getJournalDefaultQuotas();
        $paymentUsers = $dao->getPaymentAccessUsernames();
        $roleAssignments = $dao->getAllUserRoleAssignments();
        $syncLogs = $dao->getLatestSyncLogsByJournal();
        $auditLogs = $dao->getRecentAuditLogs(25);

        $rolesMap = [
            'director' => [],
            'journal_manager' => [],
            'finance_admin' => [],
            'technical_admin' => [],
        ];
        foreach ($roleAssignments as $ra) {
            $rk = (string) $ra['role_key'];
            if (isset($rolesMap[$rk])) {
                $rolesMap[$rk][] = (string) $ra['username'];
            }
        }

        $journalQuotasList = [];
        foreach ($journals as $cid => $jInfo) {
            $journalQuotasList[] = [
                'contextId' => $cid,
                'journalName' => $jInfo['name'],
                'quota' => isset($defaultQuotas[$cid]) ? (int) $defaultQuotas[$cid] : 10,
            ];
        }

        $tokenExpDays = (int) $plugin->getSetting(0, 'tokenExpirationDays');
        if ($tokenExpDays <= 0) {
            $tokenExpDays = 30;
        }
        $maxUploadMb = (int) $plugin->getSetting(0, 'maxUploadSizeMb');
        if ($maxUploadMb <= 0) {
            $maxUploadMb = 1;
        }
        $loaValidationUrl = (string) $plugin->getSetting(0, 'loaValidationUrl');
        $enableLangId = $plugin->getSetting(0, 'enableLangId');
        $enableLangEn = $plugin->getSetting(0, 'enableLangEn');
        if ($enableLangId === null) {
            $enableLangId = true;
        }
        if ($enableLangEn === null) {
            $enableLangEn = true;
        }

        $globalSync = isset($syncLogs[0]) ? $syncLogs[0] : null;
        $lastSyncFormatted = $globalSync && !empty($globalSync->finished_at) ? $dashboardService->formatDateTimeShort($globalSync->finished_at, $locale) : '-';
        $rawSyncStatus = $globalSync ? strtolower((string) $globalSync->sync_status) : 'connected';
        if (!in_array($rawSyncStatus, ['connected', 'pending', 'failed'], true)) {
            $rawSyncStatus = 'connected';
        }
        $lastSyncStatusLabel = __('mjcp.journals.status.' . $rawSyncStatus);

        $rawWorkingDays = $plugin->getSetting(0, 'workingDays');
        $workingDays = [];
        if (is_string($rawWorkingDays) && trim($rawWorkingDays) !== '') {
            foreach (preg_split('/[\s,]+/', trim($rawWorkingDays), -1, PREG_SPLIT_NO_EMPTY) as $wd) {
                $wdInt = (int) $wd;
                if ($wdInt >= 1 && $wdInt <= 7) {
                    $workingDays[] = $wdInt;
                }
            }
        }
        if (empty($workingDays)) {
            $workingDays = [1, 2, 3, 4, 5];
        }

        $weekDaysList = [];
        for ($d = 1; $d <= 7; $d++) {
            $weekDaysList[] = [
                'dayNum' => $d,
                'label' => __('mjcp.weekday.' . $d),
                'checked' => in_array($d, $workingDays, true),
            ];
        }

        $this->_assignCommonPanelVars($request, $perms, 'settings', $filters, $locale);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'thresholds' => $thresholds,
            'holidays' => $holidays,
            'workingDays' => $workingDays,
            'weekDaysList' => $weekDaysList,
            'journalQuotasList' => $journalQuotasList,
            'paymentUsersCsv' => implode(', ', $paymentUsers),
            'directorUsersCsv' => implode(', ', $rolesMap['director']),
            'jmUsersCsv' => implode(', ', $rolesMap['journal_manager']),
            'financeUsersCsv' => implode(', ', $rolesMap['finance_admin']),
            'technicalUsersCsv' => implode(', ', $rolesMap['technical_admin']),
            'tokenExpirationDays' => $tokenExpDays,
            'maxUploadSizeMb' => $maxUploadMb,
            'loaValidationUrl' => $loaValidationUrl,
            'enableLangId' => (bool) $enableLangId,
            'enableLangEn' => (bool) $enableLangEn,
            'lastSyncFormatted' => $lastSyncFormatted,
            'lastSyncStatus' => $lastSyncStatusLabel,
            'auditLogs' => $auditLogs,
        ]);

        $templateMgr->display($plugin->getTemplateResource('settings/index.tpl'));
    }

    /**
     * POST endpoint: Save Settings from `settings/index.tpl` (Technical Admin only).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function saveSettings($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canManageSettings', false, 'save_settings_attempt');
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dao = new MultiJournalControlPanelDAO();
        $user = $request->getUser();
        $userId = (int) $user->getId();
        $username = (string) $user->getUsername();

        $round1Limit = max(14, min(21, (int) $request->getUserVar('round1Limit') ? (int) $request->getUserVar('round1Limit') : 21));
        $round2Limit = max(1, (int) $request->getUserVar('round2Limit') ? (int) $request->getUserVar('round2Limit') : 7);
        $copyeditingWarning = max(1, (int) $request->getUserVar('copyeditingWarning') ? (int) $request->getUserVar('copyeditingWarning') : 10);

        $dao->saveStageThresholds(
            0,
            [
                'round1Limit' => $round1Limit,
                'round2Limit' => $round2Limit,
                'copyeditingWarning' => $copyeditingWarning,
            ],
            $userId
        );

        if ($request->getUserVar('paymentAccessUsernames') !== null) {
            $dao->setPaymentAccessUsernames((string) $request->getUserVar('paymentAccessUsernames'), $userId);
        }
        if ($request->getUserVar('directorUsernames') !== null) {
            $dao->setRoleUsernames('director', (string) $request->getUserVar('directorUsernames'), $userId);
        }
        if ($request->getUserVar('jmUsernames') !== null) {
            $dao->setRoleUsernames('journal_manager', (string) $request->getUserVar('jmUsernames'), $userId);
        }
        if ($request->getUserVar('financeUsernames') !== null) {
            $dao->setRoleUsernames('finance_admin', (string) $request->getUserVar('financeUsernames'), $userId);
        }
        if ($request->getUserVar('technicalUsernames') !== null) {
            $dao->setRoleUsernames('technical_admin', (string) $request->getUserVar('technicalUsernames'), $userId);
        }

        // Save per-journal quotas
        $quotasInput = $request->getUserVar('journalQuotas');
        if (is_array($quotasInput)) {
            foreach ($quotasInput as $ctxId => $qVal) {
                $dao->saveIssueQuota((int) $ctxId, 0, max(1, (int) $qVal), null, $userId);
            }
        }

        $tokenExpDays = max(1, (int) $request->getUserVar('tokenExpirationDays') ? (int) $request->getUserVar('tokenExpirationDays') : 30);
        $maxUploadMb = max(1, min(10, (int) $request->getUserVar('maxUploadSizeMb') ? (int) $request->getUserVar('maxUploadSizeMb') : 1));
        $loaValidationUrl = trim((string) $request->getUserVar('loaValidationUrl'));
        $houseName = trim((string) $request->getUserVar('publishingHouseName'));
        $enableLangId = (int) $request->getUserVar('enableLangId') === 1;
        $enableLangEn = (int) $request->getUserVar('enableLangEn') === 1;

        $workingDaysInput = $request->getUserVar('workingDays');
        $selectedWorkingDays = [];
        if (is_array($workingDaysInput)) {
            foreach ($workingDaysInput as $wd) {
                $wdInt = (int) $wd;
                if ($wdInt >= 1 && $wdInt <= 7 && !in_array($wdInt, $selectedWorkingDays, true)) {
                    $selectedWorkingDays[] = $wdInt;
                }
            }
            sort($selectedWorkingDays);
        }
        if (empty($selectedWorkingDays)) {
            $selectedWorkingDays = [1, 2, 3, 4, 5];
        }
        $workingDaysCsv = implode(',', $selectedWorkingDays);

        $plugin->updateSetting(0, 'workingDays', $workingDaysCsv, 'string');
        $plugin->updateSetting(0, 'tokenExpirationDays', $tokenExpDays, 'int');
        $plugin->updateSetting(0, 'maxUploadSizeMb', $maxUploadMb, 'int');
        $plugin->updateSetting(0, 'maxUploadSizeBytes', $maxUploadMb * 1048576, 'int');
        $plugin->updateSetting(0, 'loaValidationUrl', $loaValidationUrl, 'string');
        if ($houseName !== '') {
            $plugin->updateSetting(0, 'publishingHouseName', $houseName, 'string');
        }
        $plugin->updateSetting(0, 'enableLangId', $enableLangId, 'bool');
        $plugin->updateSetting(0, 'enableLangEn', $enableLangEn, 'bool');

        $dao->logAudit(0, null, $userId, $username, 'settings_saved', 'mjcp_stage_thresholds', null, [
            'round1Limit' => $round1Limit,
            'round2Limit' => $round2Limit,
            'copyeditingWarning' => $copyeditingWarning,
            'workingDays' => $workingDaysCsv,
            'tokenExpirationDays' => $tokenExpDays,
            'maxUploadSizeMb' => $maxUploadMb,
        ]);

        // Re-synchronize so updated thresholds and working days immediately take effect
        $syncService = new SynchronizationService($plugin, $dao);
        $syncService->synchronizeJournals(null, 'auto', $userId, $this->_resolveActiveLocale($request));

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson(['ok' => true, 'message' => __('common.changesSaved')]);
            return;
        }

        $this->_notifyUser($request, NOTIFICATION_TYPE_SUCCESS, __('common.changesSaved'));
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', 'settings');
    }

    /**
     * POST endpoint: Add a holiday entry in `mjcp_holidays`.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function addHoliday($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canManageSettings', false);
        if (!$perms) {
            return;
        }

        $holidayDate = trim((string) $request->getUserVar('holidayDate'));
        $description = trim((string) $request->getUserVar('description'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate) || $description === '') {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.settings.error.invalidHoliday')], 400);
            return;
        }

        $dao = new MultiJournalControlPanelDAO();
        $user = $request->getUser();
        $holidayId = $dao->addHoliday(0, $holidayDate, $description, (int) $user->getId());
        $dao->logAudit(0, null, (int) $user->getId(), (string) $user->getUsername(), 'holiday_added', 'mjcp_holidays', null, ['date' => $holidayDate, 'description' => $description]);

        $syncService = new SynchronizationService(self::getPlugin(), $dao);
        $syncService->synchronizeJournals(null, 'auto', (int) $user->getId(), $this->_resolveActiveLocale($request));

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson(['ok' => true, 'holidayId' => $holidayId, 'message' => __('common.changesSaved')]);
            return;
        }

        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', 'settings');
    }

    /**
     * POST endpoint: Delete a holiday entry.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function deleteHoliday($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canManageSettings', false);
        if (!$perms) {
            return;
        }

        $holidayId = (int) $request->getUserVar('holidayId');
        $dao = new MultiJournalControlPanelDAO();
        $user = $request->getUser();
        $dao->deleteHoliday($holidayId);
        $dao->logAudit(0, null, (int) $user->getId(), (string) $user->getUsername(), 'holiday_deleted', 'mjcp_holidays', $holidayId, null);

        $syncService = new SynchronizationService(self::getPlugin(), $dao);
        $syncService->synchronizeJournals(null, 'auto', (int) $user->getId(), $this->_resolveActiveLocale($request));

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson(['ok' => true, 'message' => __('common.changesSaved')]);
            return;
        }

        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', 'settings');
    }

    /**
     * POST endpoint: Trigger manual synchronization for all journals or a specific journal.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function sync($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.invalidCsrf')], 403);
            return;
        }

        $perms = $this->_requireInternalPermission($request, 'canViewConnectedJournals', false);
        if (!$perms) {
            return;
        }

        $onlyContextId = $request->getUserVar('contextId') ? (int) $request->getUserVar('contextId') : null;
        $plugin = self::getPlugin();
        $syncService = new SynchronizationService($plugin);
        $locale = $this->_resolveActiveLocale($request);
        $user = $request->getUser();

        $result = $syncService->synchronizeJournals($onlyContextId, 'manual', (int) $user->getId(), $locale);

        if ($this->_isAjaxRequest($request)) {
            $this->_sendJson([
                'ok' => true,
                'message' => __('mjcp.sync.completedSuccess', ['count' => $result['articlesProcessed']]),
                'result' => $result,
            ]);
            return;
        }

        $this->_notifyUser($request, NOTIFICATION_TYPE_SUCCESS, __('mjcp.sync.completedSuccess', ['count' => $result['articlesProcessed']]));
        $returnTab = trim((string) $request->getUserVar('returnTab')) ? trim((string) $request->getUserVar('returnTab')) : 'settings';
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $request->redirect($contextPath, 'multiJournalControlPanel', $returnTab);
    }

    /**
     * Export endpoint ("export") - Excel (.xlsx), CSV (.csv), and PDF (.pdf) reports.
     * Enforces payment export authorization (`canExportPayment`) when `module === 'payments'`.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function export($args, $request)
    {
        $module = trim((string) $request->getUserVar('module')) ? trim((string) $request->getUserVar('module')) : 'pipeline';
        $format = trim((string) $request->getUserVar('format')) ? trim((string) $request->getUserVar('format')) : 'xlsx';

        if ($module === 'payments') {
            $perms = $this->_requireInternalPermission($request, 'canExportPayment', false, 'export_payments_attempt');
        } else {
            $perms = $this->_requireInternalPermission($request, 'canExportGeneral', false, 'export_general_attempt');
        }
        if (!$perms) {
            return;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $paymentService = new PaymentService($plugin);
        $exportService = new ExportService();
        $locale = $this->_resolveActiveLocale($request);

        $filters = $this->_readCommonFilters($request);
        $filters['quickFilter'] = trim((string) $request->getUserVar('quickFilter')) ? trim((string) $request->getUserVar('quickFilter')) : 'all';
        $filters['unfilledOnly'] = (int) $request->getUserVar('unfilledOnly') === 1;

        $houseName = $this->_resolveHouseName($request);

        $metadata = [
            'reportTitle' => __('plugins.generic.multiJournalControlPanel.displayName') . ' (' . ucfirst($module) . ')',
            'siteTitle' => $houseName,
            'exportedAt' => date('Y-m-d H:i:s'),
            'activeFiltersText' => __('mjcp.export.filterSummary', [
                'journal' => $filters['contextId'],
                'period' => $filters['period'],
            ]),
            'exportedBy' => $perms['username'],
        ];

        switch ($module) {
            case 'bottleneck':
                $data = $dashboardService->getBottleneckSubmissions($filters, $request, $locale);
                break;
            case 'issue':
                $data = $dashboardService->getIssueHealthList($filters, $locale);
                break;
            case 'performance':
                $data = $dashboardService->getJournalPerformance($filters, $locale);
                break;
            case 'payments':
                $data = $paymentService->getPaymentsDashboardData($filters, $locale);
                break;
            case 'links':
                $data = $paymentService->getAuthorLinksList($filters, $request, $locale);
                break;
            case 'testimonials':
                $ctxId = $filters['contextId'] !== 'all' && (int) $filters['contextId'] > 0 ? (int) $filters['contextId'] : 0;
                $data = $paymentService->getTestimonials(true, $ctxId, $locale);
                break;
            case 'pipeline':
            default:
                $data = [
                    'summary' => $dashboardService->getDashboardSummary($filters, $locale),
                    'pipeline' => $dashboardService->getSubmissionPipeline($filters, $locale),
                ];
                break;
        }

        $exportService->exportReport($module, $format, $data, $metadata);
    }

    /**
     * 11. Public Author Manuscript Page ("confirm") - Accessible via unique token link without OJS login.
     * Combines:
     * 1. Payment Confirmation Form (or Verified summary card once submitted/verified)
     * 2. LOA / Letter of Acceptance card (Locked until payment status is 'paid'; Download & Validate buttons active when 'paid')
     * 3. Publication Link card (Unpublished before publication; automatic article link + DOI after publication)
     * Bilingual language switcher (`lang=id_ID` / `lang=en_US`).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function confirm($args, $request)
    {
        $rawToken = !empty($args[0]) ? trim((string) $args[0]) : trim((string) $request->getUserVar('token'));
        $locale = $this->_resolveActiveLocale($request);

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $resolved = $paymentService->resolveAuthorToken($rawToken, $locale);

        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $baseUrl = $request->getBaseUrl();
        $pluginUrl = $baseUrl . '/' . $plugin->getPluginPath();

        $templateMgr->addStyleSheet('mjcpStyles', $pluginUrl . '/styles/multiJournalControlPanel.css');
        $templateMgr->addJavaScript('mjcpScript', $pluginUrl . '/js/multiJournalControlPanel.js');

        $houseName = $this->_resolveHouseName($request);

        $customLoaValUrl = trim((string) $plugin->getSetting(0, 'loaValidationUrl'));
        $baseLoaValidationUrl = $customLoaValUrl !== '' ? $customLoaValUrl : $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'validateLoa');

        $justSubmitted = (int) $request->getUserVar('submitted') === 1;

        $templateMgr->assign([
            'baseUrl' => $baseUrl,
            'pluginUrl' => $pluginUrl,
            'houseName' => $houseName,
            'activeLocale' => $locale,
            'rawToken' => $rawToken,
            'tokenValid' => !empty($resolved['isValid']),
            'tokenReason' => isset($resolved['reason']) ? $resolved['reason'] : 'invalid',
            'detail' => isset($resolved['detail']) ? $resolved['detail'] : null,
            'justSubmitted' => $justSubmitted,
            'formErrors' => [],
            'confirmUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'confirm', [$rawToken]),
            'submitConfirmationUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'submitConfirmation', [$rawToken]),
            'downloadLoaUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'downloadLoa', [$rawToken]),
            'baseLoaValidationUrl' => $baseLoaValidationUrl,
            'csrfToken' => $request->getSession()->getCSRFToken(),
        ]);

        $templateMgr->display($plugin->getTemplateResource('authors/confirmation.tpl'));
    }

    /**
     * POST endpoint: Submit Author Confirmation Form via token link (no OJS login required).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function submitConfirmation($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_renderAccessDenied($request, 'mjcp.error.invalidCsrf');
            return;
        }

        $rawToken = !empty($args[0]) ? trim((string) $args[0]) : trim((string) $request->getUserVar('token'));
        $locale = $this->_resolveActiveLocale($request);

        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);

        $input = [
            'fullNameAuthors' => $request->getUserVar('fullNameAuthors'),
            'affiliation' => $request->getUserVar('affiliation'),
            'correspondentEmail' => $request->getUserVar('correspondentEmail'),
            'whatsappNumber' => $request->getUserVar('whatsappNumber'),
            'testimonial' => $request->getUserVar('testimonial'),
            'testimonialConsent' => $request->getUserVar('testimonialConsent'),
            'locale' => $locale,
        ];
        $uploadedFile = isset($_FILES['transferProof']) ? $_FILES['transferProof'] : null;
        $clientIp = (string) $request->getRemoteAddr();

        $result = $paymentService->submitAuthorConfirmationForm($rawToken, $input, $uploadedFile, $clientIp);

        $dispatcher = $request->getDispatcher();
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';

        if ($result['ok']) {
            $redirectUrl = $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'confirm', [$rawToken], ['submitted' => 1, 'lang' => $locale]);
            $request->redirectUrl($redirectUrl);
            return;
        }

        // Render form with validation errors
        $resolved = $paymentService->resolveAuthorToken($rawToken, $locale);
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $baseUrl = $request->getBaseUrl();
        $pluginUrl = $baseUrl . '/' . $plugin->getPluginPath();

        $templateMgr->addStyleSheet('mjcpStyles', $pluginUrl . '/styles/multiJournalControlPanel.css');
        $templateMgr->addJavaScript('mjcpScript', $pluginUrl . '/js/multiJournalControlPanel.js');

        $houseName = $this->_resolveHouseName($request);
        $customLoaValUrl = trim((string) $plugin->getSetting(0, 'loaValidationUrl'));
        $baseLoaValidationUrl = $customLoaValUrl !== '' ? $customLoaValUrl : $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'validateLoa');

        $templateMgr->assign([
            'baseUrl' => $baseUrl,
            'pluginUrl' => $pluginUrl,
            'houseName' => $houseName,
            'activeLocale' => $locale,
            'rawToken' => $rawToken,
            'tokenValid' => !empty($resolved['isValid']),
            'tokenReason' => isset($resolved['reason']) ? $resolved['reason'] : 'invalid',
            'detail' => isset($resolved['detail']) ? $resolved['detail'] : null,
            'justSubmitted' => false,
            'formErrors' => $result['errors'],
            'formInput' => $input,
            'confirmUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'confirm', [$rawToken]),
            'submitConfirmationUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'submitConfirmation', [$rawToken]),
            'downloadLoaUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'downloadLoa', [$rawToken]),
            'baseLoaValidationUrl' => $baseLoaValidationUrl,
            'csrfToken' => $request->getSession()->getCSRFToken(),
        ]);

        $templateMgr->display($plugin->getTemplateResource('authors/confirmation.tpl'));
    }

    /**
     * Access-controlled LOA PDF download handler.
     * Can be accessed:
     * 1. By an author via valid token (`$args[0]`), ONLY when `payment_status === 'paid'`.
     * 2. By an internal authorized Journal Manager / Finance Admin (`submissionId` query parameter).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function downloadLoa($args, $request)
    {
        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $dashboardService = new DashboardService($plugin);

        $submissionId = 0;
        $rawToken = !empty($args[0]) ? trim((string) $args[0]) : trim((string) $request->getUserVar('token'));

        if ($rawToken !== '' && !ctype_digit($rawToken)) {
            // Author token access: strictly enforce payment_status === 'paid'
            $resolved = $paymentService->resolveAuthorToken($rawToken);
            if (empty($resolved['isValid']) || empty($resolved['detail'])) {
                $this->_renderAccessDenied($request, 'mjcp.loa.error.accessLocked');
                return;
            }
            $detail = $resolved['detail'];
            if ($detail['payment']['status'] !== 'paid') {
                $this->_renderAccessDenied($request, 'mjcp.loa.error.accessLocked');
                return;
            }
            $submissionId = (int) $detail['submissionId'];
        } else {
            // Internal user access (Journal Manager or Finance Admin)
            $user = $request->getUser();
            $perms = $dashboardService->resolveUserPermissions($user, 0);
            if (!$perms['canManageLoa'] && !$perms['canViewPayment']) {
                $this->_renderAccessDenied($request);
                return;
            }
            $submissionId = ctype_digit($rawToken) ? (int) $rawToken : (int) $request->getUserVar('submissionId');
        }

        $currentLoa = Capsule::table('mjcp_loa_files')->where('submission_id', '=', $submissionId)->where('is_current', '=', 1)->orderBy('loa_id', 'desc')->first();

        if (!$currentLoa || empty($currentLoa->file_path) || !is_readable($currentLoa->file_path)) {
            $this->_renderAccessDenied($request, 'mjcp.loa.error.fileNotFound');
            return;
        }

        import('lib.pkp.classes.file.FileManager');
        $fileMgr = new FileManager();
        $fileMgr->downloadByPath($currentLoa->file_path, 'application/pdf', false, basename((string) $currentLoa->original_filename));
        exit();
    }

    /**
     * 12. Public LOA Validation Page ("validateLoa").
     * Renders `loa/detail.tpl` showing the verification result for an LOA number
     * (article title, author(s), journal, LOA date) ONLY when payment is verified ('paid').
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function validateLoa($args, $request)
    {
        $plugin = self::getPlugin();
        $paymentService = new PaymentService($plugin);
        $locale = $this->_resolveActiveLocale($request);

        $loaNumber = !empty($args[0]) ? trim((string) $args[0]) : trim((string) $request->getUserVar('loa'));

        $validationResult = null;
        if ($loaNumber !== '') {
            $validationResult = $paymentService->validateLoaByNumber($loaNumber, $locale);
        }

        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';
        $baseUrl = $request->getBaseUrl();
        $pluginUrl = $baseUrl . '/' . $plugin->getPluginPath();

        $templateMgr->addStyleSheet('mjcpStyles', $pluginUrl . '/styles/multiJournalControlPanel.css');
        $templateMgr->addJavaScript('mjcpScript', $pluginUrl . '/js/multiJournalControlPanel.js');

        $houseName = $this->_resolveHouseName($request);

        $templateMgr->assign([
            'baseUrl' => $baseUrl,
            'pluginUrl' => $pluginUrl,
            'houseName' => $houseName,
            'activeLocale' => $locale,
            'loaNumber' => $loaNumber,
            'validationResult' => $validationResult,
            'validateLoaUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'validateLoa'),
        ]);

        $templateMgr->display($plugin->getTemplateResource('loa/detail.tpl'));
    }

    /**
     * Verify internal permission and redirect or return 403 if unauthorized.
     * If a user without dashboard access (e.g., pure Finance Admin) visits `index`, redirects them to `payments`.
     *
     * @param PKPRequest $request
     * @param string $permKey
     * @param bool $redirectToPaymentIfPaymentOnly
     * @param string|null $auditActionOnDeny
     * @return array|null
     */
    protected function _requireInternalPermission($request, $permKey, $redirectToPaymentIfPaymentOnly = false, $auditActionOnDeny = null)
    {
        $user = $request->getUser();
        if (!$user) {
            Validation::redirectLogin();
            return null;
        }

        $plugin = self::getPlugin();
        $dashboardService = new DashboardService($plugin);
        $context = $request->getContext();
        $perms = $dashboardService->resolveUserPermissions($user, $context ? (int) $context->getId() : 0);

        if ($redirectToPaymentIfPaymentOnly && empty($perms[$permKey]) && !empty($perms['canViewPayment'])) {
            $contextPath = $context ? $context->getPath() : 'index';
            $request->redirect($contextPath, 'multiJournalControlPanel', 'payments');
            return null;
        }

        if (empty($perms[$permKey])) {
            if ($auditActionOnDeny) {
                $this->_logUnauthorizedAttempt($request, $auditActionOnDeny);
            }
            if ($this->_isAjaxRequest($request)) {
                $this->_sendJson(['ok' => false, 'message' => __('mjcp.error.accessDenied')], 403);
                return null;
            }
            $this->_renderAccessDenied($request);
            return null;
        }

        return $perms;
    }

    /**
     * Log unauthorized access attempt to `mjcp_audit_logs`.
     *
     * @param PKPRequest $request
     * @param string $action
     * @return void
     */
    protected function _logUnauthorizedAttempt($request, $action)
    {
        $user = $request->getUser();
        $dao = new MultiJournalControlPanelDAO();
        $ipHash = hash('sha256', (string) $request->getRemoteAddr() . '|mjcp_privacy_salt_2026');
        $dao->logAudit(0, $request->getUserVar('submissionId') ? (int) $request->getUserVar('submissionId') : null, $user ? (int) $user->getId() : null, $user ? (string) $user->getUsername() : 'anonymous', 'unauthorized_' . $action, 'security', null, 'Access Denied', $ipHash);
    }

    /**
     * Assign common navigation URLs, permissions, journal dropdown list, and assets to Smarty TemplateManager.
     *
     * @param PKPRequest $request
     * @param array $perms
     * @param string $activeTab
     * @param array $filters
     * @param string $locale
     * @return void
     */
    protected function _assignCommonPanelVars($request, $perms, $activeTab, $filters, $locale)
    {
        $this->setupTemplate($request);
        $plugin = self::getPlugin();
        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();
        $contextPath = $context ? $context->getPath() : 'index';
        $baseUrl = $request->getBaseUrl();
        $pluginUrl = $baseUrl . '/' . $plugin->getPluginPath();

        $dao = new MultiJournalControlPanelDAO();
        $journals = $dao->getAllJournals($locale);

        $houseName = $this->_resolveHouseName($request);

        $templateMgr->addStyleSheet('mjcpStyles', $pluginUrl . '/styles/multiJournalControlPanel.css', ['contexts' => ['backend', 'frontend']]);
        $templateMgr->addJavaScript('mjcpScript', $pluginUrl . '/js/multiJournalControlPanel.js', ['contexts' => ['backend', 'frontend']]);

        $templateMgr->assign([
            'baseUrl' => $baseUrl,
            'pluginUrl' => $pluginUrl,
            'pageTitle' => __('plugins.generic.multiJournalControlPanel.displayName') . ' – ' . $houseName,
            'houseName' => $houseName,
            'activeTab' => $activeTab,
            'activeLocale' => $locale,
            'perms' => $perms,
            'journalsList' => $journals,
            'activeFilters' => $filters,
            'csrfToken' => $request->getSession()->getCSRFToken(),
            'navUrls' => [
                'dashboard' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'dashboard'),
                'bottlenecks' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'bottlenecks'),
                'journals' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'journals'),
                'issues' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'issues'),
                'performance' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'performance'),
                'payments' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'payments'),
                'paymentDetail' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'paymentDetail'),
                'authorLinks' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'authorLinks'),
                'testimonials' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'testimonials'),
                'settings' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'settings'),
                'export' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'export'),
                'drilldown' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'drilldown'),
                'sync' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'sync'),
                'verifyPayment' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'verifyPayment'),
                'importPayments' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'importPayments'),
                'uploadLoa' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'uploadLoa'),
                'downloadProof' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'downloadProof'),
                'downloadLoa' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'downloadLoa'),
                'generateToken' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'generateToken'),
                'revokeToken' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'revokeToken'),
                'toggleTestimonialConsent' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'toggleTestimonialConsent'),
                'saveSettings' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'saveSettings'),
                'addHoliday' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'addHoliday'),
                'deleteHoliday' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'deleteHoliday'),
                'validateLoa' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'validateLoa'),
            ],
        ]);
    }

    /**
     * Read common journal and period filters from the request.
     * Defaults to `period = 'all'` so All Article and Published reflect all submissions since journal inception.
     *
     * @param PKPRequest $request
     * @return array
     */
    protected function _readCommonFilters($request)
    {
        $ctxId = trim((string) $request->getUserVar('contextId'));
        $period = trim((string) $request->getUserVar('period'));
        $search = trim((string) $request->getUserVar('search'));

        return [
            'contextId' => $ctxId !== '' ? $ctxId : 'all',
            'period' => $period !== '' ? $period : 'all',
            'search' => $search,
        ];
    }

    /**
     * Resolve neutral publishing house / site display name from plugin settings, OJS site title, or localization fallback.
     *
     * @param PKPRequest $request
     * @return string
     */
    protected function _resolveHouseName($request)
    {
        $plugin = self::getPlugin();
        $houseName = trim((string) $plugin->getSetting(0, 'publishingHouseName'));
        if ($houseName !== '') {
            return $houseName;
        }
        $site = $request && method_exists($request, 'getSite') ? $request->getSite() : null;
        if ($site && method_exists($site, 'getLocalizedTitle')) {
            $siteTitle = trim((string) $site->getLocalizedTitle());
            if ($siteTitle !== '') {
                return $siteTitle;
            }
        }
        return __('mjcp.common.defaultHouseName');
    }

    /**
     * Determine active UI locale ('en_US' or 'id_ID'), supporting `?uiLocale=...` / `?lang=...` switch.
     *
     * @param PKPRequest $request
     * @return string
     */
    protected function _resolveActiveLocale($request)
    {
        $plugin = self::getPlugin();
        $reqLang = trim((string) $request->getUserVar('uiLocale'));
        if ($reqLang === '') {
            $reqLang = trim((string) $request->getUserVar('lang'));
        }
        if (in_array($reqLang, ['id_ID', 'en_US'], true)) {
            $session = $request->getSession();
            if ($session) {
                $session->setSessionVar('mjcpLocale', $reqLang);
                $session->setSessionVar('currentLocale', $reqLang);
            }
            $plugin->addLocaleData($reqLang);
            return $reqLang;
        }

        $session = $request->getSession();
        if ($session && in_array($session->getSessionVar('mjcpLocale'), ['id_ID', 'en_US'], true)) {
            $loc = (string) $session->getSessionVar('mjcpLocale');
            $plugin->addLocaleData($loc);
            return $loc;
        }

        $ojsLocale = AppLocale::getLocale();
        $loc = strpos((string) $ojsLocale, 'id') === 0 ? 'id_ID' : 'en_US';
        $plugin->addLocaleData($loc);
        return $loc;
    }

    /**
     * Check if request was sent via AJAX / Fetch.
     *
     * @param PKPRequest $request
     * @return bool
     */
    protected function _isAjaxRequest($request)
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        return (int) $request->getUserVar('ajax') === 1;
    }

    /**
     * Create an OJS trivial notification for the current user.
     *
     * @param PKPRequest $request
     * @param int $type
     * @param string $message
     * @return void
     */
    protected function _notifyUser($request, $type, $message)
    {
        $user = $request->getUser();
        if (!$user) {
            return;
        }
        import('classes.notification.NotificationManager');
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification((int) $user->getId(), $type, ['contents' => $message]);
    }

    /**
     * Render 403 Access Denied page.
     *
     * @param PKPRequest $request
     * @param string $localeKey
     * @return void
     */
    protected function _renderAccessDenied($request, $localeKey = 'mjcp.error.accessDenied')
    {
        if (!headers_sent()) {
            header('HTTP/1.0 403 Forbidden');
        }
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('common.error'),
            'errorMsg' => $localeKey,
            'backLink' => $request->getBaseUrl(),
            'backLinkLabel' => 'common.back',
        ]);
        $templateMgr->display('frontend/pages/error.tpl');
    }

    /**
     * Send JSON response and terminate.
     *
     * @param array $payload
     * @param int $statusCode
     * @return void
     */
    protected function _sendJson($payload, $statusCode = 200)
    {
        if (!headers_sent()) {
            http_response_code((int) $statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($payload);
        exit();
    }
}
