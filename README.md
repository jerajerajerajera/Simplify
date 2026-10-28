# Simplify

A [Pelican](https://pelican.dev) plugin that gives you one clear overview of your servers. Add-ons such as Server Maker
are separate plugins that show up inside Simplify when both are installed.

Simplify is being rebuilt for version 1.0. This repository holds the new code; the earlier PelicanSimplify (0.6.0 and
before) lives in [PelicanSimplify](https://github.com/jerajerajerajera/PelicanSimplify).

## Install

Install Simplify from the [Pelican Hub](https://hub.pelican.dev). Updates come from the Hub only.

## Add-ons

See [ADDONS.md](ADDONS.md) for how an add-on plugs into Simplify.

## What it sends where

Nothing. Simplify only talks to your own panel and your Wings nodes.

Open routes (no login): `/simplify/assets/{name}` serves Simplify's stylesheet and logo, and `/simplify/brand/{file}` serves the panel logo and app icon an admin uploaded in Simplify's settings. `/simplify/manifest.webmanifest` and `/simplify-worker.js` let a browser install the panel as an app; the worker caches only a small offline page.

## Development

- `bash tests/run.sh` runs the checks that need no panel, including `tests/hub-check.py`.
- `pint` formats the code (Laravel preset).
- `python3 scripts/build-package.py` builds the zip for the Hub.

## License

Source available, see [LICENSE.md](LICENSE.md). Copyright (c) 2026 Jeroen (jerajerajerajera).
