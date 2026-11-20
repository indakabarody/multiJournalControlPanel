# Installation Guide

This guide describes how to install, enable, upgrade, and safely uninstall the **Multi-Journal Control Panel** plugin on **OJS 3.3.0-24**.

---

## System Requirements

- **OJS Version:** 3.3.0-24 (compatible with OJS 3.3.x)
- **PHP Version:** 7.3 – 8.1 (matching OJS 3.3 requirements)
- **PHP Extensions:** `pdo_mysql` (or `mysqli`), `zip` (`ZipArchive` for `.xlsx` export/import), `fileinfo` (`finfo` for MIME validation), `simplexml`
- **Database:** MySQL 5.7+ / MariaDB 10.2+

---

## Installation Steps

1. **Copy the plugin directory** into your OJS installation:
   ```bash
   cp -R multiJournalControlPanel /path/to/ojs/plugins/generic/multiJournalControlPanel
   ```
2. **Verify file permissions** so the web server user can read plugin files and write to the OJS private `files_dir`:
   ```bash
   chown -R www-data:www-data /path/to/ojs/plugins/generic/multiJournalControlPanel
   chmod -R 755 /path/to/ojs/plugins/generic/multiJournalControlPanel
   ```
3. **Register the plugin version** (recommended for production installations):
   ```bash
   php /path/to/ojs/lib/pkp/tools/installPluginVersion.php \
     /path/to/ojs/plugins/generic/multiJournalControlPanel/version.xml
   ```
4. **Enable the plugin** in OJS:
   - Log in as **Site Administrator**.
   - Go to **Settings → Website → Plugins → Installed Plugins → Generic Plugins**.
   - Locate **Multi-Journal Control Panel** and check **Enable**.
   - Enabling the plugin automatically executes `MultiJournalControlPanelSchemaMigration::up()`, creating all 15 `mjcp_*` database tables and seeding default roles and stage thresholds.
5. **Access the Control Panel:**
   - Click **Multi-Journal Control Panel** in the OJS backend sidebar navigation, or visit:
     `https://your-ojs-domain/index.php/index/multiJournalControlPanel`

---

## Database Tables Created

All plugin data is isolated in `mjcp_*` tables (no OJS core tables are modified):

| Table | Purpose |
| :--- | :--- |
| `mjcp_article_data` | Pre-aggregated submission summary cache for fast dashboard queries |
| `mjcp_payments` | Payment verification status (`unpaid`/`paid`), amount, notes, and private proof reference |
| `mjcp_payment_logs` | Immutable audit history of all payment status, amount, notes, and proof changes |
| `mjcp_author_forms` | Author confirmation form submissions and privacy-hashed client IP |
| `mjcp_author_tokens` | SHA-256 hashed unique author access tokens and expiration timestamps |
| `mjcp_loa_files` | Private LOA PDF file metadata, LOA numbers, and replacement version history |
| `mjcp_issue_quotas` | Configurable target article quotas per journal and issue |
| `mjcp_stage_thresholds` | Configurable working-day limits per editorial stage |
| `mjcp_holidays` | Configurable holiday calendar excluded from working-day calculations |
| `mjcp_access_users` | Explicit allow-list of usernames permitted to view/manage payments |
| `mjcp_roles` | Plugin role definitions (`director`, `journal_manager`, `finance_admin`, `technical_admin`) |
| `mjcp_user_roles` | Username-to-role assignments |
| `mjcp_audit_logs` | System-wide audit trail for configuration, security, and data mutations |
| `mjcp_sync_logs` | Synchronization status and execution logs per journal |
| `mjcp_testimonials` | Author testimonials and publication consent status |

---

## Scheduled Synchronization

The plugin registers `scheduledTasks.xml` with OJS `AcronPlugin` automatically (`SynchronizationTask`, running hourly). If your server uses system cron instead of Acron, ensure OJS scheduled tasks are running:

```bash
0 * * * * php /path/to/ojs/tools/runScheduledTasks.php /path/to/ojs/plugins/generic/multiJournalControlPanel/scheduledTasks.xml
```

---

## Safe Uninstallation

- Disabling or uninstalling the plugin via the OJS interface **never** modifies or deletes any OJS editorial data.
- By default, `MultiJournalControlPanelPlugin::uninstall(false)` preserves all `mjcp_*` tables so financial and audit records are not lost accidentally.
- To permanently remove plugin tables during cleanup, call `uninstall(true)` explicitly after backing up your database.
