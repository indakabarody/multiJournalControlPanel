{**
 * templates/settings/index.tpl
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
	data-active-tab="settings"
	data-sync-url="{$navUrls.sync|escape}"
	data-add-holiday-url="{$navUrls.addHoliday|escape}"
	data-delete-holiday-url="{$navUrls.deleteHoliday|escape}"
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
				<a href="{$navUrls.payments|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.payments"}</a>
			{/if}
			{if $perms.canManageAuthorLinks}
				<a href="{$navUrls.authorLinks|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.authorLinks"}</a>
			{/if}
			{if $perms.canViewDashboard}
				<a href="{$navUrls.testimonials|escape}" class="mjcp-nav-item">{translate key="mjcp.nav.testimonials"}</a>
			{/if}
			{if $perms.canManageSettings}
				<a href="{$navUrls.settings|escape}" class="mjcp-nav-item mjcp-nav-item--active">{translate key="mjcp.nav.settings"}</a>
			{/if}
		</div>
	</nav>

	<div class="mjcp-dashboard">
		<div class="mjcp-header">
			<div>
				<h1 class="mjcp-title">{translate key="mjcp.settings.title"}</h1>
				<p class="mjcp-subtitle">{translate key="mjcp.settings.subtitle"}</p>
			</div>
		</div>

		<form method="POST" action="{$navUrls.saveSettings|escape}" class="mjcp-settings-form">
			<input type="hidden" name="csrfToken" value="{$csrfToken|escape}" />

			{* 2x3 Cards Grid *}
			<div class="mjcp-three-cols">
				{* Card 1: Stage time thresholds *}
				<div class="mjcp-card">
					<h2 class="mjcp-box-title">{translate key="mjcp.settings.thresholdsHeading"}</h2>
					<div class="mjcp-form-group">
						<label for="mjcpHouseName">{translate key="mjcp.settings.houseNameLabel"}</label>
						<input type="text" id="mjcpHouseName" name="publishingHouseName" class="mjcp-input"
							value="{$houseName|escape}" />
					</div>
					<div class="mjcp-form-group">
						<label for="mjcpRound1Limit">{translate key="mjcp.settings.round1LimitLabel"}</label>
						<input type="number" id="mjcpRound1Limit" name="round1Limit" min="14" max="21" class="mjcp-input"
							value="{$thresholds.round1Limit|escape}" />
					</div>
					<div class="mjcp-form-group">
						<label for="mjcpRound2Limit">{translate key="mjcp.settings.round2LimitLabel"}</label>
						<input type="number" id="mjcpRound2Limit" name="round2Limit" min="1" max="60" class="mjcp-input"
							value="{$thresholds.round2Limit|escape}" />
					</div>
					<div class="mjcp-form-group">
						<label for="mjcpCeWarn">{translate key="mjcp.settings.copyeditingWarningLabel"}</label>
						<input type="number" id="mjcpCeWarn" name="copyeditingWarning" min="1" max="60" class="mjcp-input"
							value="{$thresholds.copyeditingWarning|escape}" />
					</div>
				</div>

				{* Card 2: Access and roles *}
				<div class="mjcp-card">
					<h2 class="mjcp-box-title">{translate key="mjcp.settings.rolesHeading"}</h2>
					<dl class="mjcp-kv-list mjcp-kv-list--compact">
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.settings.role.director"}</dt>
							<dd>{translate key="mjcp.settings.role.directorDesc"}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.settings.role.jm"}</dt>
							<dd>{translate key="mjcp.settings.role.jmDesc"}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.settings.role.finance"}</dt>
							<dd>{translate key="mjcp.settings.role.financeDesc"}</dd>
						</div>
						<div class="mjcp-kv-row">
							<dt>{translate key="mjcp.settings.role.technical"}</dt>
							<dd>{translate key="mjcp.settings.role.technicalDesc"}</dd>
						</div>
					</dl>

					<div class="mjcp-form-group">
						<label for="mjcpPaymentUsers">{translate key="mjcp.settings.paymentAccessUsersLabel"}</label>
						<input type="text" id="mjcpPaymentUsers" name="paymentAccessUsernames" class="mjcp-input"
							value="{$paymentUsersCsv|escape}"
							placeholder="{translate|escape key="mjcp.settings.usernamesPlaceholder"}" />
					</div>

					<div class="mjcp-form-group">
						<label for="mjcpFinanceUsers">{translate key="mjcp.settings.role.finance"} ({translate key="mjcp.settings.usernamesLabel"})</label>
						<input type="text" id="mjcpFinanceUsers" name="financeUsernames" class="mjcp-input"
							value="{$financeUsersCsv|escape}"
							placeholder="{translate|escape key="mjcp.settings.usernamesPlaceholder"}" />
					</div>

					<div class="mjcp-form-group">
						<label for="mjcpJmUsers">{translate key="mjcp.settings.role.jm"} ({translate key="mjcp.settings.usernamesLabel"})</label>
						<input type="text" id="mjcpJmUsers" name="jmUsernames" class="mjcp-input"
							value="{$jmUsersCsv|escape}"
							placeholder="{translate|escape key="mjcp.settings.usernamesPlaceholder"}" />
					</div>

					<div class="mjcp-form-group">
						<label for="mjcpDirectorUsers">{translate key="mjcp.settings.role.director"} ({translate key="mjcp.settings.usernamesLabel"})</label>
						<input type="text" id="mjcpDirectorUsers" name="directorUsernames" class="mjcp-input"
							value="{$directorUsersCsv|escape}"
							placeholder="{translate|escape key="mjcp.settings.usernamesPlaceholder"}" />
					</div>

					<div class="mjcp-form-group">
						<label for="mjcpTechnicalUsers">{translate key="mjcp.settings.role.technical"} ({translate key="mjcp.settings.usernamesLabel"})</label>
						<input type="text" id="mjcpTechnicalUsers" name="technicalUsernames" class="mjcp-input"
							value="{$technicalUsersCsv|escape}"
							placeholder="{translate|escape key="mjcp.settings.usernamesPlaceholder"}" />
					</div>
				</div>

				{* Card 3: Author form *}
				<div class="mjcp-card">
					<h2 class="mjcp-box-title">{translate key="mjcp.settings.authorFormHeading"}</h2>
					<div class="mjcp-form-group">
						<label for="mjcpTokenExpDays">{translate key="mjcp.settings.tokenExpDaysLabel"}</label>
						<input type="number" id="mjcpTokenExpDays" name="tokenExpirationDays" min="1" max="365" class="mjcp-input"
							value="{$tokenExpirationDays|escape}" />
					</div>
					<div class="mjcp-form-group">
						<label for="mjcpMaxUploadMb">{translate key="mjcp.settings.maxUploadSizeLabel"}</label>
						<input type="number" id="mjcpMaxUploadMb" name="maxUploadSizeMb" min="1" max="10" class="mjcp-input"
							value="{$maxUploadSizeMb|escape}" />
					</div>
					<div class="mjcp-form-group">
						<label for="mjcpLoaValUrl">{translate key="mjcp.settings.loaValidationUrlLabel"}</label>
						<input type="text" id="mjcpLoaValUrl" name="loaValidationUrl" class="mjcp-input"
							value="{$loaValidationUrl|escape}" />
					</div>
					<div class="mjcp-form-group">
						<label class="mjcp-checkbox-label">
							<input type="checkbox" name="enableLangId" value="1" {if $enableLangId}checked{/if} />
							<span>{translate key="mjcp.common.langIndonesia"}</span>
						</label>
						<label class="mjcp-checkbox-label">
							<input type="checkbox" name="enableLangEn" value="1" {if $enableLangEn}checked{/if} />
							<span>{translate key="mjcp.common.langEnglish"}</span>
						</label>
					</div>
				</div>

				{* Card 4: Issue quota per journal *}
				<div class="mjcp-card">
					<h2 class="mjcp-box-title">{translate key="mjcp.settings.quotasHeading"}</h2>
					<table class="mjcp-table mjcp-table--compact">
						<thead>
							<tr>
								<th>{translate key="mjcp.table.journal"}</th>
								<th>{translate key="mjcp.settings.quotaPerIssue"}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$journalQuotasList item="jq"}
								<tr>
									<td>{$jq.journalName|escape}</td>
									<td>
										<input type="number" name="journalQuotas[{$jq.contextId|escape}]" min="1" max="200"
											class="mjcp-input mjcp-input--sm" value="{$jq.quota|escape}" />
									</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>

				{* Card 5: Working Days & Holidays *}
				<div class="mjcp-card">
					<h2 class="mjcp-box-title">{translate key="mjcp.settings.holidaysHeading"}</h2>

					<div class="mjcp-form-group">
						<label>{translate key="mjcp.settings.workingDaysLabel"}</label>
						<div class="mjcp-weekdays-grid">
							{foreach from=$weekDaysList item="wd"}
								<label class="mjcp-checkbox-label">
									<input type="checkbox" name="workingDays[]" value="{$wd.dayNum|escape}" {if $wd.checked}checked{/if} />
									<span>{$wd.label|escape}</span>
								</label>
							{/foreach}
						</div>
						<small class="mjcp-muted">{translate key="mjcp.settings.workingDaysHint"}</small>
					</div>

					<div class="mjcp-history-section">
						{if empty($holidays)}
							<p class="mjcp-muted">{translate key="mjcp.settings.noHolidays"}</p>
						{else}
							<ul class="mjcp-history-list">
								{foreach from=$holidays item="hol"}
									<li>
										<strong>{$hol.date|escape}</strong> &ndash; {$hol.description|escape}
										{if $hol.id > 0}
											<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-btn--xs mjcp-delete-holiday-btn"
												data-holiday-id="{$hol.id|escape}">
												{translate key="mjcp.common.delete"}
											</button>
										{/if}
									</li>
								{/foreach}
							</ul>
						{/if}
						<div class="mjcp-inline-form mjcp-mt-sm">
							<input type="date" id="mjcpNewHolidayDate" class="mjcp-input mjcp-input--sm" aria-label="{translate|escape key="mjcp.common.date"}" />
							<input type="text" id="mjcpNewHolidayDesc" class="mjcp-input mjcp-input--sm"
								placeholder="{translate|escape key="mjcp.settings.holidayDescPlaceholder"}" />
							<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-btn--sm" id="mjcpAddHolidayBtn">
								{translate key="mjcp.settings.addHolidayBtn"}
							</button>
						</div>
					</div>
				</div>

				{* Card 6: Synchronization *}
				<div class="mjcp-card">
					<h2 class="mjcp-box-title">{translate key="mjcp.settings.syncHeading"}</h2>
					<p class="mjcp-muted">
						{translate key="mjcp.settings.lastSyncInfo" time=$lastSyncFormatted|escape status=$lastSyncStatus|escape}
					</p>
					<div class="mjcp-form-actions">
						<button type="button" class="mjcp-btn mjcp-btn--primary" id="mjcpTriggerSyncNowBtn">
							{translate key="mjcp.settings.syncNowBtn"}
						</button>
					</div>
				</div>
			</div>

			<div class="mjcp-form-actions mjcp-mt-md">
				<button type="submit" class="mjcp-btn mjcp-btn--primary">
					{translate key="mjcp.common.save"} {translate key="mjcp.settings.title"}
				</button>
			</div>
		</form>

		{* Audit Trail Table *}
		{if !empty($auditLogs)}
			<div class="mjcp-panel-box mjcp-mt-md">
				<h2 class="mjcp-box-title">{translate key="mjcp.settings.auditHeading"}</h2>
				<div class="mjcp-table-responsive">
					<table class="mjcp-table">
						<thead>
							<tr>
								<th>{translate key="mjcp.audit.timestamp"}</th>
								<th>{translate key="mjcp.audit.user"}</th>
								<th>{translate key="mjcp.audit.action"}</th>
								<th>{translate key="mjcp.audit.entity"}</th>
								<th>{translate key="mjcp.audit.details"}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$auditLogs item="al"}
								<tr>
									<td>{$al.created_at|escape}</td>
									<td>{$al.username|default:"-"|escape}</td>
									<td><code>{$al.action|escape}</code></td>
									<td>{$al.entity_type|default:"-"|escape}</td>
									<td><code>{$al.new_value|default:"-"|escape}</code></td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			</div>
		{/if}
	</div>
</div>
{/block}
