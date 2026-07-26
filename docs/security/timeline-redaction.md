# Timeline history redaction (M-REMAIN-06)

## The problem

`audit_logs.metadata` stores complete `before` and `after` snapshots of a row.
That is the right behaviour for an audit trail. The problem is the replay path:
the timeline endpoint returns those snapshots to whoever is allowed to read the
row **now**.

When a row changes hands, the reader therefore receives personal data that no
longer belongs to them:

- a santri reassigned to a different guardian keeps the previous guardian's
  name, phone number and address in the history;
- a reassigned account keeps the previous user's identifiers;
- a corrected contact field keeps the superseded value in clear text;
- a room or class transfer keeps the previous occupant's context.

Tenant scoping does not help here, because the leak happens inside one tenant.

## The rule

`App\Support\TimelineRedactor` applies one narrow rule:

> A personal field is returned only while its value still matches the current
> row. Once superseded, it is replaced with `[diredaksi]`.

The timeline still shows that a change happened and when, without disclosing
the previous personal value. Secret-like keys (`password`, `secret`, `token`,
`mfa`, `recovery_code`, `signature`, `private_key`) are removed
unconditionally. Unknown current state fails closed, meaning it redacts.

Structural fields such as `status`, `id` and timestamps are untouched, so the
timeline remains useful for operational review.

## Remaining wiring

The redactor is not yet called. `CrudController::timeline` fetches history with:

```php
$logs = DB::table('audit_logs')
    ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
    ->where('audit_logs.module', $table)
    ->where('audit_logs.row_id', $id)
```

Before returning, each entry's decoded `metadata` must pass through the
redactor, using the already-loaded scoped `$row` as the current state:

```php
use App\Support\TimelineRedactor;

$metadata = json_decode((string) $log->metadata, true);
$log->metadata = is_array($metadata)
    ? TimelineRedactor::redactMetadata($metadata, $row)
    : null;
```

Apply this inside the existing mapping over `$logs` so the response shape does
not change. Note that `metadata` becomes a decoded array rather than a JSON
string if the current response returns it raw; keep whichever form the frontend
already consumes.

Until this call site is wired, **M-REMAIN-06 remains open** and the release
decision stays NO-GO for production.

## Verification

A regression test should cover, at minimum:

1. an unchanged personal field stays visible;
2. a changed personal field is replaced with `[diredaksi]` in both the `before`
   and `after` snapshot of the entry that changed it, and in every older entry;
3. a secret-like key is redacted even when unchanged;
4. `status` and timestamps stay readable.
