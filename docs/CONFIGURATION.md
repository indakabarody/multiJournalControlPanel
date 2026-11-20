# Configuration Guide

After enabling **Multi-Journal Control Panel**, navigate to the **Settings** tab (or open the quick settings modal from **Installed Plugins → Multi-Journal Control Panel → Settings**) as a **Technical Admin** or **Site Administrator**.

---

## 1. Publishing House & Stage Time Thresholds

- **Publishing House Name:** Displayed in the panel header, reports, and public author pages.
- **Review Round 1 Limit (working days):** Configurable between `14` and `21` working days (default: `21`).
- **Review Round 2+ Limit (working days):** Default `7` working days; the counter automatically resets whenever a submission enters a new review round.
- **Copyediting Reminder (D-):** Default `10` calendar days before the end of the target issue month.

> Note: Active working days are configurable via checkboxes in **Settings → Holidays** (default: Monday to Friday). Unchecked days and all dates listed in the **Holidays** calendar are automatically excluded from working-day calculations.

---

## 2. Access Control & Roles

The plugin enforces server-side Role-Based Access Control across four operational roles:

| Role Key | Display Name | Capabilities |
| :--- | :--- | :--- |
| `director` | Directors | Dashboard, Pipeline, Bottlenecks, Connected Journals, Issue Health, Performance, Testimonials, General Exports. **Strictly no access to Payments.** |
| `journal_manager` | Journal Manager | All Dashboard modules, Author Links management, LOA upload/replacement. **Read-only Payment access ONLY if username is also listed in Payment Access Usernames.** |
| `finance_admin` | Finance Admin | Payment verification (view & edit), bulk Excel/CSV import, Payment exports — **ONLY if username is also listed in Payment Access Usernames.** |
| `technical_admin` | Technical Admin | Full plugin configuration, role management, holiday calendar, issue quotas, manual synchronization, and audit log viewing. |

### Configuring Payment Access
1. Enter comma-separated OJS usernames into **Usernames authorized for payment access** (stored in `mjcp_access_users`).
2. Assign usernames to `finance_admin`, `journal_manager`, `director`, and `technical_admin` as needed.
3. Click **Save Settings**. Every change is recorded in `mjcp_audit_logs`.

---

## 3. Author Form & LOA Settings

- **Link Validity Period (days):** Default `30` days. Tokens are automatically extended to at least 30 days past the target issue date and remain active after publication so authors can always retrieve their LOA and publication link.
- **Max Proof Upload Size (MB):** Default `1` MB (JPG, PNG, PDF). Files are stored privately outside the public web root under `<files_dir>/multiJournalControlPanel/`.
- **Custom LOA Validation URL (optional):** Leave blank to use the built-in public validation page (`/multiJournalControlPanel/validateLoa`), or specify an external validation endpoint.
- **Form Languages:** Enable/disable Indonesian (`id_ID`) and English (`en_US`) on the author self-service page.

---

## 4. Issue Quotas & Holiday Calendar

- **Issue Quota per Journal:** Set the target article count for each journal's upcoming issue. Issue Health cards automatically flag journals as `On track`, `Short N`, or `Over N`.
- **Holidays:** Add national or institutional holidays (`YYYY-MM-DD` + description). Added holidays immediately affect working-day bottleneck calculations on the next sync.

---

## 5. Synchronization

- Data is synchronized automatically via `SynchronizationTask` and on-demand whenever the cache is stale.
- Click **Synchronize now** on the **Settings** or **Journals** tab to trigger an immediate manual refresh across all journals or a single journal.
