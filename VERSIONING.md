# Ready Estate version history

The GitHub repository is `https://github.com/sfahid/readyestate`. The `main` branch contains the current Ready Estate source. Its earlier Ledgercraft source commit is preserved in the history so it can be inspected, branched or restored. A `ledgercraft-baseline` branch points to that earlier state. The `readyestate-v1` tag identifies the first Ready Estate release.

For each future source update, open `ready-estate.code-workspace` in VS Code, test the change, then use Source Control to commit it and push to `origin/main`. The equivalent Git commands from this folder are:

```text
git status
git add -A
git commit -m "Describe the change"
git push origin main
```

For a new line of work, create a branch before editing:

```text
git switch -c feature/your-change
git push -u origin feature/your-change
```

To inspect an older version without changing `main`, create a branch from a tag or commit, for example `git switch -c review/v1 readyestate-v1`. To roll back a released change on `main`, review its impact and use a new `git revert` commit rather than rewriting published history. Git history rolls back source code; it does **not** roll back MySQL records or schema changes. Back up the database separately before migrations.

The WAMP application at `C:/wamp64/www/readyestate` is a separate deployment copy. After testing a source change, copy only the reviewed application files there. Keep its private `config/config.php` and live database records. Never commit either of them. Generated test data and screenshots are ignored.
