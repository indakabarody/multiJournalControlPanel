{**
 * templates/payments/form.tpl
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
				<h1 class="mjcp-title">{translate key="mjcp.payment.detailTitle" id=$detail.submissionId|escape}</h1>
				<p class="mjcp-subtitle">{translate key="mjcp.payment.detailSubtitle" journal=$detail.journalName|escape}</p>
			</div>
			<div>
				{if $perms.canViewPayment}
					<a href="{$navUrls.payments|escape}" class="mjcp-btn mjcp-btn--outline">
						&larr; {translate key="mjcp.nav.payments"}
					</a>
				{else}
					<a href="{$navUrls.authorLinks|escape}" class="mjcp-btn mjcp-btn--outline">
						&larr; {translate key="mjcp.nav.authorLinks"}
					</a>
				{/if}
			</div>
		</div>

		{* 3-Column Layout *}
		<div class="mjcp-three-cols">
			{if $perms.canViewPayment}
				{* Column 1: Data Submitted by Author *}
				<div class="mjcp-card">
					<h2 class="mjcp-box-title">
						{if $detail.authorForm.filled}
							{translate key="mjcp.payment.submittedByAuthorHeading" date=$detail.authorForm.submittedAtFormatted|escape}
						{else}
							{translate key="mjcp.payment.submittedByAuthorEmpty"}
						{/if}
					</h2>

					<dl class="mjcp-kv-list">
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.authorForm.manuscriptId"}</dt>
							<dd>{$detail.submissionId|escape}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.authorForm.journalChoice"}</dt>
							<dd>{$detail.journalName|escape}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.authorForm.articleTitle"}</dt>
							<dd>{$detail.title|escape}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.authorForm.fullNameAuthors"}</dt>
							<dd>{$detail.authorForm.fullNameAuthors|escape}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.authorForm.affiliation"}</dt>
							<dd>{$detail.authorForm.affiliation|escape}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.authorForm.correspondentEmail"}</dt>
							<dd>{$detail.authorForm.correspondentEmail|escape}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.authorForm.whatsappNumber"}</dt>
							<dd>{$detail.authorForm.whatsappNumber|escape}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.authorForm.transferProofLabel"}</dt>
							<dd>
								{if $detail.payment.hasProof}
									{$detail.payment.proofName|escape}
									{if $detail.payment.proofSizeKb > 0}({$detail.payment.proofSizeKb|escape} KB){/if}
									<a href="{$navUrls.downloadProof|escape}/{$detail.submissionId|escape}" class="mjcp-link">
										{translate key="mjcp.common.download"}
									</a>
								{else}
									-
								{/if}
							</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.testimonial.text"}</dt>
							<dd>{$detail.authorForm.testimonial|escape}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.testimonial.consentStatus"}</dt>
							<dd>
								{if $detail.authorForm.testimonialConsent}
									<span class="mjcp-badge mjcp-badge-success">{translate key="mjcp.testimonial.status.approvedFull"}</span>
								{else}
									<span class="mjcp-badge mjcp-badge-warning">-</span>
								{/if}
							</dd>
						</div>
					</dl>
				</div>

				{* Column 2: Payment Verification (Finance Admin) *}
				<div class="mjcp-card">
					<h2 class="mjcp-box-title">{translate key="mjcp.payment.verificationHeading"}</h2>

					<form method="POST" action="{$navUrls.verifyPayment|escape}" enctype="multipart/form-data" class="mjcp-form">
						<input type="hidden" name="csrfToken" value="{$csrfToken|escape}" />
						<input type="hidden" name="submissionId" value="{$detail.submissionId|escape}" />

						<div class="mjcp-form-group">
							<label for="mjcpPaymentStatus">{translate key="mjcp.payment.status"}</label>
							<select id="mjcpPaymentStatus" name="paymentStatus" class="mjcp-select" {if !$perms.canEditPayment}disabled{/if}>
								<option value="unpaid" {if $detail.payment.status == 'unpaid'}selected{/if}>{translate key="mjcp.payment.status.unpaid"}</option>
								<option value="paid" {if $detail.payment.status == 'paid'}selected{/if}>{translate key="mjcp.payment.status.paid"}</option>
							</select>
						</div>

						<div class="mjcp-form-group">
							<label for="mjcpPaymentAmount">{translate key="mjcp.payment.amountLabel"}</label>
							<input type="text" id="mjcpPaymentAmount" name="amount" class="mjcp-input"
								value="{$detail.payment.amountFormattedInput|escape}"
								{if !$perms.canEditPayment}readonly{/if} />
						</div>

						<div class="mjcp-form-group">
							<label for="mjcpPaymentNotes">{translate key="mjcp.payment.notesOptional"}</label>
							<textarea id="mjcpPaymentNotes" name="notes" class="mjcp-textarea" rows="3"
								{if !$perms.canEditPayment}readonly{/if}>{$detail.payment.notes|escape}</textarea>
						</div>

						{if $perms.canEditPayment}
							<div class="mjcp-form-group">
								<label for="mjcpProofFile">{translate key="mjcp.payment.replaceProofOptional"}</label>
								<input type="file" id="mjcpProofFile" name="proofFile" accept=".jpg,.jpeg,.png,.pdf" class="mjcp-input" />
							</div>

							<div class="mjcp-form-actions">
								<button type="submit" class="mjcp-btn mjcp-btn--primary">{translate key="mjcp.payment.save"}</button>
								<a href="{$navUrls.payments|escape}" class="mjcp-btn mjcp-btn--outline">{translate key="mjcp.common.cancel"}</a>
							</div>
						{/if}
					</form>

					<div class="mjcp-history-section">
						<h3 class="mjcp-subheading">{translate key="mjcp.payment.historyHeading"}</h3>
						{if empty($detail.paymentLogs)}
							<p class="mjcp-muted">-</p>
						{else}
							<ul class="mjcp-history-list">
								{foreach from=$detail.paymentLogs item="plog"}
									<li>
										{$plog.changedAtFormatted|escape}: {$plog.oldStatusLabel|escape} &rarr; <strong>{$plog.newStatusLabel|escape}</strong>,
										{$plog.newAmountFormatted|escape}
										{if $plog.newNotes}({$plog.newNotes|escape}){/if}
										({$plog.changedByUsername|escape})
									</li>
								{/foreach}
							</ul>
						{/if}
						<p class="mjcp-muted">{translate key="mjcp.payment.jmReadOnlyNotice"}</p>
					</div>
				</div>
			{/if}

			{* Column 3: LOA (uploaded by Journal Manager) *}
			<div class="mjcp-card">
				<h2 class="mjcp-box-title">{translate key="mjcp.loa.cardHeading"}</h2>

				<form method="POST" action="{$navUrls.uploadLoa|escape}" enctype="multipart/form-data" class="mjcp-form">
					<input type="hidden" name="csrfToken" value="{$csrfToken|escape}" />
					<input type="hidden" name="submissionId" value="{$detail.submissionId|escape}" />

					<div class="mjcp-form-group">
						<label for="mjcpLoaNumber">{translate key="mjcp.loa.numberLabel"}</label>
						<input type="text" id="mjcpLoaNumber" name="loaNumber" class="mjcp-input"
							value="{$detail.loa.loaNumber|escape}"
							placeholder="{translate|escape key="mjcp.loa.numberPlaceholder"}"
							{if !$perms.canManageLoa}readonly{/if} required />
					</div>

					{if $perms.canManageLoa}
						<div class="mjcp-form-group">
							<label for="mjcpLoaFile">{translate key="mjcp.loa.fileLabel"}</label>
							<input type="file" id="mjcpLoaFile" name="loaFile" accept=".pdf,application/pdf" class="mjcp-input" />
						</div>
					{/if}

					{if $perms.canViewPayment}
						<p class="mjcp-muted">
							{translate key="mjcp.loa.visibilityNote"}
							{if $detail.payment.status == 'paid'}
								<span class="mjcp-badge mjcp-badge-success">{translate key="mjcp.payment.status.paid"}</span>
							{else}
								<span class="mjcp-badge mjcp-badge-danger">{translate key="mjcp.payment.status.unpaid"}</span>
							{/if}
						</p>
					{/if}

					{if $perms.canManageLoa}
						<div class="mjcp-form-actions">
							<button type="submit" class="mjcp-btn mjcp-btn--primary">{translate key="mjcp.loa.uploadBtn"}</button>
						</div>
					{/if}
				</form>

				{if $detail.loa.exists}
					<div class="mjcp-history-section">
						<p>
							<strong>{$detail.loa.originalFilename|escape}</strong> ({$detail.loa.fileSizeKb|escape} KB)
							&middot;
							<a href="{$navUrls.downloadLoa|escape}/{$detail.submissionId|escape}" class="mjcp-link">
								{translate key="mjcp.loa.download"}
							</a>
						</p>
					</div>
				{/if}

				{if !empty($detail.loaHistory)}
					<div class="mjcp-history-section">
						<h3 class="mjcp-subheading">{translate key="mjcp.payment.historyHeading"} ({translate key="mjcp.links.loaStatus"})</h3>
						<ul class="mjcp-history-list">
							{foreach from=$detail.loaHistory item="lh"}
								<li>
									{$lh.uploadedAtFormatted|escape}: <strong>{$lh.loaNumber|escape}</strong>
									({$lh.originalFilename|escape}) &ndash; {$lh.uploadedByUsername|escape}
								</li>
							{/foreach}
						</ul>
					</div>
				{/if}
			</div>
		</div>
	</div>
</div>
{/block}
