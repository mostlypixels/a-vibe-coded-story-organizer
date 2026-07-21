# Dependency overrides

Sometimes a package we depend on depends, in turn, on something we do **not** want the
version of. We cannot edit another project's `package.json`, and bumping our own direct
dependency does not always help — the maintainer may not have released a fix yet, or may
have pinned the bad version deliberately.

An **override** is npm's escape hatch for exactly that case: it lets us dictate which
version of a *transitive* dependency gets installed, no matter what the package in between
asked for.

This page explains the one override this project carries, when adding another is the right
call, and the traps to watch for.

## The mechanism

npm reads an `overrides` block at the top level of `package.json`:

```json
"overrides": {
    "shell-quote": "^1.9.0"
}
```

Read that as: *"anywhere in the whole dependency tree, whoever asks for `shell-quote`, give
them `^1.9.0` instead of what they requested."* It applies at any depth, and it silently
overrules the intermediate package's own declared range.

> [!NOTE]
> This is npm-specific syntax. Yarn calls the same idea `resolutions`, and pnpm calls it
> `pnpm.overrides`. This project uses npm — the `package-lock.json` in the repo root is what
> decides that (see `.claude/conventions/tooling.md`). Don't copy a `resolutions` block from
> a Stack Overflow answer; npm will simply ignore it, and you will think you fixed something
> you didn't.

After editing the block you must re-resolve the tree, which is what rewrites the lockfile:

```bash
npm install
npm ls <package> --all   # confirm the version that actually got installed
```

Commit the resulting `package-lock.json` alongside the `package.json` change. An override
without its lockfile update is not really applied for anyone else.

## The override this project carries

### `shell-quote: ^1.9.0`

**Why.** `shell-quote` at `<= 1.8.4` has a high-severity advisory —
[GHSA-395f-4hp3-45gv](https://github.com/advisories/GHSA-395f-4hp3-45gv) / CVE-2026-13311, a
quadratic-complexity denial of service in its `parse()` function. Fixed in `1.9.0`.

**Why an override rather than an upgrade.** We do not depend on `shell-quote` directly. The
path is:

```
imagoldfish → concurrently → shell-quote
```

and `concurrently` pins it **exactly**, not as a range:

```jsonc
// concurrently's own package.json
"dependencies": { "shell-quote": "1.8.4" }
```

Because the pin is exact, no amount of upgrading `concurrently` helps — at the time of
writing even the latest `concurrently@10.0.3` still names `1.8.4`. An override is the only
lever that moves it.

**How exposed were we, really?** Barely at all, and it is worth being honest about that
rather than implying we closed a live hole:

* It is a **development-only** dependency. `concurrently` is used in exactly one place — the
  `composer dev` script, which runs `php artisan serve`, the queue worker, `pail`, and Vite
  side by side on a developer's machine.
* The strings its `parse()` sees are the hardcoded commands in that script. They are not
  attacker-controlled, which is what the DoS would require.
* It ships in **neither Docker image**. The production `Dockerfile` runs `npm ci` and
  `npm run build` in a builder stage and copies only `public/build` into the final image.

So the override was applied mostly to stop a high-severity alert from firing on every push —
a permanently red security tab trains everyone to ignore the next alert, which may be a real
one. That is a legitimate reason, but it is a different reason from "we were vulnerable."

## When to reach for an override

> [!WARNING]
> An override is a **lie you tell the dependency tree**. You are giving a package a version
> of something it never tested against. If the override crosses a major version, or the API
> changed, the failure shows up at runtime in someone else's code — with a stack trace that
> points at the innocent intermediate package, not at your `package.json`. Whoever debugs it
> will not think to look here.

Prefer, in order:

1. **Upgrade our own direct dependency.** If a newer `concurrently` had loosened its pin,
   that would have been the correct fix — no lie, no note to maintain.
2. **Drop the dependency** if it is barely earning its place.
3. **Override**, but only when the version jump is small (a patch or minor within the same
   major, as here: `1.8.4 → 1.9.0`) and you have actually exercised the code path.
4. **Dismiss the alert** as "not affected", if the override's risk genuinely exceeds the
   vulnerability's. This is a real option for dev-only, unreachable findings — it is just
   less durable, because the reasoning lives in GitHub's UI instead of in the repo.

## Maintaining them

Every override is a small debt with an expiry date. When the upstream package finally
releases a version that resolves the dependency correctly on its own, **delete the override**
rather than leaving it to rot — a stale one can silently hold a package *back* later.

A practical check when you touch npm dependencies:

```bash
npm ls shell-quote --all   # is concurrently still pinning the old version?
npm audit                  # does the tree still report clean?
```

If `concurrently` (or whoever) has moved on, drop the entry, run `npm install`, confirm
`npm audit` is still clean, and remove the corresponding section from this page.

## See also

* `.claude/conventions/tooling.md` — how the lockfile decides the package manager.
* [`documentation/docker.md`](docker.md) — what does and does not get installed in each image.
