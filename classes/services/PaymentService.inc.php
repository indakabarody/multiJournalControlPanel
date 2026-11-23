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

require_once __DIR__ . '/DashboardService.inc.php';
require_once dirname(__DIR__) . '/dao/MultiJournalControlPanelDAO.inc.php';

use Illuminate\Database\Capsule\Manager as Capsule;

class PaymentService
{
    /** @var MultiJournalControlPanelPlugin */
    protected $_plugin;

    /** @var MultiJournalControlPanelDAO */
    protected $_dao;

    /** @var DashboardService */
    protected $_dashboardService;

    /** @var int Maximum payment proof file size in bytes (1 MB default) */
    const MAX_PROOF_SIZE_BYTES = 1048576;

    /** @var int Maximum LOA PDF file size in bytes (10 MB) */
    const MAX_LOA_SIZE_BYTES = 10485760;

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
        $this->_dashboardService = new DashboardService($plugin, $this->_dao);
    }

    /**
     * Fetch payment management list, summary counts, and journal/issue recap.
     *
     * Quick filters (`quickFilter`):
     * - `all`: All accepted/payment-tracked articles
     * - `form_unfilled`: Author form not filled yet
     * - `form_unverified`: Author form filled, payment not verified yet (`formFilled = true` AND `payment_status = 'unpaid'`)
     * - `loa_unpaid`: LOA uploaded but unpaid (`hasLoa = true` AND `payment_status = 'unpaid'`)
     * - `paid_not_copyediting`: Paid but not yet in copyediting (`payment_status = 'paid'` AND `stage_id < 4`)
     * - `paid_unpublished`: Paid but not yet published (`payment_status = 'paid'` AND `is_published = 0`)
     *
     * @param array $filters {contextId, quickFilter, search}
     * @param string $locale
     * @return array
     */
    public function getPaymentsDashboardData($filters = [], $locale = 'en_US')
    {
        $this->_dashboardService->getSyncService()->ensureSynchronized($locale);

        $filterCtxId = isset($filters['contextId']) && $filters['contextId'] !== 'all' && $filters['contextId'] !== '' ? (int) $filters['contextId'] : 0;
        $quickFilter = isset($filters['quickFilter']) && $filters['quickFilter'] !== '' ? (string) $filters['quickFilter'] : 'all';
        $search = isset($filters['search']) ? strtolower(trim((string) $filters['search'])) : '';

        // Query accepted articles (or articles that already have a payment or author form entry)
        $query = Capsule::table('mjcp_article_data as a')
            ->leftJoin('mjcp_payments as p', 'a.submission_id', '=', 'p.submission_id')
            ->leftJoin('mjcp_author_forms as f', 'a.submission_id', '=', 'f.submission_id')
            ->select(['a.submission_id', 'a.context_id', 'a.journal_name', 'a.journal_path', 'a.article_title', 'a.author_names', 'a.stage_id', 'a.pipeline_bucket', 'a.is_accepted', 'a.is_published', 'a.loa_status', 'a.target_issue_id', 'a.target_issue_label', 'p.payment_id', 'p.payment_status', 'p.amount', 'p.notes', 'p.proof_file_path as payment_proof_path', 'p.proof_original_name as payment_proof_name', 'p.updated_by as payment_updated_by', 'p.updated_at as payment_updated_at', 'f.author_form_id', 'f.full_name_authors as form_author_name', 'f.affiliation as form_affiliation', 'f.correspondent_email as form_email', 'f.whatsapp_number as form_whatsapp', 'f.transfer_proof_path as form_proof_path', 'f.transfer_proof_original_name as form_proof_name', 'f.submitted_at as form_submitted_at'])
            ->where(function ($q) {
                $q->where('a.is_accepted', '=', 1)->orWhereNotNull('f.author_form_id')->orWhere('p.payment_status', '=', 'paid')->orWhere('p.amount', '>', 0);
            })
            ->orderBy('a.submission_id', 'desc');

        if ($filterCtxId > 0) {
            $query->where('a.context_id', '=', $filterCtxId);
        }

        $rows = $query->get();

        $paidCount = 0;
        $unpaidCount = 0;
        $formUnfilledCount = 0;
        $totalAmount = 0;

        $recapByJournal = [];
        $recapByIssue = [];
        $items = [];

        foreach ($rows as $r) {
            $subId = (int) $r->submission_id;
            $ctxId = (int) $r->context_id;
            $payStatus = !empty($r->payment_status) ? strtolower((string) $r->payment_status) : 'unpaid';
            if ($payStatus !== 'paid') {
                $payStatus = 'unpaid';
            }
            $amount = (int) $r->amount;
            $notes = (string) $r->notes;
            $formFilled = !empty($r->author_form_id);
            $hasLoa = $r->loa_status === 'uploaded';
            $isPub = (int) $r->is_published === 1;

            // Summary counters (computed before quickFilter)
            if ($payStatus === 'paid') {
                $paidCount++;
                $totalAmount += $amount;
            } else {
                $unpaidCount++;
            }
            if (!$formFilled) {
                $formUnfilledCount++;
            }

            // Per-journal recap
            if (!isset($recapByJournal[$ctxId])) {
                $recapByJournal[$ctxId] = [
                    'contextId' => $ctxId,
                    'journalName' => (string) $r->journal_name,
                    'paidCount' => 0,
                    'unpaidCount' => 0,
                    'totalAmount' => 0,
                    'totalAmountFormatted' => $this->formatCurrency(0),
                ];
            }
            if ($payStatus === 'paid') {
                $recapByJournal[$ctxId]['paidCount']++;
                $recapByJournal[$ctxId]['totalAmount'] += $amount;
            } else {
                $recapByJournal[$ctxId]['unpaidCount']++;
            }

            // Per-issue recap
            $issueId = !empty($r->target_issue_id) ? (int) $r->target_issue_id : 0;
            $issueKey = $ctxId . ':' . $issueId;
            if (!isset($recapByIssue[$issueKey])) {
                $issueLabel = !empty($r->target_issue_label) ? (string) $r->journal_name . ' ' . (string) $r->target_issue_label : (string) $r->journal_name . ' - ' . __('mjcp.issue.unassignedIssue');
                $recapByIssue[$issueKey] = [
                    'contextId' => $ctxId,
                    'issueId' => $issueId,
                    'journalName' => (string) $r->journal_name,
                    'issueLabel' => $issueLabel,
                    'paidCount' => 0,
                    'unpaidCount' => 0,
                    'totalAmount' => 0,
                    'totalAmountFormatted' => $this->formatCurrency(0),
                ];
            }
            if ($payStatus === 'paid') {
                $recapByIssue[$issueKey]['paidCount']++;
                $recapByIssue[$issueKey]['totalAmount'] += $amount;
            } else {
                $recapByIssue[$issueKey]['unpaidCount']++;
            }

            // Apply quick filter
            if ($quickFilter === 'form_unfilled' && $formFilled) {
                continue;
            }
            if ($quickFilter === 'form_unverified' && (!$formFilled || $payStatus === 'paid')) {
                continue;
            }
            if ($quickFilter === 'loa_unpaid' && (!$hasLoa || $payStatus === 'paid')) {
                continue;
            }
            if ($quickFilter === 'paid_not_copyediting' && ($payStatus !== 'paid' || (int) $r->stage_id >= 4)) {
                continue;
            }
            if ($quickFilter === 'paid_unpublished' && ($payStatus !== 'paid' || $isPub)) {
                continue;
            }

            $displayAuthor = $formFilled && !empty($r->form_author_name) ? (string) $r->form_author_name : ((string) $r->author_names !== '' ? (string) $r->author_names : '-');

            $proofName = !empty($r->payment_proof_name) ? (string) $r->payment_proof_name : (!empty($r->form_proof_name) ? (string) $r->form_proof_name : '');
            $hasProof = !empty($r->payment_proof_path) || !empty($r->form_proof_path);

            if ($search !== '') {
                $haystack = strtolower($subId . ' ' . $r->journal_name . ' ' . $r->article_title . ' ' . $displayAuthor . ' ' . $notes);
                if (strpos($haystack, $search) === false) {
                    continue;
                }
            }

            $items[] = [
                'submissionId' => $subId,
                'contextId' => $ctxId,
                'journalName' => (string) $r->journal_name,
                'journalPath' => (string) $r->journal_path,
                'title' => (string) $r->article_title,
                'authorName' => $formFilled ? $displayAuthor : '-',
                'formFilled' => $formFilled,
                'formStatusLabel' => $formFilled ? __('mjcp.payment.formFilled') : __('mjcp.payment.formUnfilled'),
                'formBadgeType' => $formFilled ? 'success' : 'warning',
                'paymentStatus' => $payStatus,
                'paymentStatusLabel' => $payStatus === 'paid' ? __('mjcp.payment.status.paid') : __('mjcp.payment.status.unpaid'),
                'paymentBadgeType' => $payStatus === 'paid' ? 'success' : 'danger',
                'amount' => $amount,
                'amountFormatted' => $payStatus === 'paid' || $amount > 0 ? $this->formatCurrency($amount) : '-',
                'notes' => $notes !== '' ? $notes : '-',
                'rawNotes' => $notes,
                'hasProof' => $hasProof,
                'proofName' => $hasProof ? $proofName : '-',
                'hasLoa' => $hasLoa,
                'isPublished' => $isPub,
                'updatedAt' => (string) $r->payment_updated_at,
            ];
        }

        foreach ($recapByJournal as $cid => &$rec) {
            $rec['totalAmountFormatted'] = $this->formatCurrency($rec['totalAmount']);
        }
        unset($rec);

        foreach ($recapByIssue as $ik => &$irec) {
            $irec['totalAmountFormatted'] = $this->formatCurrency($irec['totalAmount']);
        }
        unset($irec);

        return [
            'summary' => [
                'paidCount' => $paidCount,
                'unpaidCount' => $unpaidCount,
                'formUnfilledCount' => $formUnfilledCount,
                'totalAmount' => $totalAmount,
                'totalAmountFormatted' => $this->formatCurrency($totalAmount),
            ],
            'items' => $items,
            'recapByJournal' => array_values($recapByJournal),
            'recapByIssue' => array_values($recapByIssue),
        ];
    }

    /**
     * Fetch complete detail for a single submission on the Author Details / Payment Verification & LOA page.
     *
     * @param int $submissionId
     * @param string $locale
     * @return array|null
     */
    public function getSubmissionPaymentAndLoaDetail($submissionId, $locale = 'en_US')
    {
        $this->_dashboardService->getSyncService()->ensureSynchronized($locale);
        $subId = (int) $submissionId;

        $article = Capsule::table('mjcp_article_data')->where('submission_id', '=', $subId)->first();
        if (!$article) {
            return null;
        }

        $payment = Capsule::table('mjcp_payments')->where('submission_id', '=', $subId)->first();
        $authorForm = Capsule::table('mjcp_author_forms')->where('submission_id', '=', $subId)->first();
        $currentLoa = Capsule::table('mjcp_loa_files')->where('submission_id', '=', $subId)->where('is_current', '=', 1)->orderBy('loa_id', 'desc')->first();
        $loaHistoryRows = Capsule::table('mjcp_loa_files')->where('submission_id', '=', $subId)->orderBy('loa_id', 'desc')->get();
        $paymentLogRows = Capsule::table('mjcp_payment_logs')->where('submission_id', '=', $subId)->orderBy('payment_log_id', 'desc')->get();
        $activeToken = Capsule::table('mjcp_author_tokens')->where('submission_id', '=', $subId)->where('is_active', '=', 1)->orderBy('token_id', 'desc')->first();

        $payStatus = $payment && !empty($payment->payment_status) ? strtolower((string) $payment->payment_status) : 'unpaid';
        if ($payStatus !== 'paid') {
            $payStatus = 'unpaid';
        }
        $amount = $payment ? (int) $payment->amount : 0;
        $notes = $payment ? (string) $payment->notes : '';

        $proofPath = $payment && !empty($payment->proof_file_path) ? (string) $payment->proof_file_path : ($authorForm && !empty($authorForm->transfer_proof_path) ? (string) $authorForm->transfer_proof_path : '');
        $proofName = $payment && !empty($payment->proof_original_name) ? (string) $payment->proof_original_name : ($authorForm && !empty($authorForm->transfer_proof_original_name) ? (string) $authorForm->transfer_proof_original_name : '');
        $proofSize = $payment && !empty($payment->proof_file_size) ? (int) $payment->proof_file_size : ($authorForm && !empty($authorForm->transfer_proof_size) ? (int) $authorForm->transfer_proof_size : 0);

        $paymentLogs = [];
        foreach ($paymentLogRows as $pl) {
            $oldSt = strtolower((string) $pl->old_status) === 'paid' ? 'paid' : 'unpaid';
            $newSt = strtolower((string) $pl->new_status) === 'paid' ? 'paid' : 'unpaid';
            $oldProof = !empty($pl->old_proof_file_path) ? (string) $pl->old_proof_file_path : '';
            $newProof = !empty($pl->new_proof_file_path) ? (string) $pl->new_proof_file_path : '';
            $proofChanged = $oldProof !== $newProof;
            $paymentLogs[] = [
                'id' => (int) $pl->payment_log_id,
                'oldStatus' => $oldSt,
                'oldStatusLabel' => $oldSt === 'paid' ? __('mjcp.payment.status.paid') : __('mjcp.payment.status.unpaid'),
                'newStatus' => $newSt,
                'newStatusLabel' => $newSt === 'paid' ? __('mjcp.payment.status.paid') : __('mjcp.payment.status.unpaid'),
                'oldAmount' => (int) $pl->old_amount,
                'oldAmountFormatted' => $this->formatCurrency((int) $pl->old_amount),
                'newAmount' => (int) $pl->new_amount,
                'newAmountFormatted' => $this->formatCurrency((int) $pl->new_amount),
                'oldNotes' => (string) $pl->old_notes,
                'newNotes' => (string) $pl->new_notes,
                'proofChanged' => $proofChanged,
                'hasOldProof' => $oldProof !== '' && is_readable($oldProof),
                'hasNewProof' => $newProof !== '' && is_readable($newProof),
                'oldProofName' => $oldProof !== '' ? basename($oldProof) : '',
                'newProofName' => $newProof !== '' ? basename($newProof) : '',
                'changedByUsername' => (string) $pl->changed_by_username,
                'changedAt' => (string) $pl->changed_at,
                'changedAtFormatted' => $this->_dashboardService->formatDateTimeShort($pl->changed_at, $locale),
            ];
        }

        $loaHistory = [];
        foreach ($loaHistoryRows as $lh) {
            $loaHistory[] = [
                'loaId' => (int) $lh->loa_id,
                'loaNumber' => (string) $lh->loa_number,
                'originalFilename' => (string) $lh->original_filename,
                'fileSizeKb' => round(((int) $lh->file_size) / 1024, 1),
                'isCurrent' => (int) $lh->is_current === 1,
                'uploadedByUsername' => (string) $lh->uploaded_by_username,
                'uploadedAt' => (string) $lh->uploaded_at,
                'uploadedAtFormatted' => $this->_dashboardService->formatDateTimeShort($lh->uploaded_at, $locale),
            ];
        }

        return [
            'submissionId' => $subId,
            'contextId' => (int) $article->context_id,
            'journalName' => (string) $article->journal_name,
            'journalPath' => (string) $article->journal_path,
            'title' => (string) $article->article_title,
            'authorNames' => (string) $article->author_names,
            'correspondentEmail' => (string) $article->correspondent_email,
            'isPublished' => (int) $article->is_published === 1,
            'doi' => (string) $article->doi,
            'publicationUrl' => (string) $article->publication_url,
            'payment' => [
                'status' => $payStatus,
                'statusLabel' => $payStatus === 'paid' ? __('mjcp.payment.status.paid') : __('mjcp.payment.status.unpaid'),
                'amount' => $amount,
                'amountFormattedInput' => number_format($amount, 0, ',', '.'),
                'amountFormatted' => $this->formatCurrency($amount),
                'notes' => $notes,
                'hasProof' => $proofPath !== '',
                'proofName' => $proofName,
                'proofSizeKb' => $proofSize > 0 ? round($proofSize / 1024) : 0,
            ],
            'authorForm' => $authorForm
                ? [
                    'filled' => true,
                    'fullNameAuthors' => (string) $authorForm->full_name_authors,
                    'affiliation' => (string) $authorForm->affiliation,
                    'correspondentEmail' => (string) $authorForm->correspondent_email,
                    'whatsappNumber' => (string) $authorForm->whatsapp_number,
                    'testimonial' => (string) $authorForm->testimonial,
                    'testimonialConsent' => (int) $authorForm->testimonial_consent === 1,
                    'submittedAt' => (string) $authorForm->submitted_at,
                    'submittedAtFormatted' => $this->_dashboardService->formatDateTimeShort($authorForm->submitted_at, $locale),
                ]
                : [
                    'filled' => false,
                    'fullNameAuthors' => (string) $article->author_names,
                    'affiliation' => '-',
                    'correspondentEmail' => (string) $article->correspondent_email,
                    'whatsappNumber' => '-',
                    'testimonial' => '-',
                    'testimonialConsent' => false,
                    'submittedAt' => '',
                    'submittedAtFormatted' => '-',
                ],
            'loa' => $currentLoa
                ? [
                    'exists' => true,
                    'loaId' => (int) $currentLoa->loa_id,
                    'loaNumber' => (string) $currentLoa->loa_number,
                    'originalFilename' => (string) $currentLoa->original_filename,
                    'fileSizeKb' => round(((int) $currentLoa->file_size) / 1024, 1),
                    'uploadedByUsername' => (string) $currentLoa->uploaded_by_username,
                    'uploadedAt' => (string) $currentLoa->uploaded_at,
                    'uploadedAtFormatted' => $this->_dashboardService->formatDateTimeShort($currentLoa->uploaded_at, $locale),
                ]
                : [
                    'exists' => false,
                    'loaId' => 0,
                    'loaNumber' => '',
                    'originalFilename' => '',
                    'fileSizeKb' => 0,
                    'uploadedByUsername' => '',
                    'uploadedAt' => '',
                    'uploadedAtFormatted' => '-',
                ],
            'loaHistory' => $loaHistory,
            'paymentLogs' => $paymentLogs,
            'activeToken' => $activeToken ? (string) $activeToken->token_display : '',
        ];
    }

    /**
     * Verify or update a payment record (Finance Admin only).
     * Enforces:
     * - Status in ('unpaid', 'paid')
     * - FREE articles: if notes is 'FREE' (case-insensitive), amount is 0, status is 'paid', and notes normalized to 'FREE'.
     * - Proof file upload optional, validated <= 1 MB and JPG/PNG/PDF MIME type; stored in private directory.
     * - Previous proof files are preserved in `mjcp_payment_logs`.
     * - Full audit trail in `mjcp_payment_logs` and `mjcp_audit_logs`.
     *
     * @param int $submissionId
     * @param string $status 'unpaid'|'paid'
     * @param string|int $rawAmount
     * @param string|null $rawNotes
     * @param array|null $uploadedFile $_FILES['proofFile'] entry if uploaded
     * @param User $user
     * @return array {ok: bool, message: string}
     */
    public function verifyPayment($submissionId, $status, $rawAmount, $rawNotes, $uploadedFile, $user)
    {
        $this->_dao->ensureSchema();
        $subId = (int) $submissionId;

        $article = Capsule::table('mjcp_article_data')->where('submission_id', '=', $subId)->first();
        if (!$article) {
            return ['ok' => false, 'message' => __('mjcp.payment.error.articleNotFound')];
        }

        $ctxId = (int) $article->context_id;
        $cleanStatus = $this->_normalizePaymentStatus($status);

        // Strip formatting separators from amount input (e.g. "1.500.000" -> 1500000)
        $digitsOnly = preg_replace('/[^0-9]/', '', (string) $rawAmount);
        $amount = $digitsOnly !== '' ? (int) $digitsOnly : 0;
        $notes = trim((string) $rawNotes);

        // FREE payment normalization
        if (strtoupper($notes) === 'FREE') {
            $notes = 'FREE';
            $amount = 0;
            $cleanStatus = 'paid';
        }

        $existing = Capsule::table('mjcp_payments')->where('submission_id', '=', $subId)->first();

        $oldStatus = $existing ? (string) $existing->payment_status : 'unpaid';
        $oldAmount = $existing ? (int) $existing->amount : 0;
        $oldNotes = $existing ? (string) $existing->notes : '';
        $oldProofPath = $existing ? (string) $existing->proof_file_path : null;

        $newProofPath = $oldProofPath;
        $newProofName = $existing ? (string) $existing->proof_original_name : null;
        $newProofMime = $existing ? (string) $existing->proof_mime_type : null;
        $newProofSize = $existing ? (int) $existing->proof_file_size : null;

        $hasProofUpload = $uploadedFile && !empty($uploadedFile['tmp_name']) && (is_uploaded_file($uploadedFile['tmp_name']) || (!empty($uploadedFile['_allowTestLocalFile']) && is_file($uploadedFile['tmp_name'])));

        if ($hasProofUpload) {
            $uploadResult = $this->storePrivateProofFile($subId, $ctxId, $uploadedFile);
            if (!$uploadResult['ok']) {
                return $uploadResult;
            }
            $newProofPath = $uploadResult['filePath'];
            $newProofName = $uploadResult['originalName'];
            $newProofMime = $uploadResult['mimeType'];
            $newProofSize = $uploadResult['fileSize'];
        }

        $now = date('Y-m-d H:i:s');
        $userId = (int) $user->getId();
        $username = (string) $user->getUsername();

        $payload = [
            'submission_id' => $subId,
            'context_id' => $ctxId,
            'payment_status' => $cleanStatus,
            'amount' => $amount,
            'notes' => $notes,
            'proof_file_path' => $newProofPath,
            'proof_original_name' => $newProofName,
            'proof_mime_type' => $newProofMime,
            'proof_file_size' => $newProofSize,
            'updated_by' => $userId,
            'updated_at' => $now,
        ];

        if ($existing) {
            $paymentId = (int) $existing->payment_id;
            Capsule::table('mjcp_payments')->where('payment_id', '=', $paymentId)->update($payload);
        } else {
            $payload['created_at'] = $now;
            $paymentId = (int) Capsule::table('mjcp_payments')->insertGetId($payload, 'payment_id');
        }

        // Record immutable payment history log in mjcp_payment_logs
        Capsule::table('mjcp_payment_logs')->insert([
            'payment_id' => $paymentId,
            'submission_id' => $subId,
            'context_id' => $ctxId,
            'old_status' => $oldStatus,
            'new_status' => $cleanStatus,
            'old_amount' => $oldAmount,
            'new_amount' => $amount,
            'old_notes' => $oldNotes,
            'new_notes' => $notes,
            'old_proof_file_path' => $oldProofPath,
            'new_proof_file_path' => $newProofPath,
            'changed_by' => $userId,
            'changed_by_username' => $username,
            'changed_at' => $now,
        ]);

        $this->_dao->logAudit($ctxId, $subId, $userId, $username, 'payment_verified', 'mjcp_payments', ['status' => $oldStatus, 'amount' => $oldAmount, 'notes' => $oldNotes], ['status' => $cleanStatus, 'amount' => $amount, 'notes' => $notes]);

        return [
            'ok' => true,
            'message' => __('mjcp.payment.savedSuccess'),
            'paymentStatus' => $cleanStatus,
            'amount' => $amount,
            'notes' => $notes,
        ];
    }

    /**
     * Validate and preview/execute Bulk Payment Import from CSV/XLSX rows.
     * Columns expected: submission_id (article code), payment_status (unpaid/paid), amount, notes.
     * Validates that `submission_id` exists and `amount` is numeric.
     *
     * @param array $rawRows Array of associative or indexed rows
     * @param bool $commit If true, saves valid rows when there are no validation errors
     * @param User $user
     * @return array {ok: bool, hasErrors: bool, previewRows: array, savedCount: int}
     */
    public function processBulkPaymentRows($rawRows, $commit, $user)
    {
        $this->_dao->ensureSchema();

        $previewRows = [];
        $hasErrors = false;
        $headerCodeLocalized = strtolower(trim(__('mjcp.table.code')));

        foreach ((array) $rawRows as $idx => $r) {
            $subIdRaw = isset($r['submission_id']) ? trim((string) $r['submission_id']) : (isset($r[0]) ? trim((string) $r[0]) : '');
            $statusRaw = isset($r['payment_status']) ? trim((string) $r['payment_status']) : (isset($r[1]) ? trim((string) $r[1]) : 'unpaid');
            $amountRaw = isset($r['amount']) ? trim((string) $r['amount']) : (isset($r[2]) ? trim((string) $r[2]) : '0');
            $notesRaw = isset($r['notes']) ? trim((string) $r['notes']) : (isset($r[3]) ? trim((string) $r[3]) : '');

            $subLower = strtolower($subIdRaw);
            if ($subIdRaw === '' || $subLower === 'submission_id' || $subLower === 'code' || $subLower === 'id' || ($headerCodeLocalized !== '' && $subLower === $headerCodeLocalized)) {
                continue;
            }

            $rowErrors = [];
            if (!ctype_digit($subIdRaw)) {
                $rowErrors[] = __('mjcp.payment.import.errorInvalidCode');
            } else {
                $articleExists = Capsule::table('mjcp_article_data')->where('submission_id', '=', (int) $subIdRaw)->exists();
                if (!$articleExists) {
                    $rowErrors[] = __('mjcp.payment.import.errorArticleNotFound');
                }
            }

            $currencyPrefix = trim(__('mjcp.currency.prefix'));
            $stripTokens = ['.', ',', ' ', 'IDR', 'idr', 'Rp', 'rp'];
            if ($currencyPrefix !== '') {
                $stripTokens[] = $currencyPrefix;
                $stripTokens[] = strtolower($currencyPrefix);
            }
            $cleanAmountStr = str_replace($stripTokens, '', $amountRaw);
            if ($cleanAmountStr !== '' && !ctype_digit($cleanAmountStr)) {
                $rowErrors[] = __('mjcp.payment.import.errorInvalidAmount');
            }

            $normStatus = $this->_normalizePaymentStatus($statusRaw);
            $normAmount = $cleanAmountStr !== '' && ctype_digit($cleanAmountStr) ? (int) $cleanAmountStr : 0;
            if (strtoupper($notesRaw) === 'FREE') {
                $notesRaw = 'FREE';
                $normAmount = 0;
                $normStatus = 'paid';
            }

            if (!empty($rowErrors)) {
                $hasErrors = true;
            }

            $previewRows[] = [
                'rowNum' => $idx + 1,
                'submissionId' => $subIdRaw,
                'paymentStatus' => $normStatus,
                'paymentStatusLabel' => $normStatus === 'paid' ? __('mjcp.payment.status.paid') : __('mjcp.payment.status.unpaid'),
                'amount' => $normAmount,
                'amountRaw' => $amountRaw,
                'notes' => $notesRaw,
                'isValid' => empty($rowErrors),
                'errorText' => implode('; ', $rowErrors),
            ];
        }

        $savedCount = 0;
        if ($commit && !$hasErrors && !empty($previewRows)) {
            foreach ($previewRows as $pr) {
                $res = $this->verifyPayment((int) $pr['submissionId'], $pr['paymentStatus'], $pr['amount'], $pr['notes'], null, $user);
                if (!empty($res['ok'])) {
                    $savedCount++;
                }
            }
        }

        return [
            'ok' => !$hasErrors,
            'hasErrors' => $hasErrors,
            'previewRows' => $previewRows,
            'savedCount' => $savedCount,
        ];
    }

    /**
     * Normalize payment status input (supports canonical 'paid'/'unpaid' and active/installed locale translations).
     *
     * @param string $rawStatus
     * @return string 'paid'|'unpaid'
     */
    protected function _normalizePaymentStatus($rawStatus)
    {
        $clean = strtolower(trim((string) $rawStatus));
        if ($clean === 'paid' || $clean === '1' || $clean === 'yes' || $clean === 'true') {
            return 'paid';
        }
        $paidCurrent = strtolower(trim(__('mjcp.payment.status.paid')));
        $paidId = strtolower(trim(__('mjcp.payment.status.paid', [], 'id_ID')));
        $paidEn = strtolower(trim(__('mjcp.payment.status.paid', [], 'en_US')));

        if ($clean !== '' && ($clean === $paidCurrent || $clean === $paidId || $clean === $paidEn)) {
            return 'paid';
        }
        return 'unpaid';
    }

    /**
     * Upload or replace an LOA PDF file and LOA number for a submission (Journal Manager only).
     * - Validates PDF format and MIME type (`application/pdf` + `%PDF-` magic header check).
     * - Stores file privately outside public web root.
     * - Keeps previous LOA files in `mjcp_loa_files` history with `is_current = 0`.
     * - Updates `mjcp_article_data.loa_status = 'uploaded'` and readiness (`copyediting_done === 1`).
     *
     * @param int $submissionId
     * @param string $loaNumber
     * @param array|null $uploadedFile $_FILES['loaFile']
     * @param User $user
     * @return array
     */
    public function uploadLoa($submissionId, $loaNumber, $uploadedFile, $user)
    {
        $this->_dao->ensureSchema();
        $subId = (int) $submissionId;
        $cleanLoaNum = trim((string) $loaNumber);

        if ($cleanLoaNum === '') {
            return ['ok' => false, 'message' => __('mjcp.loa.error.numberRequired')];
        }

        // Prevent duplicate active LOA numbers across different manuscripts
        $duplicateLoa = Capsule::table('mjcp_loa_files')
            ->where('loa_number', '=', $cleanLoaNum)
            ->where('is_current', '=', 1)
            ->where('submission_id', '!=', $subId)
            ->exists();
        if ($duplicateLoa) {
            return ['ok' => false, 'message' => __('mjcp.loa.error.duplicateNumber')];
        }

        $article = Capsule::table('mjcp_article_data')->where('submission_id', '=', $subId)->first();
        if (!$article) {
            return ['ok' => false, 'message' => __('mjcp.payment.error.articleNotFound')];
        }
        $ctxId = (int) $article->context_id;

        $currentLoa = Capsule::table('mjcp_loa_files')->where('submission_id', '=', $subId)->where('is_current', '=', 1)->first();

        $hasNewFile = $uploadedFile && !empty($uploadedFile['tmp_name']) && (is_uploaded_file($uploadedFile['tmp_name']) || (!empty($uploadedFile['_allowTestLocalFile']) && is_file($uploadedFile['tmp_name'])));

        if (!$hasNewFile && !$currentLoa) {
            return ['ok' => false, 'message' => __('mjcp.loa.error.fileRequired')];
        }

        $userId = (int) $user->getId();
        $username = (string) $user->getUsername();
        $now = date('Y-m-d H:i:s');

        if (!$hasNewFile && $currentLoa) {
            // Update LOA number on current record and log audit
            $oldNum = (string) $currentLoa->loa_number;
            Capsule::table('mjcp_loa_files')
                ->where('loa_id', '=', (int) $currentLoa->loa_id)
                ->update([
                    'loa_number' => $cleanLoaNum,
                    'uploaded_by' => $userId,
                    'uploaded_by_username' => $username,
                    'uploaded_at' => $now,
                ]);

            $this->_dao->logAudit($ctxId, $subId, $userId, $username, 'loa_number_updated', 'mjcp_loa_files', $oldNum, $cleanLoaNum);
            return ['ok' => true, 'message' => __('mjcp.loa.savedSuccess')];
        }

        $storeResult = $this->storePrivateLoaFile($subId, $ctxId, $uploadedFile);
        if (!$storeResult['ok']) {
            return $storeResult;
        }

        // Mark previous LOAs as historical (`is_current = 0`)
        Capsule::table('mjcp_loa_files')
            ->where('submission_id', '=', $subId)
            ->update(['is_current' => 0]);

        $loaId = (int) Capsule::table('mjcp_loa_files')->insertGetId(
            [
                'submission_id' => $subId,
                'context_id' => $ctxId,
                'loa_number' => $cleanLoaNum,
                'file_path' => $storeResult['filePath'],
                'original_filename' => $storeResult['originalName'],
                'mime_type' => 'application/pdf',
                'file_size' => $storeResult['fileSize'],
                'is_current' => 1,
                'uploaded_by' => $userId,
                'uploaded_by_username' => $username,
                'uploaded_at' => $now,
            ],
            'loa_id',
        );

        // Update article summary LOA status & readiness (ready only when LOA is uploaded AND copyediting is done)
        $isReady = (int) $article->copyediting_done === 1 ? 1 : 0;
        Capsule::table('mjcp_article_data')
            ->where('submission_id', '=', $subId)
            ->update([
                'loa_status' => 'uploaded',
                'is_ready_to_publish' => $isReady,
            ]);

        $this->_dao->logAudit($ctxId, $subId, $userId, $username, 'loa_uploaded', 'mjcp_loa_files', $currentLoa ? $currentLoa->original_filename : null, ['loaId' => $loaId, 'loaNumber' => $cleanLoaNum, 'file' => $storeResult['originalName']]);

        return [
            'ok' => true,
            'message' => __('mjcp.loa.savedSuccess'),
            'loaId' => $loaId,
        ];
    }

    /**
     * Look up an LOA by its LOA number for public LOA Validation.
     * Only returns valid result if the article's payment status is 'paid'.
     *
     * @param string $loaNumber
     * @param string $locale
     * @return array|null
     */
    public function validateLoaByNumber($loaNumber, $locale = 'en_US')
    {
        $this->_dao->ensureSchema();
        $cleanNum = trim((string) $loaNumber);
        if ($cleanNum === '') {
            return null;
        }

        $loa = Capsule::table('mjcp_loa_files')->where('loa_number', '=', $cleanNum)->where('is_current', '=', 1)->orderBy('loa_id', 'desc')->first();
        if (!$loa) {
            return null;
        }

        $subId = (int) $loa->submission_id;
        $payment = Capsule::table('mjcp_payments')->where('submission_id', '=', $subId)->first();
        if (!$payment || strtolower((string) $payment->payment_status) !== 'paid') {
            return null;
        }

        $article = Capsule::table('mjcp_article_data')->where('submission_id', '=', $subId)->first();
        $authorForm = Capsule::table('mjcp_author_forms')->where('submission_id', '=', $subId)->first();

        $authorNames = $authorForm && !empty($authorForm->full_name_authors) ? (string) $authorForm->full_name_authors : ($article ? (string) $article->author_names : '-');

        return [
            'isValid' => true,
            'loaNumber' => (string) $loa->loa_number,
            'submissionId' => $subId,
            'articleTitle' => $article ? (string) $article->article_title : '-',
            'authorNames' => $authorNames,
            'journalName' => $article ? (string) $article->journal_name : '-',
            'uploadedAt' => (string) $loa->uploaded_at,
            'uploadedAtFormatted' => $this->_dashboardService->formatDateWithYear($loa->uploaded_at, $locale),
        ];
    }

    /**
     * Generate a cryptographically secure author confirmation token for an accepted submission.
     * - Uses `random_bytes(16)` (32 hex chars).
     * - Stores SHA-256 `token_hash` in `mjcp_author_tokens` along with `token_display` so managers can copy/export the link.
     * - Deactivates any previous tokens for the same submission.
     * - Sets `expires_at` to `max(now + expirationDays, target_issue_date + 30 days)` so the link never expires before publication.
     *
     * @param int $submissionId
     * @param int $expirationDays Default 30
     * @param int|null $createdByUserId
     * @return array {ok: bool, token: string, expiresAt: string}
     */
    public function generateAuthorToken($submissionId, $expirationDays = 30, $createdByUserId = null)
    {
        $this->_dao->ensureSchema();
        $subId = (int) $submissionId;

        $article = Capsule::table('mjcp_article_data')->where('submission_id', '=', $subId)->first();
        if (!$article) {
            return ['ok' => false, 'message' => __('mjcp.payment.error.articleNotFound')];
        }

        $ctxId = (int) $article->context_id;
        $now = date('Y-m-d H:i:s');
        $days = max(1, (int) $expirationDays);
        $expiresTs = strtotime("+$days days");

        if (!empty($article->target_issue_date)) {
            $issuePlusGrace = strtotime((string) $article->target_issue_date . ' +30 days');
            if ($issuePlusGrace !== false && $issuePlusGrace > $expiresTs) {
                $expiresTs = $issuePlusGrace;
            }
        }
        $expiresAt = date('Y-m-d H:i:s', $expiresTs);

        // Deactivate prior active tokens for this submission
        Capsule::table('mjcp_author_tokens')
            ->where('submission_id', '=', $subId)
            ->where('is_active', '=', 1)
            ->update([
                'is_active' => 0,
                'revoked_at' => $now,
            ]);

        $rawToken = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $rawToken);

        Capsule::table('mjcp_author_tokens')->insert([
            'submission_id' => $subId,
            'context_id' => $ctxId,
            'token_hash' => $tokenHash,
            'token_display' => $rawToken,
            'is_active' => 1,
            'expires_at' => $expiresAt,
            'created_by' => $createdByUserId ? (int) $createdByUserId : null,
            'created_at' => $now,
        ]);

        $this->_dao->logAudit($ctxId, $subId, $createdByUserId, null, 'author_token_generated', 'mjcp_author_tokens', null, ['expiresAt' => $expiresAt]);

        return [
            'ok' => true,
            'submissionId' => $subId,
            'token' => $rawToken,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Deactivate (revoke) an author token for a submission.
     *
     * @param int $submissionId
     * @param int|null $userId
     * @return bool
     */
    public function revokeAuthorToken($submissionId, $userId = null)
    {
        $this->_dao->ensureSchema();
        $subId = (int) $submissionId;
        $now = date('Y-m-d H:i:s');

        $updated = Capsule::table('mjcp_author_tokens')
            ->where('submission_id', '=', $subId)
            ->where('is_active', '=', 1)
            ->update([
                'is_active' => 0,
                'revoked_at' => $now,
            ]);

        $this->_dao->logAudit(0, $subId, $userId, null, 'author_token_revoked', 'mjcp_author_tokens', 'active', 'revoked');
        return (bool) $updated;
    }

    /**
     * Fetch list of accepted submissions for the Author Form Links tab.
     *
     * @param array $filters {contextId, unfilledOnly, search}
     * @param PKPRequest|null $request
     * @param string $locale
     * @return array
     */
    public function getAuthorLinksList($filters = [], $request = null, $locale = 'en_US')
    {
        $this->_dashboardService->getSyncService()->ensureSynchronized($locale);

        $filterCtxId = isset($filters['contextId']) && $filters['contextId'] !== 'all' && $filters['contextId'] !== '' ? (int) $filters['contextId'] : 0;
        $unfilledOnly = !empty($filters['unfilledOnly']);

        $query = Capsule::table('mjcp_article_data as a')
            ->leftJoin('mjcp_author_forms as f', 'a.submission_id', '=', 'f.submission_id')
            ->leftJoin('mjcp_author_tokens as t', function ($join) {
                $join->on('a.submission_id', '=', 't.submission_id')->where('t.is_active', '=', 1);
            })
            ->select(['a.submission_id', 'a.context_id', 'a.journal_name', 'a.journal_path', 'a.article_title', 'a.loa_status', 'a.is_published', 'a.publication_url', 'f.author_form_id', 't.token_id', 't.token_display', 't.expires_at'])
            ->where('a.is_accepted', '=', 1)
            ->orderBy('a.submission_id', 'desc');

        if ($filterCtxId > 0) {
            $query->where('a.context_id', '=', $filterCtxId);
        }

        $rows = $query->get();
        $dispatcher = $request ? $request->getDispatcher() : null;
        $contextPath = $request && $request->getContext() ? $request->getContext()->getPath() : 'index';

        $unfilledTotal = 0;
        $items = [];
        $seenSubs = [];

        foreach ($rows as $r) {
            $subId = (int) $r->submission_id;
            if (isset($seenSubs[$subId])) {
                continue;
            }
            $seenSubs[$subId] = true;

            $formFilled = !empty($r->author_form_id);
            if (!$formFilled) {
                $unfilledTotal++;
            }
            if ($unfilledOnly && $formFilled) {
                continue;
            }

            $hasToken = !empty($r->token_id) && !empty($r->token_display);
            $tokenStr = $hasToken ? (string) $r->token_display : '';
            $shortTokenPath = $hasToken ? '.../confirm/' . substr($tokenStr, 0, 8) : __('mjcp.links.notCreated');

            $fullLinkUrl = '';
            $manageLoaUrl = '';
            if ($dispatcher) {
                if ($hasToken) {
                    $fullLinkUrl = $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'confirm', [$tokenStr]);
                }
                $manageLoaUrl = $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'multiJournalControlPanel', 'paymentDetail', [$subId]);
            }

            $hasLoa = $r->loa_status === 'uploaded';
            $isPub = (int) $r->is_published === 1;

            $items[] = [
                'submissionId' => $subId,
                'contextId' => (int) $r->context_id,
                'journalName' => (string) $r->journal_name,
                'title' => (string) $r->article_title,
                'formFilled' => $formFilled,
                'formStatusLabel' => $formFilled ? __('mjcp.payment.formFilled') : __('mjcp.payment.formUnfilled'),
                'formBadgeType' => $formFilled ? 'success' : 'warning',
                'hasLoa' => $hasLoa,
                'loaStatusLabel' => $hasLoa ? __('mjcp.loa.status.uploaded') : __('mjcp.loa.status.notUploaded'),
                'loaBadgeType' => $hasLoa ? 'success' : 'warning',
                'isPublished' => $isPub,
                'publicationStatusLabel' => $isPub ? __('mjcp.publication.status.published') : __('mjcp.publication.status.unpublished'),
                'hasToken' => $hasToken,
                'token' => $tokenStr,
                'shortLinkText' => $shortTokenPath,
                'fullLinkUrl' => $fullLinkUrl,
                'manageLoaUrl' => $manageLoaUrl,
                'expiresAt' => $hasToken ? (string) $r->expires_at : '',
                'expiresAtFormatted' => $hasToken && !empty($r->expires_at) ? $this->_dashboardService->formatDateWithYear($r->expires_at, $locale) : '-',
            ];
        }

        return [
            'unfilledCount' => $unfilledTotal,
            'items' => $items,
        ];
    }

    /**
     * Resolve an author confirmation token by SHA-256 hash and enforce expiration & publication rules.
     * - Revoked tokens (`is_active == 0`) return `isValid = false`, `reason = 'revoked'`.
     * - Published articles or already-submitted forms remain accessible so authors can retrieve LOA and publication links.
     * - Unfilled forms whose token timestamp has passed return `isValid = false`, `reason = 'expired'`.
     *
     * @param string $rawToken
     * @param string $locale
     * @return array
     */
    public function resolveAuthorToken($rawToken, $locale = 'en_US')
    {
        $this->_dao->ensureSchema();
        $cleanToken = trim((string) $rawToken);
        if ($cleanToken === '') {
            return ['isValid' => false, 'reason' => 'invalid'];
        }

        $tokenHash = hash('sha256', $cleanToken);
        $tokenRow = Capsule::table('mjcp_author_tokens')->where('token_hash', '=', $tokenHash)->first();

        if (!$tokenRow) {
            return ['isValid' => false, 'reason' => 'not_found'];
        }

        if ((int) $tokenRow->is_active !== 1) {
            return ['isValid' => false, 'reason' => 'revoked'];
        }

        $subId = (int) $tokenRow->submission_id;
        $detail = $this->getSubmissionPaymentAndLoaDetail($subId, $locale);
        if (!$detail) {
            return ['isValid' => false, 'reason' => 'not_found'];
        }

        $isExpiredTime = !empty($tokenRow->expires_at) && strtotime((string) $tokenRow->expires_at) < time();
        if ($isExpiredTime && !$detail['isPublished'] && !$detail['authorForm']['filled']) {
            return ['isValid' => false, 'reason' => 'expired', 'detail' => $detail];
        }

        return [
            'isValid' => true,
            'reason' => 'ok',
            'tokenRow' => $tokenRow,
            'detail' => $detail,
        ];
    }

    /**
     * Process Author Confirmation Form submission.
     * Validates:
     * - Full Name Author(s) required
     * - Affiliation required
     * - Correspondent Email required & valid email format
     * - WhatsApp Number required & valid phone/WhatsApp format (`^\+?[0-9][0-9\s\-]{7,19}$`)
     * - Transfer Proof optional, max 1 MB (or configured max), JPG/PNG/PDF
     * - Testimonial required
     * - Testimonial Publication Consent checkbox required (`testimonial_consent == 1`)
     * Stores privacy-preserving IP address hash (`hash('sha256', $ip . $salt)`) and submission timestamp.
     * Automatically links uploaded proof and author details to `mjcp_payments` and `mjcp_testimonials`.
     *
     * @param string $rawToken
     * @param array $input {fullNameAuthors, affiliation, correspondentEmail, whatsappNumber, testimonial, testimonialConsent, locale}
     * @param array|null $uploadedFile $_FILES['transferProof']
     * @param string $clientIp
     * @return array {ok: bool, errors: array, message: string}
     */
    public function submitAuthorConfirmationForm($rawToken, $input, $uploadedFile, $clientIp = '')
    {
        $submittedLocale = !empty($input['locale']) ? trim((string) $input['locale']) : 'en_US';
        $resolved = $this->resolveAuthorToken($rawToken, $submittedLocale);
        if (empty($resolved['isValid'])) {
            return [
                'ok' => false,
                'errors' => [__('mjcp.authorForm.error.tokenInvalidOrExpired', [], $submittedLocale)],
                'message' => __('mjcp.authorForm.error.tokenInvalidOrExpired', [], $submittedLocale),
            ];
        }

        $detail = $resolved['detail'];
        $subId = (int) $detail['submissionId'];
        $ctxId = (int) $detail['contextId'];

        $fullName = trim((string) (isset($input['fullNameAuthors']) ? $input['fullNameAuthors'] : ''));
        $affiliation = trim((string) (isset($input['affiliation']) ? $input['affiliation'] : ''));
        $email = trim((string) (isset($input['correspondentEmail']) ? $input['correspondentEmail'] : ''));
        $whatsapp = trim((string) (isset($input['whatsappNumber']) ? $input['whatsappNumber'] : ''));
        $testimonial = trim((string) (isset($input['testimonial']) ? $input['testimonial'] : ''));
        $consent = !empty($input['testimonialConsent']) ? 1 : 0;

        $errors = [];
        if ($fullName === '') {
            $errors[] = __('mjcp.authorForm.error.fullNameRequired', [], $submittedLocale);
        }
        if ($affiliation === '') {
            $errors[] = __('mjcp.authorForm.error.affiliationRequired', [], $submittedLocale);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('mjcp.authorForm.error.invalidEmail', [], $submittedLocale);
        }
        if ($whatsapp === '' || !preg_match('/^\+?[0-9][0-9\s\-]{7,19}$/', $whatsapp)) {
            $errors[] = __('mjcp.authorForm.error.invalidWhatsapp', [], $submittedLocale);
        }
        if ($testimonial === '') {
            $errors[] = __('mjcp.authorForm.error.testimonialRequired', [], $submittedLocale);
        }
        if ($consent !== 1) {
            $errors[] = __('mjcp.authorForm.error.consentRequired', [], $submittedLocale);
        }

        // Optional transfer proof validation
        $proofPath = null;
        $proofOrigName = null;
        $proofMime = null;
        $proofSize = null;

        if ($uploadedFile && !empty($uploadedFile['name']) && (int) $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE) {
            if ((int) $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                $errors[] = __('mjcp.authorForm.error.fileUploadError', [], $submittedLocale);
            } else {
                $storeRes = $this->storePrivateProofFile($subId, $ctxId, $uploadedFile, $submittedLocale);
                if (!$storeRes['ok']) {
                    $errors[] = $storeRes['message'];
                } else {
                    $proofPath = $storeRes['filePath'];
                    $proofOrigName = $storeRes['originalName'];
                    $proofMime = $storeRes['mimeType'];
                    $proofSize = $storeRes['fileSize'];
                }
            }
        }

        if (!empty($errors)) {
            return [
                'ok' => false,
                'errors' => $errors,
                'message' => implode(' ', $errors),
            ];
        }

        $now = date('Y-m-d H:i:s');
        // Privacy-aware IP logging
        $ipHash = $clientIp !== '' ? hash('sha256', $clientIp . '|mjcp_privacy_salt_2026') : null;

        $existingForm = Capsule::table('mjcp_author_forms')->where('submission_id', '=', $subId)->first();
        $oldFormSnapshot = $existingForm
            ? [
                'fullNameAuthors' => (string) $existingForm->full_name_authors,
                'affiliation' => (string) $existingForm->affiliation,
                'email' => (string) $existingForm->correspondent_email,
                'whatsapp' => (string) $existingForm->whatsapp_number,
                'testimonial' => (string) $existingForm->testimonial,
                'proofPath' => (string) $existingForm->transfer_proof_path,
            ]
            : null;

        $formPayload = [
            'submission_id' => $subId,
            'context_id' => $ctxId,
            'full_name_authors' => $fullName,
            'affiliation' => $affiliation,
            'correspondent_email' => $email,
            'whatsapp_number' => $whatsapp,
            'testimonial' => $testimonial,
            'testimonial_consent' => 1,
            'ip_address_hash' => $ipHash,
            'submitted_locale' => $submittedLocale,
            'updated_at' => $now,
        ];

        if ($proofPath !== null) {
            $formPayload['transfer_proof_path'] = $proofPath;
            $formPayload['transfer_proof_original_name'] = $proofOrigName;
            $formPayload['transfer_proof_mime'] = $proofMime;
            $formPayload['transfer_proof_size'] = $proofSize;
        }

        if ($existingForm) {
            Capsule::table('mjcp_author_forms')->where('author_form_id', '=', (int) $existingForm->author_form_id)->update($formPayload);
        } else {
            $formPayload['submitted_at'] = $now;
            Capsule::table('mjcp_author_forms')->insert($formPayload);
        }

        // Link proof file to mjcp_payments automatically if uploaded, preserving previous proof in mjcp_payment_logs
        $paymentRow = Capsule::table('mjcp_payments')->where('submission_id', '=', $subId)->first();
        if ($paymentRow) {
            if ($proofPath !== null) {
                $oldProofPath = !empty($paymentRow->proof_file_path) ? (string) $paymentRow->proof_file_path : null;
                Capsule::table('mjcp_payments')
                    ->where('payment_id', '=', (int) $paymentRow->payment_id)
                    ->update([
                        'proof_file_path' => $proofPath,
                        'proof_original_name' => $proofOrigName,
                        'proof_mime_type' => $proofMime,
                        'proof_file_size' => $proofSize,
                        'updated_at' => $now,
                    ]);

                Capsule::table('mjcp_payment_logs')->insert([
                    'payment_id' => (int) $paymentRow->payment_id,
                    'submission_id' => $subId,
                    'context_id' => $ctxId,
                    'old_status' => (string) $paymentRow->payment_status,
                    'new_status' => (string) $paymentRow->payment_status,
                    'old_amount' => (int) $paymentRow->amount,
                    'new_amount' => (int) $paymentRow->amount,
                    'old_notes' => (string) $paymentRow->notes,
                    'new_notes' => (string) $paymentRow->notes,
                    'old_proof_file_path' => $oldProofPath,
                    'new_proof_file_path' => $proofPath,
                    'changed_by' => null,
                    'changed_by_username' => $fullName,
                    'changed_at' => $now,
                ]);
            }
        } else {
            $newPayId = (int) Capsule::table('mjcp_payments')->insertGetId(
                [
                    'submission_id' => $subId,
                    'context_id' => $ctxId,
                    'payment_status' => 'unpaid',
                    'amount' => 0,
                    'proof_file_path' => $proofPath,
                    'proof_original_name' => $proofOrigName,
                    'proof_mime_type' => $proofMime,
                    'proof_file_size' => $proofSize,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'payment_id',
            );
            if ($proofPath !== null) {
                Capsule::table('mjcp_payment_logs')->insert([
                    'payment_id' => $newPayId,
                    'submission_id' => $subId,
                    'context_id' => $ctxId,
                    'old_status' => 'unpaid',
                    'new_status' => 'unpaid',
                    'old_amount' => 0,
                    'new_amount' => 0,
                    'old_notes' => '',
                    'new_notes' => '',
                    'old_proof_file_path' => null,
                    'new_proof_file_path' => $proofPath,
                    'changed_by' => null,
                    'changed_by_username' => $fullName,
                    'changed_at' => $now,
                ]);
            }
        }

        // Upsert testimonial into mjcp_testimonials
        $existingTesti = Capsule::table('mjcp_testimonials')->where('submission_id', '=', $subId)->first();
        $testiPayload = [
            'submission_id' => $subId,
            'context_id' => $ctxId,
            'author_name' => $fullName,
            'institution' => $affiliation,
            'journal_name' => (string) $detail['journalName'],
            'testimonial_text' => $testimonial,
            'consent_given' => 1,
            'consent_withdrawn' => 0,
            'withdrawn_at' => null,
            'submitted_at' => $now,
        ];
        if ($existingTesti) {
            Capsule::table('mjcp_testimonials')->where('testimonial_id', '=', (int) $existingTesti->testimonial_id)->update($testiPayload);
        } else {
            Capsule::table('mjcp_testimonials')->insert($testiPayload);
        }

        $this->_dao->logAudit(
            $ctxId,
            $subId,
            null,
            $fullName,
            $existingForm ? 'author_confirmation_updated' : 'author_confirmation_submitted',
            'mjcp_author_forms',
            $oldFormSnapshot,
            ['fullNameAuthors' => $fullName, 'affiliation' => $affiliation, 'email' => $email, 'whatsapp' => $whatsapp, 'hasProof' => $proofPath !== null],
            $ipHash,
        );

        return [
            'ok' => true,
            'errors' => [],
            'message' => __('mjcp.authorForm.submittedSuccess', [], $submittedLocale),
        ];
    }

    /**
     * Fetch all testimonials (or only consented & not withdrawn for export).
     *
     * @param bool $approvedOnly
     * @param int $contextId
     * @param string $locale
     * @return array
     */
    public function getTestimonials($approvedOnly = false, $contextId = 0, $locale = 'en_US')
    {
        $this->_dao->ensureSchema();

        $query = Capsule::table('mjcp_testimonials')->orderBy('testimonial_id', 'desc');

        if ((int) $contextId > 0) {
            $query->where('context_id', '=', (int) $contextId);
        }
        if ($approvedOnly) {
            $query->where('consent_given', '=', 1)->where('consent_withdrawn', '=', 0);
        }

        $rows = $query->get();
        $list = [];
        foreach ($rows as $r) {
            $isApproved = (int) $r->consent_given === 1 && (int) $r->consent_withdrawn === 0;
            $list[] = [
                'testimonialId' => (int) $r->testimonial_id,
                'submissionId' => (int) $r->submission_id,
                'contextId' => (int) $r->context_id,
                'authorName' => (string) $r->author_name,
                'institution' => (string) $r->institution,
                'journalName' => (string) $r->journal_name,
                'testimonialText' => (string) $r->testimonial_text,
                'consentGiven' => (int) $r->consent_given === 1,
                'consentWithdrawn' => (int) $r->consent_withdrawn === 1,
                'isApproved' => $isApproved,
                'statusLabel' => $isApproved ? __('mjcp.testimonial.status.approved') : __('mjcp.testimonial.status.withdrawn'),
                'badgeType' => $isApproved ? 'success' : 'warning',
                'submittedAt' => (string) $r->submitted_at,
                'submittedAtFormatted' => $this->_dashboardService->formatDateTimeShort($r->submitted_at, $locale),
            ];
        }

        return $list;
    }

    /**
     * Toggle testimonial consent withdrawal status.
     *
     * @param int $testimonialId
     * @param bool $withdraw
     * @param int $userId
     * @return bool
     */
    public function setTestimonialConsentWithdrawn($testimonialId, $withdraw, $userId)
    {
        $this->_dao->ensureSchema();
        $tid = (int) $testimonialId;
        $now = date('Y-m-d H:i:s');

        $row = Capsule::table('mjcp_testimonials')->where('testimonial_id', '=', $tid)->first();
        if (!$row) {
            return false;
        }

        Capsule::table('mjcp_testimonials')
            ->where('testimonial_id', '=', $tid)
            ->update([
                'consent_withdrawn' => $withdraw ? 1 : 0,
                'withdrawn_at' => $withdraw ? $now : null,
                'withdrawn_by' => $withdraw ? (int) $userId : null,
            ]);

        $this->_dao->logAudit((int) $row->context_id, (int) $row->submission_id, (int) $userId, null, $withdraw ? 'testimonial_consent_withdrawn' : 'testimonial_consent_restored', 'mjcp_testimonials', !$withdraw, $withdraw);

        return true;
    }

    /**
     * Validate and store a payment proof file (JPG, PNG, or PDF, max 1 MB) in OJS private files_dir.
     *
     * @param int $submissionId
     * @param int $contextId
     * @param array $uploadedFile
     * @return array
     */
    public function storePrivateProofFile($submissionId, $contextId, $uploadedFile, $locale = null)
    {
        $maxSize = (int) $this->_plugin->getSetting(0, 'maxUploadSizeBytes');
        if ($maxSize <= 0) {
            $maxSize = self::MAX_PROOF_SIZE_BYTES;
        }

        $tmpPath = isset($uploadedFile['tmp_name']) ? (string) $uploadedFile['tmp_name'] : '';
        $diskSize = $tmpPath !== '' && is_file($tmpPath) ? (int) filesize($tmpPath) : 0;
        $reportedSize = isset($uploadedFile['size']) ? (int) $uploadedFile['size'] : 0;
        $fileSize = $diskSize > 0 ? $diskSize : $reportedSize;

        if ($fileSize <= 0 || $fileSize > $maxSize) {
            return [
                'ok' => false,
                'message' => __('mjcp.upload.error.fileTooLarge', ['maxKb' => round($maxSize / 1024), 'maxMb' => round($maxSize / 1048576, 1)], $locale),
            ];
        }

        $origName = basename((string) $uploadedFile['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($ext, $allowedExtensions, true)) {
            return [
                'ok' => false,
                'message' => __('mjcp.upload.error.invalidProofFormat', [], $locale),
            ];
        }

        // Server-side MIME verification
        $detectedMime = $this->_detectMimeType($tmpPath);
        $allowedMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'application/pdf', 'application/x-pdf'];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            return [
                'ok' => false,
                'message' => __('mjcp.upload.error.invalidProofFormat', [], $locale),
            ];
        }

        $baseDir = $this->getPrivateStorageDir('proofs/' . (int) $contextId);
        $safeRandom = bin2hex(random_bytes(8));
        $storedFileName = 'proof_' . (int) $submissionId . '_' . date('YmdHis') . '_' . $safeRandom . '.' . $ext;
        $destPath = $baseDir . '/' . $storedFileName;

        if (!@move_uploaded_file($tmpPath, $destPath) && !@copy($tmpPath, $destPath)) {
            return [
                'ok' => false,
                'message' => __('mjcp.upload.error.storageFailed', [], $locale),
            ];
        }

        @chmod($destPath, 0640);

        return [
            'ok' => true,
            'filePath' => $destPath,
            'originalName' => $origName,
            'mimeType' => $detectedMime,
            'fileSize' => $fileSize,
        ];
    }

    /**
     * Validate and store an LOA file (PDF only) in OJS private files_dir.
     *
     * @param int $submissionId
     * @param int $contextId
     * @param array $uploadedFile
     * @return array
     */
    public function storePrivateLoaFile($submissionId, $contextId, $uploadedFile)
    {
        $tmpPath = isset($uploadedFile['tmp_name']) ? (string) $uploadedFile['tmp_name'] : '';
        $diskSize = $tmpPath !== '' && is_file($tmpPath) ? (int) filesize($tmpPath) : 0;
        $reportedSize = isset($uploadedFile['size']) ? (int) $uploadedFile['size'] : 0;
        $fileSize = $diskSize > 0 ? $diskSize : $reportedSize;

        if ($fileSize <= 0 || $fileSize > self::MAX_LOA_SIZE_BYTES) {
            return [
                'ok' => false,
                'message' => __('mjcp.loa.error.invalidPdf'),
            ];
        }

        $origName = basename((string) $uploadedFile['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return [
                'ok' => false,
                'message' => __('mjcp.loa.error.invalidPdf'),
            ];
        }

        $detectedMime = $this->_detectMimeType($tmpPath);
        $fh = @fopen($tmpPath, 'rb');
        $magic = $fh ? fread($fh, 5) : '';
        if ($fh) {
            fclose($fh);
        }

        if (!in_array($detectedMime, ['application/pdf', 'application/x-pdf'], true) || strpos((string) $magic, '%PDF-') !== 0) {
            return [
                'ok' => false,
                'message' => __('mjcp.loa.error.invalidPdf'),
            ];
        }

        $baseDir = $this->getPrivateStorageDir('loa/' . (int) $contextId);
        $safeRandom = bin2hex(random_bytes(8));
        $storedFileName = 'loa_' . (int) $submissionId . '_' . date('YmdHis') . '_' . $safeRandom . '.pdf';
        $destPath = $baseDir . '/' . $storedFileName;

        if (!@move_uploaded_file($tmpPath, $destPath) && !@copy($tmpPath, $destPath)) {
            return [
                'ok' => false,
                'message' => __('mjcp.upload.error.storageFailed'),
            ];
        }

        @chmod($destPath, 0640);

        return [
            'ok' => true,
            'filePath' => $destPath,
            'originalName' => $origName,
            'mimeType' => 'application/pdf',
            'fileSize' => $fileSize,
        ];
    }

    /**
     * Resolve private directory path outside web-accessible public files.
     * Uses OJS `Config::getVar('files', 'files_dir') . '/multiJournalControlPanel/' . $subDir`.
     *
     * @param string $subDir
     * @return string
     */
    public function getPrivateStorageDir($subDir = '')
    {
        $filesDir = Config::getVar('files', 'files_dir');
        if (empty($filesDir) || !is_dir($filesDir) || !is_writable($filesDir)) {
            $filesDir = sys_get_temp_dir() . '/ojs_mjcp_private_files';
        }
        $fullDir = rtrim($filesDir, '/') . '/multiJournalControlPanel' . ($subDir !== '' ? '/' . trim($subDir, '/') : '');
        if (!is_dir($fullDir)) {
            @mkdir($fullDir, 0750, true);
        }
        return $fullDir;
    }

    /**
     * Detect MIME type of a file on disk using finfo / mime_content_type.
     *
     * @param string $filePath
     * @return string
     */
    protected function _detectMimeType($filePath)
    {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($filePath);
            if (!empty($mime)) {
                return strtolower((string) $mime);
            }
        }
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($filePath);
            if (!empty($mime)) {
                return strtolower((string) $mime);
            }
        }
        return 'application/octet-stream';
    }

    /**
     * Format integer amount with localized currency prefix.
     *
     * @param int $amount
     * @return string
     */
    public function formatCurrency($amount)
    {
        $prefix = __('mjcp.currency.prefix');
        if ($prefix === '' || strpos($prefix, '##') === 0 || $prefix === 'mjcp.currency.prefix') {
            $prefix = 'IDR';
        }
        return $prefix . ' ' . number_format((int) $amount, 0, ',', '.');
    }
}
