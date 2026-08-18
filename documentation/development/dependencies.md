# Dependency overrides

[Documentation](../README.md) / [Development](README.md) / Dependency overrides

An npm override replaces a transitive dependency version. Use it only when upgrading or removing the direct dependency cannot solve the problem.

## Mechanism

The root `package.json` contains the override:

```json
"overrides": {
    "shell-quote": "^1.9.0"
}
```

This rule applies at every depth in the npm dependency tree. It can give a package a version that its maintainer did not test.

After a change, resolve and verify the tree:

```bash
npm install
npm ls <package> --all
```

Commit `package-lock.json` with `package.json`. This project uses npm because it has an npm lockfile.

## Current override

`shell-quote <= 1.8.4` has a high-severity denial-of-service advisory. Version `1.9.0` fixes it. The dependency path is:

```text
imagoldfish → concurrently → shell-quote
```

`concurrently` pins the affected version, so a normal transitive update cannot replace it. The override moves it to `^1.9.0`.

The practical exposure is low:

- `concurrently` is a development dependency.
- It parses fixed commands from `composer dev`, not user input.
- It is not present in the production image.

The override keeps security reports useful without changing production behavior. See [GHSA-395f-4hp3-45gv](https://github.com/advisories/GHSA-395f-4hp3-45gv).

## Decision order

1. Upgrade the direct dependency.
2. Remove the dependency if it is not needed.
3. Add a same-major override and test the affected path.
4. Dismiss the advisory only when the override creates more risk than the finding.

Avoid major-version overrides unless compatibility is proven. A failure will often appear inside the direct dependency and hide the override as its cause.

## Maintenance

Remove an override when the upstream package accepts a safe version. When npm dependencies change, run:

```bash
npm ls shell-quote --all
npm audit
```

If the pin is gone, remove the override, run `npm install`, verify the audit, and update this page.

Docker installation boundaries are described in [Docker](docker.md#services).
