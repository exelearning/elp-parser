# Mutation testing

Line coverage measures whether code executes. Mutation testing measures whether the test suite can detect meaningful behavioral changes.

The project uses Pest's native mutation testing on PHP 8.4.

Configured quality gate:

- minimum mutation score: **80%**;
- only covered code is mutated, because line coverage is enforced separately at 90%.

## Pull requests

PRs that modify source or tests mutate only code changed relative to the common ancestor with `main`:

```bash
vendor/bin/pest \
  --mutate \
  --parallel \
  --changed-only \
  --covered-only \
  --ignore-min-score-on-zero-mutations \
  --min=80
```

The zero-mutation exception is intentional for PRs that only affect test infrastructure or non-PHP files selected by the workflow.

## Scheduled and manual full runs

A complete mutation run over all covered source code executes weekly and can also be started manually with `workflow_dispatch`:

```bash
vendor/bin/pest \
  --mutate \
  --parallel \
  --everything \
  --covered-only \
  --min=80
```

## Why PHP 8.4 only?

Mutation tooling has a newer PHP requirement than the parser itself. Keeping mutation testing in a dedicated PHP 8.4 job avoids changing the library's PHP 8.0 runtime support.
