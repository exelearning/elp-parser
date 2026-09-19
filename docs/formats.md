# Supported formats

## Legacy eXeLearning 2.x

Legacy projects contain `contentv3.xml` and are reported with:

- format family: `legacy`;
- content format: `legacy-contentv3`;
- compatibility major: `2`.

## Modern ODE packages

Modern projects contain `content.xml` with ODE root version `2.0`.

eXeLearning 3 and 4 use the same XML format family. The parser reports application/package compatibility separately from the ODE format version.

Useful APIs:

```php
$parser->getFormatFamily();
$parser->getFormatVersion();
$parser->getApplicationVersion();
$parser->getPackageProfile();
$parser->getResourceProfile();
$parser->getVersionInfo();
```

Modern packages support both the historical `eXeVersion` resource key and the current `exe_version` key.

## Resources

The parser recognizes both common v3 UUID-style resource directories and the v4 resource-tree convention under `content/resources/`.
