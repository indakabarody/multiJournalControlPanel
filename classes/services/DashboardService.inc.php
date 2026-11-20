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

require_once __DIR__ . '/SynchronizationService.inc.php';
require_once dirname(__DIR__) . '/dao/MultiJournalControlPanelDAO.inc.php';

use Illuminate\Database\Capsule\Manager as Capsule;

class DashboardService
{
    /** @var MultiJournalControlPanelPlugin */
    protected $_plugin;

    /** @var MultiJournalControlPanelDAO */
    protected $_dao;

    /** @var SynchronizationService */
    protected $_syncService;

    /**
     * Constructor.
     *
     * @param MultiJournalControlPanelPlugin $plugin
     * @param MultiJournalControlPanelDAO|null $dao
     */
    public function __construct($plugin, $dao = null)
    {
        $this->_plugin = $plugin;
        $this->_dao = $dao ? $dao : new MultiJournalControlPanelDAO();
        $this->_syncService = new SynchronizationService($plugin, $this->_dao);
    }

    /**
     * Get the DAO instance.
     *
     * @return MultiJournalControlPanelDAO
     */
    public function getDAO()
    {
        return $this->_dao;
    }

    /**
     * Get the SynchronizationService instance.
     *
     * @return SynchronizationService
     */
    public function getSyncService()
    {
        return $this->_syncService;
    }

    /**
     * Resolve the current user's effective permissions according to the Access Control Matrix.
     *
     * Roles:
     * - `director`: Dashboard, pipeline, bottleneck, connected journals, issue health, performance, export report.
     *   STRICTLY NO ACCESS to payment module (`canViewPayment = false`, `canEditPayment = false`).
     * - `journal_manager`: Dashboard, pipeline, bottleneck, connected journals, issue health, performance,
     *   generate/manage author links, upload/replace LOA, export report.
     *   Can view payment ONLY if username is registered in `mjcp_access_users` (or is Finance Admin).
     * - `finance_admin`: Can view & edit payment status, amount, notes, and export payment report
     *   ONLY if username is registered in `mjcp_access_users`.
     * - `technical_admin`: Manage configuration, thresholds, holidays, issue quotas, roles, payment access users,
     *   manual sync, dashboard view, author links, export report.
     *
     * Note: An OJS Site Administrator (`Validation::isSiteAdmin()`) automatically receives `technical_admin`
     * and `journal_manager` capabilities unless explicitly overridden.
     * However, if a user is ONLY assigned `director`, they NEVER get payment access.
     *
     * @param User|null $user
     * @param int $contextId
     * @return array
     */
    public function resolveUserPermissions($user, $contextId = 0)
    {
        $this->_dao->ensureSchema();

        $emptyPerms = [
            'isAuthenticated' => false,
            'userId' => 0,
            'username' => '',
            'roles' => [],
            'isDirector' => false,
            'isJournalManager' => false,
            'isFinanceAdmin' => false,
            'isTechnicalAdmin' => false,
            'isRegisteredPaymentUser' => false,
            'canViewDashboard' => false,
            'canViewConnectedJournals' => false,
            'canManageAuthorLinks' => false,
            'canManageLoa' => false,
            'canViewPayment' => false,
            'canEditPayment' => false,
            'canExportGeneral' => false,
            'canExportPayment' => false,
            'canManageSettings' => false,
        ];

        if (!$user) {
            return $emptyPerms;
        }

        $userId = (int) $user->getId();
        $username = strtolower(trim((string) $user->getUsername()));

        $pluginRoles = $this->_dao->getUserPluginRoles($username);
        $ojsFlags = $this->_dao->getOjsUserRoleFlags($userId, $contextId);
        $isSiteAdmin = !empty($ojsFlags['isSiteAdmin']) || Validation::isSiteAdmin();
        $isOjsJournalManager = !empty($ojsFlags['isJournalManager']);

        $isDirector = in_array('director', $pluginRoles, true);
        $isFinanceAdmin = in_array('finance_admin', $pluginRoles, true);
        $isTechnicalAdmin = in_array('technical_admin', $pluginRoles, true) || $isSiteAdmin;
        $isJournalManager = in_array('journal_manager', $pluginRoles, true) || $isOjsJournalManager || ($isSiteAdmin && !$isDirector);

        // If a user has explicit 'director' role and is NOT explicitly granted another plugin role,
        // enforce strict director separation (directors cannot access the payment module).
        $isStrictDirector = $isDirector && !in_array('finance_admin', $pluginRoles, true) && !in_array('journal_manager', $pluginRoles, true);
        if ($isStrictDirector) {
            $isJournalManager = false;
            $isFinanceAdmin = false;
        }

        $isInAccessList = $this->_dao->isUsernameInPaymentAccessList($username);

        // Payment access rules:
        // - Director CANNOT access payment module at all.
        // - Journal Manager can view payment ONLY if their username is registered in mjcp_access_users.
        // - Finance Admin can view & edit payment ONLY if their username is registered in mjcp_access_users.
        $canViewPayment = false;
        $canEditPayment = false;

        if (!$isStrictDirector) {
            if ($isFinanceAdmin && $isInAccessList) {
                $canViewPayment = true;
                $canEditPayment = true;
            } elseif ($isJournalManager && $isInAccessList) {
                $canViewPayment = true;
            }
        }

        $canViewDashboard = $isDirector || $isJournalManager || $isTechnicalAdmin;
        $canViewConnectedJournals = $isDirector || $isJournalManager || $isTechnicalAdmin;
        $canManageAuthorLinks = ($isJournalManager || $isTechnicalAdmin) && !$isStrictDirector;
        $canManageLoa = $isJournalManager && !$isStrictDirector;
        $canExportGeneral = $isDirector || $isJournalManager || $isTechnicalAdmin;
        $canExportPayment = $canViewPayment;
        $canManageSettings = $isTechnicalAdmin && !$isStrictDirector;

        $effectiveRoles = [];
        if ($isDirector) {
            $effectiveRoles[] = 'director';
        }
        if ($isJournalManager) {
            $effectiveRoles[] = 'journal_manager';
        }
        if ($isFinanceAdmin) {
            $effectiveRoles[] = 'finance_admin';
        }
        if ($isTechnicalAdmin) {
            $effectiveRoles[] = 'technical_admin';
        }

        return [
            'isAuthenticated' => true,
            'userId' => $userId,
            'username' => $username,
            'roles' => $effectiveRoles,
            'isDirector' => $isDirector,
            'isJournalManager' => $isJournalManager,
            'isFinanceAdmin' => $isFinanceAdmin,
            'isTechnicalAdmin' => $isTechnicalAdmin,
            'isRegisteredPaymentUser' => $isInAccessList,
            'canViewDashboard' => $canViewDashboard,
            'canViewConnectedJournals' => $canViewConnectedJournals,
            'canManageAuthorLinks' => $canManageAuthorLinks,
            'canManageLoa' => $canManageLoa,
            'canViewPayment' => $canViewPayment,
            'canEditPayment' => $canEditPayment,
            'canExportGeneral' => $canExportGeneral,
            'canExportPayment' => $canExportPayment,
            'canManageSettings' => $canManageSettings,
        ];
    }

    /**
     * Build Dashboard Summary Cards:
     * - Total articles (All Article)
     * - Active submissions
     * - Overdue submissions
     * - Ready to publish submissions
     * - Issues below quota
     * - Last updated timestamp
     *
     * @param array $filters {contextId, period, search}
     * @param string $locale
     * @return array
     */
    public function getDashboardSummary($filters = [], $locale = 'en_US')
    {
        $this->_syncService->ensureSynchronized($locale);

        $query = $this->_buildFilteredArticleQuery($filters);
        $rows = $query->get();

        $totalArticles = 0;
        $activeSubmissions = 0;
        $overdueSubmissions = 0;
        $readyToPublish = 0;

        $activeBuckets = ['submission', 'review', 'revision', 'copyediting', 'production'];

        foreach ($rows as $r) {
            $totalArticles++;
            $bucket = (string) $r->pipeline_bucket;
            if (in_array($bucket, $activeBuckets, true)) {
                $activeSubmissions++;
            }
            if ($r->bottleneck_status === 'overdue') {
                $overdueSubmissions++;
            }
            if ((int) $r->is_ready_to_publish === 1 && (int) $r->is_published === 0) {
                $readyToPublish++;
            }
        }

        // Calculate issues below quota
        $issueHealthList = $this->getIssueHealthList($filters, $locale);
        $issuesBelowQuota = 0;
        foreach ($issueHealthList as $ih) {
            if ($ih['healthStatus'] === 'under_quota') {
                $issuesBelowQuota++;
            }
        }

        $latestSyncLogs = $this->_dao->getLatestSyncLogsByJournal();
        $lastUpdated = isset($latestSyncLogs[0]) && !empty($latestSyncLogs[0]->finished_at) ? (string) $latestSyncLogs[0]->finished_at : date('Y-m-d H:i:s');

        return [
            'totalArticles' => $totalArticles,
            'activeSubmissions' => $activeSubmissions,
            'overdueSubmissions' => $overdueSubmissions,
            'readyToPublish' => $readyToPublish,
            'issuesBelowQuota' => $issuesBelowQuota,
            'lastUpdated' => $lastUpdated,
            'lastUpdatedFormatted' => $this->formatDateTimeShort($lastUpdated, $locale),
        ];
    }

    /**
     * Build Editorial Pipeline table per journal.
     * Columns: Journal Name, All Article, Submission, Review, Revision, Copyediting, Production, Published.
     * Each submission is counted once in its active/completed pipeline bucket; All Article counts all unique
     * submissions since the journal was established.
     * Highlights cells with overdue (`danger`) or almost due (`warning`) submissions.
     *
     * @param array $filters {contextId, period, search, sort}
     * @param string $locale
     * @return array
     */
    public function getSubmissionPipeline($filters = [], $locale = 'en_US')
    {
        $this->_syncService->ensureSynchronized($locale);

        $journals = $this->_dao->getAllJournals($locale);
        $filterCtxId = isset($filters['contextId']) && $filters['contextId'] !== 'all' && $filters['contextId'] !== '' ? (int) $filters['contextId'] : 0;
        $search = isset($filters['search']) ? strtolower(trim((string) $filters['search'])) : '';

        if ($filterCtxId > 0) {
            $journals = isset($journals[$filterCtxId]) ? [$filterCtxId => $journals[$filterCtxId]] : [];
        }

        $pipelineRows = [];
        foreach ($journals as $cid => $jInfo) {
            if ($search !== '' && strpos(strtolower($jInfo['name'] . ' ' . $jInfo['acronym']), $search) === false) {
                continue;
            }
            $pipelineRows[$cid] = [
                'contextId' => $cid,
                'journalName' => $jInfo['name'],
                'journalPath' => $jInfo['path'],
                'allArticle' => 0,
                'submission' => 0,
                'review' => 0,
                'revision' => 0,
                'copyediting' => 0,
                'production' => 0,
                'published' => 0,
                'declined' => 0,
                'reviewDelayBadge' => 'none', // 'none'|'warning'|'danger'
                'revisionDelayBadge' => 'none',
                'copyeditingDelayBadge' => 'none',
                'productionDelayBadge' => 'none',
            ];
        }

        if (empty($pipelineRows)) {
            return [];
        }

        $query = $this->_buildFilteredArticleQuery($filters);
        $articleRows = $query->get();

        foreach ($articleRows as $ar) {
            $cid = (int) $ar->context_id;
            if (!isset($pipelineRows[$cid])) {
                continue;
            }

            $pipelineRows[$cid]['allArticle']++;
            $bucket = (string) $ar->pipeline_bucket;
            if (isset($pipelineRows[$cid][$bucket])) {
                $pipelineRows[$cid][$bucket]++;
            }

            $bStatus = (string) $ar->bottleneck_status;
            $badgeKey = $bucket . 'DelayBadge';
            if (isset($pipelineRows[$cid][$badgeKey])) {
                if ($bStatus === 'overdue') {
                    $pipelineRows[$cid][$badgeKey] = 'danger';
                } elseif ($bStatus === 'almost_due' && $pipelineRows[$cid][$badgeKey] !== 'danger') {
                    $pipelineRows[$cid][$badgeKey] = 'warning';
                }
            }
        }

        return array_values($pipelineRows);
    }

    /**
     * Fetch underlying drill-down articles for any Dashboard card or Editorial Pipeline cell.
     *
     * @param array $filters {contextId, period, bucket, cardType}
     * @param PKPRequest|null $request
     * @param string $locale
     * @return array
     */
    public function getDrilldownSubmissions($filters = [], $request = null, $locale = 'en_US')
    {
        $this->_syncService->ensureSynchronized($locale);

        $query = $this->_buildFilteredArticleQuery($filters);

        if (!empty($filters['bucket']) && $filters['bucket'] !== 'all') {
            $query->where('pipeline_bucket', '=', (string) $filters['bucket']);
        }

        if (!empty($filters['cardType'])) {
            $ct = (string) $filters['cardType'];
            if ($ct === 'active') {
                $query->whereIn('pipeline_bucket', ['submission', 'review', 'revision', 'copyediting', 'production']);
            } elseif ($ct === 'overdue') {
                $query->where('bottleneck_status', '=', 'overdue');
            } elseif ($ct === 'ready') {
                $query->where('is_ready_to_publish', '=', 1)->where('is_published', '=', 0);
            }
        }

        $rows = $query->orderBy('urgency_score', 'desc')->orderBy('submission_id', 'desc')->limit(200)->get();

        $dispatcher = $request ? $request->getDispatcher() : null;
        $list = [];
        foreach ($rows as $r) {
            $ojsUrl = $dispatcher ? $dispatcher->url($request, ROUTE_PAGE, (string) $r->journal_path, 'workflow', 'index', [(int) $r->submission_id, (int) $r->stage_id]) : '#';

            $list[] = [
                'submissionId' => (int) $r->submission_id,
                'contextId' => (int) $r->context_id,
                'journalName' => (string) $r->journal_name,
                'journalPath' => (string) $r->journal_path,
                'title' => (string) $r->article_title,
                'authorNames' => (string) $r->author_names,
                'bucket' => (string) $r->pipeline_bucket,
                'stageLabel' => $this->formatStageAndRoundLabel((string) $r->pipeline_bucket, (int) $r->current_round),
                'stageEntryDate' => $this->formatDateShort($r->stage_entry_date, $locale),
                'bottleneckStatus' => (string) $r->bottleneck_status,
                'bottleneckLabel' => $this->formatBottleneckLabel($r),
                'ojsUrl' => $ojsUrl,
            ];
        }

        return $list;
    }

    /**
     * Build Bottleneck Monitoring list.
     * Ordered from longest overdue (`urgency_score DESC`).
     * Includes overdue (`overdue`) and almost due (`almost_due`).
     *
     * @param array $filters {contextId, period, search}
     * @param PKPRequest|null $request
     * @param string $locale
     * @return array
     */
    public function getBottleneckSubmissions($filters = [], $request = null, $locale = 'en_US')
    {
        $this->_syncService->ensureSynchronized($locale);

        $query = $this->_buildFilteredArticleQuery($filters);
        $query->whereIn('bottleneck_status', ['overdue', 'almost_due']);

        $rows = $query->orderBy('urgency_score', 'desc')->orderBy('overdue_working_days', 'desc')->orderBy('elapsed_working_days', 'desc')->orderBy('submission_id', 'asc')->get();

        $dispatcher = $request ? $request->getDispatcher() : null;
        $items = [];

        foreach ($rows as $r) {
            $bucket = (string) $r->pipeline_bucket;
            $round = (int) $r->current_round;
            $stageLabel = $this->formatStageAndRoundLabel($bucket, $round);

            if ($bucket === 'copyediting') {
                $remDays = (int) $r->remaining_days;
                $overDays = (int) $r->overdue_working_days;
                if ($r->bottleneck_status === 'overdue') {
                    $durationText = __('mjcp.bottleneck.copyeditingOverdue', ['days' => $overDays]);
                    $delayBadgeText = __('mjcp.bottleneck.overdueDays', ['days' => $overDays]);
                } else {
                    $durationText = __('mjcp.bottleneck.copyeditingRemaining', ['days' => $remDays]);
                    $delayBadgeText = __('mjcp.bottleneck.almostDue');
                }
            } else {
                $durationText = (int) $r->elapsed_working_days . ' ' . __('mjcp.common.workingDays');
                if ($r->bottleneck_status === 'overdue') {
                    $delayBadgeText = __('mjcp.bottleneck.overdueDays', ['days' => (int) $r->overdue_working_days]);
                } else {
                    $delayBadgeText = __('mjcp.bottleneck.almostDue');
                }
            }

            $ojsUrl = $dispatcher ? $dispatcher->url($request, ROUTE_PAGE, (string) $r->journal_path, 'workflow', 'index', [(int) $r->submission_id, (int) $r->stage_id]) : '#';

            $items[] = [
                'submissionId' => (int) $r->submission_id,
                'contextId' => (int) $r->context_id,
                'title' => (string) $r->article_title,
                'journalName' => (string) $r->journal_name,
                'journalPath' => (string) $r->journal_path,
                'stageId' => (int) $r->stage_id,
                'stageLabel' => $stageLabel,
                'round' => $round,
                'stageEntryDate' => (string) $r->stage_entry_date,
                'stageEntryFormatted' => $this->formatDateShort($r->stage_entry_date, $locale),
                'durationText' => $durationText,
                'elapsedWorkingDays' => (int) $r->elapsed_working_days,
                'overdueWorkingDays' => (int) $r->overdue_working_days,
                'bottleneckStatus' => (string) $r->bottleneck_status,
                'delayBadgeText' => $delayBadgeText,
                'badgeType' => $r->bottleneck_status === 'overdue' ? 'danger' : 'warning',
                'ojsUrl' => $ojsUrl,
            ];
        }

        return $items;
    }

    /**
     * Build Connected Journals list.
     *
     * @param PKPRequest|null $request
     * @param string $locale
     * @return array
     */
    public function getConnectedJournals($request = null, $locale = 'en_US')
    {
        $this->_syncService->ensureSynchronized($locale);

        $journals = $this->_dao->getAllJournals($locale);
        $syncLogs = $this->_dao->getLatestSyncLogsByJournal();

        // Active submissions count per journal
        $activeCountsRows = Capsule::table('mjcp_article_data')
            ->select(['context_id', Capsule::raw('COUNT(*) as active_cnt')])
            ->whereIn('pipeline_bucket', ['submission', 'review', 'revision', 'copyediting', 'production'])
            ->groupBy('context_id')
            ->get();

        $activeByCtx = [];
        foreach ($activeCountsRows as $acr) {
            $activeByCtx[(int) $acr->context_id] = (int) $acr->active_cnt;
        }

        $dispatcher = $request ? $request->getDispatcher() : null;
        $baseUrl = $request ? $request->getBaseUrl() : '';
        $list = [];

        foreach ($journals as $cid => $jInfo) {
            $logRow = isset($syncLogs[$cid]) ? $syncLogs[$cid] : null;
            $status = $logRow ? strtolower((string) $logRow->sync_status) : 'pending';
            if (!in_array($status, ['connected', 'pending', 'failed'], true)) {
                $status = 'connected';
            }

            $lastSyncRaw = $logRow && !empty($logRow->finished_at) ? (string) $logRow->finished_at : null;

            $badgeType = 'success';
            $statusLabel = __('mjcp.journals.status.connected');
            if ($status === 'pending') {
                $badgeType = 'warning';
                $statusLabel = __('mjcp.journals.status.pending');
            } elseif ($status === 'failed') {
                $badgeType = 'danger';
                $statusLabel = __('mjcp.journals.status.failed');
            }

            $ojsUrl = $dispatcher ? $dispatcher->url($request, ROUTE_PAGE, $jInfo['path'], 'submissions') : $baseUrl . '/index.php/' . $jInfo['path'] . '/submissions';

            $list[] = [
                'contextId' => $cid,
                'journalName' => $jInfo['name'],
                'journalPath' => $jInfo['path'],
                'journalUrl' => $baseUrl . '/index.php/' . $jInfo['path'],
                'openOjsUrl' => $ojsUrl,
                'activeSubmissions' => $status === 'failed' ? '-' : (isset($activeByCtx[$cid]) ? $activeByCtx[$cid] : 0),
                'syncStatus' => $status,
                'syncStatusLabel' => $statusLabel,
                'badgeType' => $badgeType,
                'lastSyncAt' => $lastSyncRaw,
                'lastSyncFormatted' => $lastSyncRaw ? $this->formatDateTimeShort($lastSyncRaw, $locale) : '-',
                'errorMessage' => $logRow ? (string) $logRow->error_message : '',
            ];
        }

        return $list;
    }

    /**
     * Build Issue Health cards per journal.
     * Shows next target issue, article quota, plotted articles, ready articles (LOA + copyediting done),
     * in-progress articles, warning badge when under or over quota, and per-article readiness breakdown.
     *
     * @param array $filters
     * @param string $locale
     * @return array
     */
    public function getIssueHealthList($filters = [], $locale = 'en_US')
    {
        $this->_syncService->ensureSynchronized($locale);

        $journals = $this->_dao->getAllJournals($locale);
        $filterCtxId = isset($filters['contextId']) && $filters['contextId'] !== 'all' && $filters['contextId'] !== '' ? (int) $filters['contextId'] : 0;
        if ($filterCtxId > 0) {
            $journals = isset($journals[$filterCtxId]) ? [$filterCtxId => $journals[$filterCtxId]] : [];
        }

        if (empty($journals)) {
            return [];
        }

        $contextIds = array_keys($journals);
        $issuesMap = $this->_dao->getIssuesMap($contextIds, $locale);
        $defaultQuotas = $this->_dao->getJournalDefaultQuotas();

        // Fetch all accepted/in-progress/scheduled articles for these journals
        $articleRows = Capsule::table('mjcp_article_data')->whereIn('context_id', $contextIds)->where('is_accepted', '=', 1)->orderBy('submission_id', 'asc')->get();

        $articlesByCtx = [];
        foreach ($articleRows as $ar) {
            $articlesByCtx[(int) $ar->context_id][] = $ar;
        }

        $cards = [];
        foreach ($journals as $cid => $jInfo) {
            // Find next unpublished issue for this journal
            $targetIssue = null;
            foreach ($issuesMap as $iss) {
                if ((int) $iss['contextId'] === $cid && (int) $iss['published'] === 0) {
                    $targetIssue = $iss;
                    break;
                }
            }
            // Fallback to current/latest issue if no future issue exists
            if (!$targetIssue) {
                foreach ($issuesMap as $iss) {
                    if ((int) $iss['contextId'] === $cid) {
                        $targetIssue = $iss;
                    }
                }
            }

            $quota = isset($defaultQuotas[$cid]) ? (int) $defaultQuotas[$cid] : ($targetIssue ? (int) $targetIssue['quota'] : 10);
            if ($quota <= 0) {
                $quota = 10;
            }

            $issueCompact = $targetIssue ? $targetIssue['compactNumber'] : '1.1';
            $issueId = $targetIssue ? (int) $targetIssue['id'] : 0;
            $targetDate = $targetIssue ? $targetIssue['targetDate'] : date('Y-m-t');

            $jArticles = isset($articlesByCtx[$cid]) ? $articlesByCtx[$cid] : [];
            $plottedCount = 0;
            $readyCount = 0;
            $inProgressCount = 0;
            $loaUploadedCount = 0;
            $copyeditingDoneCount = 0;
            $publishedCount = 0;
            $articleDetails = [];

            foreach ($jArticles as $ar) {
                // Match articles plotted to this target issue, or active accepted articles if not published in another issue
                $arIssueId = !empty($ar->target_issue_id) ? (int) $ar->target_issue_id : 0;
                $isForThisIssue = ($issueId > 0 && $arIssueId === $issueId) || ($arIssueId === 0 && (int) $ar->is_published === 0);

                if (!$isForThisIssue) {
                    continue;
                }

                $plottedCount++;
                $hasLoa = $ar->loa_status === 'uploaded';
                $ceDone = (int) $ar->copyediting_done === 1;
                $isPub = (int) $ar->is_published === 1;
                $isReady = (int) $ar->is_ready_to_publish === 1 || $isPub;

                if ($hasLoa) {
                    $loaUploadedCount++;
                }
                if ($ceDone) {
                    $copyeditingDoneCount++;
                }
                if ($isPub) {
                    $publishedCount++;
                }
                if ($isReady) {
                    $readyCount++;
                } else {
                    $inProgressCount++;
                }

                $articleDetails[] = [
                    'submissionId' => (int) $ar->submission_id,
                    'title' => (string) $ar->article_title,
                    'loaStatus' => $hasLoa ? 'uploaded' : 'missing',
                    'copyeditingDone' => $ceDone,
                    'isPublished' => $isPub,
                    'isReady' => $isReady,
                ];
            }

            // Determine health status and warning badge
            $effectiveCompareCount = $plottedCount > $quota ? $plottedCount : $readyCount;
            if ($plottedCount > $quota) {
                $diff = $plottedCount - $quota;
                $healthStatus = 'over_quota';
                $badgeType = 'warning';
                $badgeLabel = __('mjcp.issue.overQuota', ['count' => $diff]);
                $progressText = __('mjcp.issue.plottedOfQuota', ['count' => $plottedCount, 'quota' => $quota]);
            } elseif ($readyCount === $quota) {
                $healthStatus = 'on_track';
                $badgeType = 'success';
                $badgeLabel = __('mjcp.issue.onTrack');
                $progressText = __('mjcp.issue.readyOfQuota', ['count' => $readyCount, 'quota' => $quota]);
            } elseif ($readyCount < $quota) {
                $diff = $quota - $readyCount;
                $healthStatus = 'under_quota';
                $badgeType = $diff >= 5 ? 'danger' : 'warning';
                $badgeLabel = __('mjcp.issue.underQuota', ['count' => $diff]);
                $progressText = __('mjcp.issue.readyOfQuota', ['count' => $readyCount, 'quota' => $quota]);
            } else {
                $diff = $readyCount - $quota;
                $healthStatus = 'over_quota';
                $badgeType = 'warning';
                $badgeLabel = __('mjcp.issue.overQuota', ['count' => $diff]);
                $progressText = __('mjcp.issue.readyOfQuota', ['count' => $readyCount, 'quota' => $quota]);
            }

            $progressPct = min(100, round(($effectiveCompareCount / max(1, $quota)) * 100));

            $cards[] = [
                'contextId' => $cid,
                'journalName' => $jInfo['name'],
                'issueId' => $issueId,
                'issueTitle' => $jInfo['name'] . ' ' . $issueCompact,
                'issueIdentification' => $targetIssue ? $targetIssue['identification'] : $jInfo['name'] . ' ' . $issueCompact,
                'targetDate' => $targetDate,
                'quota' => $quota,
                'plottedCount' => $plottedCount,
                'readyCount' => $readyCount,
                'inProgressCount' => $inProgressCount,
                'loaUploadedCount' => $loaUploadedCount,
                'copyeditingDoneCount' => $copyeditingDoneCount,
                'publishedCount' => $publishedCount,
                'healthStatus' => $healthStatus,
                'badgeType' => $badgeType,
                'badgeLabel' => $badgeLabel,
                'progressText' => $progressText,
                'progressPct' => $progressPct,
                'articles' => $articleDetails,
            ];
        }

        return $cards;
    }

    /**
     * Build Journal Performance KPIs & Monthly Trend Data:
     * - Monthly submission vs published trend (last 6 months)
     * - Count of accepted, rejected, published
     * - Average days from submission to publication
     * - Acceptance rate (%) & Rejection rate (%)
     * - On-time stage percentage (%)
     *
     * @param array $filters {contextId, period}
     * @param string $locale
     * @return array
     */
    public function getJournalPerformance($filters = [], $locale = 'en_US')
    {
        $this->_syncService->ensureSynchronized($locale);

        $query = $this->_buildFilteredArticleQuery($filters);
        $rows = $query->get();

        $totalSubmissions = 0;
        $acceptedCount = 0;
        $rejectedCount = 0;
        $publishedCount = 0;
        $onTimeCount = 0;
        $totalTurnaroundDays = 0;
        $turnaroundSampleCount = 0;

        // Build 6-month buckets ending at current month
        $monthlyBuckets = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime("-$i months", strtotime(date('Y-m-01')));
            $ym = date('Y-m', $ts);
            $monthlyBuckets[$ym] = [
                'ym' => $ym,
                'label' => $this->formatMonthShort((int) date('n', $ts), $locale),
                'submitted' => 0,
                'accepted' => 0,
                'rejected' => 0,
                'published' => 0,
            ];
        }

        foreach ($rows as $r) {
            $totalSubmissions++;
            $isAcc = (int) $r->is_accepted === 1;
            $isDec = (int) $r->is_declined === 1;
            $isPub = (int) $r->is_published === 1;

            if ($isAcc) {
                $acceptedCount++;
            }
            if ($isDec) {
                $rejectedCount++;
            }
            if ($isPub) {
                $publishedCount++;
            }
            if ($r->bottleneck_status !== 'overdue') {
                $onTimeCount++;
            }

            // Average days from submission to publication
            if ($isPub && !empty($r->date_submitted) && !empty($r->date_published)) {
                $subTs = strtotime(substr((string) $r->date_submitted, 0, 10));
                $pubTs = strtotime(substr((string) $r->date_published, 0, 10));
                if ($subTs !== false && $pubTs !== false && $pubTs >= $subTs) {
                    $diffDays = (int) round(($pubTs - $subTs) / 86400);
                    $totalTurnaroundDays += $diffDays;
                    $turnaroundSampleCount++;
                }
            }

            // Monthly trend aggregation
            if (!empty($r->date_submitted)) {
                $subYm = substr((string) $r->date_submitted, 0, 7);
                if (isset($monthlyBuckets[$subYm])) {
                    $monthlyBuckets[$subYm]['submitted']++;
                    if ($isAcc) {
                        $monthlyBuckets[$subYm]['accepted']++;
                    }
                    if ($isDec) {
                        $monthlyBuckets[$subYm]['rejected']++;
                    }
                }
            }
            if ($isPub && !empty($r->date_published)) {
                $pubYm = substr((string) $r->date_published, 0, 7);
                if (isset($monthlyBuckets[$pubYm])) {
                    $monthlyBuckets[$pubYm]['published']++;
                }
            }
        }

        $decidedTotal = $acceptedCount + $rejectedCount;
        $denom = $decidedTotal > 0 ? $decidedTotal : max(1, $totalSubmissions);
        $acceptanceRate = $totalSubmissions > 0 ? (int) round(($acceptedCount / $denom) * 100) : 0;
        $rejectionRate = $totalSubmissions > 0 ? (int) round(($rejectedCount / $denom) * 100) : 0;
        $onTimeRate = $totalSubmissions > 0 ? (int) round(($onTimeCount / $totalSubmissions) * 100) : 100;
        $avgSubmitToPublishDays = $turnaroundSampleCount > 0 ? (int) round($totalTurnaroundDays / $turnaroundSampleCount) : 0;

        // Compute bar height percentages for responsive rendering
        $maxMonthlyValue = 1;
        foreach ($monthlyBuckets as $mb) {
            $maxMonthlyValue = max($maxMonthlyValue, $mb['submitted'], $mb['published']);
        }
        foreach ($monthlyBuckets as $ym => &$mb) {
            $mb['submittedHeightPct'] = max(8, (int) round(($mb['submitted'] / $maxMonthlyValue) * 100));
            $mb['publishedHeightPct'] = max(8, (int) round(($mb['published'] / $maxMonthlyValue) * 100));
        }
        unset($mb);

        return [
            'totalSubmissions' => $totalSubmissions,
            'acceptedCount' => $acceptedCount,
            'rejectedCount' => $rejectedCount,
            'publishedCount' => $publishedCount,
            'avgSubmitToPublishDays' => $avgSubmitToPublishDays,
            'acceptanceRate' => $acceptanceRate,
            'rejectionRate' => $rejectionRate,
            'onTimeRate' => $onTimeRate,
            'monthlyTrends' => array_values($monthlyBuckets),
            'chartJson' => json_encode(array_values($monthlyBuckets)),
        ];
    }

    /**
     * Build a filtered query builder against `mjcp_article_data`.
     *
     * @param array $filters {contextId, period}
     * @return \Illuminate\Database\Query\Builder
     */
    protected function _buildFilteredArticleQuery($filters = [])
    {
        $query = Capsule::table('mjcp_article_data');

        if (isset($filters['contextId']) && $filters['contextId'] !== 'all' && $filters['contextId'] !== '' && (int) $filters['contextId'] > 0) {
            $query->where('context_id', '=', (int) $filters['contextId']);
        }

        if (isset($filters['period']) && $filters['period'] !== 'all' && $filters['period'] !== '') {
            $period = (string) $filters['period'];
            $days = 0;
            if ($period === '30d') {
                $days = 30;
            } elseif ($period === '90d') {
                $days = 90;
            } elseif ($period === '180d') {
                $days = 180;
            } elseif ($period === '365d') {
                $days = 365;
            }

            if ($days > 0) {
                $cutoff = date('Y-m-d 00:00:00', strtotime("-$days days"));
                $query->where(function ($q) use ($cutoff) {
                    $q->where('date_submitted', '>=', $cutoff)->orWhere('stage_entry_date', '>=', substr($cutoff, 0, 10))->orWhereNull('date_submitted');
                });
            }
        }

        return $query;
    }

    /**
     * Build a localized bottleneck label from an article summary row.
     *
     * @param object $row
     * @return string
     */
    public function formatBottleneckLabel($row)
    {
        $status = (string) $row->bottleneck_status;
        if ($status === 'overdue') {
            return __('mjcp.bottleneck.overdueDays', ['days' => (int) $row->overdue_working_days]);
        }
        if ($status === 'almost_due') {
            return __('mjcp.bottleneck.almostDue');
        }
        return '';
    }

    /**
     * Format stage and review round label.
     *
     * @param string $bucket
     * @param int $round
     * @return string
     */
    public function formatStageAndRoundLabel($bucket, $round = 0)
    {
        switch ($bucket) {
            case 'review':
                $r = max(1, (int) $round);
                return __('mjcp.stage.reviewRound', ['round' => $r]);
            case 'revision':
                return __('mjcp.stage.authorRevision');
            case 'copyediting':
                return __('mjcp.stage.copyediting');
            case 'production':
                return __('mjcp.stage.production');
            case 'published':
                return __('mjcp.stage.published');
            case 'declined':
                return __('mjcp.stage.declined');
            case 'submission':
            default:
                return __('mjcp.stage.submission');
        }
    }

    /**
     * Format a date ('Y-m-d') into localized short date (e.g. "10 Oct").
     *
     * @param string|null $dateStr
     * @param string $locale
     * @return string
     */
    public function formatDateShort($dateStr, $locale = 'en_US')
    {
        if (empty($dateStr)) {
            return '-';
        }
        $ts = strtotime(substr((string) $dateStr, 0, 10));
        if ($ts === false) {
            return (string) $dateStr;
        }
        $day = (int) date('j', $ts);
        $month = (int) date('n', $ts);
        return $day . ' ' . $this->formatMonthShort($month, $locale);
    }

    /**
     * Format a datetime ('Y-m-d H:i:s') into localized short datetime (e.g. "13 Nov, 08:00").
     *
     * @param string|null $dtStr
     * @param string $locale
     * @return string
     */
    public function formatDateTimeShort($dtStr, $locale = 'en_US')
    {
        if (empty($dtStr)) {
            return '-';
        }
        $ts = strtotime((string) $dtStr);
        if ($ts === false) {
            return (string) $dtStr;
        }
        $day = (int) date('j', $ts);
        $month = (int) date('n', $ts);
        $time = date('H:i', $ts);
        return $day . ' ' . $this->formatMonthShort($month, $locale) . ', ' . $time;
    }

    /**
     * Format a date ('Y-m-d') with year (e.g. "20 Dec 2026").
     *
     * @param string|null $dateStr
     * @param string $locale
     * @return string
     */
    public function formatDateWithYear($dateStr, $locale = 'en_US')
    {
        if (empty($dateStr)) {
            return '-';
        }
        $ts = strtotime(substr((string) $dateStr, 0, 10));
        if ($ts === false) {
            return (string) $dateStr;
        }
        $day = (int) date('j', $ts);
        $month = (int) date('n', $ts);
        $year = date('Y', $ts);
        return $day . ' ' . $this->formatMonthShort($month, $locale) . ' ' . $year;
    }

    /**
     * Return localized 3-letter month name via OJS localization keys.
     *
     * @param int $monthNum 1..12
     * @param string|null $locale
     * @return string
     */
    public function formatMonthShort($monthNum, $locale = null)
    {
        $m = max(1, min(12, (int) $monthNum));
        $key = 'mjcp.month.short.' . $m;
        $translated = __($key, [], $locale);
        if ($translated !== '' && strpos($translated, '##') !== 0 && $translated !== $key) {
            return $translated;
        }
        return date('M', mktime(0, 0, 0, $m, 1, 2026));
    }
}
