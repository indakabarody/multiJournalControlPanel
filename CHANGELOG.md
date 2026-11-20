# Changelog

All notable changes to the **Multi-Journal Control Panel** plugin for OJS 3.3.0-24 are documented in this file.

## [1.0.0.0] - 2026-11-20

### Added
- Initial release compatible with OJS 3.3.0-24 and PKP Library `stable-3_3_0`.
- 15 isolated `mjcp_*` database tables via `MultiJournalControlPanelSchemaMigration` and `schema.xml`.
- Dashboard summary cards and per-journal Editorial Pipeline table with interactive drill-down.
- Bottleneck Monitoring with configurable working-day thresholds, holiday exclusions, and review round timer resets.
- Connected Journals monitoring with manual and scheduled (`SynchronizationTask`) synchronization.
- Issue Health tracking with per-journal quotas and strict readiness (`LOA uploaded` + `copyediting completed`).
- Journal Performance module with 6-month submission/publication trends and KPIs.
- Payment Management module with strict server-side RBAC, `FREE` normalization, immutable payment logs, quick filters, Excel (`.xlsx`) / CSV bulk import with preview validation, and OJS workflow unpaid warning notification.
- Author Self-Service Portal (`confirm` and `validateLoa`) with SHA-256 hashed tokens, bilingual switcher (`en_US` / `id_ID`), mandatory testimonial consent, payment-gated LOA download/validation, and automatic publication link + DOI display.
- Author Testimonials management with consent withdrawal and approved-only export.
- Multi-format report export engine (`.xlsx`, `.csv`, `.pdf`) preserving active filters.
- Full English (`en_US`) and Indonesian (`id_ID`) localization in both `.po` and `.xml` formats.
- Automated 16-suite verification test runner (`tests/run_tests.php`).
