# Kubernetes production secret boundaries

The canary deployment deliberately uses separate Kubernetes Secrets so a compromise of one process does not expose every production credential.

| Secret | Intended process | Sensitive values permitted |
|---|---|---|
| `pesantenku-app-env` | HTTP application | `APP_KEY`, application DB/Redis credentials, object-storage credentials, SMTP credentials, Reverb client credentials, metrics bearer token |
| `pesantenku-worker-default-env` | Default queue worker | `APP_KEY`, application DB/Redis credentials, SMTP credentials, Reverb client credentials |
| `pesantenku-worker-backup-env` | Backup/restore worker | `APP_KEY`, application DB/Redis credentials, object-storage credentials, KMS key identifier/access |
| `pesantenku-worker-import-env` | Import worker | `APP_KEY`, application DB/Redis credentials, object-storage credentials |
| `pesantenku-worker-export-env` | Export worker | `APP_KEY`, application DB/Redis credentials, object-storage credentials |
| `pesantenku-migration-env` | Ephemeral migration/readiness Job | The union needed for migration and non-destructive integration probes |

Do not place database root credentials, Grafana administrator credentials, registry credentials, Kubernetes credentials, or bootstrap plaintext in any application Secret. Bootstrap password and TOTP values must be mounted as one-time files from the cluster secret manager and removed after successful provisioning.

Redis must require authentication. Use a dedicated application ACL identity in managed Redis when available, and put only that identity in the process Secrets above. Rotate process Secrets independently and restart only the workloads that consume the rotated credential.
