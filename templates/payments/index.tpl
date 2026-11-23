{**
 * templates/payments/index.tpl
 *
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div class="mjcp-container" id="mjcpControlPanelRoot"
	data-active-tab="payments"
	data-export-url="{$navUrls.export|escape}"
	data-import-payments-url="{$navUrls.importPayments|escape}"
	data-generate-token-url="{$navUrls.generateToken|escape}"
	data-csrf-token="{$csrfToken|escape}">

	{* Top Navigation Bar *}
	<nav class="mjcp-topbar" aria-label="{translate|escape key="plugins.generic.multiJournalControlPanel.displayName"}">
		<div class="mjcp-topbar__brand">
			{translate key="mjcp.nav.brand" house=$houseName|escape}
		</div>
		<div class="mjcp-topbar__links">
			{if $perms.canViewDashboard}
				<a href="{$navUrls.dashboard|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.dashboard"}</a>
				<a href="{$navUrls.bottlenecks|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.bottlenecks"}</a>
				<a href="{$navUrls.journals|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.journals"}</a>
				<a href="{$navUrls.issues|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.issues"}</a>
				<a href="{$navUrls.performance|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.performance"}</a>
			{/if}
			{if $perms.canViewPayment}
				<a href="{$navUrls.payments|escape}" class="mjcp-nav-item mjcp-nav-item--active">{translate key="mjcp.nav.payments"}</a>
			{/if}
			{if $perms.canManageAuthorLinks}
				<a href="{$navUrls.authorLinks|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.authorLinks"}</a>
			{/if}
			{if $perms.canViewDashboard}
				<a href="{$navUrls.testimonials|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.testimonials"}</a>
			{/if}
			{if $perms.canManageSettings}
				<a href="{$navUrls.settings|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.settings"}</a>
			{/if}
		</div>
	</nav>

	<div class="mjcp-dashboard">
		<div class="mjcp-header">
			<div>
				<h1 class="mjcp-title">{translate key="mjcp.payment.title"}</h1>
				<p class="mjcp-subtitle">{translate key="mjcp.payment.subtitle"}</p>
			</div>
			{if $perms.canExportPayment}
				<div class="mjcp-export-group">
					<button type="button" class="mjcp-btn mjcp-btn--primary mjcp-export-trigger" data-module="payments" data-format="xlsx">
						{translate key="mjcp.export.excel"}
					</button>
					<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="payments" data-format="csv">
						{translate key="mjcp.export.csv"}
					</button>
					<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="payments" data-format="pdf">
						{translate key="mjcp.export.pdf"}
					</button>
				</div>
			{/if}
		</div>

		{* Restricted Access Banner *}
		<div class="mjcp-alert mjcp-alert--warning">
			{translate key="mjcp.payment.restrictedBanner"}
		</div>

		{* Quick Filter Pills & Import Excel/CSV Button *}
		<div class="mjcp-toolbar">
			<div class="mjcp-pill-group">
				<a href="{$navUrls.payments|escape}{$querySep|escape}quickFilter=all&amp;contextId={$activeFilters.contextId|escape}"
					class="mjcp-pill {if $activeQuickFilter == 'all'}mjcp-pill--active{/if}">
					{translate key="mjcp.payment.filter.all"}
				</a>
				<a href="{$navUrls.payments|escape}{$querySep|escape}quickFilter=form_unfilled&amp;contextId={$activeFilters.contextId|escape}"
					class="mjcp-pill {if $activeQuickFilter == 'form_unfilled'}mjcp-pill--active{/if}">
					{translate key="mjcp.payment.filter.formUnfilled"}
				</a>
				<a href="{$navUrls.payments|escape}{$querySep|escape}quickFilter=form_unverified&amp;contextId={$activeFilters.contextId|escape}"
					class="mjcp-pill {if $activeQuickFilter == 'form_unverified'}mjcp-pill--active{/if}">
					{translate key="mjcp.payment.filter.formUnverified"}
				</a>
				<a href="{$navUrls.payments|escape}{$querySep|escape}quickFilter=loa_unpaid&amp;contextId={$activeFilters.contextId|escape}"
					class="mjcp-pill {if $activeQuickFilter == 'loa_unpaid'}mjcp-pill--active{/if}">
					{translate key="mjcp.payment.filter.loaUnpaid"}
				</a>
				<a href="{$navUrls.payments|escape}{$querySep|escape}quickFilter=paid_not_copyediting&amp;contextId={$activeFilters.contextId|escape}"
					class="mjcp-pill {if $activeQuickFilter == 'paid_not_copyediting'}mjcp-pill--active{/if}">
					{translate key="mjcp.payment.filter.paidNotCopyediting"}
				</a>
				<a href="{$navUrls.payments|escape}{$querySep|escape}quickFilter=paid_unpublished&amp;contextId={$activeFilters.contextId|escape}"
					class="mjcp-pill {if $activeQuickFilter == 'paid_unpublished'}mjcp-pill--active{/if}">
					{translate key="mjcp.payment.filter.paidUnpublished"}
				</a>
				{if $perms.canEditPayment}
					<button type="button" class="mjcp-pill" id="mjcpOpenBulkImportBtn">
						{translate key="mjcp.payment.importExcelBtn"}
					</button>
				{/if}
			</div>

			<form id="mjcpFilterForm" method="GET" action="{$navUrls.payments|escape}" class="mjcp-filter">
				<input type="hidden" name="quickFilter" value="{$activeQuickFilter|escape}" />
				<select name="contextId" class="mjcp-select mjcp-auto-submit" aria-label="{translate|escape key="mjcp.table.journal"}">
					<option value="all" {if $activeFilters.contextId == 'all'}selected{/if}>
						{translate key="mjcp.common.allJournals" house=$houseName|escape}
					</option>
					{foreach from=$journalsList key="jId" item="jInfo"}
						<option value="{$jId|escape}" {if $activeFilters.contextId == $jId}selected{/if}>{$jInfo.name|escape}</option>
					{/foreach}
				</select>
			</form>
		</div>

		{* Main Payment Table *}
		<div class="mjcp-panel-box">
			<div class="mjcp-table-responsive">
				<table class="mjcp-table">
					<thead>
						<tr>
							<th>{translate key="mjcp.table.code"}</th>
							<th>{translate key="mjcp.table.journal"}</th>
							<th>{translate key="mjcp.payment.author"}</th>
							<th>{translate key="mjcp.payment.formStatus"}</th>
							<th>{translate key="mjcp.payment.status"}</th>
							<th>{translate key="mjcp.payment.amount"}</th>
							<th>{translate key="mjcp.payment.notes"}</th>
							<th>{translate key="mjcp.payment.proof"}</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{if empty($paymentItems)}
							<tr>
								<td colspan="9" class="mjcp-empty-cell">{translate key="mjcp.common.noData"}</td>
							</tr>
						{else}
							{foreach from=$paymentItems item="pItem"}
								<tr>
									<td>{$pItem.submissionId|escape}</td>
									<td>{$pItem.journalName|escape}</td>
									<td>{$pItem.authorName|escape}</td>
									<td>
										<span class="mjcp-badge mjcp-badge-{$pItem.formBadgeType|escape}">
											{$pItem.formStatusLabel|escape}
										</span>
									</td>
									<td>
										<span class="mjcp-badge mjcp-badge-{$pItem.paymentBadgeType|escape}">
											{$pItem.paymentStatusLabel|escape}
										</span>
									</td>
									<td>{$pItem.amountFormatted|escape}</td>
									<td>{$pItem.notes|escape}</td>
									<td>
										{if $pItem.hasProof}
											<a href="{$pItem.downloadProofUrl|escape}" class="mjcp-link">
												{$pItem.proofName|escape}
											</a>
										{else}
											-
										{/if}
									</td>
									<td>
										{if !$pItem.formFilled && $perms.canManageAuthorLinks}
											<a href="{$navUrls.authorLinks|escape}" class="mjcp-link">
												{translate key="mjcp.payment.sendLink"}
											</a>
											&middot;
										{/if}
										<a href="{$pItem.detailUrl|escape}" class="mjcp-link">
											{translate key="mjcp.payment.viewSubmission"}
										</a>
									</td>
								</tr>
							{/foreach}
						{/if}
					</tbody>
				</table>
			</div>
		</div>

		{* 3 Summary Cards below table *}
		<div class="mjcp-cards-grid mjcp-cards-grid--three">
			<div class="mjcp-card">
				<div class="mjcp-card-title">{translate key="mjcp.payment.card.paid"}</div>
				<div class="mjcp-card-value">{$paymentSummary.paidCount|escape}</div>
			</div>
			<div class="mjcp-card">
				<div class="mjcp-card-title">{translate key="mjcp.payment.card.unpaid"}</div>
				<div class="mjcp-card-value">{$paymentSummary.unpaidCount|escape}</div>
			</div>
			<div class="mjcp-card">
				<div class="mjcp-card-title">{translate key="mjcp.payment.card.formUnfilled"}</div>
				<div class="mjcp-card-value mjcp-card-value--danger">{$paymentSummary.formUnfilledCount|escape}</div>
			</div>
		</div>

		<p class="mjcp-footnote">{translate key="mjcp.payment.footnote"}</p>

		{* Per-Journal Payment Recap *}
		{if !empty($paymentRecapByJournal)}
			<div class="mjcp-panel-box">
				<h2 class="mjcp-box-title">{translate key="mjcp.payment.recapHeading"}</h2>
				<div class="mjcp-table-responsive">
					<table class="mjcp-table">
						<thead>
							<tr>
								<th>{translate key="mjcp.table.journal"}</th>
								<th>{translate key="mjcp.payment.card.paid"}</th>
								<th>{translate key="mjcp.payment.card.unpaid"}</th>
								<th>{translate key="mjcp.payment.amount"}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$paymentRecapByJournal item="rec"}
								<tr>
									<td><strong>{$rec.journalName|escape}</strong></td>
									<td>{$rec.paidCount|escape}</td>
									<td>{$rec.unpaidCount|escape}</td>
									<td><strong>{$rec.totalAmountFormatted|escape}</strong></td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			</div>
		{/if}

		{* Per-Issue Payment Recap *}
		{if !empty($paymentRecapByIssue)}
			<div class="mjcp-panel-box">
				<h2 class="mjcp-box-title">{translate key="mjcp.payment.recapByIssueHeading"}</h2>
				<div class="mjcp-table-responsive">
					<table class="mjcp-table">
						<thead>
							<tr>
								<th>{translate key="mjcp.issue.targetIssue"}</th>
								<th>{translate key="mjcp.payment.card.paid"}</th>
								<th>{translate key="mjcp.payment.card.unpaid"}</th>
								<th>{translate key="mjcp.payment.amount"}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$paymentRecapByIssue item="irec"}
								<tr>
									<td><strong>{$irec.issueLabel|escape}</strong></td>
									<td>{$irec.paidCount|escape}</td>
									<td>{$irec.unpaidCount|escape}</td>
									<td><strong>{$irec.totalAmountFormatted|escape}</strong></td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			</div>
		{/if}
	</div>

	{* Bulk Payment Import Modal *}
	{if $perms.canEditPayment}
		<div id="mjcpBulkImportModal" class="mjcp-modal" role="dialog" aria-modal="true" aria-labelledby="mjcpBulkImportTitle" hidden>
			<div class="mjcp-modal__backdrop" id="mjcpCloseBulkImportBackdrop"></div>
			<div class="mjcp-modal__content">
				<div class="mjcp-modal__header">
					<h2 id="mjcpBulkImportTitle" class="mjcp-modal__title">{translate key="mjcp.payment.import.modalTitle"}</h2>
					<button type="button" class="mjcp-modal__close" id="mjcpCloseBulkImportBtn" aria-label="{translate|escape key="mjcp.common.close"}">&times;</button>
				</div>
				<div class="mjcp-modal__body">
					<p class="mjcp-subtitle">{translate key="mjcp.payment.import.instructions"}</p>
					<div class="mjcp-form-group mjcp-mt-sm">
						<label for="mjcpBulkImportFile">{translate key="mjcp.payment.import.fileLabel"}</label>
						<input type="file" id="mjcpBulkImportFile" accept=".csv,.xlsx" class="mjcp-input" />
					</div>
					<textarea id="mjcpBulkImportCsvText" class="mjcp-textarea mjcp-mt-sm" rows="6" placeholder="{translate|escape key="mjcp.payment.import.placeholder"}"></textarea>
					<div class="mjcp-modal__actions">
						<button type="button" class="mjcp-btn mjcp-btn--outline" id="mjcpPreviewBulkImportBtn">
							{translate key="mjcp.payment.import.previewBtn"}
						</button>
						<button type="button" class="mjcp-btn mjcp-btn--primary" id="mjcpCommitBulkImportBtn" disabled>
							{translate key="mjcp.payment.import.commitBtn"}
						</button>
					</div>
					<div id="mjcpBulkImportPreviewArea" class="mjcp-table-responsive"
						data-col-code="{translate|escape key="mjcp.table.code"}"
						data-col-status="{translate|escape key="mjcp.common.status"}"
						data-col-amount="{translate|escape key="mjcp.payment.amount"}"
						data-col-notes="{translate|escape key="mjcp.payment.notes"}"
						data-col-validation="{translate|escape key="mjcp.payment.import.validationCol"}"
						data-ok-badge="{translate|escape key="mjcp.payment.import.okBadge"}"></div>
				</div>
			</div>
		</div>
	{/if}
</div>
{/block}
