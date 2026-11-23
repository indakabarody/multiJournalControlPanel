{**
 * templates/dashboard/index.tpl
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
	data-active-tab="{$activeTab|escape}"
	data-export-url="{$navUrls.export|escape}"
	data-drilldown-url="{$navUrls.drilldown|escape}"
	data-sync-url="{$navUrls.sync|escape}"
	data-generate-token-url="{$navUrls.generateToken|escape}"
	data-revoke-token-url="{$navUrls.revokeToken|escape}"
	data-toggle-testimonial-url="{$navUrls.toggleTestimonialConsent|escape}"
	data-copied-text="{translate|escape key="mjcp.links.copied"}"
	data-select-one-text="{translate|escape key="mjcp.links.selectAtLeastOne"}"
	data-confirm-regenerate-text="{translate|escape key="mjcp.links.confirmRegenerate"}"
	data-confirm-deactivate-text="{translate|escape key="mjcp.links.confirmDeactivate"}"
	data-csrf-token="{$csrfToken|escape}">

	{* Top Navigation Bar *}
	<nav class="mjcp-topbar" aria-label="{translate|escape key="plugins.generic.multiJournalControlPanel.displayName"}">
		<div class="mjcp-topbar__brand">
			{translate key="mjcp.nav.brand" house=$houseName|escape}
		</div>
		<div class="mjcp-topbar__links">
			{if $perms.canViewDashboard}
				<a href="{$navUrls.dashboard|escape}" class="mjcp-nav-item {if $activeTab == 'dashboard'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.dashboard"}
				</a>
				<a href="{$navUrls.bottlenecks|escape}" class="mjcp-nav-item {if $activeTab == 'bottlenecks'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.bottlenecks"}
				</a>
				<a href="{$navUrls.journals|escape}" class="mjcp-nav-item {if $activeTab == 'journals'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.journals"}
				</a>
				<a href="{$navUrls.issues|escape}" class="mjcp-nav-item {if $activeTab == 'issues'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.issues"}
				</a>
				<a href="{$navUrls.performance|escape}" class="mjcp-nav-item {if $activeTab == 'performance'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.performance"}
				</a>
			{/if}
			{if $perms.canViewPayment}
				<a href="{$navUrls.payments|escape}" class="mjcp-nav-item {if $activeTab == 'payments'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.payments"}
				</a>
			{/if}
			{if $perms.canManageAuthorLinks}
				<a href="{$navUrls.authorLinks|escape}" class="mjcp-nav-item {if $activeTab == 'authorLinks'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.authorLinks"}
				</a>
			{/if}
			{if $perms.canViewDashboard}
				<a href="{$navUrls.testimonials|escape}" class="mjcp-nav-item {if $activeTab == 'testimonials'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.testimonials"}
				</a>
			{/if}
			{if $perms.canManageSettings}
				<a href="{$navUrls.settings|escape}" class="mjcp-nav-item {if $activeTab == 'settings'}mjcp-nav-item--active{/if}">
					{translate key="mjcp.nav.settings"}
				</a>
			{/if}
		</div>
	</nav>

	<div class="mjcp-dashboard">
		{* ===================================================================== *}
		{* TAB 1: DASHBOARD (Summary Cards + Editorial Pipeline)                 *}
		{* ===================================================================== *}
		{if $activeTab == 'dashboard'}
			<div class="mjcp-header">
				<div>
					<h1 class="mjcp-title">{translate key="mjcp.dashboard.title" house=$houseName|escape}</h1>
					<p class="mjcp-subtitle">
						{translate key="mjcp.dashboard.subtitle" house=$houseName|escape}
						&middot; {translate key="mjcp.common.lastUpdated" time=$summary.lastUpdatedFormatted|escape}
					</p>
				</div>
				{if $perms.canExportGeneral}
					<div class="mjcp-export-group">
						<button type="button" class="mjcp-btn mjcp-btn--primary mjcp-export-trigger" data-module="pipeline" data-format="xlsx">
							{translate key="mjcp.export.excel"}
						</button>
						<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="pipeline" data-format="csv">
							{translate key="mjcp.export.csv"}
						</button>
						<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="pipeline" data-format="pdf">
							{translate key="mjcp.export.pdf"}
						</button>
					</div>
				{/if}
			</div>

			<form id="mjcpFilterForm" class="mjcp-toolbar" method="GET" action="{$navUrls.dashboard|escape}">
				<div class="mjcp-filter">
					<select name="contextId" class="mjcp-select mjcp-auto-submit" aria-label="{translate|escape key="mjcp.table.journal"}">
						<option value="all" {if $activeFilters.contextId == 'all'}selected{/if}>
							{translate key="mjcp.common.allJournals" house=$houseName|escape}
						</option>
						{foreach from=$journalsList key="jId" item="jInfo"}
							<option value="{$jId|escape}" {if $activeFilters.contextId == $jId}selected{/if}>{$jInfo.name|escape}</option>
						{/foreach}
					</select>

					<select name="period" class="mjcp-select mjcp-auto-submit" aria-label="{translate|escape key="mjcp.common.period"}">
						<option value="all" {if $activeFilters.period == 'all'}selected{/if}>{translate key="mjcp.common.periodAll"}</option>
						<option value="30d" {if $activeFilters.period == '30d'}selected{/if}>{translate key="mjcp.common.period30d"}</option>
						<option value="90d" {if $activeFilters.period == '90d'}selected{/if}>{translate key="mjcp.common.period90d"}</option>
						<option value="180d" {if $activeFilters.period == '180d'}selected{/if}>{translate key="mjcp.common.period180d"}</option>
						<option value="365d" {if $activeFilters.period == '365d'}selected{/if}>{translate key="mjcp.common.period365d"}</option>
					</select>

					<select name="sort" class="mjcp-select mjcp-auto-submit" aria-label="{translate|escape key="mjcp.pipeline.sortLabel"}">
						<option value="" {if $activeFilters.sort == ''}selected{/if}>{translate key="mjcp.pipeline.sortLabel"}</option>
						<option value="name_asc" {if $activeFilters.sort == 'name_asc'}selected{/if}>{translate key="mjcp.pipeline.sort.nameAsc"}</option>
						<option value="name_desc" {if $activeFilters.sort == 'name_desc'}selected{/if}>{translate key="mjcp.pipeline.sort.nameDesc"}</option>
						<option value="all_desc" {if $activeFilters.sort == 'all_desc'}selected{/if}>{translate key="mjcp.pipeline.sort.allDesc"}</option>
						<option value="overdue_desc" {if $activeFilters.sort == 'overdue_desc'}selected{/if}>{translate key="mjcp.pipeline.sort.overdueDesc"}</option>
					</select>

					<input type="search" name="search" class="mjcp-input"
						value="{$activeFilters.search|escape}"
						placeholder="{translate|escape key="mjcp.common.search"}" />
				</div>
			</form>

			{* 5 Summary Cards *}
			<div class="mjcp-cards-grid mjcp-cards-grid--five">
				<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillCard=all" class="mjcp-card mjcp-card--clickable">
					<div class="mjcp-card-title">{translate key="mjcp.dashboard.totalArticles"}</div>
					<div class="mjcp-card-value">{$summary.totalArticles|number_format:0:",":"."}</div>
				</a>
				<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillCard=active" class="mjcp-card mjcp-card--clickable">
					<div class="mjcp-card-title">{translate key="mjcp.dashboard.activeSubmissions"}</div>
					<div class="mjcp-card-value">{$summary.activeSubmissions|number_format:0:",":"."}</div>
				</a>
				<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillCard=overdue" class="mjcp-card mjcp-card--clickable">
					<div class="mjcp-card-title">{translate key="mjcp.dashboard.overdueSubmissions"}</div>
					<div class="mjcp-card-value mjcp-card-value--danger">{$summary.overdueSubmissions|number_format:0:",":"."}</div>
				</a>
				<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillCard=ready" class="mjcp-card mjcp-card--clickable">
					<div class="mjcp-card-title">{translate key="mjcp.dashboard.readyToPublish"}</div>
					<div class="mjcp-card-value">{$summary.readyToPublish|number_format:0:",":"."}</div>
				</a>
				<a href="{$navUrls.issues|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}" class="mjcp-card mjcp-card--clickable">
					<div class="mjcp-card-title">{translate key="mjcp.dashboard.issuesBelowQuota"}</div>
					<div class="mjcp-card-value mjcp-card-value--danger">{$summary.issuesBelowQuota|number_format:0:",":"."}</div>
				</a>
			</div>

			{* Pipeline per Journal Table *}
			<div class="mjcp-panel-box">
				<h2 class="mjcp-box-title">{translate key="mjcp.pipeline.title"}</h2>
				<div class="mjcp-table-responsive">
					<table class="mjcp-table">
						<thead>
							<tr>
								<th>{translate key="mjcp.table.journal"}</th>
								<th>{translate key="mjcp.pipeline.allArticle"}</th>
								<th>{translate key="mjcp.pipeline.submission"}</th>
								<th>{translate key="mjcp.pipeline.review"}</th>
								<th>{translate key="mjcp.pipeline.revision"}</th>
								<th>{translate key="mjcp.pipeline.copyediting"}</th>
								<th>{translate key="mjcp.pipeline.production"}</th>
								<th>{translate key="mjcp.pipeline.published"}</th>
								<th>{translate key="mjcp.pipeline.declined"}</th>
							</tr>
						</thead>
						<tbody>
							{if empty($pipeline)}
								<tr>
									<td colspan="9" class="mjcp-empty-cell">{translate key="mjcp.common.noData"}</td>
								</tr>
							{else}
								{foreach from=$pipeline item="row"}
									<tr>
										<td><strong>{$row.journalName|escape}</strong></td>
										<td>
											<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillContextId={$row.contextId|escape}&amp;drillBucket=all" class="mjcp-cell-link mjcp-cell-link--bold">
												{$row.allArticle|escape}
											</a>
										</td>
										<td>
											<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillContextId={$row.contextId|escape}&amp;drillBucket=submission" class="mjcp-cell-link">
												{$row.submission|escape}
											</a>
										</td>
										<td>
											<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillContextId={$row.contextId|escape}&amp;drillBucket=review"
												class="mjcp-cell-link {if $row.reviewDelayBadge == 'danger'}mjcp-badge mjcp-badge-danger{elseif $row.reviewDelayBadge == 'warning'}mjcp-badge mjcp-badge-warning{/if}">
												{$row.review|escape}
											</a>
										</td>
										<td>
											<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillContextId={$row.contextId|escape}&amp;drillBucket=revision"
												class="mjcp-cell-link {if $row.revisionDelayBadge == 'danger'}mjcp-badge mjcp-badge-danger{elseif $row.revisionDelayBadge == 'warning'}mjcp-badge mjcp-badge-warning{/if}">
												{$row.revision|escape}
											</a>
										</td>
										<td>
											<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillContextId={$row.contextId|escape}&amp;drillBucket=copyediting"
												class="mjcp-cell-link {if $row.copyeditingDelayBadge == 'danger'}mjcp-badge mjcp-badge-danger{elseif $row.copyeditingDelayBadge == 'warning'}mjcp-badge mjcp-badge-warning{/if}">
												{$row.copyediting|escape}
											</a>
										</td>
										<td>
											<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillContextId={$row.contextId|escape}&amp;drillBucket=production" class="mjcp-cell-link">
												{$row.production|escape}
											</a>
										</td>
										<td>
											<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillContextId={$row.contextId|escape}&amp;drillBucket=published" class="mjcp-cell-link">
												{$row.published|escape}
											</a>
										</td>
										<td>
											<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}&amp;drillContextId={$row.contextId|escape}&amp;drillBucket=declined" class="mjcp-cell-link">
												{$row.declined|escape}
											</a>
										</td>
									</tr>
								{/foreach}
							{/if}
						</tbody>
					</table>
				</div>
				<p class="mjcp-footnote">{translate key="mjcp.pipeline.footnote"}</p>
			</div>

			{* Drilldown Supporting Articles Section *}
			{if !empty($drilldownItems)}
				<div class="mjcp-panel-box mjcp-drilldown-box">
					<div class="mjcp-box-header">
						<h2 class="mjcp-box-title">{translate key="mjcp.dashboard.drilldownHeading"} ({$drilldownItems|@count})</h2>
						<a href="{$navUrls.dashboard|escape}{$querySep|escape}contextId={$activeFilters.contextId|escape}&amp;period={$activeFilters.period|escape}" class="mjcp-btn mjcp-btn--outline mjcp-btn--sm">
							{translate key="mjcp.common.close"}
						</a>
					</div>
					<div class="mjcp-table-responsive">
						<table class="mjcp-table">
							<thead>
								<tr>
									<th>{translate key="mjcp.table.code"}</th>
									<th>{translate key="mjcp.table.title"}</th>
									<th>{translate key="mjcp.table.journal"}</th>
									<th>{translate key="mjcp.table.stage"}</th>
									<th>{translate key="mjcp.table.stageEntry"}</th>
									<th>{translate key="mjcp.common.action"}</th>
								</tr>
							</thead>
							<tbody>
								{foreach from=$drilldownItems item="dItem"}
									<tr>
										<td>{$dItem.submissionId|escape}</td>
										<td>{$dItem.title|escape}</td>
										<td>{$dItem.journalName|escape}</td>
										<td>{$dItem.stageLabel|escape}</td>
										<td>{$dItem.stageEntryDate|escape}</td>
										<td>
											<a href="{$dItem.ojsUrl|escape}" class="mjcp-link">
												{translate key="mjcp.common.openInOjs"}
											</a>
										</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				</div>
			{/if}

		{* ===================================================================== *}
		{* TAB 2: BOTTLENECKS (Bottleneck Monitoring)                            *}
		{* ===================================================================== *}
		{elseif $activeTab == 'bottlenecks'}
			<div class="mjcp-header">
				<div>
					<h1 class="mjcp-title">{translate key="mjcp.bottleneck.title"}</h1>
					<p class="mjcp-subtitle">{translate key="mjcp.bottleneck.subtitle"}</p>
				</div>
				{if $perms.canExportGeneral}
					<div class="mjcp-export-group">
						<button type="button" class="mjcp-btn mjcp-btn--primary mjcp-export-trigger" data-module="bottleneck" data-format="xlsx">
							{translate key="mjcp.export.excel"}
						</button>
						<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="bottleneck" data-format="csv">
							{translate key="mjcp.export.csv"}
						</button>
						<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="bottleneck" data-format="pdf">
							{translate key="mjcp.export.pdf"}
						</button>
					</div>
				{/if}
			</div>

			<form id="mjcpFilterForm" class="mjcp-toolbar" method="GET" action="{$navUrls.bottlenecks|escape}">
				<div class="mjcp-filter">
					<select name="contextId" class="mjcp-select mjcp-auto-submit" aria-label="{translate|escape key="mjcp.table.journal"}">
						<option value="all" {if $activeFilters.contextId == 'all'}selected{/if}>
							{translate key="mjcp.common.allJournals" house=$houseName|escape}
						</option>
						{foreach from=$journalsList key="jId" item="jInfo"}
							<option value="{$jId|escape}" {if $activeFilters.contextId == $jId}selected{/if}>{$jInfo.name|escape}</option>
						{/foreach}
					</select>

					<select name="period" class="mjcp-select mjcp-auto-submit" aria-label="{translate|escape key="mjcp.common.period"}">
						<option value="all" {if $activeFilters.period == 'all'}selected{/if}>{translate key="mjcp.common.periodAll"}</option>
						<option value="30d" {if $activeFilters.period == '30d'}selected{/if}>{translate key="mjcp.common.period30d"}</option>
						<option value="90d" {if $activeFilters.period == '90d'}selected{/if}>{translate key="mjcp.common.period90d"}</option>
						<option value="180d" {if $activeFilters.period == '180d'}selected{/if}>{translate key="mjcp.common.period180d"}</option>
						<option value="365d" {if $activeFilters.period == '365d'}selected{/if}>{translate key="mjcp.common.period365d"}</option>
					</select>

					<input type="search" name="search" class="mjcp-input"
						value="{$activeFilters.search|escape}"
						placeholder="{translate|escape key="mjcp.common.search"}" />
				</div>
			</form>

			<div class="mjcp-panel-box">
				<div class="mjcp-table-responsive">
					<table class="mjcp-table">
						<thead>
							<tr>
								<th>{translate key="mjcp.table.code"}</th>
								<th>{translate key="mjcp.table.title"}</th>
								<th>{translate key="mjcp.table.journal"}</th>
								<th>{translate key="mjcp.table.stage"}</th>
								<th>{translate key="mjcp.table.stageEntry"}</th>
								<th>{translate key="mjcp.table.duration"}</th>
								<th>{translate key="mjcp.table.overdue"}</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{if empty($bottleneckItems)}
								<tr>
									<td colspan="8" class="mjcp-empty-cell">{translate key="mjcp.common.noData"}</td>
								</tr>
							{else}
								{foreach from=$bottleneckItems item="bItem"}
									<tr>
										<td>{$bItem.submissionId|escape}</td>
										<td>{$bItem.title|escape}</td>
										<td>{$bItem.journalName|escape}</td>
										<td>{$bItem.stageLabel|escape}</td>
										<td>{$bItem.stageEntryFormatted|escape}</td>
										<td>{$bItem.durationText|escape}</td>
										<td>
											<span class="mjcp-badge mjcp-badge-{$bItem.badgeType|escape}">
												{$bItem.delayBadgeText|escape}
											</span>
										</td>
										<td>
											<a href="{$bItem.ojsUrl|escape}" class="mjcp-link">
												{translate key="mjcp.common.openInOjs"}
											</a>
										</td>
									</tr>
								{/foreach}
							{/if}
						</tbody>
					</table>
				</div>
			</div>
			<p class="mjcp-footnote">{translate key="mjcp.bottleneck.footnote"}</p>

		{* ===================================================================== *}
		{* TAB 3: CONNECTED JOURNALS                                             *}
		{* ===================================================================== *}
		{elseif $activeTab == 'journals'}
			<div class="mjcp-header">
				<div>
					<h1 class="mjcp-title">{translate key="mjcp.journals.title"}</h1>
					<p class="mjcp-subtitle">{translate key="mjcp.journals.subtitle" house=$houseName|escape}</p>
				</div>
				{if $perms.canTriggerSync}
					<div>
						<form method="POST" action="{$navUrls.sync|escape}" class="mjcp-inline-form">
							<input type="hidden" name="csrfToken" value="{$csrfToken|escape}" />
							<input type="hidden" name="returnTab" value="journals" />
							<button type="submit" class="mjcp-btn mjcp-btn--primary">
								{translate key="mjcp.settings.syncNowBtn"}
							</button>
						</form>
					</div>
				{/if}
			</div>

			<div class="mjcp-panel-box">
				<div class="mjcp-table-responsive">
					<table class="mjcp-table">
						<thead>
							<tr>
								<th>{translate key="mjcp.table.journal"}</th>
								<th>{translate key="mjcp.journals.lastSync"}</th>
								<th>{translate key="mjcp.common.status"}</th>
								<th>{translate key="mjcp.journals.activeSubmissions"}</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{if empty($connectedJournals)}
								<tr>
									<td colspan="5" class="mjcp-empty-cell">{translate key="mjcp.common.noData"}</td>
								</tr>
							{else}
								{foreach from=$connectedJournals item="cj"}
									<tr>
										<td>
											<strong>{$cj.journalName|escape}</strong>
										</td>
										<td>{$cj.lastSyncFormatted|escape}</td>
										<td>
											<span class="mjcp-badge mjcp-badge-{$cj.badgeType|escape}">
												{$cj.syncStatusLabel|escape}
											</span>
										</td>
										<td>{$cj.activeSubmissions|escape}</td>
										<td class="mjcp-action-cell">
											<a href="{$cj.openOjsUrl|escape}" class="mjcp-link">
												{translate key="mjcp.common.openOjs"}
											</a>
											{if $perms.canTriggerSync}
												<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-btn--xs mjcp-sync-journal-btn" data-context-id="{$cj.contextId|escape}">
													{translate key="mjcp.journals.syncNow"}
												</button>
											{/if}
										</td>
									</tr>
								{/foreach}
							{/if}
						</tbody>
					</table>
				</div>
			</div>
			<p class="mjcp-footnote">{translate key="mjcp.journals.footnote"}</p>

		{* ===================================================================== *}
		{* TAB 4: ISSUE HEALTH                                                   *}
		{* ===================================================================== *}
		{elseif $activeTab == 'issues'}
			<div class="mjcp-header">
				<div>
					<h1 class="mjcp-title">{translate key="mjcp.issue.title"}</h1>
					<p class="mjcp-subtitle">{translate key="mjcp.issue.subtitle"}</p>
				</div>
				{if $perms.canExportGeneral}
					<div class="mjcp-export-group">
						<button type="button" class="mjcp-btn mjcp-btn--primary mjcp-export-trigger" data-module="issue" data-format="xlsx">
							{translate key="mjcp.export.excel"}
						</button>
						<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="issue" data-format="pdf">
							{translate key="mjcp.export.pdf"}
						</button>
					</div>
				{/if}
			</div>

			<div class="mjcp-issue-grid">
				{if empty($issueHealthCards)}
					<div class="mjcp-panel-box">{translate key="mjcp.common.noData"}</div>
				{else}
					{foreach from=$issueHealthCards item="ih"}
						<div class="mjcp-card mjcp-issue-card">
							<div class="mjcp-issue-card__header">
								<span class="mjcp-issue-card__title">{$ih.issueTitle|escape}</span>
								<span class="mjcp-badge mjcp-badge-{$ih.badgeType|escape}">{$ih.badgeLabel|escape}</span>
							</div>
							<div class="mjcp-issue-card__sub">
								{$ih.progressText|escape}
							</div>
							<div class="mjcp-progress-track">
								<div class="mjcp-progress-fill" data-width="{$ih.progressPct|escape}"></div>
							</div>
							<div class="mjcp-issue-card__timeliness">
								<span class="mjcp-muted">{translate key="mjcp.issue.targetDateLabel" date=$ih.targetDateFormatted|escape}</span>
								<span class="mjcp-badge mjcp-badge-{$ih.timelinessBadgeType|escape}">{$ih.timelinessLabel|escape}</span>
							</div>
							<div class="mjcp-issue-card__meta">
								<span>{translate key="mjcp.links.loaStatus"}: {$ih.loaUploadedCount|escape}/{$ih.plottedCount|escape}</span>
								<span>{translate key="mjcp.pipeline.copyediting"}: {$ih.copyeditingDoneCount|escape}/{$ih.plottedCount|escape}</span>
								<span>{translate key="mjcp.pipeline.published"}: {$ih.publishedCount|escape}/{$ih.plottedCount|escape}</span>
							</div>
							{if !empty($ih.articles)}
								<details class="mjcp-issue-articles">
									<summary class="mjcp-issue-articles__summary">
										{translate key="mjcp.issue.articlesToggle" count=$ih.articles|@count}
									</summary>
									<ul class="mjcp-issue-articles__list">
										{foreach from=$ih.articles item="iArt"}
											<li class="mjcp-issue-articles__item">
												<span class="mjcp-issue-articles__title">#{$iArt.submissionId|escape} {$iArt.title|escape}</span>
												<span class="mjcp-issue-articles__badges">
													<span class="mjcp-badge {if $iArt.loaStatus == 'uploaded'}mjcp-badge-success{else}mjcp-badge-warning{/if}">{translate key="mjcp.links.loaStatus"}: {$iArt.loaLabel|escape}</span>
													<span class="mjcp-badge {if $iArt.copyeditingDone}mjcp-badge-success{else}mjcp-badge-warning{/if}">{translate key="mjcp.pipeline.copyediting"}: {$iArt.copyeditingLabel|escape}</span>
													<span class="mjcp-badge {if $iArt.isPublished}mjcp-badge-success{else}mjcp-badge-warning{/if}">{$iArt.publishedLabel|escape}</span>
												</span>
											</li>
										{/foreach}
									</ul>
								</details>
							{/if}
						</div>
					{/foreach}
				{/if}
			</div>
			<p class="mjcp-footnote">{translate key="mjcp.issue.footnote"}</p>

		{* ===================================================================== *}
		{* TAB 5: JOURNAL PERFORMANCE & KPIs                                     *}
		{* ===================================================================== *}
		{elseif $activeTab == 'performance'}
			<div class="mjcp-header">
				<div>
					<h1 class="mjcp-title">{translate key="mjcp.performance.title"}</h1>
					<p class="mjcp-subtitle">{translate key="mjcp.performance.subtitle"}</p>
				</div>
				{if $perms.canExportGeneral}
					<div class="mjcp-export-group">
						<button type="button" class="mjcp-btn mjcp-btn--primary mjcp-export-trigger" data-module="performance" data-format="xlsx">
							{translate key="mjcp.export.excel"}
						</button>
						<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="performance" data-format="pdf">
							{translate key="mjcp.export.pdf"}
						</button>
					</div>
				{/if}
			</div>

			<form id="mjcpFilterForm" class="mjcp-toolbar" method="GET" action="{$navUrls.performance|escape}">
				<div class="mjcp-filter">
					<select name="contextId" class="mjcp-select mjcp-auto-submit" aria-label="{translate|escape key="mjcp.table.journal"}">
						<option value="all" {if $activeFilters.contextId == 'all'}selected{/if}>
							{translate key="mjcp.common.allJournals" house=$houseName|escape}
						</option>
						{foreach from=$journalsList key="jId" item="jInfo"}
							<option value="{$jId|escape}" {if $activeFilters.contextId == $jId}selected{/if}>{$jInfo.name|escape}</option>
						{/foreach}
					</select>

					<select name="period" class="mjcp-select mjcp-auto-submit" aria-label="{translate|escape key="mjcp.common.period"}">
						<option value="all" {if $activeFilters.period == 'all'}selected{/if}>{translate key="mjcp.common.periodAll"}</option>
						<option value="30d" {if $activeFilters.period == '30d'}selected{/if}>{translate key="mjcp.common.period30d"}</option>
						<option value="90d" {if $activeFilters.period == '90d'}selected{/if}>{translate key="mjcp.common.period90d"}</option>
						<option value="180d" {if $activeFilters.period == '180d'}selected{/if}>{translate key="mjcp.common.period180d"}</option>
						<option value="365d" {if $activeFilters.period == '365d'}selected{/if}>{translate key="mjcp.common.period365d"}</option>
					</select>
				</div>
			</form>

			{* Monthly Bar Chart Box *}
			<div class="mjcp-panel-box">
				<h2 class="mjcp-box-title">{translate key="mjcp.performance.chartHeading"}</h2>
				<div class="mjcp-barchart" id="mjcpPerformanceChart" data-trends="{$performance.chartJson|escape}">
					{foreach from=$performance.monthlyTrends item="mt"}
						<div class="mjcp-barchart__group">
							<div class="mjcp-barchart__bars">
								<div class="mjcp-barchart__bar mjcp-barchart__bar--dark"
									data-height="{$mt.submittedHeightPct|escape}"
									title="{translate|escape key="mjcp.performance.submitted"}: {$mt.submitted|escape}">
								</div>
								<div class="mjcp-barchart__bar mjcp-barchart__bar--light"
									data-height="{$mt.publishedHeightPct|escape}"
									title="{translate|escape key="mjcp.performance.published"}: {$mt.published|escape}">
								</div>
							</div>
							<div class="mjcp-barchart__label">{$mt.label|escape}</div>
						</div>
					{/foreach}
				</div>
				<p class="mjcp-footnote">{translate key="mjcp.performance.chartLegend"}</p>
			</div>

			{* KPI Cards *}
			<div class="mjcp-cards-grid mjcp-cards-grid--four">
				<div class="mjcp-card">
					<div class="mjcp-card-title">{translate key="mjcp.performance.avgTurnaround"}</div>
					<div class="mjcp-card-value">{$performance.avgSubmitToPublishDays|escape} {translate key="mjcp.common.days"}</div>
				</div>
				<div class="mjcp-card">
					<div class="mjcp-card-title">{translate key="mjcp.performance.acceptanceRate"}</div>
					<div class="mjcp-card-value">{$performance.acceptanceRate|escape}%</div>
				</div>
				<div class="mjcp-card">
					<div class="mjcp-card-title">{translate key="mjcp.performance.rejectionRate"}</div>
					<div class="mjcp-card-value">{$performance.rejectionRate|escape}%</div>
				</div>
				<div class="mjcp-card">
					<div class="mjcp-card-title">{translate key="mjcp.performance.onTimeRate"}</div>
					<div class="mjcp-card-value">{$performance.onTimeRate|escape}%</div>
				</div>
			</div>

			{* Per-Stage On-Time vs Overdue Breakdown *}
			{if !empty($performance.stageOnTimeStats)}
				<div class="mjcp-panel-box">
					<h2 class="mjcp-box-title">{translate key="mjcp.performance.stageOnTimeHeading"}</h2>
					<div class="mjcp-table-responsive">
						<table class="mjcp-table">
							<thead>
								<tr>
									<th>{translate key="mjcp.table.stage"}</th>
									<th>{translate key="mjcp.performance.onTimeCount"}</th>
									<th>{translate key="mjcp.performance.overdueCount"}</th>
									<th>{translate key="mjcp.performance.onTimePct"}</th>
									<th>{translate key="mjcp.performance.overduePct"}</th>
								</tr>
							</thead>
							<tbody>
								{foreach from=$performance.stageOnTimeStats item="stRow"}
									<tr>
										<td><strong>{$stRow.stageLabel|escape}</strong></td>
										<td>{$stRow.onTimeCount|escape}</td>
										<td>{$stRow.overdueCount|escape}</td>
										<td><span class="mjcp-badge mjcp-badge-success">{$stRow.onTimePct|escape}%</span></td>
										<td><span class="mjcp-badge {if $stRow.overduePct > 0}mjcp-badge-danger{else}mjcp-badge-success{/if}">{$stRow.overduePct|escape}%</span></td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				</div>
			{/if}

			{* Monthly Trend Details Table *}
			{if !empty($performance.monthlyTrends)}
				<div class="mjcp-panel-box">
					<h2 class="mjcp-box-title">{translate key="mjcp.performance.monthlyTableHeading"}</h2>
					<div class="mjcp-table-responsive">
						<table class="mjcp-table">
							<thead>
								<tr>
									<th>{translate key="mjcp.performance.month"}</th>
									<th>{translate key="mjcp.performance.submitted"}</th>
									<th>{translate key="mjcp.performance.accepted"}</th>
									<th>{translate key="mjcp.performance.rejected"}</th>
									<th>{translate key="mjcp.performance.published"}</th>
								</tr>
							</thead>
							<tbody>
								{foreach from=$performance.monthlyTrends item="mtRow"}
									<tr>
										<td><strong>{$mtRow.label|escape} ({$mtRow.ym|escape})</strong></td>
										<td>{$mtRow.submitted|escape}</td>
										<td>{$mtRow.accepted|escape}</td>
										<td>{$mtRow.rejected|escape}</td>
										<td>{$mtRow.published|escape}</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				</div>
			{/if}

			{* Cross-Journal Comparison Table *}
			{if !empty($performance.journalComparison)}
				<div class="mjcp-panel-box">
					<h2 class="mjcp-box-title">{translate key="mjcp.performance.comparisonHeading"}</h2>
					<div class="mjcp-table-responsive">
						<table class="mjcp-table">
							<thead>
								<tr>
									<th>{translate key="mjcp.table.journal"}</th>
									<th>{translate key="mjcp.performance.submitted"}</th>
									<th>{translate key="mjcp.performance.accepted"}</th>
									<th>{translate key="mjcp.performance.rejected"}</th>
									<th>{translate key="mjcp.performance.published"}</th>
									<th>{translate key="mjcp.performance.acceptanceRate"}</th>
									<th>{translate key="mjcp.performance.avgTurnaround"}</th>
									<th>{translate key="mjcp.performance.onTimeRate"}</th>
								</tr>
							</thead>
							<tbody>
								{foreach from=$performance.journalComparison item="jcRow"}
									<tr>
										<td><strong>{$jcRow.journalName|escape}</strong></td>
										<td>{$jcRow.submitted|escape}</td>
										<td>{$jcRow.accepted|escape}</td>
										<td>{$jcRow.rejected|escape}</td>
										<td>{$jcRow.published|escape}</td>
										<td>{$jcRow.acceptanceRate|escape}%</td>
										<td>{$jcRow.avgTurnaroundDays|escape} {translate key="mjcp.common.days"}</td>
										<td>{$jcRow.onTimeRate|escape}%</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				</div>
			{/if}

		{* ===================================================================== *}
		{* TAB 8: AUTHOR FORM LINKS                                              *}
		{* ===================================================================== *}
		{elseif $activeTab == 'authorLinks'}
			<div class="mjcp-header">
				<div>
					<h1 class="mjcp-title">{translate key="mjcp.links.title"}</h1>
					<p class="mjcp-subtitle">{translate key="mjcp.links.subtitle"}</p>
				</div>
			</div>

			<div class="mjcp-toolbar">
				<div class="mjcp-pill-group">
					<button type="button" class="mjcp-btn mjcp-btn--primary" id="mjcpGenerateSelectedTokensBtn">
						{translate key="mjcp.links.generateSelectedBtn"}
					</button>
					<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-export-trigger" data-module="links" data-format="xlsx">
						{translate key="mjcp.links.exportLinksBtn"}
					</button>
					<a href="{$navUrls.authorLinks|escape}{if !$unfilledOnly}{$querySep|escape}unfilledOnly=1&amp;contextId={$activeFilters.contextId|escape}{else}{$querySep|escape}contextId={$activeFilters.contextId|escape}{/if}"
						class="mjcp-btn {if $unfilledOnly}mjcp-btn--primary{else}mjcp-btn--outline{/if}">
						{translate key="mjcp.links.filterUnfilledBtn" count=$unfilledLinksCount}
					</a>
				</div>
				<form id="mjcpFilterForm" method="GET" action="{$navUrls.authorLinks|escape}" class="mjcp-filter">
					{if $unfilledOnly}
						<input type="hidden" name="unfilledOnly" value="1" />
					{/if}
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

			<div class="mjcp-panel-box">
				<div class="mjcp-table-responsive">
					<table class="mjcp-table">
						<thead>
							<tr>
								<th><input type="checkbox" id="mjcpSelectAllLinksCheckbox" aria-label="{translate|escape key="mjcp.common.selectAll"}" /></th>
								<th>{translate key="mjcp.table.code"}</th>
								<th>{translate key="mjcp.table.journal"}</th>
								<th>{translate key="mjcp.table.title"}</th>
								<th>{translate key="mjcp.payment.formStatus"}</th>
								<th>{translate key="mjcp.links.loaStatus"}</th>
								<th>{translate key="mjcp.links.publicationStatus"}</th>
								<th>{translate key="mjcp.links.url"}</th>
								<th>{translate key="mjcp.links.expiresAt"}</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{if empty($authorLinks)}
								<tr>
									<td colspan="10" class="mjcp-empty-cell">{translate key="mjcp.common.noData"}</td>
								</tr>
							{else}
								{foreach from=$authorLinks item="al"}
									<tr>
										<td>
											<input type="checkbox" class="mjcp-link-row-checkbox" value="{$al.submissionId|escape}" data-has-token="{if $al.hasToken}1{else}0{/if}" aria-label="{$al.submissionId|escape}" />
										</td>
										<td>{$al.submissionId|escape}</td>
										<td>{$al.journalName|escape}</td>
										<td>{$al.title|escape}</td>
										<td>
											<span class="mjcp-badge mjcp-badge-{$al.formBadgeType|escape}">
												{$al.formStatusLabel|escape}
											</span>
										</td>
										<td>
											<span class="mjcp-badge mjcp-badge-{$al.loaBadgeType|escape}">
												{$al.loaStatusLabel|escape}
											</span>
										</td>
										<td>{$al.publicationStatusLabel|escape}</td>
										<td>
											{if $al.hasToken}
												<code class="mjcp-code-snippet" title="{$al.fullLinkUrl|escape}">{$al.shortLinkText|escape}</code>
											{else}
												<span class="mjcp-muted">{$al.shortLinkText|escape}</span>
											{/if}
										</td>
										<td>{$al.expiresAtFormatted|escape}</td>
										<td class="mjcp-action-cell">
											{if $al.hasToken}
												<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-btn--xs mjcp-copy-link-btn" data-url="{$al.fullLinkUrl|escape}">
													{translate key="mjcp.links.copyBtn"}
												</button>
												<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-btn--xs mjcp-create-token-btn" data-submission-id="{$al.submissionId|escape}" data-has-token="1">
													{translate key="mjcp.links.regenerateBtn"}
												</button>
												<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-btn--xs mjcp-revoke-token-btn" data-submission-id="{$al.submissionId|escape}">
													{translate key="mjcp.links.deactivateBtn"}
												</button>
											{else}
												<button type="button" class="mjcp-btn mjcp-btn--primary mjcp-btn--xs mjcp-create-token-btn" data-submission-id="{$al.submissionId|escape}" data-has-token="0">
													{translate key="mjcp.links.createBtn"}
												</button>
											{/if}
											{if $perms.canManageLoa && $al.manageLoaUrl}
												<a href="{$al.manageLoaUrl|escape}" class="mjcp-btn mjcp-btn--outline mjcp-btn--xs">
													{translate key="mjcp.links.manageLoaBtn"}
												</a>
											{/if}
										</td>
									</tr>
								{/foreach}
							{/if}
						</tbody>
					</table>
				</div>
			</div>
			<p class="mjcp-footnote">{translate key="mjcp.links.footnote"}</p>

		{* ===================================================================== *}
		{* TAB 9: AUTHOR TESTIMONIALS                                            *}
		{* ===================================================================== *}
		{elseif $activeTab == 'testimonials'}
			<div class="mjcp-header">
				<div>
					<h1 class="mjcp-title">{translate key="mjcp.testimonial.title"}</h1>
					<p class="mjcp-subtitle">{translate key="mjcp.testimonial.subtitle"}</p>
				</div>
			</div>

			<div class="mjcp-toolbar">
				<button type="button" class="mjcp-btn mjcp-btn--primary mjcp-export-trigger" data-module="testimonials" data-format="xlsx">
					{translate key="mjcp.testimonial.exportApprovedBtn"}
				</button>
			</div>

			<div class="mjcp-panel-box">
				<div class="mjcp-table-responsive">
					<table class="mjcp-table">
						<thead>
							<tr>
								<th>{translate key="mjcp.testimonial.text"}</th>
								<th>{translate key="mjcp.testimonial.authorName"}</th>
								<th>{translate key="mjcp.testimonial.institution"}</th>
								<th>{translate key="mjcp.table.journal"}</th>
								<th>{translate key="mjcp.testimonial.consentStatus"}</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{if empty($testimonials)}
								<tr>
									<td colspan="6" class="mjcp-empty-cell">{translate key="mjcp.common.noData"}</td>
								</tr>
							{else}
								{foreach from=$testimonials item="tm"}
									<tr>
										<td>{$tm.testimonialText|escape}</td>
										<td>{$tm.authorName|escape}</td>
										<td>{$tm.institution|escape}</td>
										<td>{$tm.journalName|escape}</td>
										<td>
											<span class="mjcp-badge mjcp-badge-{$tm.badgeType|escape}">
												{$tm.statusLabel|escape}
											</span>
										</td>
										<td class="mjcp-action-cell">
											{if $perms.canManageTestimonials}
												{if $tm.isApproved}
													<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-btn--xs mjcp-toggle-testimonial-btn"
														data-testimonial-id="{$tm.testimonialId|escape}"
														data-withdraw="1">
														{translate key="mjcp.testimonial.withdrawAction"}
													</button>
												{else}
													<button type="button" class="mjcp-btn mjcp-btn--outline mjcp-btn--xs mjcp-toggle-testimonial-btn"
														data-testimonial-id="{$tm.testimonialId|escape}"
														data-withdraw="0">
														{translate key="mjcp.testimonial.restoreAction"}
													</button>
												{/if}
											{/if}
										</td>
									</tr>
								{/foreach}
							{/if}
						</tbody>
					</table>
				</div>
			</div>
			<p class="mjcp-footnote">{translate key="mjcp.testimonial.footnote"}</p>
		{/if}
	</div>
</div>
{/block}
