{**
 * templates/authors/confirmation.tpl
 *
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *}
<!DOCTYPE html>
<html lang="{if $activeLocale == 'id_ID'}id{else}en{/if}">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>{translate key="mjcp.authorForm.title"} - {$houseName|escape}</title>
	<link rel="stylesheet" href="{$pluginUrl|escape}/styles/multiJournalControlPanel.css" />
</head>
<body class="mjcp-author-page-body">
	<script src="{$pluginUrl|escape}/js/multiJournalControlPanel.js" defer></script>
	<div class="mjcp-container mjcp-author-container">
		{* Top Header with Bilingual Language Switcher *}
		<header class="mjcp-author-topbar">
			<div class="mjcp-topbar__brand">{$houseName|escape}</div>
			{if $enableLangId && $enableLangEn}
				<div class="mjcp-lang-switcher">
					<a href="{$confirmUrlId|escape}"
						class="mjcp-pill {if $activeLocale == 'id_ID'}mjcp-pill--active{/if}">
						{translate key="mjcp.common.langIndonesia"}
					</a>
					<a href="{$confirmUrlEn|escape}"
						class="mjcp-pill {if $activeLocale == 'en_US'}mjcp-pill--active{/if}">
						{translate key="mjcp.common.langEnglish"}
					</a>
				</div>
			{/if}
		</header>

		<main class="mjcp-author-main">
			{if !$tokenValid || !$detail}
				<div class="mjcp-card mjcp-author-card">
					<h1 class="mjcp-title">{translate key="mjcp.authorForm.title"}</h1>
					<div class="mjcp-alert mjcp-alert--danger">
						{translate key="mjcp.authorForm.error.tokenInvalidOrExpired"}
					</div>
				</div>
			{else}
				<div class="mjcp-author-header">
					<h1 class="mjcp-title">{translate key="mjcp.authorForm.title"}</h1>
					<p class="mjcp-subtitle">
						{if $detail.payment.status == 'paid'}
							{translate key="mjcp.authorForm.subtitleVerified"}
						{else}
							{translate key="mjcp.authorForm.subtitlePending"}
						{/if}
					</p>
				</div>

				{if $justSubmitted}
					<div class="mjcp-alert mjcp-alert--success">
						{translate key="mjcp.authorForm.submittedSuccessBanner"}
					</div>
				{/if}

				{if !empty($formErrors)}
					<div class="mjcp-alert mjcp-alert--danger">
						<ul>
							{foreach from=$formErrors item="err"}
								<li>{$err|escape}</li>
							{/foreach}
						</ul>
					</div>
				{/if}

				{* ============================================================= *}
				{* CARD 1: Payment Confirmation                                  *}
				{* ============================================================= *}
				<section class="mjcp-card mjcp-author-card">
					{if $detail.payment.status == 'paid'}
						<div class="mjcp-author-card__head">
							<h2 class="mjcp-box-title">{translate key="mjcp.authorForm.section1Title"}</h2>
							<span class="mjcp-badge mjcp-badge-success">{translate key="mjcp.authorForm.verifiedBadge"}</span>
						</div>
						<dl class="mjcp-kv-list">
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.authorForm.manuscriptId"}</dt>
								<dd>{$detail.submissionId|escape}</dd>
							</div>
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.table.journal"}</dt>
								<dd>{$detail.journalName|escape}</dd>
							</div>
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.authorForm.submittedDateLabel"}</dt>
								<dd>{$detail.authorForm.submittedAtFormatted|escape}</dd>
							</div>
						</dl>
					{else}
						<div class="mjcp-author-card__head">
							<h2 class="mjcp-box-title">{translate key="mjcp.authorForm.section1Title"}</h2>
						</div>

						<form method="POST" action="{$submitConfirmationUrl|escape}" enctype="multipart/form-data" class="mjcp-form">
							<input type="hidden" name="csrfToken" value="{$csrfToken|escape}" />
							<input type="hidden" name="uiLocale" value="{$activeLocale|escape}" />

							<div class="mjcp-form-group">
								<label for="mjcpAuthSubId">{translate key="mjcp.authorForm.manuscriptId"}</label>
								<input type="text" id="mjcpAuthSubId" class="mjcp-input mjcp-input--readonly"
									value="{$detail.submissionId|escape}" readonly />
							</div>

							<div class="mjcp-form-group">
								<label for="mjcpAuthJournal">{translate key="mjcp.authorForm.journalChoice"}</label>
								<input type="text" id="mjcpAuthJournal" class="mjcp-input mjcp-input--readonly"
									value="{$detail.journalName|escape}" readonly />
							</div>

							<div class="mjcp-form-group">
								<label for="mjcpAuthTitle">{translate key="mjcp.authorForm.articleTitle"}</label>
								<input type="text" id="mjcpAuthTitle" class="mjcp-input mjcp-input--readonly"
									value="{$detail.title|escape}" readonly />
							</div>

							<div class="mjcp-form-group">
								<label for="mjcpAuthFullName">{translate key="mjcp.authorForm.fullNameAuthors"}</label>
								<input type="text" id="mjcpAuthFullName" name="fullNameAuthors" class="mjcp-input"
									value="{if isset($formInput.fullNameAuthors)}{$formInput.fullNameAuthors|escape}{elseif $detail.authorForm.filled}{$detail.authorForm.fullNameAuthors|escape}{else}{$detail.authorNames|escape}{/if}"
									required />
							</div>

							<div class="mjcp-form-group">
								<label for="mjcpAuthAffil">{translate key="mjcp.authorForm.affiliation"}</label>
								<input type="text" id="mjcpAuthAffil" name="affiliation" class="mjcp-input"
									value="{if isset($formInput.affiliation)}{$formInput.affiliation|escape}{elseif $detail.authorForm.filled}{$detail.authorForm.affiliation|escape}{/if}"
									required />
							</div>

							<div class="mjcp-form-group">
								<label for="mjcpAuthEmail">{translate key="mjcp.authorForm.correspondentEmail"}</label>
								<input type="email" id="mjcpAuthEmail" name="correspondentEmail" class="mjcp-input"
									value="{if isset($formInput.correspondentEmail)}{$formInput.correspondentEmail|escape}{elseif $detail.authorForm.filled}{$detail.authorForm.correspondentEmail|escape}{else}{$detail.correspondentEmail|escape}{/if}"
									required />
							</div>

							<div class="mjcp-form-group">
								<label for="mjcpAuthWa">{translate key="mjcp.authorForm.whatsappNumber"}</label>
								<input type="text" id="mjcpAuthWa" name="whatsappNumber" class="mjcp-input"
									value="{if isset($formInput.whatsappNumber)}{$formInput.whatsappNumber|escape}{elseif $detail.authorForm.filled}{$detail.authorForm.whatsappNumber|escape}{/if}"
									placeholder="{translate|escape key="mjcp.authorForm.whatsappPlaceholder"}" required />
							</div>

							<div class="mjcp-form-group">
								<label for="mjcpAuthProof">{translate key="mjcp.authorForm.transferProof"}</label>
								<input type="file" id="mjcpAuthProof" name="transferProof" accept=".jpg,.jpeg,.png,.pdf" class="mjcp-input" />
							</div>

							<div class="mjcp-form-group">
								<label for="mjcpAuthTestimonial">{translate key="mjcp.authorForm.testimonial"}</label>
								<textarea id="mjcpAuthTestimonial" name="testimonial" class="mjcp-textarea" rows="3" required>{if isset($formInput.testimonial)}{$formInput.testimonial|escape}{elseif $detail.authorForm.filled}{$detail.authorForm.testimonial|escape}{/if}</textarea>
							</div>

							<div class="mjcp-form-group">
								<label class="mjcp-checkbox-label">
									<input type="checkbox" name="testimonialConsent" value="1"
										{if (isset($formInput.testimonialConsent) && $formInput.testimonialConsent) || ($detail.authorForm.filled && $detail.authorForm.testimonialConsent)}checked{/if}
										required />
									<span>{translate key="mjcp.authorForm.consentText"}</span>
								</label>
								<small class="mjcp-muted">{translate key="mjcp.authorForm.consentWithdrawNote"}</small>
							</div>

							<div class="mjcp-form-actions">
								<button type="submit" class="mjcp-btn mjcp-btn--primary">
									{translate key="mjcp.authorForm.submit"}
								</button>
							</div>
						</form>
					{/if}
				</section>

				{* ============================================================= *}
				{* CARD 2: LOA / Letter of Acceptance                            *}
				{* ============================================================= *}
				<section class="mjcp-card mjcp-author-card">
					{if $detail.payment.status == 'paid' && $detail.loa.exists}
						<div class="mjcp-author-card__head">
							<h2 class="mjcp-box-title">{translate key="mjcp.authorForm.section2Title"}</h2>
							<span class="mjcp-badge mjcp-badge-success">{translate key="mjcp.loa.status.available"}</span>
						</div>
						<dl class="mjcp-kv-list">
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.loa.numberLabel"}</dt>
								<dd><strong>{$detail.loa.loaNumber|escape}</strong></dd>
							</div>
						</dl>
						<div class="mjcp-form-actions">
							<a href="{$downloadLoaUrl|escape}" class="mjcp-btn mjcp-btn--primary">
								{translate key="mjcp.loa.download"}
							</a>
							<a href="{$loaValidationUrlFull|escape}" target="_blank" class="mjcp-btn mjcp-btn--primary">
								{translate key="mjcp.loa.validateBtn"}
							</a>
						</div>
						<p class="mjcp-muted">{translate key="mjcp.authorForm.loaValidationHint"}</p>
					{elseif $detail.payment.status == 'paid' && !$detail.loa.exists}
						<div class="mjcp-author-card__head">
							<h2 class="mjcp-box-title">{translate key="mjcp.authorForm.section2Title"}</h2>
							<span class="mjcp-badge mjcp-badge-warning">{translate key="mjcp.authorForm.loaStatusPendingUpload"}</span>
						</div>
						<p class="mjcp-muted">{translate key="mjcp.authorForm.loaPendingUploadNote"}</p>
						<div class="mjcp-form-actions">
							<button type="button" class="mjcp-btn mjcp-btn--disabled" disabled>{translate key="mjcp.loa.download"}</button>
							<button type="button" class="mjcp-btn mjcp-btn--disabled" disabled>{translate key="mjcp.loa.validateBtn"}</button>
						</div>
					{else}
						<div class="mjcp-author-card__head">
							<h2 class="mjcp-box-title">{translate key="mjcp.authorForm.section2Title"}</h2>
							<span class="mjcp-badge mjcp-badge-warning">{translate key="mjcp.loa.status.locked"}</span>
						</div>
						<p class="mjcp-muted">{translate key="mjcp.authorForm.loaLockedNote"}</p>
						<div class="mjcp-form-actions">
							<button type="button" class="mjcp-btn mjcp-btn--disabled" disabled>{translate key="mjcp.loa.download"}</button>
							<button type="button" class="mjcp-btn mjcp-btn--disabled" disabled>{translate key="mjcp.loa.validateBtn"}</button>
						</div>
					{/if}
				</section>

				{* ============================================================= *}
				{* CARD 3: Publication Link                                      *}
				{* ============================================================= *}
				<section class="mjcp-card mjcp-author-card">
					{if $detail.isPublished}
						<div class="mjcp-author-card__head">
							<h2 class="mjcp-box-title">{translate key="mjcp.authorForm.section3Title"}</h2>
							<span class="mjcp-badge mjcp-badge-success">{translate key="mjcp.publication.status.published"}</span>
						</div>
						<dl class="mjcp-kv-list">
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.authorForm.articleLinkLabel"}</dt>
								<dd>
									<a href="{$detail.publicationUrl|escape}" target="_blank" class="mjcp-link">
										{$detail.publicationUrl|escape}
									</a>
								</dd>
							</div>
							{if $detail.doi}
								<div class="mjcp-kv-row">
									<dt>{translate key="mjcp.authorForm.doiLabel"}</dt>
									<dd>
										<a href="https://doi.org/{$detail.doi|escape:'url'}" target="_blank" class="mjcp-link">
											{$detail.doi|escape}
										</a>
									</dd>
								</div>
							{/if}
						</dl>
					{else}
						<div class="mjcp-author-card__head">
							<h2 class="mjcp-box-title">{translate key="mjcp.authorForm.section3Title"}</h2>
							<span class="mjcp-badge mjcp-badge-warning">{translate key="mjcp.publication.status.unpublished"}</span>
						</div>
						<p class="mjcp-muted">{translate key="mjcp.authorForm.publicationPendingNote"}</p>
					{/if}
				</section>

				<p class="mjcp-footnote">{translate key="mjcp.authorForm.footerNote"}</p>
			{/if}
		</main>
	</div>
</body>
</html>
