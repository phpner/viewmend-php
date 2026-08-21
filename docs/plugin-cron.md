# Cron integration contract for plugins

Cron lets a ViewMend-connected plugin register one scheduled callback without exposing arbitrary outbound-request controls.

## Ownership of settings

The user creates a connection in ViewMend by entering a name and domain. ViewMend shows one connection token once. The user pastes that token into the plugin, where they choose the schedule.

The plugin registers:

- a five-field cron expression;
- an IANA timezone;
- a callback path on the connected domain;
- a stable plugin ID and optional version;
- whether the schedule is enabled.

The plugin does not register a host, method, custom headers, or custom request body. ViewMend combines the stored domain with the path, requires HTTPS, and always sends `POST`.

Cron connection tokens and Site Tracker API tokens have separate scopes. Passing a `vmt_` Site Tracker token to a Cron operation returns `401 token_scope_invalid`; the SDK maps it to `TokenScopeException`. This classification uses only the token format and does not confirm that the supplied Site Tracker token exists or is valid.

## Registration

```php
$viewmend = ViewMend\ViewMend::client(token: $token);
$cron = $viewmend->cron();

$registration = $cron->register(
    cron: '*/15 * * * *',
    timezone: 'Europe/London',
    endpointPath: '/wp-json/viewmend/v1/cron',
    pluginId: 'viewmend-wordpress',
    pluginVersion: '1.2.0',
);
```

The server validates a minimum interval, verifies DNS and the destination network at delivery time, and rejects paths containing a host, query, fragment, or parent traversal. A new or changed endpoint remains `pending_verification` until its challenge is returned successfully.

`current()` returns the typed registration or `null` when the plugin has not registered one. `disable()` idempotently pauses the schedule.

## Callback authentication

ViewMend sends these headers:

- `X-ViewMend-Key-Id` identifies the active signing key;
- `X-ViewMend-Request-Id` is the stable run ID;
- `X-ViewMend-Timestamp` is a Unix timestamp;
- `X-ViewMend-Signature` is `v1=` followed by an HMAC-SHA256 signature.

The signed value is `timestamp + "." + raw request body`. Pass the exact body bytes and request headers to `CallbackVerifier`; do not decode and re-encode JSON before verification.

```php
$callback = $cron->verifyCallback($requestHeaders, $rawRequestBody);
```

The verifier rejects missing or duplicate signature headers, invalid signatures, mismatched connection or request IDs, malformed payloads, and timestamps more than five minutes from the local clock.

## Verification and runs

For a `cron.verification` callback, return `verificationResponseBody()` as JSON with a 2xx status. It contains only the signed challenge expected by ViewMend.

For a `cron.run` callback, perform the scheduled plugin work and return a 2xx status on success. A transient failure can be retried, so delivery is at least once. The same logical delivery retains its `runId` while `attempt` increases. Persist completed run IDs and skip duplicate side effects.

Do not log the connection token, signing secret, raw Authorization header, or signature.
