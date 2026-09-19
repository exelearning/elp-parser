# Detailed JSON Schema

`toDetailedArray()` and `exportDetailedJson()` expose a versioned machine-readable contract.

The current schema version is `1.0`.

```php
echo ELPParser::getDetailedSchemaVersion();

$schemaPath = ELPParser::getDetailedJsonSchemaPath();
$schemaJson = ELPParser::getDetailedJsonSchema();

$data = $parser->toDetailedArray();
echo $data['schemaVersion'];
```

The bundled schema is stored at:

```text
schema/elp-parser-1.0.schema.json
```

It uses JSON Schema draft 2020-12.

## Compatibility

The `schemaVersion` is independent from:

- the eXeLearning application version;
- the ODE XML format version;
- the PHP package version.

Additive fields may be introduced without changing the schema major version because the schema intentionally allows additional properties in extensible records. Removing fields, changing field meanings or incompatible type changes require a new schema version.

The compact `toArray()` / `exportJson()` representation remains unchanged and is not governed by this detailed schema.
