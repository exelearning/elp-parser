# Upgrading

## Compatibility policy

Public methods and documented array/JSON fields are intended to follow Semantic Versioning.

Minor releases may add:

- new getters and model types;
- new optional diagnostics;
- new manifest fields;
- support for additional eXeLearning package conventions.

Breaking changes to existing public methods or documented output contracts should be reserved for a major release.

## Deprecations

Deprecated APIs should remain available for at least one minor release cycle where practical and should document their replacement.

## Format versions

The eXeLearning application version and the package/XML format version are separate concepts. eXeLearning 3 and 4 both use the modern ODE `content.xml` format with root format version `2.0`. Use `getFormatVersion()`, `getApplicationVersion()` and `getVersionInfo()` instead of deriving one concept from another.
