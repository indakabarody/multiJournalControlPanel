# Testing & Verification Guide

The plugin includes a standalone automated test suite (`tests/run_tests.php`) that exercises database migrations, OJS 3.3 data synchronization, bottleneck calculations, RBAC authorization, payment verification, bulk import, secure author tokens, file upload security, LOA access locking, publication links, localization parity, UI compliance, and safe uninstallation using an isolated in-memory SQLite database via OJS 3.3's `Illuminate\Database\Capsule\Manager`.

---

## Running Automated Tests

From the plugin root directory:

```bash
php tests/run_tests.php
```

### Syntax Verification Across All PHP Files

```bash
find . -name '*.php' -exec php -l {} +
```

---

## Test Coverage Summary (16 Automated Test Suites)

1. **Plugin Installation (`install()`):** Verifies clean installation returns `true`.
2. **Plugin Upgrade (`upgrade()`):** Verifies idempotent upgrade execution without errors.
3. **Database Migration:** Confirms all 15 `mjcp_*` tables, indexes, and default seed roles are created.
4. **Dashboard Accuracy:** Verifies total articles, active submissions, and overdue counters against seeded OJS submissions.
5. **Editorial Pipeline Mapping:** Verifies each submission is counted once in its active/completed stage (`All Article` equals sum of stages).
6. **Bottleneck Calculation:** Verifies exclusion of weekends and configured holidays, Review Round 1 (21-day) limit, and Review Round 2 timer reset (7-day limit).
7. **Payment Authorization (RBAC):** Verifies Directors and unregistered Journal Managers are blocked from payments, registered Journal Managers have read-only access, Finance Admins have read/write access, and Technical Admins can manage settings.
8. **Payment Verification, `FREE` Rule, Audit Trail & Bulk Import:** Verifies optional proof handling, `FREE` normalization (`paid`, amount `0`, notes `FREE`), immutable `mjcp_payment_logs`, and invalid row rejection in bulk import preview.
9. **Author Token & Confirmation Form:** Verifies 32-char cryptographic token generation, SHA-256 storage, mandatory testimonial + consent enforcement, and token revocation.
10. **File Upload Security & Strict Readiness:** Verifies rejection of oversized files (>1 MB) and executable files (`.php`), private storage of valid `%PDF-1.4` LOA files, and strict readiness requiring both LOA upload and completed copyediting.
11. **LOA Access Control:** Verifies LOA validation/download is active when `paid`, immediately locked when payment reverts to `unpaid`, and surfaced in the `loa_unpaid` quick filter.
12. **Publication Link & Token Persistence:** Verifies automatic publication URL and DOI display and confirms links remain accessible after publication.
13. **Localization Completeness:** Verifies 100% key parity between `en_US` and `id_ID` across both `locale.po` and `locale.xml`, and confirms zero missing template translation keys.
14. **UI, CSS Prefix & Copyright Compliance:** Verifies zero inline CSS/JS in Smarty templates, `mjcp-*` responsive CSS classes, and required GPL copyright headers on all PHP files.
15. **Uninstall Safety:** Confirms `uninstall(false)` preserves all OJS core submissions and retains `mjcp_*` tables unless explicitly confirmed.
16. **Naming Standardization:** Confirms zero legacy prefix occurrences across the entire plugin codebase.
