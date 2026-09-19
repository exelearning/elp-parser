# Command-line interface

Installing the package with Composer exposes `vendor/bin/elp-parser`.

## Commands

```text
elp-parser inspect <file> [--json]
elp-parser validate <file> [--json]
elp-parser manifest <file>
elp-parser assets <file>
elp-parser json <file> [--detailed]
elp-parser diff <left> <right>
elp-parser fingerprint <file> [--json]
elp-parser extract <file> <destination>
elp-parser help
```

## Exit codes

- `0`: command succeeded; validation passed; or projects have no semantic differences.
- `1`: parser/runtime error; validation failed; or projects differ.
- `2`: invalid CLI usage.

This makes validation and semantic comparison useful in CI:

```bash
vendor/bin/elp-parser validate build/course.elpx
vendor/bin/elp-parser diff previous.elpx current.elpx
```

## JSON output

```bash
vendor/bin/elp-parser inspect course.elpx --json
vendor/bin/elp-parser validate course.elpx --json
vendor/bin/elp-parser fingerprint course.elpx --json
vendor/bin/elp-parser json course.elpx --detailed
```

The CLI is intentionally dependency-free and delegates all parsing, validation and security behavior to the library APIs.
