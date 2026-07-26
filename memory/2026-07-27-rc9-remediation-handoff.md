# RC9 remediation handoff (2026-07-27)

Release decision: **NO-GO for production.** Nothing in this pass changes that.
No test suite, build, container, database or cluster gate was executed while
making these changes, so none of the work below is verified by execution.

## Closed in code during this pass

| ID | Finding | Delivery |
| --- | --- | --- |
| H-REMAIN-01 | Queued imports never re-applied operational row scope | `Services/OperationalScope.php`, `Support/ImportRowScope.php`, rewritten `Jobs/ProcessResourceImportJob.php` |
| MH-REMAIN-03 | Queued exports froze the initial scope query | `Jobs/BuildResourceExportJob.php`: scope snapshot resolved per page, fingerprint mismatch aborts the job |
| H-REMAIN-02 | Plan/subscription provisioning was neither idempotent nor audited | `Services/SubscriptionProvisioner.php`, `Support/ProvisioningException.php`, `Console/Commands/ProvisionTenantSubscription.php` |
| M-REMAIN-05 | Disable-MFA guard ignored privileged tenant custom roles | `Support/PrivilegedAccess.php`, `Http/Controllers/MfaController.php` |
| M-REMAIN-07 | Android bundle shipped without SBOM or attestation | `.github/workflows/android-supply-chain.yml` |

## Still open

### M-REMAIN-06 — timeline history redaction (partially delivered)

`Support/TimelineRedactor.php` and `docs/security/timeline-redaction.md` are in
place, but the call site inside `CrudController::timeline` is **not wired**. The
redactor is currently unreachable code. See the doc for the exact change.

Reason it was not applied: `CrudController.php` is ~44 KB and the tooling used
for this pass can only replace whole files, which is not a safe way to touch a
central controller. Apply it locally with a normal editor and a diff review.

### M-REMAIN-04 — canary proofs

Out of code scope. Requires a staging run proving long-running import/export
workers, scheduler heartbeat, and Reverb, with evidence attached.

### Maintenance debt

- MAINT-01: `resources/js/Pages/App.jsx` is ~101 KB and should be split.
- MAINT-02: no CI assertion for `prefers-reduced-motion` behaviour.
- No PHPUnit coverage yet for `OperationalScope`, `ImportRowScope`,
  `SubscriptionProvisioner`, `PrivilegedAccess`, or `TimelineRedactor`.

## Gates that must pass before any GO decision

Run from `backend/` unless noted:

```bash
composer install --no-interaction --prefer-dist
vendor/bin/pint --test
npm ci
npm run lint:openapi
npm run lint:csp
npm run lint:hardening
npm run build
npm run check:performance-budget
php artisan test
npm run test:e2e
npm run audit:ui
composer audit --locked
npm audit --audit-level=high
```

Then on MySQL 8.4, not SQLite:

```bash
php artisan migrate:fresh --seed --force
php artisan pesantenku:domain-preflight
php artisan test tests/Feature/ProductionHardeningTest.php tests/Feature/BackupIntegrityTest.php
```

CSS budget is 250,000 B and total 1,500,000 B. `motion.css` and `ui-kit.css`
were added earlier in the day and have never been measured against the budget.

## New operator workflow

Provisioning is now idempotent desired-state reconciliation. Preview first:

```bash
php artisan pesantrenku:provision-subscription \
  --pesantren-id=1 --plan-code=growth --status=active \
  --starts-at=2026-08-01 --ends-at=2027-07-31 \
  --reason='TICKET-123 upgrade' --actor-email=owner@example.test --dry-run
```

Drop `--dry-run` to apply. Running it twice reports `unchanged` and writes an
audit record instead of issuing a second subscription. A tenant that already
holds more than one live subscription is refused, not silently repaired.

## Hard constraints carried forward

Never introduce: public `AuditReport`-style production-ready claims, fabricated
testimonials or user counts, a `99.9%` SLA figure, unverified completion
percentages, inline styles or `<style>` blocks (CSP lint), `motion/react`, the
shadcn/MUI/Radix bundle, a standalone Vite entry, or a PNPM workspace config.
Never flip the release status to GO on static review or SQLite-only tests.
