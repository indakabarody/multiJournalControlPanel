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

require_once __DIR__ . '/WorkingDayCalculator.inc.php';
require_once dirname(__DIR__) . '/dao/MultiJournalControlPanelDAO.inc.php';

use Illuminate\Database\Capsule\Manager as Capsule;

class SynchronizationService
{
    /** @var MultiJournalControlPanelPlugin */
    protected $_plugin;

    /** @var MultiJournalControlPanelDAO */
    protected $_dao;

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
    }

    /**
     * Synchronize all journals or a specific journal into `mjcp_article_data` and `mjcp_sync_logs`.
     *
     * Mapping rules for OJS 3.3 Editorial Pipeline:
     * - Each submission is counted once in `all_article` and assigned to a unique pipeline bucket:
     *   1. `published`: `status == STATUS_PUBLISHED (3)`
     *   2. `production`: `status == STATUS_SCHEDULED (5)` OR (`status == STATUS_QUEUED (1)` AND `stage_id == WORKFLOW_STAGE_ID_PRODUCTION (5)`)
     *   3. `copyediting`: `status == STATUS_QUEUED (1)` AND `stage_id == WORKFLOW_STAGE_ID_EDITING (4)`
     *   4. `revision`: `status == STATUS_QUEUED (1)` AND `stage_id IN (2, 3)` AND latest review round status IN (1, 2, 11, 15)
     *   5. `review`: `status == STATUS_QUEUED (1)` AND `stage_id IN (2, 3)`
     *   6. `submission`: `status == STATUS_QUEUED (1)` AND `stage_id == WORKFLOW_STAGE_ID_SUBMISSION (1)`
     *   7. `declined`: `status == STATUS_DECLINED (4)`
     *
     * @param int|null $onlyContextId Null for all journals, or specific journal ID
     * @param string $mode 'scheduled'|'manual'|'auto'
     * @param int|null $triggeredByUserId
     * @param string $locale
     * @return array Summary of synchronization results
     */
    public function synchronizeJournals($onlyContextId = null, $mode = 'manual', $triggeredByUserId = null, $locale = 'en_US')
    {
        $this->_dao->ensureSchema();
        $startedAt = date('Y-m-d H:i:s');

        $journals = $this->_dao->getAllJournals($locale);
        if ($onlyContextId !== null && (int) $onlyContextId > 0) {
            $cid = (int) $onlyContextId;
            $journals = isset($journals[$cid]) ? [$cid => $journals[$cid]] : [];
        }

        if (empty($journals)) {
            return [
                'ok' => true,
                'journalsSynced' => 0,
                'articlesProcessed' => 0,
                'activeArticles' => 0,
                'syncedAt' => $startedAt,
            ];
        }

        $contextIds = array_keys($journals);
        $issuesMap = $this->_dao->getIssuesMap($contextIds, $locale);
        $batch = $this->_dao->getOjsSubmissionsBatchForSync($contextIds, $locale);

        $subsByContext = [];
        foreach ($batch['submissions'] as $subRow) {
            $subsByContext[(int) $subRow->context_id][] = $subRow;
        }

        $globalHolidays = $this->_dao->getHolidays(0);
        $globalHolidayDates = array_column($globalHolidays, 'date');

        $totalProcessed = 0;
        $totalActive = 0;
        $journalsSynced = 0;

        $request = Application::get()->getRequest();
        $dispatcher = $request && method_exists($request, 'getDispatcher') ? $request->getDispatcher() : null;
        $baseUrl = $request ? $request->getBaseUrl() : '';
        if ($baseUrl === '') {
            $baseUrl = (string) Config::getVar('general', 'base_url', '');
        }
        $restfulUrls = (bool) Config::getVar('general', 'restful_urls', false);
        $timeZoneName = Config::getVar('general', 'time_zone', 'UTC');
        $configuredWorkingDays = $this->_plugin ? $this->_plugin->getSetting(0, 'workingDays') : null;
        if (empty($configuredWorkingDays)) {
            $configuredWorkingDays = [1, 2, 3, 4, 5];
        }

        foreach ($journals as $ctxId => $jInfo) {
            $jStartedAt = date('Y-m-d H:i:s');
            try {
                $thresholds = $this->_dao->getStageThresholds($ctxId);
                $jHolidays = $this->_dao->getHolidays($ctxId);
                $holidayDates = array_values(array_unique(array_merge($globalHolidayDates, array_column($jHolidays, 'date'))));
                $calculator = new WorkingDayCalculator($holidayDates, $timeZoneName, $configuredWorkingDays);

                $nextTargetIssue = $this->_findNextUnpublishedIssueForJournal($ctxId, $issuesMap);

                $jSubs = isset($subsByContext[$ctxId]) ? $subsByContext[$ctxId] : [];
                $jProcessed = 0;
                $jActive = 0;

                foreach ($jSubs as $subRow) {
                    $subId = (int) $subRow->submission_id;
                    $stageId = (int) $subRow->stage_id;
                    $status = (int) $subRow->status;

                    $title = isset($batch['titles'][$subId]) && $batch['titles'][$subId] !== '' ? $batch['titles'][$subId] : __('mjcp.common.untitledManuscript', ['id' => $subId]);

                    $pubObj = isset($batch['publications'][$subId]) ? $batch['publications'][$subId] : null;
                    $primaryContactId = $pubObj && !empty($pubObj->primary_contact_id) ? (int) $pubObj->primary_contact_id : 0;

                    $authorList = isset($batch['authors'][$subId]) ? $batch['authors'][$subId] : [];
                    $authorNamesArr = [];
                    $correspondentEmail = '';
                    $firstAuthorEmail = '';
                    foreach ($authorList as $aInfo) {
                        if (!empty($aInfo['name'])) {
                            $authorNamesArr[] = $aInfo['name'];
                        }
                        if ($firstAuthorEmail === '' && !empty($aInfo['email'])) {
                            $firstAuthorEmail = $aInfo['email'];
                        }
                        if ($primaryContactId > 0 && isset($aInfo['authorId']) && (int) $aInfo['authorId'] === $primaryContactId && !empty($aInfo['email'])) {
                            $correspondentEmail = $aInfo['email'];
                        }
                    }
                    if ($correspondentEmail === '') {
                        $correspondentEmail = $firstAuthorEmail;
                    }
                    $authorNamesStr = !empty($authorNamesArr) ? implode(', ', $authorNamesArr) : '-';

                    $rounds = isset($batch['reviewRounds'][$subId]) ? $batch['reviewRounds'][$subId] : [];
                    $assignments = isset($batch['reviewAssignments'][$subId]) ? $batch['reviewAssignments'][$subId] : [];
                    $decisions = isset($batch['editDecisions'][$subId]) ? $batch['editDecisions'][$subId] : [];

                    $decisionSummary = $this->_analyzeDecisions($decisions, $status, $stageId);
                    $isPublished = $status === 3 ? 1 : 0; // STATUS_PUBLISHED = 3
                    $isDeclined = $status === 4 || $decisionSummary['isDeclined'] ? 1 : 0; // STATUS_DECLINED = 4
                    $isAccepted = $isPublished || $stageId >= 4 || $decisionSummary['isAccepted'] ? 1 : 0;

                    $currentRound = 0;
                    $roundStatus = null;
                    if (!empty($rounds)) {
                        $lastRoundObj = $rounds[count($rounds) - 1];
                        $currentRound = (int) $lastRoundObj->round;
                        $roundStatus = (int) $lastRoundObj->status;
                    } elseif ($stageId === 2 || $stageId === 3) {
                        $currentRound = 1;
                    }

                    $pipelineBucket = $this->_determinePipelineBucket($status, $stageId, $roundStatus);
                    if (in_array($pipelineBucket, ['submission', 'review', 'revision', 'copyediting', 'production'], true)) {
                        $jActive++;
                        $totalActive++;
                    }

                    $targetIssueId = null;
                    $targetIssueLabel = null;
                    $targetIssueDate = null;

                    if (isset($batch['erTargetIssues'][$subId]) && !empty($batch['erTargetIssues'][$subId]->issue_id)) {
                        $targetIssueId = (int) $batch['erTargetIssues'][$subId]->issue_id;
                        if (!empty($batch['erTargetIssues'][$subId]->target_date)) {
                            $targetIssueDate = substr((string) $batch['erTargetIssues'][$subId]->target_date, 0, 10);
                        }
                    } elseif (isset($batch['issueIds'][$subId])) {
                        $targetIssueId = (int) $batch['issueIds'][$subId];
                    } elseif ($isAccepted && !$isPublished && $nextTargetIssue) {
                        $targetIssueId = (int) $nextTargetIssue['id'];
                    }

                    if ($targetIssueId && isset($issuesMap[$targetIssueId])) {
                        $targetIssueLabel = $issuesMap[$targetIssueId]['compactNumber'];
                        if (!$targetIssueDate) {
                            $targetIssueDate = $issuesMap[$targetIssueId]['targetDate'];
                        }
                    }

                    $stageEntryDate = $this->_determineStageEntryDate($subRow, $pipelineBucket, $currentRound, $decisions, $assignments);
                    $elapsedWorkingDays = 0;
                    $limitWorkingDays = 0;
                    $overdueWorkingDays = 0;
                    $remainingDays = 0;
                    $bottleneckStatus = 'normal';
                    $urgencyScore = 0;

                    if ($pipelineBucket === 'review' || $pipelineBucket === 'revision') {
                        $rNum = max(1, $currentRound);
                        $limitWorkingDays = $rNum <= 1 ? $thresholds['round1Limit'] : $thresholds['round2Limit'];
                        $warnWorkingDays = $rNum <= 1 ? $thresholds['round1Warning'] : $thresholds['round2Warning'];

                        $eval = $calculator->evaluateReviewRound($stageEntryDate, $limitWorkingDays, $warnWorkingDays);
                        $elapsedWorkingDays = (int) $eval['elapsedWorkingDays'];
                        $overdueWorkingDays = (int) $eval['overdueWorkingDays'];
                        $remainingDays = (int) $eval['remainingWorkingDays'];
                        $bottleneckStatus = $eval['status'];
                        $urgencyScore = (int) $eval['urgencyScore'];
                    } elseif ($pipelineBucket === 'copyediting') {
                        $elapsedWorkingDays = $calculator->countWorkingDaysBetween($stageEntryDate, $calculator->getTodayDateString());
                        $copyEval = $calculator->evaluateCopyediting($targetIssueDate, $thresholds['copyeditingWarning']);
                        $overdueWorkingDays = (int) $copyEval['overdueCalendarDays'];
                        $remainingDays = (int) $copyEval['remainingCalendarDays'];
                        $bottleneckStatus = $copyEval['status'];
                        $urgencyScore = (int) $copyEval['urgencyScore'];
                    }

                    // Ready to publish = LOA uploaded AND copyediting finished (stage >= 5 or published)
                    $hasLoa = isset($batch['loaMap'][$subId]);
                    $loaStatus = $hasLoa ? 'uploaded' : 'missing';
                    $copyeditingDone = $stageId >= 5 || $isPublished ? 1 : 0;
                    $isReadyToPublish = $isAccepted && !$isPublished && $hasLoa && $copyeditingDone ? 1 : 0;

                    $datePublished = $pubObj && !empty($pubObj->date_published) ? substr((string) $pubObj->date_published, 0, 10) : ($isPublished && !empty($subRow->last_modified) ? substr((string) $subRow->last_modified, 0, 10) : null);

                    $doi = isset($batch['dois'][$subId]) ? $batch['dois'][$subId] : null;
                    $publicationUrl = null;
                    if ($isPublished) {
                        $bestArticleId = $pubObj && !empty($pubObj->url_path) ? (string) $pubObj->url_path : (string) $subId;
                        if ($dispatcher && $request) {
                            $publicationUrl = $dispatcher->url($request, ROUTE_PAGE, $jInfo['path'], 'article', 'view', [$bestArticleId]);
                        } elseif ($baseUrl !== '') {
                            $indexPrefix = $restfulUrls ? '' : '/index.php';
                            $publicationUrl = rtrim($baseUrl, '/') . $indexPrefix . '/' . $jInfo['path'] . '/article/view/' . rawurlencode($bestArticleId);
                        }
                    }

                    $this->_dao->upsertArticleData([
                        'submission_id' => $subId,
                        'context_id' => $ctxId,
                        'journal_path' => $jInfo['path'],
                        'journal_name' => $jInfo['name'],
                        'article_title' => $title,
                        'author_names' => $authorNamesStr,
                        'correspondent_email' => $correspondentEmail,
                        'stage_id' => $stageId,
                        'pipeline_bucket' => $pipelineBucket,
                        'submission_status' => $status,
                        'is_accepted' => $isAccepted,
                        'is_declined' => $isDeclined,
                        'is_published' => $isPublished,
                        'current_round' => $currentRound,
                        'round_status' => $roundStatus,
                        'stage_entry_date' => $stageEntryDate,
                        'date_submitted' => $subRow->date_submitted ? (string) $subRow->date_submitted : null,
                        'date_accepted' => $decisionSummary['dateAccepted'],
                        'date_published' => $datePublished,
                        'target_issue_id' => $targetIssueId,
                        'target_issue_label' => $targetIssueLabel,
                        'target_issue_date' => $targetIssueDate,
                        'elapsed_working_days' => $elapsedWorkingDays,
                        'limit_working_days' => $limitWorkingDays,
                        'overdue_working_days' => $overdueWorkingDays,
                        'remaining_days' => $remainingDays,
                        'bottleneck_status' => $bottleneckStatus,
                        'urgency_score' => $urgencyScore,
                        'loa_status' => $loaStatus,
                        'copyediting_done' => $copyeditingDone,
                        'is_ready_to_publish' => $isReadyToPublish,
                        'doi' => $doi,
                        'publication_url' => $publicationUrl,
                        'last_synced_at' => $jStartedAt,
                    ]);

                    $jProcessed++;
                    $totalProcessed++;
                }

                // Remove stale cached rows for submissions that no longer exist in OJS for this journal
                $currentSubIds = [];
                foreach ($jSubs as $sr) {
                    $currentSubIds[] = (int) $sr->submission_id;
                }
                if (!empty($currentSubIds)) {
                    Capsule::table('mjcp_article_data')->where('context_id', '=', $ctxId)->whereNotIn('submission_id', $currentSubIds)->delete();
                } else {
                    Capsule::table('mjcp_article_data')->where('context_id', '=', $ctxId)->delete();
                }

                $this->_dao->logSync($ctxId, 'connected', $mode, $jProcessed, $jActive, null, $triggeredByUserId, $jStartedAt);
                $journalsSynced++;
            } catch (Exception $e) {
                $this->_dao->logSync($ctxId, 'failed', $mode, 0, 0, $e->getMessage(), $triggeredByUserId, $jStartedAt);
            }
        }

        $this->_dao->logSync(0, 'connected', $mode, $totalProcessed, $totalActive, null, $triggeredByUserId, $startedAt);

        return [
            'ok' => true,
            'journalsSynced' => $journalsSynced,
            'articlesProcessed' => $totalProcessed,
            'activeArticles' => $totalActive,
            'syncedAt' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Ensure synchronized data exists; if `mjcp_sync_logs` is empty, run initial sync automatically.
     *
     * @param string $locale
     * @return void
     */
    public function ensureSynchronized($locale = 'en_US')
    {
        $this->_dao->ensureSchema();
        $hasSync = Capsule::table('mjcp_sync_logs')->exists();
        if (!$hasSync) {
            $this->synchronizeJournals(null, 'auto', null, $locale);
        }
    }

    /**
     * Map OJS 3.3 submission status, stage_id, and review round status to a unique pipeline bucket.
     *
     * @param int $status
     * @param int $stageId
     * @param int|null $roundStatus
     * @return string 'submission'|'review'|'revision'|'copyediting'|'production'|'published'|'declined'
     */
    protected function _determinePipelineBucket($status, $stageId, $roundStatus = null)
    {
        if ($status === 3) {
            return 'published';
        }
        if ($status === 4) {
            return 'declined';
        }
        if ($status === 5) {
            return 'production';
        }

        if ($stageId === 5) {
            return 'production';
        }
        if ($stageId === 4) {
            return 'copyediting';
        }
        if ($stageId === 2 || $stageId === 3) {
            if (in_array((int) $roundStatus, [1, 2, 11, 15], true)) {
                return 'revision';
            }
            return 'review';
        }

        return 'submission';
    }

    /**
     * Inspect editorial decisions to determine acceptance/decline flags and acceptance date.
     *
     * @param array $decisions
     * @param int $status
     * @param int $stageId
     * @return array
     */
    protected function _analyzeDecisions($decisions, $status, $stageId)
    {
        $isAccepted = false;
        $isDeclined = false;
        $dateAccepted = null;

        foreach ($decisions as $d) {
            $code = (int) $d->decision;
            if ($code === 1 || $code === 7) {
                $isAccepted = true;
                $isDeclined = false;
                if (!$dateAccepted && !empty($d->date_decided)) {
                    $dateAccepted = substr((string) $d->date_decided, 0, 10);
                }
            } elseif ($code === 4 || $code === 9) {
                $isDeclined = true;
            } elseif ($code === 17) {
                // SUBMISSION_EDITOR_DECISION_REVERT_DECLINE restores submission to active workflow
                $isDeclined = false;
            }
        }

        if ($status === 4) {
            $isDeclined = true;
        } elseif ($status === 1) {
            // Active queued submission must not remain marked declined if decline was reverted
            $isDeclined = false;
        }

        if ($stageId >= 4 || $status === 3 || $status === 5) {
            $isAccepted = true;
            $isDeclined = false;
        }

        return [
            'isAccepted' => $isAccepted,
            'isDeclined' => $isDeclined,
            'dateAccepted' => $dateAccepted,
        ];
    }

    /**
     * Determine the date the submission entered its current stage or review round.
     * Calculation restarts when moving to a new review round.
     *
     * @param object $subRow
     * @param string $bucket
     * @param int $currentRound
     * @param array $decisions
     * @param array $assignments
     * @return string 'Y-m-d'
     */
    protected function _determineStageEntryDate($subRow, $bucket, $currentRound, $decisions, $assignments)
    {
        if ($bucket === 'revision') {
            $revDate = null;
            foreach ($decisions as $d) {
                if ((int) $d->round === (int) $currentRound && in_array((int) $d->decision, [2, 3], true) && !empty($d->date_decided)) {
                    $revDate = substr((string) $d->date_decided, 0, 10);
                }
            }
            if ($revDate) {
                return $revDate;
            }
        }

        if ($bucket === 'review' || $bucket === 'revision') {
            if ($currentRound <= 1) {
                $r1Date = null;
                foreach ($decisions as $d) {
                    // SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW (8) starts Review Round 1
                    if ((int) $d->decision === 8 && !empty($d->date_decided)) {
                        $r1Date = substr((string) $d->date_decided, 0, 10);
                    }
                }
                if ($r1Date) {
                    return $r1Date;
                }
            } else {
                $newRoundDate = null;
                foreach ($decisions as $d) {
                    if ((int) $d->decision === 16 && ((int) $d->round === $currentRound || (int) $d->round === $currentRound - 1) && !empty($d->date_decided)) {
                        $newRoundDate = substr((string) $d->date_decided, 0, 10);
                    }
                }
                if ($newRoundDate) {
                    return $newRoundDate;
                }
            }

            $earliestAssign = null;
            foreach ($assignments as $ra) {
                if ((int) $ra->round === (int) $currentRound && !empty($ra->date_assigned)) {
                    $ymd = substr((string) $ra->date_assigned, 0, 10);
                    if ($earliestAssign === null || $ymd < $earliestAssign) {
                        $earliestAssign = $ymd;
                    }
                }
            }
            if ($earliestAssign) {
                return $earliestAssign;
            }
        }

        if ($bucket === 'copyediting') {
            foreach ($decisions as $d) {
                if ((int) $d->decision === 1 && !empty($d->date_decided)) {
                    return substr((string) $d->date_decided, 0, 10);
                }
            }
        }

        if ($bucket === 'production') {
            foreach ($decisions as $d) {
                if ((int) $d->decision === 7 && !empty($d->date_decided)) {
                    return substr((string) $d->date_decided, 0, 10);
                }
            }
        }

        if (!empty($subRow->date_submitted)) {
            return substr((string) $subRow->date_submitted, 0, 10);
        }
        if (!empty($subRow->date_last_activity)) {
            return substr((string) $subRow->date_last_activity, 0, 10);
        }
        return date('Y-m-d');
    }

    /**
     * Find the next unpublished target issue for a journal (or latest issue if none unpublished).
     *
     * @param int $contextId
     * @param array $issuesMap
     * @return array|null
     */
    protected function _findNextUnpublishedIssueForJournal($contextId, $issuesMap)
    {
        $latestAny = null;
        foreach ($issuesMap as $iss) {
            if ((int) $iss['contextId'] !== (int) $contextId) {
                continue;
            }
            $latestAny = $iss;
            if ((int) $iss['published'] === 0) {
                return $iss;
            }
        }
        return $latestAny;
    }
}
