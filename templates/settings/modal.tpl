{**
 * templates/settings/modal.tpl
 *
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *}
<form class="pkp_form" id="mjcpPluginSettingsForm" method="POST" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{fbvFormArea id="mjcpSettingsArea"}
		{fbvFormSection title="mjcp.settings.houseNameLabel" for="publishingHouseName"}
			{fbvElement type="text" id="publishingHouseName" value=$publishingHouseName}
		{/fbvFormSection}
		{fbvFormSection title="mjcp.settings.round1LimitLabel" for="round1Limit"}
			{fbvElement type="text" id="round1Limit" value=$round1Limit}
		{/fbvFormSection}
		{fbvFormSection title="mjcp.settings.round2LimitLabel" for="round2Limit"}
			{fbvElement type="text" id="round2Limit" value=$round2Limit}
		{/fbvFormSection}
		{fbvFormSection title="mjcp.settings.copyeditingWarningLabel" for="copyeditingWarning"}
			{fbvElement type="text" id="copyeditingWarning" value=$copyeditingWarning}
		{/fbvFormSection}
		{fbvFormSection title="mjcp.settings.paymentAccessUsersLabel" for="paymentAccessUsernames"}
			{fbvElement type="text" id="paymentAccessUsernames" value=$paymentAccessUsernames}
		{/fbvFormSection}
		{fbvFormSection title="mjcp.settings.tokenExpDaysLabel" for="tokenExpirationDays"}
			{fbvElement type="text" id="tokenExpirationDays" value=$tokenExpirationDays}
		{/fbvFormSection}
		{fbvFormSection title="mjcp.settings.maxUploadSizeLabel" for="maxUploadSizeMb"}
			{fbvElement type="text" id="maxUploadSizeMb" value=$maxUploadSizeMb}
		{/fbvFormSection}
	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
</form>
