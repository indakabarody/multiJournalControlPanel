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

import('lib.pkp.classes.db.DAO');

use Illuminate\Database\Capsule\Manager as Capsule;

class MultiJournalControlPanelDAO extends DAO
{
    /** @var bool Whether schema verification has executed in this request */
    protected static $_schemaChecked = false;

    /**
     * Ensure all 15 mjcp_* tables and seed data exist.
     *
     * @return void
     */
    public function ensureSchema()
    {
        if (self::$_schemaChecked) {
            return;
        }
        self::$_schemaChecked = true;

        try {
            if (!Capsule::schema()->hasTable('mjcp_article_data') || !Capsule::schema()->hasTable('mjcp_payments')) {
                require_once dirname(dirname(__DIR__)) . '/MultiJournalControlPanelSchemaMigration.inc.php';
                $migration = new MultiJournalControlPanelSchemaMigration();
                $migration->up();
            }
        } catch (Exception $e) {
            error_log('MultiJournalControlPanelDAO::ensureSchema warning: ' . $e->getMessage());
        }
    }

    /**
     * Fetch all journals in the current OJS installation using batch queries (no N+1).
     *
     * @param string $locale
     * @return array Map of contextId => journal metadata
     */
    public function getAllJournals($locale = 'en_US')
    {
        $rows = Capsule::table('journals')->orderBy('seq', 'asc')->orderBy('journal_id', 'asc')->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $journalIds = [];
        foreach ($rows as $r) {
            $journalIds[] = (int) $r->journal_id;
        }

        $settingRows = Capsule::table('journal_settings')
            ->whereIn('journal_id', $journalIds)
            ->whereIn('setting_name', ['name', 'acronym', 'contactEmail'])
            ->get();

        $settingsByJournal = [];
        foreach ($settingRows as $s) {
            $jid = (int) $s->journal_id;
            $loc = (string) $s->locale;
            $settingsByJournal[$jid][$s->setting_name][$loc] = $s->setting_value;
        }

        $journals = [];
        foreach ($rows as $r) {
            $jid = (int) $r->journal_id;
            $primaryLocale = $r->primary_locale ? (string) $r->primary_locale : 'en_US';
            $nameMap = isset($settingsByJournal[$jid]['name']) ? $settingsByJournal[$jid]['name'] : [];
            $acronymMap = isset($settingsByJournal[$jid]['acronym']) ? $settingsByJournal[$jid]['acronym'] : [];

            $name = $this->pickLocalizedValue($nameMap, $locale, $primaryLocale, (string) $r->path);
            $acronym = $this->pickLocalizedValue($acronymMap, $locale, $primaryLocale, strtoupper((string) $r->path));

            $journals[$jid] = [
                'id' => $jid,
                'path' => (string) $r->path,
                'enabled' => (int) $r->enabled,
                'primaryLocale' => $primaryLocale,
                'name' => $name,
                'acronym' => $acronym,
            ];
        }

        return $journals;
    }

    /**
     * Batch-fetch all completed OJS submissions (`submission_progress = 0`) across the given journals
     * along with titles, authors, review rounds, review assignments, edit decisions, and target issues.
     * Zero N+1 queries.
     *
     * @param array $contextIds
     * @param string $locale
     * @return array
     */
    public function getOjsSubmissionsBatchForSync($contextIds, $locale = 'en_US')
    {
        $this->ensureSchema();

        $cleanContextIds = array_values(array_unique(array_map('intval', (array) $contextIds)));
        if (empty($cleanContextIds)) {
            return ['submissions' => []];
        }

        $submissionRows = Capsule::table('submissions as s')
            ->select(['s.submission_id', 's.context_id', 's.current_publication_id', 's.date_submitted', 's.date_last_activity', 's.last_modified', 's.stage_id', 's.status', 's.submission_progress', 's.locale as submission_locale'])
            ->whereIn('s.context_id', $cleanContextIds)
            ->where('s.submission_progress', '=', 0)
            ->orderBy('s.submission_id', 'asc')
            ->get();

        if ($submissionRows->isEmpty()) {
            return [
                'submissions' => [],
                'publications' => [],
                'titles' => [],
                'dois' => [],
                'issueIds' => [],
                'authors' => [],
                'reviewRounds' => [],
                'reviewAssignments' => [],
                'editDecisions' => [],
                'erTargetIssues' => [],
                'loaMap' => [],
            ];
        }

        $submissionIds = [];
        $publicationIds = [];
        foreach ($submissionRows as $row) {
            $submissionIds[] = (int) $row->submission_id;
            if (!empty($row->current_publication_id)) {
                $publicationIds[] = (int) $row->current_publication_id;
            }
        }

        // 1. Publications and publication_settings (title, fullTitle, issueId, pub-id::doi)
        $publicationsBySub = [];
        $titlesBySub = [];
        $doisBySub = [];
        $issueIdsBySub = [];
        $pubToSub = [];

        if (!empty($publicationIds)) {
            $pubRows = Capsule::table('publications')->whereIn('publication_id', $publicationIds)->get();

            foreach ($pubRows as $p) {
                $pubId = (int) $p->publication_id;
                $subId = (int) $p->submission_id;
                $pubToSub[$pubId] = $subId;
                $publicationsBySub[$subId] = $p;
            }

            $pubSettings = Capsule::table('publication_settings')
                ->whereIn('publication_id', $publicationIds)
                ->whereIn('setting_name', ['title', 'fullTitle', 'issueId', 'pub-id::doi'])
                ->get();

            $rawTitles = [];
            foreach ($pubSettings as $ps) {
                $pubId = (int) $ps->publication_id;
                if (!isset($pubToSub[$pubId])) {
                    continue;
                }
                $subId = $pubToSub[$pubId];
                $sName = (string) $ps->setting_name;
                if ($sName === 'issueId' && !empty($ps->setting_value)) {
                    $issueIdsBySub[$subId] = (int) $ps->setting_value;
                } elseif ($sName === 'pub-id::doi' && !empty($ps->setting_value)) {
                    $doisBySub[$subId] = trim((string) $ps->setting_value);
                } else {
                    $rawTitles[$subId][$sName][(string) $ps->locale] = $ps->setting_value;
                }
            }

            foreach ($submissionRows as $row) {
                $subId = (int) $row->submission_id;
                $subLoc = $row->submission_locale ? (string) $row->submission_locale : $locale;
                $fullTitleMap = isset($rawTitles[$subId]['fullTitle']) ? $rawTitles[$subId]['fullTitle'] : [];
                $titleMap = isset($rawTitles[$subId]['title']) ? $rawTitles[$subId]['title'] : [];
                $title = $this->pickLocalizedValue($fullTitleMap, $locale, $subLoc, '');
                if ($title === '') {
                    $title = $this->pickLocalizedValue($titleMap, $locale, $subLoc, '');
                }
                $titlesBySub[$subId] = trim(strip_tags((string) $title));
            }

            // 2. Authors and primary correspondent email
            $authorRows = Capsule::table('authors')->whereIn('publication_id', $publicationIds)->orderBy('seq', 'asc')->orderBy('author_id', 'asc')->get();

            $authorIds = [];
            foreach ($authorRows as $ar) {
                $authorIds[] = (int) $ar->author_id;
            }

            $authorSettingsByAuthor = [];
            if (!empty($authorIds)) {
                $aSetRows = Capsule::table('author_settings')
                    ->whereIn('author_id', $authorIds)
                    ->whereIn('setting_name', ['givenName', 'familyName', 'preferredPublicName', 'affiliation'])
                    ->get();
                foreach ($aSetRows as $asr) {
                    $aid = (int) $asr->author_id;
                    $authorSettingsByAuthor[$aid][$asr->setting_name][(string) $asr->locale] = $asr->setting_value;
                }
            }

            $authorsBySub = [];
            foreach ($authorRows as $ar) {
                $pubId = (int) $ar->publication_id;
                if (!isset($pubToSub[$pubId])) {
                    continue;
                }
                $subId = $pubToSub[$pubId];
                $aid = (int) $ar->author_id;
                $aSet = isset($authorSettingsByAuthor[$aid]) ? $authorSettingsByAuthor[$aid] : [];
                $given = trim($this->pickLocalizedValue(isset($aSet['givenName']) ? $aSet['givenName'] : [], $locale, 'en_US', ''));
                $family = trim($this->pickLocalizedValue(isset($aSet['familyName']) ? $aSet['familyName'] : [], $locale, 'en_US', ''));
                $fullName = trim($given . ' ' . $family);
                if ($fullName === '') {
                    $fullName = trim($this->pickLocalizedValue(isset($aSet['preferredPublicName']) ? $aSet['preferredPublicName'] : [], $locale, 'en_US', ''));
                }
                $affil = trim($this->pickLocalizedValue(isset($aSet['affiliation']) ? $aSet['affiliation'] : [], $locale, 'en_US', ''));
                $authorsBySub[$subId][] = [
                    'name' => $fullName,
                    'email' => (string) $ar->email,
                    'affiliation' => $affil,
                ];
            }
        } else {
            $authorsBySub = [];
        }

        // 3. Review Rounds
        $roundRows = Capsule::table('review_rounds')->whereIn('submission_id', $submissionIds)->orderBy('stage_id', 'asc')->orderBy('round', 'asc')->get();
        $reviewRoundsBySub = [];
        foreach ($roundRows as $rr) {
            $reviewRoundsBySub[(int) $rr->submission_id][] = $rr;
        }

        // 4. Review Assignments
        $raRows = Capsule::table('review_assignments')->whereIn('submission_id', $submissionIds)->orderBy('round', 'asc')->orderBy('review_id', 'asc')->get();
        $reviewAssignmentsBySub = [];
        foreach ($raRows as $ra) {
            $reviewAssignmentsBySub[(int) $ra->submission_id][] = $ra;
        }

        // 5. Edit Decisions
        $edRows = Capsule::table('edit_decisions')->whereIn('submission_id', $submissionIds)->orderBy('date_decided', 'asc')->orderBy('edit_decision_id', 'asc')->get();
        $editDecisionsBySub = [];
        foreach ($edRows as $ed) {
            $editDecisionsBySub[(int) $ed->submission_id][] = $ed;
        }

        // 6. Editorial Reminder target issues integration (if installed)
        $erTargetIssuesBySub = [];
        try {
            if (Capsule::schema()->hasTable('editorial_reminder_target_issues')) {
                $erRows = Capsule::table('editorial_reminder_target_issues')->whereIn('submission_id', $submissionIds)->get();
                foreach ($erRows as $er) {
                    $erTargetIssuesBySub[(int) $er->submission_id] = $er;
                }
            }
        } catch (Exception $e) {
        }

        // 7. Existing LOA records in mjcp_loa_files
        $loaRows = Capsule::table('mjcp_loa_files')->whereIn('submission_id', $submissionIds)->where('is_current', '=', 1)->get();
        $loaMap = [];
        foreach ($loaRows as $lr) {
            $loaMap[(int) $lr->submission_id] = $lr;
        }

        return [
            'submissions' => $submissionRows,
            'publications' => $publicationsBySub,
            'titles' => $titlesBySub,
            'dois' => $doisBySub,
            'issueIds' => $issueIdsBySub,
            'authors' => $authorsBySub,
            'reviewRounds' => $reviewRoundsBySub,
            'reviewAssignments' => $reviewAssignmentsBySub,
            'editDecisions' => $editDecisionsBySub,
            'erTargetIssues' => $erTargetIssuesBySub,
            'loaMap' => $loaMap,
        ];
    }

    /**
     * Fetch all issues across journals along with target dates and configured quotas.
     * Integrates with `editorial_reminder_issue_targets` when available.
     *
     * @param array|int $contextIds
     * @param string $locale
     * @return array Map of issueId => issue metadata
     */
    public function getIssuesMap($contextIds, $locale = 'en_US')
    {
        $this->ensureSchema();

        $cleanContextIds = is_array($contextIds) ? array_map('intval', $contextIds) : [(int) $contextIds];
        if (empty($cleanContextIds)) {
            return [];
        }

        $issueRows = Capsule::table('issues')->whereIn('journal_id', $cleanContextIds)->orderBy('published', 'asc')->orderBy('year', 'asc')->orderBy('volume', 'asc')->orderBy('number', 'asc')->orderBy('issue_id', 'asc')->get();

        if ($issueRows->isEmpty()) {
            return [];
        }

        $issueIds = [];
        foreach ($issueRows as $ir) {
            $issueIds[] = (int) $ir->issue_id;
        }

        $settingRows = Capsule::table('issue_settings')->whereIn('issue_id', $issueIds)->where('setting_name', '=', 'title')->get();

        $titlesByIssue = [];
        foreach ($settingRows as $sr) {
            $titlesByIssue[(int) $sr->issue_id][(string) $sr->locale] = $sr->setting_value;
        }

        // Check mjcp_issue_quotas
        $quotaRows = Capsule::table('mjcp_issue_quotas')->whereIn('context_id', $cleanContextIds)->get();
        $quotasByCtxIssue = [];
        foreach ($quotaRows as $qr) {
            $quotasByCtxIssue[(int) $qr->context_id][(int) $qr->issue_id] = $qr;
        }

        // Check editorial_reminder_issue_targets if present
        $erTargetsByIssue = [];
        try {
            if (Capsule::schema()->hasTable('editorial_reminder_issue_targets')) {
                $erRows = Capsule::table('editorial_reminder_issue_targets')->whereIn('issue_id', $issueIds)->get();
                foreach ($erRows as $er) {
                    $erTargetsByIssue[(int) $er->issue_id] = substr((string) $er->target_date, 0, 10);
                }
            }
        } catch (Exception $e) {
        }

        $issues = [];
        foreach ($issueRows as $ir) {
            $issueId = (int) $ir->issue_id;
            $ctxId = (int) $ir->journal_id;
            $titleMap = isset($titlesByIssue[$issueId]) ? $titlesByIssue[$issueId] : [];
            $title = trim($this->pickLocalizedValue($titleMap, $locale, 'en_US', ''));

            $volParts = [];
            if (!empty($ir->volume)) {
                $volParts[] = $ir->volume;
            }
            if ($ir->number !== null && $ir->number !== '') {
                $volParts[] = $ir->number;
            }
            $compactNumber = !empty($volParts) ? implode('.', $volParts) : '#' . $issueId;

            $fullLabel = '';
            if (!empty($ir->volume)) {
                $fullLabel .= 'Vol ' . $ir->volume;
            }
            if ($ir->number !== null && $ir->number !== '') {
                $fullLabel .= ($fullLabel !== '' ? ' ' : '') . 'No ' . $ir->number;
            }
            if (!empty($ir->year)) {
                $fullLabel .= ($fullLabel !== '' ? ' ' : '') . '(' . $ir->year . ')';
            }
            if ($title !== '') {
                $fullLabel .= ($fullLabel !== '' ? ': ' : '') . $title;
            }
            if ($fullLabel === '') {
                $fullLabel = 'Issue #' . $issueId;
            }

            $defaultTargetDate = $this->inferIssueTargetDate($ir, $title);
            $customQuotaRow = isset($quotasByCtxIssue[$ctxId][$issueId]) ? $quotasByCtxIssue[$ctxId][$issueId] : (isset($quotasByCtxIssue[$ctxId][0]) ? $quotasByCtxIssue[$ctxId][0] : null);

            $targetDate = $customQuotaRow && !empty($customQuotaRow->target_date) ? substr((string) $customQuotaRow->target_date, 0, 10) : (isset($erTargetsByIssue[$issueId]) ? $erTargetsByIssue[$issueId] : $defaultTargetDate);

            $quota = $customQuotaRow ? (int) $customQuotaRow->quota : 10;

            $issues[$issueId] = [
                'id' => $issueId,
                'contextId' => $ctxId,
                'volume' => $ir->volume,
                'number' => $ir->number,
                'year' => $ir->year,
                'published' => (int) $ir->published,
                'current' => (int) $ir->current,
                'title' => $title,
                'compactNumber' => $compactNumber,
                'identification' => $fullLabel,
                'targetDate' => $targetDate,
                'quota' => $quota,
            ];
        }

        return $issues;
    }

    /**
     * Infer default target publication date as the last day of the issue's month.
     * Uses localized month names from OJS locale files (`mjcp.month.full.*` / `mjcp.month.short.*`).
     *
     * @param object $issueRow
     * @param string $title
     * @return string 'Y-m-d'
     */
    public function inferIssueTargetDate($issueRow, $title = '')
    {
        $year = !empty($issueRow->year) ? (int) $issueRow->year : (int) date('Y');
        if ($year < 1970 || $year > 2100) {
            $year = (int) date('Y');
        }

        $lowerTitle = strtolower((string) $title);
        $detectedMonth = null;

        if ($lowerTitle !== '') {
            $localesToCheck = ['en_US', 'id_ID'];
            for ($m = 1; $m <= 12 && $detectedMonth === null; $m++) {
                $candidates = [];
                foreach ($localesToCheck as $loc) {
                    $full = strtolower((string) __('mjcp.month.full.' . $m, [], $loc));
                    $short = strtolower((string) __('mjcp.month.short.' . $m, [], $loc));
                    if (strpos($full, '##') === false && $full !== '') {
                        $candidates[] = $full;
                    }
                    if (strpos($short, '##') === false && $short !== '') {
                        $candidates[] = $short;
                    }
                }
                foreach (array_unique($candidates) as $kw) {
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $lowerTitle)) {
                        $detectedMonth = $m;
                        break;
                    }
                }
            }
        }

        if ($detectedMonth === null && !empty($issueRow->date_published)) {
            $ts = strtotime((string) $issueRow->date_published);
            if ($ts !== false) {
                return date('Y-m-t', $ts);
            }
        }

        if ($detectedMonth === null) {
            $detectedMonth = (int) date('n');
        }

        $firstOfMonth = sprintf('%04d-%02d-01', $year, $detectedMonth);
        $ts = strtotime($firstOfMonth);
        return $ts !== false ? date('Y-m-t', $ts) : date('Y-m-t');
    }

    /**
     * Upsert a synchronized article summary record into `mjcp_article_data`
     * and ensure a default payment record (`unpaid`) exists in `mjcp_payments`.
     *
     * @param array $row
     * @return void
     */
    public function upsertArticleData($row)
    {
        $this->ensureSchema();
        $subId = (int) $row['submission_id'];
        $ctxId = (int) $row['context_id'];
        $now = date('Y-m-d H:i:s');

        $existing = Capsule::table('mjcp_article_data')->where('submission_id', '=', $subId)->first();

        if ($existing) {
            Capsule::table('mjcp_article_data')->where('submission_id', '=', $subId)->update($row);
        } else {
            Capsule::table('mjcp_article_data')->insert($row);
        }

        // Ensure default payment record exists with status 'unpaid'
        $paymentExists = Capsule::table('mjcp_payments')->where('submission_id', '=', $subId)->exists();

        if (!$paymentExists) {
            Capsule::table('mjcp_payments')->insert([
                'submission_id' => $subId,
                'context_id' => $ctxId,
                'payment_status' => 'unpaid',
                'amount' => 0,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Get stage thresholds for a context (falling back to global context_id = 0).
     *
     * @param int $contextId
     * @return array
     */
    public function getStageThresholds($contextId = 0)
    {
        $this->ensureSchema();

        $row = null;
        if ((int) $contextId > 0) {
            $row = Capsule::table('mjcp_stage_thresholds')->where('context_id', '=', (int) $contextId)->first();
        }
        if (!$row) {
            $row = Capsule::table('mjcp_stage_thresholds')->where('context_id', '=', 0)->first();
        }

        $round1Limit = $row ? max(14, min(21, (int) $row->round1_limit_days)) : 21;
        $round2Limit = $row ? max(1, (int) $row->round2_limit_days) : 7;
        $round1Warn = $row ? max(1, (int) $row->round1_warning_days) : 3;
        $round2Warn = $row ? max(1, (int) $row->round2_warning_days) : 2;
        $copyeditingWarn = $row ? max(1, (int) $row->copyediting_warning_days) : 10;

        return [
            'round1Limit' => $round1Limit,
            'round2Limit' => $round2Limit,
            'round1Warning' => $round1Warn,
            'round2Warning' => $round2Warn,
            'copyeditingWarning' => $copyeditingWarn,
        ];
    }

    /**
     * Save stage thresholds and sync with Editorial Reminder Dashboard plugin_settings if installed.
     *
     * @param int $contextId
     * @param array $data
     * @param int $userId
     * @return void
     */
    public function saveStageThresholds($contextId, $data, $userId)
    {
        $this->ensureSchema();
        $ctxId = (int) $contextId;
        $now = date('Y-m-d H:i:s');

        $round1Limit = max(14, min(21, (int) $data['round1Limit']));
        $round2Limit = max(1, (int) $data['round2Limit']);
        $round1Warn = isset($data['round1Warning']) ? max(1, (int) $data['round1Warning']) : 3;
        $round2Warn = isset($data['round2Warning']) ? max(1, (int) $data['round2Warning']) : 2;
        $ceWarn = max(1, (int) $data['copyeditingWarning']);

        $existing = Capsule::table('mjcp_stage_thresholds')->where('context_id', '=', $ctxId)->first();

        $payload = [
            'context_id' => $ctxId,
            'round1_limit_days' => $round1Limit,
            'round2_limit_days' => $round2Limit,
            'round1_warning_days' => $round1Warn,
            'round2_warning_days' => $round2Warn,
            'copyediting_warning_days' => $ceWarn,
            'updated_by' => (int) $userId,
            'updated_at' => $now,
        ];

        if ($existing) {
            Capsule::table('mjcp_stage_thresholds')->where('context_id', '=', $ctxId)->update($payload);
        } else {
            Capsule::table('mjcp_stage_thresholds')->insert($payload);
        }

        // Phase 4 integration: share threshold settings with Editorial Reminder Dashboard if present
        try {
            $erSettings = [
                'round1Limit' => ['val' => $round1Limit, 'type' => 'int'],
                'round2Limit' => ['val' => $round2Limit, 'type' => 'int'],
                'round1Warning' => ['val' => $round1Warn, 'type' => 'int'],
                'round2Warning' => ['val' => $round2Warn, 'type' => 'int'],
                'copyeditingWarning' => ['val' => $ceWarn, 'type' => 'int'],
            ];
            foreach ($erSettings as $sName => $sMeta) {
                $existsEr = Capsule::table('plugin_settings')->where('plugin_name', '=', 'editorialreminderdashboardplugin')->where('context_id', '=', $ctxId)->where('setting_name', '=', $sName)->exists();
                if ($existsEr) {
                    Capsule::table('plugin_settings')
                        ->where('plugin_name', '=', 'editorialreminderdashboardplugin')
                        ->where('context_id', '=', $ctxId)
                        ->where('setting_name', '=', $sName)
                        ->update(['setting_value' => (string) $sMeta['val'], 'setting_type' => $sMeta['type']]);
                }
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Get combined holidays from `mjcp_holidays` and `editorial_reminder_holidays` (if installed).
     *
     * @param int $contextId
     * @return array
     */
    public function getHolidays($contextId = 0)
    {
        $this->ensureSchema();
        $ctxList = (int) $contextId > 0 ? [0, (int) $contextId] : [0];

        $rows = Capsule::table('mjcp_holidays')->whereIn('context_id', $ctxList)->orderBy('holiday_date', 'asc')->get();

        $holidays = [];
        $seenDates = [];
        foreach ($rows as $r) {
            $ymd = substr((string) $r->holiday_date, 0, 10);
            $seenDates[$ymd] = true;
            $holidays[] = [
                'id' => (int) $r->holiday_id,
                'contextId' => (int) $r->context_id,
                'date' => $ymd,
                'description' => (string) $r->description,
            ];
        }

        // Integration with Editorial Reminder Dashboard holidays
        try {
            if (Capsule::schema()->hasTable('editorial_reminder_holidays')) {
                $erRows = Capsule::table('editorial_reminder_holidays')->whereIn('context_id', $ctxList)->orderBy('holiday_date', 'asc')->get();
                foreach ($erRows as $er) {
                    $ymd = substr((string) $er->holiday_date, 0, 10);
                    if (!isset($seenDates[$ymd])) {
                        $seenDates[$ymd] = true;
                        $holidays[] = [
                            'id' => 0,
                            'contextId' => (int) $er->context_id,
                            'date' => $ymd,
                            'description' => (string) $er->description,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
        }

        return $holidays;
    }

    /**
     * Add or update a holiday in `mjcp_holidays` (and sync to `editorial_reminder_holidays` if installed).
     *
     * @param int $contextId
     * @param string $holidayDate 'Y-m-d'
     * @param string $description
     * @param int $userId
     * @return int
     */
    public function addHoliday($contextId, $holidayDate, $description, $userId)
    {
        $this->ensureSchema();
        $ctxId = (int) $contextId;
        $ymd = substr(trim((string) $holidayDate), 0, 10);
        $desc = trim((string) $description);
        $now = date('Y-m-d H:i:s');

        $existing = Capsule::table('mjcp_holidays')->where('context_id', '=', $ctxId)->where('holiday_date', '=', $ymd)->first();

        if ($existing) {
            Capsule::table('mjcp_holidays')
                ->where('holiday_id', '=', (int) $existing->holiday_id)
                ->update(['description' => $desc]);
            $holidayId = (int) $existing->holiday_id;
        } else {
            $holidayId = (int) Capsule::table('mjcp_holidays')->insertGetId(
                [
                    'context_id' => $ctxId,
                    'holiday_date' => $ymd,
                    'description' => $desc,
                    'created_by' => (int) $userId,
                    'created_at' => $now,
                ],
                'holiday_id',
            );
        }

        // Sync with Editorial Reminder Dashboard holidays table if present
        try {
            if (Capsule::schema()->hasTable('editorial_reminder_holidays')) {
                $erExisting = Capsule::table('editorial_reminder_holidays')->where('context_id', '=', $ctxId)->where('holiday_date', '=', $ymd)->first();
                if (!$erExisting) {
                    Capsule::table('editorial_reminder_holidays')->insert([
                        'context_id' => $ctxId,
                        'holiday_date' => $ymd,
                        'description' => $desc,
                        'created_at' => $now,
                    ]);
                }
            }
        } catch (Exception $e) {
        }

        return $holidayId;
    }

    /**
     * Delete a holiday by ID.
     *
     * @param int $holidayId
     * @return bool
     */
    public function deleteHoliday($holidayId)
    {
        $this->ensureSchema();
        return (bool) Capsule::table('mjcp_holidays')->where('holiday_id', '=', (int) $holidayId)->delete();
    }

    /**
     * Save default issue quota for a journal (issue_id = 0) or a specific issue.
     *
     * @param int $contextId
     * @param int $issueId
     * @param int $quota
     * @param string|null $targetDate
     * @param int $userId
     * @return void
     */
    public function saveIssueQuota($contextId, $issueId, $quota, $targetDate, $userId)
    {
        $this->ensureSchema();
        $ctxId = (int) $contextId;
        $issId = (int) $issueId;
        $qVal = max(1, (int) $quota);
        $now = date('Y-m-d H:i:s');

        $existing = Capsule::table('mjcp_issue_quotas')->where('context_id', '=', $ctxId)->where('issue_id', '=', $issId)->first();

        $payload = [
            'context_id' => $ctxId,
            'issue_id' => $issId,
            'quota' => $qVal,
            'target_date' => $targetDate ? substr((string) $targetDate, 0, 10) : null,
            'updated_by' => (int) $userId,
            'updated_at' => $now,
        ];

        if ($existing) {
            Capsule::table('mjcp_issue_quotas')->where('quota_id', '=', (int) $existing->quota_id)->update($payload);
        } else {
            Capsule::table('mjcp_issue_quotas')->insert($payload);
        }
    }

    /**
     * Get default journal quotas map (contextId => quota).
     *
     * @return array
     */
    public function getJournalDefaultQuotas()
    {
        $this->ensureSchema();
        $rows = Capsule::table('mjcp_issue_quotas')->where('issue_id', '=', 0)->get();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->context_id] = (int) $r->quota;
        }
        return $map;
    }

    /**
     * Write a synchronization log record in `mjcp_sync_logs`.
     *
     * @param int $contextId
     * @param string $status 'connected'|'pending'|'failed'
     * @param string $mode 'scheduled'|'manual'|'auto'
     * @param int $articlesProcessed
     * @param int $activeCount
     * @param string|null $errorMessage
     * @param int|null $triggeredBy
     * @param string|null $startedAt
     * @return void
     */
    public function logSync($contextId, $status, $mode, $articlesProcessed, $activeCount, $errorMessage = null, $triggeredBy = null, $startedAt = null)
    {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');
        Capsule::table('mjcp_sync_logs')->insert([
            'context_id' => (int) $contextId,
            'sync_status' => strtolower((string) $status),
            'sync_mode' => (string) $mode,
            'articles_processed' => (int) $articlesProcessed,
            'active_count' => (int) $activeCount,
            'error_message' => $errorMessage,
            'triggered_by' => $triggeredBy ? (int) $triggeredBy : null,
            'started_at' => $startedAt ? $startedAt : $now,
            'finished_at' => $now,
        ]);
    }

    /**
     * Get latest synchronization log per journal (and overall context_id = 0).
     *
     * @return array
     */
    public function getLatestSyncLogsByJournal()
    {
        $this->ensureSchema();
        $rows = Capsule::table('mjcp_sync_logs')->orderBy('sync_id', 'desc')->limit(300)->get();

        $latest = [];
        foreach ($rows as $r) {
            $cid = (int) $r->context_id;
            if (!isset($latest[$cid])) {
                $latest[$cid] = $r;
            }
        }
        return $latest;
    }

    /**
     * Record an entry in `mjcp_audit_logs`.
     *
     * @param int $contextId
     * @param int|null $submissionId
     * @param int|null $userId
     * @param string|null $username
     * @param string $action
     * @param string|null $entityType
     * @param mixed $oldValue
     * @param mixed $newValue
     * @param string|null $ipHash
     * @return void
     */
    public function logAudit($contextId, $submissionId, $userId, $username, $action, $entityType = null, $oldValue = null, $newValue = null, $ipHash = null)
    {
        $this->ensureSchema();
        try {
            Capsule::table('mjcp_audit_logs')->insert([
                'context_id' => (int) $contextId,
                'submission_id' => $submissionId ? (int) $submissionId : null,
                'user_id' => $userId ? (int) $userId : null,
                'username' => $username ? substr((string) $username, 0, 128) : null,
                'action' => substr((string) $action, 0, 128),
                'entity_type' => $entityType ? substr((string) $entityType, 0, 64) : null,
                'old_value' => is_scalar($oldValue) || $oldValue === null ? $oldValue : json_encode($oldValue),
                'new_value' => is_scalar($newValue) || $newValue === null ? $newValue : json_encode($newValue),
                'ip_address_hash' => $ipHash,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log('MultiJournalControlPanelDAO::logAudit error: ' . $e->getMessage());
        }
    }

    /**
     * Fetch recent general audit logs.
     *
     * @param int $limit
     * @return array
     */
    public function getRecentAuditLogs($limit = 30)
    {
        $this->ensureSchema();
        $rows = Capsule::table('mjcp_audit_logs')->orderBy('audit_id', 'desc')->limit((int) $limit)->get();

        $list = [];
        foreach ($rows as $r) {
            $list[] = (array) $r;
        }
        return $list;
    }

    /**
     * Check OJS user roles in a given context (or across all contexts when $contextId = 0).
     * Distinguishes actual Journal Managers from Journal Editors (`role_id = 16`).
     *
     * @param int $userId
     * @param int $contextId
     * @return array
     */
    public function getOjsUserRoleFlags($userId, $contextId = 0)
    {
        $uid = (int) $userId;
        $ctxId = (int) $contextId;

        $query = Capsule::table('user_user_groups as uug')
            ->join('user_groups as ug', 'uug.user_group_id', '=', 'ug.user_group_id')
            ->select(['ug.user_group_id', 'ug.role_id', 'ug.context_id'])
            ->where('uug.user_id', '=', $uid);

        if ($ctxId > 0) {
            $query->whereIn('ug.context_id', [0, $ctxId]);
        }

        $rows = $query->get();

        $isSiteAdmin = false;
        $managerGroupIds = [];

        foreach ($rows as $r) {
            $roleId = (int) $r->role_id;
            if ($roleId === 1) {
                $isSiteAdmin = true;
            } elseif ($roleId === 16) {
                $managerGroupIds[] = (int) $r->user_group_id;
            }
        }

        $isJournalManager = false;
        if (!empty($managerGroupIds)) {
            $jmGroups = $this->filterJournalManagerGroupIds($managerGroupIds);
            $isJournalManager = !empty($jmGroups);
        }

        return [
            'isSiteAdmin' => $isSiteAdmin,
            'isJournalManager' => $isJournalManager,
        ];
    }

    /**
     * Filter `role_id = 16` user group IDs to only actual Journal Manager groups.
     *
     * @param array $userGroupIds
     * @return array
     */
    public function filterJournalManagerGroupIds($userGroupIds)
    {
        $cleanIds = array_values(array_unique(array_map('intval', (array) $userGroupIds)));
        if (empty($cleanIds)) {
            return [];
        }

        $settingRows = Capsule::table('user_group_settings')
            ->whereIn('user_group_id', $cleanIds)
            ->whereIn('setting_name', ['nameLocaleKey', 'abbrevLocaleKey', 'name', 'abbrev'])
            ->get();

        $byGroup = [];
        foreach ($settingRows as $sr) {
            $ugId = (int) $sr->user_group_id;
            $sName = (string) $sr->setting_name;
            $sVal = trim((string) $sr->setting_value);
            if ($sName === 'nameLocaleKey' || $sName === 'abbrevLocaleKey') {
                $byGroup[$ugId][$sName] = $sVal;
            } else {
                $byGroup[$ugId][$sName][] = strtolower($sVal);
            }
        }

        $jmIds = [];
        foreach ($cleanIds as $ugId) {
            $g = isset($byGroup[$ugId]) ? $byGroup[$ugId] : [];
            $nameKey = isset($g['nameLocaleKey']) ? $g['nameLocaleKey'] : '';
            $abbrevKey = isset($g['abbrevLocaleKey']) ? $g['abbrevLocaleKey'] : '';

            if ($nameKey !== '' || $abbrevKey !== '') {
                if ($nameKey === 'default.groups.name.manager' || $abbrevKey === 'default.groups.abbrev.manager') {
                    $jmIds[] = $ugId;
                }
                continue;
            }

            $names = isset($g['name']) ? $g['name'] : [];
            $hasManager = false;
            $hasEditor = false;
            foreach ($names as $n) {
                if (strpos($n, 'editor') !== false) {
                    $hasEditor = true;
                }
                if (strpos($n, 'manager') !== false) {
                    $hasManager = true;
                }
            }
            if ($hasManager && !$hasEditor) {
                $jmIds[] = $ugId;
            }
        }

        return $jmIds;
    }

    /**
     * Get explicit plugin role assignments for a username from `mjcp_user_roles`.
     *
     * @param string $username
     * @return array List of role_key strings ('director', 'journal_manager', 'finance_admin', 'technical_admin')
     */
    public function getUserPluginRoles($username)
    {
        $this->ensureSchema();
        $uname = strtolower(trim((string) $username));
        if ($uname === '') {
            return [];
        }

        $rows = Capsule::table('mjcp_user_roles')
            ->whereRaw('LOWER(username) = ?', [$uname])
            ->get();

        $roles = [];
        foreach ($rows as $r) {
            $roles[] = (string) $r->role_key;
        }
        return array_values(array_unique($roles));
    }

    /**
     * Get all user role assignments in `mjcp_user_roles`.
     *
     * @return array
     */
    public function getAllUserRoleAssignments()
    {
        $this->ensureSchema();
        $rows = Capsule::table('mjcp_user_roles')->orderBy('role_key', 'asc')->orderBy('username', 'asc')->get();

        $list = [];
        foreach ($rows as $r) {
            $list[] = (array) $r;
        }
        return $list;
    }

    /**
     * Replace user role assignments for a given role_key from a comma-separated username list.
     *
     * @param string $roleKey
     * @param string $usernamesCsv
     * @param int $assignedBy
     * @return void
     */
    public function setRoleUsernames($roleKey, $usernamesCsv, $assignedBy)
    {
        $this->ensureSchema();
        $rKey = trim((string) $roleKey);
        $tokens = preg_split('/[\s,;]+/', strtolower(trim((string) $usernamesCsv)), -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_unique($tokens));
        $now = date('Y-m-d H:i:s');

        Capsule::table('mjcp_user_roles')->where('role_key', '=', $rKey)->delete();

        foreach ($tokens as $uname) {
            $userRow = Capsule::table('users')
                ->whereRaw('LOWER(username) = ?', [$uname])
                ->first();
            Capsule::table('mjcp_user_roles')->insert([
                'user_id' => $userRow ? (int) $userRow->user_id : null,
                'username' => $uname,
                'role_key' => $rKey,
                'context_id' => 0,
                'assigned_by' => (int) $assignedBy,
                'assigned_at' => $now,
            ]);
        }
    }

    /**
     * Check whether a username is registered in `mjcp_access_users` for payment access.
     *
     * @param string $username
     * @return bool
     */
    public function isUsernameInPaymentAccessList($username)
    {
        $this->ensureSchema();
        $uname = strtolower(trim((string) $username));
        if ($uname === '') {
            return false;
        }

        return Capsule::table('mjcp_access_users')
            ->whereRaw('LOWER(username) = ?', [$uname])
            ->where('is_active', '=', 1)
            ->exists();
    }

    /**
     * Get all active usernames in `mjcp_access_users`.
     *
     * @return array
     */
    public function getPaymentAccessUsernames()
    {
        $this->ensureSchema();
        $rows = Capsule::table('mjcp_access_users')->where('is_active', '=', 1)->orderBy('username', 'asc')->get();

        $list = [];
        foreach ($rows as $r) {
            $list[] = (string) $r->username;
        }
        return array_values(array_unique($list));
    }

    /**
     * Update the whitelist of payment access usernames in `mjcp_access_users`.
     *
     * @param string $usernamesCsv
     * @param int $updatedBy
     * @return void
     */
    public function setPaymentAccessUsernames($usernamesCsv, $updatedBy)
    {
        $this->ensureSchema();
        $tokens = preg_split('/[\s,;]+/', strtolower(trim((string) $usernamesCsv)), -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_unique($tokens));
        $now = date('Y-m-d H:i:s');

        Capsule::table('mjcp_access_users')->delete();

        foreach ($tokens as $uname) {
            $userRow = Capsule::table('users')
                ->whereRaw('LOWER(username) = ?', [$uname])
                ->first();
            Capsule::table('mjcp_access_users')->insert([
                'user_id' => $userRow ? (int) $userRow->user_id : null,
                'username' => $uname,
                'access_type' => 'payment_access',
                'is_active' => 1,
                'created_by' => (int) $updatedBy,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * Helper to pick a localized string with sensible fallbacks.
     *
     * @param array $map
     * @param string $preferredLocale
     * @param string $fallbackLocale
     * @param string $default
     * @return string
     */
    public function pickLocalizedValue($map, $preferredLocale, $fallbackLocale = 'en_US', $default = '')
    {
        if (!empty($map[$preferredLocale])) {
            return (string) $map[$preferredLocale];
        }
        if (!empty($map[$fallbackLocale])) {
            return (string) $map[$fallbackLocale];
        }
        if (!empty($map[''])) {
            return (string) $map[''];
        }
        foreach ((array) $map as $val) {
            if (!empty($val)) {
                return (string) $val;
            }
        }
        return (string) $default;
    }
}
