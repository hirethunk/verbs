## Upgrading to `v0.5.1`

The structure of the `verbs_snapshots` table changed after version `0.4.5` to better account for
non-Snowflake IDs (like ULIDs/etc). Running migrations should update your tables accordingly:

```
php artisan vendor:publish --tag=verbs-migrations
php artisan migrate
```

### The `__verbs_snapshots_pre_050` table

Once you migrate, you will find a new table called `__verbs_snapshots_pre_050` which is a copy
of the `verb_snapshots` table as it existed before the migration. Out of an abundance of caution,
we are leaving that table as-is for you to delete when you are certain you will not need to
downgrade or migrate down.

### What changed

Part of the `v0.5.x` updates includes the following changes to the `verb_snapshots` table:

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
