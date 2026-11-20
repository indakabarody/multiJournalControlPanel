# Multi-Journal Control Panel for OJS 3.3

**Multi-Journal Control Panel** is a generic plugin for **Open Journal Systems (OJS) 3.3.0-24** that integrates cross-journal editorial monitoring, payment management, issue readiness, journal performance KPIs, and author self-service into a unified publishing house operational dashboard.

- **Plugin Directory:** `plugins/generic/multiJournalControlPanel/`
- **Plugin Class:** `MultiJournalControlPanelPlugin`
- **Target Compatibility:** OJS 3.3.0-24 (verified against `pkp/ojs` and `pkp/pkp-lib` `stable-3_3_0`)
- **Copyright:** Copyright (c) 2026 Indaka Barody
- **License:** GPL-3.0-or-later

---

## Key Features

1. **Dashboard & Editorial Pipeline (`dashboard`):**
   - Five operational summary cards: Total Articles (`All Article`), Active Manuscripts, Overdue Manuscripts, Ready to Publish, and Issues Below Quota.
   - Per-journal pipeline breakdown (`All Article`, `Submission`, `Review`, `Revision`, `Copyediting`, `Production`, `Published`) where every submission is counted once in its active or completed bucket.
   - Interactive drill-down to inspect underlying submissions and jump directly to the OJS workflow.
2. **Bottleneck Monitoring (`bottlenecks`):**
   - Configurable working-day thresholds (Review Round 1: 14–21 working days; Review Round 2+: 7 working days; Copyediting reminder: D-10 before target issue month-end).
   - Excludes weekends (Saturday/Sunday) and configurable holidays; timer resets automatically on review round transitions.
3. **Connected Journals (`journals`):**
   - Lists all journals in the OJS installation with active submission counts, synchronization status (`Connected`, `Pending`, `Failed`), last sync timestamp, direct OJS shortcut, and manual sync controls.
4. **Issue Health (`issues`):**
   - Tracks target issue quotas, plotted articles, ready articles (LOA uploaded **and** copyediting completed), in-progress articles, and under/over-quota warning indicators.
5. **Journal Performance (`performance`):**
   - Monthly submitted vs. published trend visualization, acceptance rate, rejection rate, on-time stage rate, and average submission-to-publication turnaround days.
6. **Payment Management (`payments` & `paymentDetail`):**
   - Strict server-side Role-Based Access Control (RBAC):
     - **Directors (`director`)**: Strictly blocked from the payment module.
     - **Journal Managers (`journal_manager`)**: Read-only access only when registered in `mjcp_access_users`.
     - **Finance Admins (`finance_admin`)**: Full verification and bulk import access when registered in `mjcp_access_users`.
   - Supports `unpaid` / `paid` statuses, `FREE` normalization (`paid`, amount `0`, notes `FREE`), immutable payment history (`mjcp_payment_logs`), private proof file storage (JPG/PNG/PDF, max 1 MB), quick filters, and Excel (`.xlsx`) / CSV bulk import with validation preview.
   - Non-blocking OJS workflow warning when a submission in Copyediting or Production is still unpaid.
7. **Author Self-Service Portal (`confirm` & `validateLoa`):**
   - Cryptographically secure unique token links (`random_bytes(16)` stored as SHA-256 hash) requiring no OJS login.
   - Pre-filled manuscript metadata, validated email & WhatsApp fields, optional transfer proof upload, mandatory testimonial with publication consent, bilingual switcher (English / Indonesian).
   - Automatic LOA download and public LOA validation unlocked strictly when payment status is `paid` (immediately re-locked if reverted to `unpaid`).
   - Automatic publication URL and DOI display once published; links remain active after publication.
8. **Testimonials (`testimonials`) & Report Exports (`export`):**
   - Testimonial consent management with withdrawal support; excluded from approved social media exports when withdrawn.
   - One-click export to Excel (`.xlsx`), CSV (`.csv`), and PDF (`.pdf`) preserving active filters and metadata.
9. **Settings & Audit Trail (`settings`):**
   - Manage publishing house name, stage thresholds, role assignments, payment access list, per-journal issue quotas, holiday calendar, scheduled/manual sync, and full activity audit log (`mjcp_audit_logs`).

---

## Non-Invasive Architecture

- Does **not** modify any OJS core or PKP Library files.
- Does **not** alter OJS core database tables or editorial workflows.
- Stores all plugin data in 15 dedicated `mjcp_*` tables created via `MultiJournalControlPanelSchemaMigration`.
- Uses OJS 3.3 localization (`locale/en_US/` and `locale/id_ID/`).

---

## Documentation

- [Installation Guide](docs/INSTALLATION.md)
- [Configuration Guide](docs/CONFIGURATION.md)
- [Testing & Verification Guide](docs/TESTING.md)
- [Changelog](CHANGELOG.md)
