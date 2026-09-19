# Mutation testing

Line coverage measures whether code executes. Mutation testing measures whether the test suite can detect meaningful behavioral changes.

The project uses Pest's native mutation testing on PHP 8.4.

Configured quality gate:

- minimum mutation score: **80%**;
- only covered code is mutated, because line coverage is enforced separately at 90%.

## Pull requests

PRs that modify source, tests or test configuration run:

```bash
vendor/bin/pest --mutate --parallel --covered-only --min=80
```

This keeps mutation quality as a required code-review signal while the regular PHP 8.0–8.5 matrix continues to validate runtime compatibility.

## Scheduled and manual runs

The same mutation gate runs weekly and can be started manually with `workflow_dispatch`.

## Why PHP 8.4 only?

Mutation tooling has a newer PHP requirement than the parser itself. Keeping mutation testing in a dedicated PHP 8.4 job avoids changing the library's PHP 8.0 runtime support.

## Local use

On a development environment that resolves Pest 5:

```bash
composer update
vendor/bin/pest --mutate --parallel --covered-only --min=80
```
