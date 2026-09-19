# Validation

Parsing is intentionally tolerant. Validation is explicit.

```php
$result = $parser->validate();

foreach ($result['errors'] as $error) {
    printf("ERROR %s: %s\n", $error['code'], $error['message']);
}

foreach ($result['warnings'] as $warning) {
    printf("WARN %s: %s\n", $warning['code'], $warning['message']);
}
```

Diagnostics cover package references, IDs, page hierarchy, ordering, internal `exe-node:` links, iDevice runtime availability, iDevice state decoding and expected v4 package files.

## Schema validation

Schema validation is optional and uses only a caller-supplied trusted local XSD/DTD.

```php
use Exelearning\Validation\SchemaValidator;

$xsd = $parser->validateSchema('/trusted/ode.xsd');
$dtd = $parser->validateSchema(
    '/trusted/content.dtd',
    SchemaValidator::TYPE_DTD
);
```

Network loading remains disabled. Do not treat a DTD embedded in an untrusted package as a trusted schema.
