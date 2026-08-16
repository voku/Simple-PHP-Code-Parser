# Repository-local agent-loop dogfood

This directory is a separate Composer project on purpose.

`voku/agent-loop` is development tooling for this repository, not part of the package contract of `voku/simple-php-code-parser`. Keeping it out of the root `composer.json` avoids raising consumer PHP requirements, leaking agent tooling into downstream installs, and creating first-party dependency cycles such as `agent-map -> agent-loop -> agent-recall-compiler -> agent-map`.

## Real-issue replay

The first replay uses historical issue #60, `Update for use with PHP 8.4`.

The workflow freezes three things before context discovery:

- issue title/body and the pre-fix base commit `5156d5d74ca1bce275219f4571efd54ec44be911`;
- the released agent toolchain: `agent-loop 0.16.5`, `agent-kanban 0.3.1`, `agent-learning 0.13.0`, `agent-map 0.8.1`, `agent-recall-compiler 0.13.2`, and `agent-session 0.6.0`;
- agent-skills commit `c7e9d8bdda59d957600bca8dc9f787f03286b277` and the `reproduce-before-fix` L2 recipe.

The issue input contains no knowledge of the later fix files. `issue-60-oracle.json` is read only after map search and Recall compilation have finished.

The historical fix in PR #84 changed:

- `composer.json`;
- `src/voku/SimplePhpParser/Parsers/PhpCodeParser.php`.

Those files intentionally belong to two different evidence channels. `agent-map` indexes PHP source and is evaluated on whether its pre-fix search result included `PhpCodeParser.php`. Recall owns bounded repository metadata and is required to expose the exact `react/filesystem ^0.2@dev` constraint because the issue names that direct dependency. Recall must not invent Composer scripts when the historical project does not define them.

A map miss is recorded as a finding rather than converted into a fake correctness threshold. Deterministic provenance and Recall contract failures do fail the replay.

## Evidence

GitHub Actions archives the generated tool `composer.lock`, resolved package list, map search output, Recall bundle/facts/system prompt, and post-context evaluation. The lock proves the exact released package set Composer resolved for the run; no sibling checkout or candidate path repository participates in normal replay evidence.
