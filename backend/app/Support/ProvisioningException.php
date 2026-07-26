<?php

namespace App\Support;

use RuntimeException;

/**
 * Raised when a provisioning intent cannot be satisfied safely.
 *
 * Every throw of this exception must leave the database unchanged: the
 * provisioning workflow runs inside a transaction and records the rejection in
 * the audit trail only after that transaction has rolled back.
 */
final class ProvisioningException extends RuntimeException {}
