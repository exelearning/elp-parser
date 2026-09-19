# Performance

Use the lightest API that fits the task.

- `ELPParser::inspect()` for cataloging and metadata-only workflows.
- Full parsing when pages, iDevices, assets, validation or semantic diffs are required.
- Stream APIs for upload/remote-storage integrations.

Full parser instances build page/block/iDevice indexes and cache aggregate diagnostics because parsed state is immutable after construction.

The weekly upstream compatibility workflow records per-project timings, total runtime, peak memory and the slowest fixtures. These measurements are informational rather than hard CI timing thresholds.

For large public uploads, combine lightweight inspection with appropriate `ArchiveLimits` before performing more expensive downstream processing.
