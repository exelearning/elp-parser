# Mutation testing

Line coverage measures whether code executes. Mutation testing measures whether the test suite can detect meaningful behavioral changes.

The project uses Pest's native mutation testing on PHP 8.4.

Configured quality gate:

- minimum mutation score: **80%**;
- only covered code is mutated, because line coverage is enforced separately at 90%.

## Pull requests

PRs calculate the changed PHP files under `src/` with Git and pass the resulting comma-separated list to Pest's supported `--path` filter:

```bash
MUTATION_PATHS="$(git diff --name-only --diff-filter=AMR "origin/$BASE_REF...HEAD" -- 'src/*.php' 'src/**/*.php' | paste -sd, -)"

vendor/bin/pest \
  --mutate \
  --parallel \
  --path="$MUTATION_PATHS" \
  --covered-only \
  --ignore-min-score-on-zero-mutations \
  --min=80
```

When no PHP source file changed, the mutation job exits successfully without launching the mutation engine.

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
