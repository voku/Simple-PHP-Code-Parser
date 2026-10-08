# Parser performance comparison

Run each revision in a fresh PHP process against the same corpus and dependency
installation. The harness overrides only this library's PSR-4 source directory
and verifies which parser file was loaded.

```bash
git worktree add --detach /tmp/spcp-baseline dc531d5
php benchmarks/parser.php /tmp/spcp-baseline ../agent-map/src files
php benchmarks/parser.php . ../agent-map/src files
php benchmarks/parser.php /tmp/spcp-baseline ../agent-map/src directory
php benchmarks/parser.php . ../agent-map/src directory
```

Use at least three interleaved runs per revision, alternate their order, and
compare medians. Run without simultaneous tests or other benchmark processes.
Warm each directory variant first: the old persistent file-content cache can
change insertion order when some files hit and others miss.

Both modes use `ParserOptions::astOnly()`. `files` measures model extraction
from already-read source strings; `directory` also measures discovery and file
reads. Normalizing output and computing the fingerprint happen outside the
timed section. Peak memory includes normalization, so it is not a measurement
of parser memory alone. This is a library benchmark, not an end-to-end map refresh.

The `model_sha256` covers public model fields, PHPDoc string representations,
and parse errors, excluding container back-references. Compare fingerprints
within the same mode. Pass a fourth argument to save the normalized snapshot:

```bash
php benchmarks/parser.php . ../agent-map/src directory /tmp/parser-models.json
```

The script emits one JSON record with elapsed milliseconds, peak memory, file
count, and the model fingerprint. The corpus must remain unchanged throughout
the comparison. Remove the temporary worktree after use:

```bash
git worktree remove /tmp/spcp-baseline
```
