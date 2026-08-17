# Repository-local agent-loop dogfood

This directory is a separate Composer project on purpose.

`voku/agent-loop` is development tooling for this repository, not part of the package contract of `voku/simple-php-code-parser`. Keeping it out of the root `composer.json` avoids raising consumer PHP requirements, leaking agent tooling into downstream installs, and creating first-party dependency cycles such as `agent-map -> agent-loop -> agent-recall-compiler -> agent-map`.

`voku/agent-loop` is also the single root authority for its `agent-*` runtime dependency set. This tool project constrains `agent-loop` directly and keeps `voku/simple-php-code-parser` direct because that is the package under test. Resolved sibling `agent-*` versions are replay evidence, not duplicate root constraints.

## Real-issue replay

The first replay uses historical issue #60, `Update for use with PHP 8.4`.

The workflow freezes three things before context discovery:

- issue title/body and the pre-fix base commit `5156d5d74ca1bce275219f4571efd54ec44be911`;
- the `agent-loop` release recorded in the replay input as the direct first-party release-set authority;
- agent-skills commit `c7e9d8bdda59d957600bca8dc9f787f03286b277` and the `reproduce-before-fix` L2 recipe.

The replay `agent_loop_release` value and `tools/agent-loop/composer.json` intentionally describe the same direct dependency. Renovate owns updating those direct-version references together. Do not copy sibling `agent-*` versions into Composer or replay metadata; they are resolved evidence owned transitively by `agent-loop`.

Before resolution, `verify-release-set.php` fails if the tool project or replay input reintroduces sibling `agent-*` version authority. After resolution, the same verifier requires the complete first-party release set to be present in `composer.lock` and reports the versions Composer actually selected.

If a replay needs byte-for-byte dependency identity rather than compatibility through the frozen `agent-loop` release, commit and install an exact lock file. Do not approximate a lock by copying transitive package versions into another JSON authority.

The issue input contains no knowledge of the later fix files. `issue-60-oracle.json` is read only after map search and Recall compilation have finished.

The historical fix in PR #84 changed:

- `composer.json`;
- `src/voku/SimplePhpParser/Parsers/PhpCodeParser.php`.

Those files intentionally belong to two different evidence channels. `agent-map` indexes PHP source and is evaluated on whether its pre-fix search result included `PhpCodeParser.php`. Recall owns bounded repository metadata and is required to expose the exact `react/filesystem ^0.2@dev` constraint because the issue names that direct dependency. Recall must not invent Composer scripts when the historical project does not define them.

A map miss is recorded as a finding rather than converted into a fake correctness threshold. Deterministic provenance and Recall contract failures do fail the replay.

## Evidence

GitHub Actions archives the generated tool `composer.lock`, resolved package list, map search output, Recall bundle/facts/system prompt, and post-context evaluation. The generated lock and package list record the exact set Composer resolved for that run; `agent-loop` remains the only first-party release-set constraint that the replay owns.
