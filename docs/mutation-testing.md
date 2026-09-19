# Mutation testing

Line coverage measures whether code executes. Mutation testing measures whether the test suite can detect meaningful behavioral changes.

The project uses Infection 0.35.4 with the default mutator profile.

Configured quality gates:

- minimum MSI: **75%**;
- minimum covered-code MSI: **85%**;
- maximum timed-out mutants: **0**.

## Pull requests

PRs that change source/tests run Infection only against changed source lines relative to the PR base branch. This keeps review feedback focused and execution time bounded.

Escaped mutants are emitted as GitHub annotations.

## Full mutation run

A complete mutation run over `src/` executes weekly and can also be started manually with `workflow_dispatch`.

The mutation tool runs only in a dedicated PHP 8.4 job. Infection's own PHP requirement therefore does not change the parser's PHP 8.0 runtime support.

## Local use

Download the pinned Infection PHAR, verify its SHA-256 checksum, install project dependencies, then run:

```bash
php infection.phar --threads=max
```

The committed `infection.json` contains the shared thresholds and source/test-runner configuration.
