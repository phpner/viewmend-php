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

## Loading saved settings

ViewMend is the source of truth for the registered schedule. The plugin should keep the connection token locally and call `current()` whenever its settings screen opens:

```php
use ViewMend\Exception\AuthenticationException;
use ViewMend\Exception\EndpointDisabledException;
use ViewMend\Exception\NetworkException;
use ViewMend\Exception\ServerException;
use ViewMend\Exception\TokenScopeException;
use ViewMend\ViewMend;

$cron = ViewMend::client(token: $token)->cron();

try {
    $settings = $cron->current();

    if ($settings === null) {
        // First setup: show defaults and let the user create a schedule.
    } else {
        $form = [
            'cron' => $settings->cron,
            'timezone' => $settings->timezone,
            'enabled' => $settings->enabled,
        ];

        $serverState = [
            'domain' => $settings->domain,
            'endpoint' => $settings->endpointUrl,
            'status' => $settings->status,
            'verified_at' => $settings->verifiedAt,
            'next_run_at' => $settings->nextRunAt,
            'last_run_at' => $settings->lastRunAt,
            'consecutive_failures' => $settings->consecutiveFailures,
            'updated_at' => $settings->updatedAt,
        ];
    }
} catch (TokenScopeException) {
    // The supplied token has the Site Tracker format and cannot access Cron.
} catch (AuthenticationException) {
    // The Cron connection token is invalid or has been rotated.
} catch (EndpointDisabledException) {
    // The ViewMend connection is disabled.
} catch (NetworkException|ServerException) {
    // ViewMend is temporarily unavailable. Do not replace known settings with defaults.
}
```

`current()` makes `GET /api/v1/cron/registration`. A `404 registration_not_found` response becomes `null`; authentication, scope, disabled-connection, network, and server failures remain exceptions and must not be treated as an empty schedule.

Use `cron`, `timezone`, and `enabled` to populate editable controls. Treat `domain`, `endpointUrl`, `method`, `pluginId`, `pluginVersion`, `status`, verification and run times, and `consecutiveFailures` as server state. The known status values are:

- `waiting_plugin`: the token was rotated and the plugin must save its settings again;
- `pending_verification`: ViewMend is checking the callback;
- `active`: the verified schedule can run;
- `paused`: the schedule is disabled;
- `verification_failed`: the callback did not complete verification.

After the user saves, use the `RegistrationResult` returned by `register()` to refresh the form and status immediately; do not make a redundant `current()` request. A plugin may cache the last successful result for temporary offline display, but it must mark that snapshot as stale, replace it after the next successful response, and never include the connection token in the snapshot, logs, or diagnostics.

Catch `TokenScopeException` before `AuthenticationException` because `TokenScopeException` extends it. If `current()` returns settings for a newer server update than the plugin's cached copy, the server response wins.

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
