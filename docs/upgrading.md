## Upgrading to `v0.5.0`

The structure of the `verbs_snapshots` table changed after version `0.4.5` to better account for
non-Snowflake IDs (like ULIDs/etc). This change included:

- Adding a new `id` column that is unique to the **snapshot** (the ID column had previously
  been mapped to the **state**, which caused issues if the different states of different types
  had the same ID)
- Replaced the existing `id` column with a `state_id` that is not a primary index (allowing
  non-unique state IDs)
- Changed the `unique` index on `state_id` and `type` to a regular index to allow for future features
  that may let you store multiple snapshots per state
- Added an `expires_at` column to allow for snapshot purging in the future

For more details about the change, please [see the Verbs PR](https://github.com/hirethunk/verbs/pull/144)
that applied these changes.

If you’re having trouble figuring out how to migrate your existing data, please check this page in
a few days, or [ask on Discord](https://discord.gg/hDhZmD6ZC9) — we hope to have a sample migration
ready shortly!
