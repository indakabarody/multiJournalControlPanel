{**
 * templates/loa/detail.tpl
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
	<title>{translate key="mjcp.loa.validation.pageTitle"} - {$houseName|escape}</title>
	<link rel="stylesheet" href="{$pluginUrl|escape}/styles/multiJournalControlPanel.css" />
</head>
<body class="mjcp-author-page-body">
	<script src="{$pluginUrl|escape}/js/multiJournalControlPanel.js" defer></script>
	<div class="mjcp-container mjcp-author-container">
		<header class="mjcp-author-topbar">
			<div class="mjcp-topbar__brand">{$houseName|escape}</div>
			<div class="mjcp-lang-switcher">
				<a href="{$validateLoaUrl|escape}?loa={$loaNumber|escape:'url'}&amp;uiLocale=id_ID"
					class="mjcp-pill {if $activeLocale == 'id_ID'}mjcp-pill--active{/if}">
					{translate key="mjcp.common.langIndonesia"}
				</a>
				<a href="{$validateLoaUrl|escape}?loa={$loaNumber|escape:'url'}&amp;uiLocale=en_US"
					class="mjcp-pill {if $activeLocale == 'en_US'}mjcp-pill--active{/if}">
					{translate key="mjcp.common.langEnglish"}
				</a>
			</div>
		</header>

		<main class="mjcp-author-main">
			<div class="mjcp-card mjcp-author-card">
				<h1 class="mjcp-title">{translate key="mjcp.loa.validation.pageTitle"}</h1>
				<p class="mjcp-subtitle">{translate key="mjcp.loa.validation.subtitle"}</p>

				<form method="GET" action="{$validateLoaUrl|escape}" class="mjcp-inline-form">
					<input type="text" name="loa" class="mjcp-input"
						value="{$loaNumber|escape}"
						placeholder="{translate|escape key="mjcp.loa.numberPlaceholder"}" required />
					<button type="submit" class="mjcp-btn mjcp-btn--primary">
						{translate key="mjcp.loa.validation.searchBtn"}
					</button>
				</form>

				{if $loaNumber !== ''}
					{if $validationResult && $validationResult.isValid}
						<div class="mjcp-alert mjcp-alert--success">
							<strong>{translate key="mjcp.loa.validation.validBadge"}</strong>
						</div>
						<dl class="mjcp-kv-list">
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.loa.numberLabel"}</dt>
								<dd><strong>{$validationResult.loaNumber|escape}</strong></dd>
							</div>
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.table.title"}</dt>
								<dd>{$validationResult.articleTitle|escape}</dd>
							</div>
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.payment.author"}</dt>
								<dd>{$validationResult.authorNames|escape}</dd>
							</div>
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.table.journal"}</dt>
								<dd>{$validationResult.journalName|escape}</dd>
							</div>
							<div class="mjcp-kv-row">
								<dt>{translate key="mjcp.loa.validation.dateIssued"}</dt>
								<dd>{$validationResult.uploadedAtFormatted|escape}</dd>
							</div>
						</dl>
					{else}
						<div class="mjcp-alert mjcp-alert--danger">
							{translate key="mjcp.loa.validation.notFound"}
						</div>
					{/if}
				{/if}
			</div>
		</main>
	</div>
</body>
</html>
