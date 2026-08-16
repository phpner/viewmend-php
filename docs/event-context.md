# Site Tracker event context

Site Tracker events can include optional `changed_fields` and `metadata` supplied by the sending integration. These values add context to an event; they are not required to send an event and are separate from `site()` and `page()` targeting.

## Meaning

- The sending integration declares the values. ViewMend stores them as event context.
- They are not confirmation that a real website change occurred.
- They do not restrict the layers, content, or components evaluated by a subsequent Tracker check.
- Actual changes are determined from check results and comparison data.

Storage is the only behavior described here; this document makes no claim about UI presentation.

## Example

```php
<?php

declare(strict_types=1);

use ViewMend\ViewMend;

require __DIR__ . '/vendor/autoload.php';

$token = getenv('VIEWMEND_API_TOKEN');
if ($token === false || trim($token) === '') {
    throw new \RuntimeException('VIEWMEND_API_TOKEN is required.');
}
$token = trim($token);

$integrationId = getenv('VIEWMEND_INTEGRATION_ID');
if ($integrationId === false || trim($integrationId) === '') {
    throw new \RuntimeException('VIEWMEND_INTEGRATION_ID is required.');
}
$integrationId = trim($integrationId);

$viewmend = ViewMend::client(token: $token);

$result = $viewmend
    ->siteTracker($integrationId)
    ->events()
    ->deployment(
        id: 'deploy-abc123',
        title: 'Homepage deployed',
    )
    ->contentChanged()
    ->metadataChanged()
    ->customFieldChanged('product_schema')
    ->metadata(['commit' => 'abc123'])
    ->send();
```

`contentChanged()`, `metadataChanged()`, and `customFieldChanged()` append values to `changed_fields`. The separate `metadata()` method attaches a JSON-serializable array or object to the event.

## Contract limits

- `changed_fields` accepts at most 50 values.
- Each `changed_fields` value can contain at most 120 characters.
- `metadata` must be a JSON-serializable array or object.

The SDK validates these constraints before sending the event.
