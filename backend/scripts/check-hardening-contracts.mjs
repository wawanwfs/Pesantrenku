import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const repositoryRoot = path.resolve(root, '..');
const failures = [];
let checks = 0;

function read(relativePath) {
  return fs.readFileSync(path.join(repositoryRoot, relativePath), 'utf8');
}

function exists(relativePath) {
  return fs.existsSync(path.join(repositoryRoot, relativePath));
}

function check(condition, message) {
  checks += 1;
  if (!condition) failures.push(message);
}

function contains(relativePath, pattern, message) {
  const content = read(relativePath);
  check(pattern instanceof RegExp ? pattern.test(content) : content.includes(pattern), message);
}

function excludes(relativePath, pattern, message) {
  const content = read(relativePath);
  check(!(pattern instanceof RegExp ? pattern.test(content) : content.includes(pattern)), message);
}

function lineCount(relativePath) {
  return read(relativePath).split(/\r?\n/).length;
}

// H-01: dashboard module isolation and platform telemetry authorization.
check(exists('backend/app/Support/DashboardModuleRegistry.php'), 'Dashboard module registry is missing.');
contains('backend/app/Http/Controllers/WebController.php', 'DashboardModuleRegistry::allows', 'Web module routes must use the dashboard registry.');
contains('backend/app/Http/Controllers/WebController.php', 'DashboardModuleRegistry::isPlatformDataset', 'Platform datasets must receive a second authorization guard.');
contains('backend/app/Http/Controllers/WebController.php', 'assertModule($request, $panel, $module)', 'Web module routes must enforce subscription/module entitlements.');
contains('backend/app/Services/Concerns/DashboardSharedDataConcern.php', /\$panel === 'super-admin' && \$module === '(aktivitas-sistem|audit-logs|login-monitoring|error-logs)'/, 'Telemetry datasets must only be mapped from the super-admin panel.');

// H-02: privilege changes, MFA step-up, authorization generations, and device-bound PATs.
contains('backend/app/Http/Middleware/EnsureActiveUser.php', 'authz_version', 'Requests must enforce authorization generations.');
contains('backend/app/Http/Middleware/EnsureActiveUser.php', 'X-Device-Id', 'API tokens must be bound to a device header.');
contains('backend/app/Http/Middleware/EnsureActiveUser.php', "elseif ($request->hasSession())", 'Same-origin API sessions must enforce the authorization generation.');
contains('backend/app/Http/Middleware/EnsureActiveUser.php', "$request->session()->invalidate()", 'Stale API sessions must be invalidated, not merely rejected.');
contains('backend/app/Services/UserAccessRevoker.php', "increment('authz_version')", 'Access revocation must increment the authorization generation.');
contains('backend/app/Services/StepUpMfaService.php', "string('mfa_code')", 'Access administration must require step-up MFA.');
contains('backend/resources/js/components/app/AppDialogs.jsx', "requestStepUpMfa('mengubah permission')", 'Permission administration UI must submit step-up MFA.');
contains('backend/resources/js/components/app/AppDialogs.jsx', "requestStepUpMfa('menyimpan custom role')", 'Custom-role administration UI must submit step-up MFA.');
contains('backend/resources/js/Pages/App.jsx', "requestStepUpMfa('mengubah membership')", 'Membership administration UI must submit step-up MFA.');
contains('backend/app/Http/Controllers/AuthController.php', "'authz_version' =>", 'New PATs must carry the authorization generation.');
contains('backend/app/Http/Controllers/AuthController.php', "'device_id' =>", 'New PATs must carry their device identifier.');

// H-03: restore must not replay security/control-plane state.
contains('backend/app/Support/TenantBackupManifest.php', 'PROTECTED_SETTING_PREFIXES', 'Backup manifests must filter security settings.');
excludes('backend/app/Support/TenantBackupManifest.php', /['"]pesantren_user['"]/, 'Ordinary tenant backups must exclude memberships.');
excludes('backend/app/Support/TenantBackupManifest.php', /['"]tenant_roles['"]/, 'Ordinary tenant backups must exclude custom roles.');
contains('backend/app/Services/BackupRestoreService.php', 'TenantBackupManifest::isProtectedSetting', 'Restore must reject protected settings.');
contains('backend/app/Services/RestoreApprovalService.php', 'authz_version', 'Restore approvals must be bound to approver authorization generations.');
contains('backend/app/Services/RestoreApprovalService.php', 'approved_change_hash', 'Restore approvals must be bound to the approved change set.');
contains('backend/database/migrations/2026_07_26_000018_bind_restore_approvals.php', "where('status', 'executing')->exists()", 'Restore-binding migration must refuse to run during an active restore.');
contains('backend/app/Jobs/RestoreTenantBackupJob.php', 'UserAccessRevoker', 'Security-affecting restore completion must revoke tenant credentials.');

// H-04/H-05: atomic canary rollout, exact rollback, and synthetic critical-flow smoke.
contains('deploy/scripts/canary-deploy.sh', '--type=json --patch-file', 'Canary rollout must patch one PodTemplate atomically.');
contains('deploy/scripts/canary-deploy.sh', 'PREVIOUS_DEPLOYMENT_FILE', 'Canary rollback must retain the exact prior template.');
contains('deploy/scripts/canary-deploy.sh', '["op"=>"replace","path"=>"/spec/template"', 'Canary rollback must replace the complete prior PodTemplate atomically.');
excludes('deploy/scripts/canary-deploy.sh', 'rollout undo', 'Canary rollback must not rely on ambiguous rollout undo.');
contains('deploy/scripts/canary-deploy.sh', 'pesantenku:canary-smoke', 'Canary must execute critical-flow synthetic tests.');
for (const anchor of ['login', 'tenant', 'idempot', 'queue', 'storage', 'broadcast']) {
  contains('backend/app/Console/Commands/CanarySmoke.php', new RegExp(anchor, 'i'), `Canary smoke must cover ${anchor}.`);
}
contains('deploy/scripts/canary-deploy.sh', 'pesantenku.io/revision', 'Canary metrics must be revision labelled.');
contains('deploy/scripts/canary-deploy.sh', 'verify_live_rollout', 'Canary must verify ready pods and immutable image IDs after rollout.');
contains('backend/app/Console/Commands/CanarySmoke.php', 'Login privileged tanpa MFA tidak ditolak', 'Canary must prove privileged login is rejected without MFA.');

// H-06: mobile MFA and narrow, device-bound tokens.
contains('mobile/lib/core/network/api_client.dart', 'mfaCode', 'Mobile login must support MFA/recovery factors.');
contains('mobile/lib/core/network/api_client.dart', "'X-Device-Id'", 'Mobile requests must send their device identity.');
contains('backend/app/Http/Controllers/AuthController.php', "['mobile:access']", 'Mobile PATs must use a narrow ability set.');
contains('mobile/test/widget_test.dart', /MFA|mfa/, 'Mobile widget tests must exercise the MFA flow.');
contains('mobile/test/security_primitives_test.dart', /device|HMAC|hmac/i, 'Mobile security primitive tests must cover device/QR proof behavior.');
contains('mobile/lib/core/network/api_client.dart', 'static String scannerProof', 'Mobile QR proof generation must be implemented in the API client.');
check(exists('mobile/test/api_client_security_flow_test.dart'), 'Mobile API security-flow tests are missing.');
contains('mobile/test/api_client_security_flow_test.dart', 'privileged API login performs MFA retry and binds token to device', 'Mobile API tests must exercise the privileged MFA retry.');
contains('mobile/test/api_client_security_flow_test.dart', '401 clears persisted session and invokes unauthorized handler', 'Mobile API tests must prove stale credentials are cleared.');

// Tenant integrity, entitlements, transaction safety, and asynchronous authorization.
check(exists('backend/app/Services/EntitlementService.php'), 'Request-side entitlement enforcement is missing.');
contains('backend/app/Services/ResourceAuthorization.php', 'EntitlementService', 'CRUD/workflow authorization must share entitlement enforcement.');
contains('backend/app/Http/Controllers/CrudController.php', 'DB::transaction', 'CRUD mutations must be transactionally bounded.');
check(exists('backend/database/migrations/2026_07_26_000015_enforce_tenant_integrity.php'), 'Tenant integrity migration is missing.');
contains('backend/database/migrations/2026_07_26_000015_enforce_tenant_integrity.php', 'CREATE TRIGGER', 'Tenant integrity must also protect direct SQL writes.');
contains('backend/app/Http/Controllers/Concerns/ActionNotificationJobConcern.php', 'expectedAuthzVersion', 'Asynchronous job deletion must reauthorize against the current generation.');
contains('backend/app/Http/Controllers/Concerns/ActionNotificationJobConcern.php', '$this->operationStorage->delete($path) && ! $this->operationStorage->exists($path)', 'Artifact deletion must verify object removal.');

// QR anti-relay protocol.
contains('backend/app/Services/QrScannerService.php', 'hash_hmac', 'QR scans must carry an HMAC device proof.');
contains('backend/app/Services/QrScannerService.php', 'allowed_networks', 'QR scans must enforce an allowed network policy.');
contains('backend/app/Services/QrScannerService.php', 'consumed_at', 'QR challenges must be single-use.');
contains('backend/app/Console/Commands/ProductionReadinessCheck.php', 'QR scanner networks explicit and restricted', 'Production readiness must reject missing or public QR scanner networks.');
contains('backend/app/Console/Commands/ProductionReadinessCheck.php', 'validRestrictedCidr', 'Production readiness must validate QR scanner CIDR ranges.');
contains('backend/routes/api.php', '/qr/scan/challenge', 'QR challenge endpoint is missing.');
excludes('backend/routes/web.php', '/qr/scan', 'Browser QR scan endpoint must remain closed.');

// Runtime readiness, queue visibility, alerting, and central logging.
for (const file of ['deploy/alertmanager.yaml', 'deploy/loki.yaml', 'deploy/promtail.yaml']) {
  check(exists(file), `${file} is missing.`);
}
contains('deploy/prometheus.yaml', 'alertmanagers:', 'Prometheus must deliver alerts to Alertmanager.');
contains('deploy/grafana/provisioning/datasources/datasources.yaml', 'Loki', 'Grafana must provision the Loki datasource.');
contains('backend/app/Console/Commands/ProductionReadinessCheck.php', 'KMS envelope encrypt/decrypt', 'Readiness must test KMS encryption/decryption.');
contains('backend/app/Console/Commands/ProductionReadinessCheck.php', 'Operation storage signed read/write/delete', 'Readiness must test signed storage read and verified deletion.');
contains('backend/app/Console/Commands/ProductionReadinessCheck.php', 'Queue worker roundtrip', 'Readiness must test a queue round trip.');
contains('backend/app/Services/QueueMonitorService.php', 'Redis', 'Queue monitoring must inspect the configured Redis queue.');
contains('backend/app/Console/Commands/RuntimeHeartbeat.php', 'scheduler', 'Runtime heartbeat must cover the scheduler.');
contains('backend/app/Console/Commands/RuntimeHeartbeat.php', 'worker', 'Runtime heartbeat must cover workers.');
contains('docker-compose.production.yml', 'max-file:', 'Container logs must have bounded retention.');
excludes('docker-compose.production.yml', '/var/run/docker.sock', 'Central logging must not expose the Docker control socket.');
check(exists('backend/app/Console/Commands/OperationalDrill.php'), 'End-to-end operational drill command is missing.');
contains('backend/app/Console/Commands/OperationalDrill.php', 'Mail::raw', 'Operational drill must perform an SMTP transaction.');
contains('backend/app/Console/Commands/OperationalDrill.php', 'alert_received', 'Operational drill must confirm Alertmanager delivery.');
contains('backend/app/Console/Commands/OperationalDrill.php', 'reverb_received', 'Operational drill must confirm realtime subscriber delivery.');
contains('backend/app/Console/Commands/OperationalDrill.php', 'mail_received', 'Operational drill must confirm downstream mail delivery.');
contains('backend/app/Console/Commands/OperationalDrill.php', 'lokiQueryUrl', 'Operational drill must confirm centralized log ingestion.');
contains('deploy/scripts/production-up.sh', 'pesantenku:operational-drill', 'Compose promotion must block on the operational drill.');
contains('deploy/scripts/canary-deploy.sh', 'pesantenku:operational-drill', 'Canary promotion must block on the operational drill.');
contains('deploy/prometheus-rules.yaml', 'PesantenkuQueueBacklog', 'Queue backlog alert is missing.');
contains('deploy/prometheus-rules.yaml', 'PesantenkuWorkerHeartbeatStale', 'Worker heartbeat alert is missing.');
contains('deploy/prometheus-rules.yaml', 'PesantenkuBackupStale', 'Backup-age alert is missing.');
contains('deploy/prometheus-rules.yaml', 'PesantenkuOperationalDrillStale', 'Operational drill freshness alert is missing.');
contains('deploy/prometheus-rules.yaml', 'PesantenkuMetricsExporterLeaderMissing', 'Metrics exporter leader-loss alert is missing.');

contains('docs/releases/2026-07-12-production.md', 'Superseded', 'Historical production runbook must be clearly marked superseded.');
contains('docs/releases/2026-07-12-production.md', 'queue-default queue-backup queue-import queue-export', 'Historical rollback commands must reference current worker services.');
contains('docs/releases/2026-07-12-production.md', 'queue:monitor default,backups-v1,imports-v3,exports-v3', 'Historical verification commands must reference current versioned queue names.');

// Deployment/recovery and supply-chain enforcement.
contains('deploy/scripts/production-up.sh', 'previous', 'Compose rollout must retain the previous image state.');
contains('deploy/scripts/production-up.sh', 'Drain long-running consumers before replacing their containers', 'Compose rollout must drain workers deliberately.');
contains('deploy/scripts/production-up.sh', 'cosign verify-attestation', 'Compose deployment must verify provenance/SBOM attestations.');
contains('deploy/scripts/production-up.sh', 'php artisan migrate --force --isolated', 'Compose migrations must use the distributed migration lock.');
contains('deploy/scripts/production-up.sh', 'for service in app queue-default queue-backup queue-import queue-export scheduler reverb', 'Compose rollback must verify every runtime service.');
contains('deploy/scripts/production-up.sh', 'health publik tidak pulih', 'Compose rollback must verify public health recovery.');
contains('deploy/scripts/canary-deploy.sh', 'cosign verify-attestation', 'Canary deployment must verify provenance/SBOM attestations.');
check(exists('deploy/k8s/kyverno-image-policy.template.yaml'), 'Cluster image signature admission policy is missing.');
contains('.github/workflows/release.yml', 'cosign attest --yes --type slsaprovenance', 'Release must publish a signed SLSA provenance attestation.');
contains('.github/workflows/release.yml', 'cosign attest --yes --type spdxjson', 'Release must publish a signed SPDX SBOM attestation.');
contains('deploy/k8s/kyverno-image-policy.template.yaml', 'predicateType: https://slsa.dev/provenance/v0.2', 'Admission policy must require signed SLSA provenance.');
contains('deploy/k8s/kyverno-image-policy.template.yaml', 'predicateType: https://spdx.dev/Document', 'Admission policy must require a signed SPDX SBOM.');
contains('deploy/k8s/kyverno-image-policy.template.yaml', 'failurePolicy: Fail', 'Image verification admission must fail closed.');
check(exists('deploy/k8s/network-policies.yaml'), 'Kubernetes NetworkPolicy is missing.');
contains('deploy/k8s/network-policies.yaml', '__NAMESPACE__', 'NetworkPolicy must be rendered for the selected deployment namespace.');
contains('mobile/android/gradle/wrapper/gradle-wrapper.properties', /^distributionSha256Sum=[a-f0-9]{64}$/m, 'Gradle wrapper distribution checksum is missing or invalid.');
contains('.github/workflows/ci.yml', '${{ vars.MYSQL_TEST_IMAGE }}', 'CI MySQL service must use a reviewed immutable image variable.');
contains('.github/workflows/release.yml', '${{ vars.MYSQL_TEST_IMAGE }}', 'Release MySQL service must use a reviewed immutable image variable.');
contains('docker-compose.recovery.yml', 'MYSQL_RECOVERY_IMAGE must be an immutable digest', 'Recovery MySQL image must be supplied as an immutable digest.');
contains('docker-compose.integration.yml', 'MINIO_IMAGE must be an immutable digest', 'Integration object-storage image must be supplied as an immutable digest.');
contains('deploy/k8s/network-policies.yaml', 'port: 9093', 'Application egress to Alertmanager must be explicitly allowed.');
contains('deploy/k8s/network-policies.yaml', 'port: 3100', 'Application egress to Loki must be explicitly allowed.');
contains('deploy/scripts/pitr-drill.sh', 'mysqlbinlog', 'PITR drill must use MySQL binlog tooling.');
contains('deploy/scripts/pitr-drill.sh', 'FLUSH BINARY LOGS', 'PITR drill must exercise binlog rotation.');
contains('deploy/scripts/pitr-drill.sh', 'CONCURRENT_WRITER_PID', 'PITR drill must include a concurrent writer.');

// Frontend/mobile and maintainability hardening.
excludes('backend/resources/views/app.blade.php', /fonts\.(googleapis|bunny)\.com/i, 'Web fonts must not depend on runtime third parties.');
excludes('mobile/pubspec.yaml', /google_fonts/i, 'Mobile fonts must be bundled or use platform defaults.');
excludes('backend/resources/js/Pages/Login.jsx', /admin@pesantrenku|password123|demo@/i, 'Production frontend source must not contain demo credentials.');
contains('backend/tests/e2e/csp-guard.js', 'securitypolicyviolation', 'Browser E2E must fail on production CSP violations.');
contains('backend/scripts/check-performance-budget.mjs', 'budget', 'A frontend performance budget gate is missing.');
contains('backend/app/Services/MetricRegistry.php', 'retention', 'Metric registry keys must have bounded retention.');
contains('backend/app/Services/MetricRegistry.php', 'claimExporterLease', 'Shared metrics must elect one exporter per track and revision.');
contains('backend/app/Services/MetricRegistry.php', "($labels['track'] ?? null) !== $identity['track']", 'Metrics exporter must not expose another track or revision.');
contains('docker-compose.production.yml', 'PESANTRENKU_METRICS_REVISION is required', 'Compose metrics must be bound to the deployed immutable revision.');
contains('docs/openapi.yaml', 'license:', 'OpenAPI license metadata is missing.');
check(fs.statSync(path.join(repositoryRoot, 'backend/public/favicon.ico')).size > 0, 'Favicon is empty.');
check(lineCount('backend/resources/js/Pages/App.jsx') <= 1800, 'App.jsx exceeds the RC6 maintainability budget of 1800 lines.');
check(lineCount('backend/app/Http/Controllers/CrudController.php') <= 1100, 'CrudController exceeds the RC6 maintainability budget of 1100 lines.');
check(lineCount('backend/app/Http/Controllers/ActionController.php') <= 200, 'ActionController exceeds the RC6 maintainability budget of 200 lines.');
check(lineCount('backend/app/Services/DashboardService.php') <= 400, 'DashboardService exceeds the RC6 maintainability budget of 400 lines.');

if (failures.length) {
  console.error(`Hardening contract check failed: ${failures.length}/${checks}`);
  failures.forEach((failure, index) => console.error(`${index + 1}. ${failure}`));
  process.exit(1);
}

console.log(`Hardening contract check passed: ${checks}/${checks}`);
