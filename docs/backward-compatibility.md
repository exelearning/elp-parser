# Backward compatibility

The library treats its documented public PHP API as a Semantic Versioning contract.

Pull requests that modify `src/` or Composer metadata run Roave Backward Compatibility Check against:

```text
origin/main -> pull request HEAD
```

The check detects incompatible public API changes such as removed symbols, incompatible method signatures and other contract changes in Composer-autoloaded source.

## Intentional breaking changes

Breaking changes should be reserved for a planned major release. If a break is intentional:

1. document it in `CHANGELOG.md` and `UPGRADING.md`;
2. provide a migration path where practical;
3. update the BC workflow/baseline only as part of the explicitly reviewed major-version work.

The BC tool is installed only in its dedicated PHP 8.4 CI job so it does not constrain the package's PHP 8.0 runtime compatibility.
