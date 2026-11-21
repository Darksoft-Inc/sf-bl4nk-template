# Production datasets

Files in this directory are git-ignored, because production records hold
real values: the admin password, real names, real content. Create them
on the server, next to `.env.local`.

The loader never touches rows whose dataset slug column is null, so a
production load is safe to re-run: a slug new to the database inserts a
row, a known slug updates the fields the record names, and rows created
through the application stay as they are.

The first production load usually creates the admin account:

```yaml
users:
    admin:
        email: you@example.org
        # Choose a real password; it is hashed at load time.
        password: 'a real secret'
        roles: [ROLE_ADMIN]
        isVerified: true
```

Run the load against the production database:

```bash
symfony console app:dataset:load --env=prod
```

A later release can update a few records instead of repeating the whole set: a file whose references name rows of an earlier load loads on its own. Large loads write in batches; `--batch-size=N` bounds memory further.

The `--purge` option is refused in the prod environment, on purpose: it
deletes every row that carries a dataset slug. Rows dropped from a
dataset are removed with a removal entry instead, which names only the
slug:

```yaml
users:
    former-account:
        remove: true
```

The removal entry can be dropped from the file on the next load, once
the row is gone.
