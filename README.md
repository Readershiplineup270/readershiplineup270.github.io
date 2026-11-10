# Quince manifest

**The index Quince modules read at https://quinceproject.github.io/manifest.json: the
crawler list and the latest releases.**

This repository is published with GitHub Pages and serves one file, `manifest.json`.
Quince modules installed in shops download it once a day. There is nothing here to
install.

The manifest carries two things:

- the **list of crawlers and AI agents** read by
  [Quince Robots](https://github.com/quinceproject/quince_robots). When the merchant
  clicks **Check for new bots**, the module downloads this file and adds the bots it
  does not know yet;
- the **latest release of every Quince module and of the Quince theme**: version,
  package address and the package's SHA-256. Modules use it to tell the merchant that
  an update is out, and check the downloaded package against the SHA-256 before they
  install it.

---

## Where the bot list comes from

The names and facts about each bot come from
[ai-robots-txt/ai.robots.txt](https://github.com/ai-robots-txt/ai.robots.txt), a public,
community maintained list (MIT). That list says what a bot does. It does not say
whether a shop should let it in, and Quince Robots needs that too, so we add three
things of our own for every bot:

| Field | Meaning |
|-------|---------|
| `category` | `search`, `answer`, `training`, `seo`, `monitoring` or `other` |
| `verify_host` | the domain a genuine bot's address resolves to, when the operator publishes one |
| `default_policy` | our recommendation, `allow` or `block`. The merchant decides; this is only the starting point |

Those descriptions live in [`data/bots.json`](data/bots.json) and are written by hand.
The list also has a few bots ai.robots.txt does not track, such as search engine and
SEO crawlers.

## How it stays current

A [workflow](.github/workflows/sync.yml) runs every day:

1. It downloads `robots.json` from ai.robots.txt.
2. A bot described in `data/bots.json` is published exactly as described there.
3. A bot that is new in ai.robots.txt is published with its operator and description
   from there and with safe defaults for our part: category `other` and
   recommendation `allow`. Nothing gets blocked in a shop because of a bot nobody has
   looked at.
4. New bots are listed in one open issue labelled
   [`new-bots`](https://github.com/quinceproject/quinceproject.github.io/issues?q=label%3Anew-bots),
   which closes itself when every bot is described.
5. `manifest.json` is committed only when the list changed.

Some names from ai.robots.txt are left out on purpose, because as a fragment of a
user agent they would catch traffic nobody meant: the placeholders `Spider`, `Code`
and `OpenAI`, names shorter than five characters, and names with a version number
when the list also has them without one.

If the downloaded list looks broken (fewer than 100 entries), the workflow stops and
the published file stays as it was.

## Releases

The same daily workflow reads the latest release of every public repository in
[github.com/quinceproject](https://github.com/quinceproject). A repository counts when
its release carries a ZIP named after it (`quince_search.zip` in `quince_search`).
Repositories with the topic `prestashop-theme` go to `themes`, the rest to `modules`.
Each package is downloaded once to record its SHA-256.

A new release shows up in the manifest at the next run, so within a day. To publish
it straight away, run the workflow by hand (**Actions → Sync manifest → Run
workflow**).

## Describing a bot

Add or edit its entry in `data/bots.json`, under the key the module uses: the token in
lower case, with anything other than letters and digits replaced by `-`.

```json
"gptbot": {
  "token": "GPTBot",
  "label": "GPTBot",
  "vendor": "OpenAI",
  "category": "training",
  "description": "Collects pages to train OpenAI models.",
  "docs_url": "https://platform.openai.com/docs/bots",
  "verify_host": "",
  "respects": "yes",
  "default_policy": "block"
}
```

`respects` says whether the bot follows `robots.txt`: `yes`, `no` or `unclear`. A push
that changes `data/` rebuilds the manifest straight away.

To build it locally:

```bash
curl -fsSL -o robots.json https://raw.githubusercontent.com/ai-robots-txt/ai.robots.txt/main/robots.json
php scripts/build-manifest.php robots.json data/bots.json manifest.json new-bots.md
```

## Format

```json
{
  "robots": {
    "updated": "2026-11-10",
    "source": "https://github.com/ai-robots-txt/ai.robots.txt",
    "bots": {
      "gptbot": { "token": "GPTBot", "category": "training", "…": "…" }
    }
  },
  "modules": {
    "quince_search": {
      "repository": "quinceproject/quince_search",
      "version": "0.1.1",
      "package": "https://github.com/quinceproject/quince_search/releases/download/v0.1.1/quince_search.zip",
      "sha256": "124e003081eb2fc4cf79f05ac17e874b75f86602b697e7df7e9b716ff60ebec6",
      "notes": "https://github.com/quinceproject/quince_search/releases/tag/v0.1.1"
    }
  },
  "themes": {
    "quince": { "repository": "quinceproject/quince", "version": "0.1.0", "…": "…" }
  }
}
```

Modules read only the sections they know and skip the rest, so new sections can be
added without breaking older modules.

## License

Scripts and our descriptions: [Open Software License 3.0 (OSL-3.0)](LICENSE). The data
taken from ai.robots.txt stays under its MIT License, see
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If they are useful to
you, you can support development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
