# Release supply chain

Tagged releases publish more than the Composer archive.

Each release contains:

- `elp-parser-<tag>.zip` — distributable package archive;
- `elp-parser-<tag>.cdx.json` — CycloneDX JSON software bill of materials;
- `SHA256SUMS` — SHA-256 checksums for the archive and SBOM.

GitHub Artifact Attestations are also created for:

- build provenance of the ZIP artifact;
- the CycloneDX SBOM bound to that ZIP artifact.

The attestation actions use GitHub OIDC and repository-scoped workflow permissions; no additional signing key or long-lived secret is stored in the repository.

## Verify checksums

```bash
sha256sum --check SHA256SUMS
```

## Verify GitHub attestations

Consumers with the GitHub CLI can verify release artifact attestations using GitHub's artifact-attestation commands against the repository identity.

A dedicated pull-request workflow builds a test archive, generates the SBOM and validates checksums/CycloneDX structure so release packaging changes are checked before a tag is created.
