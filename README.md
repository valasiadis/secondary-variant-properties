# Secondary Variant Properties for Shopware 6

Shopware greys out a variant value if no variant exists with this value **and all other currently
selected values**. For products where one property only refines the others, this is confusing.

Example: arrow shafts sold by straightness, spine and a measured weight class. A customer has
selected `.001 / spine 500 / 5.40 GPI`. Shopware greys out spine 800, because there is no
`.001 / 800 / 5.40 GPI` variant, although `.001 / 800` exists in several weight classes.

This plugin lets you mark properties as **secondary**:

- **Primary properties** (e.g. straightness, spine) are only greyed out if no *available* variant
  exists with the other selected primary values. Secondary properties are ignored for this.
- **Secondary properties** (e.g. weight class) only offer values that are available for the
  current primary selection. Non-matching values are hidden (default) or greyed out.
- **Switching a primary value** keeps all other primary values and selects the first available
  secondary value. Shopware's default lookup drops properties from the top instead, which can
  silently change another primary value, and it ignores stock.
- **Search and filter:** secondary values are left out of the product search and secondary
  properties are not offered in the product filter of listings (both configurable).

Products without a secondary property are not affected.

## Requirements

- Shopware 6.7.x
- PHP 8.2 or newer

## Installation

Download the zip from the [releases](https://github.com/valasiadis/secondary-variant-properties/releases)
(or build it, see below) and upload it in the administration under
**Extensions → My extensions → Upload extension**. Install and activate it.

Or via Composer in a Shopware project (the package is not on Packagist yet, so add the repository first):

```bash
composer config repositories.secondary-variant-properties vcs https://github.com/valasiadis/secondary-variant-properties
composer require valasiadis/secondary-variant-properties
bin/console plugin:refresh
bin/console plugin:install --activate SecondaryVariantProperties
bin/console cache:clear
```

## Configuration

**Extensions → My extensions → Secondary variant properties → Configure** (per sales channel):

| Setting | Description |
|---|---|
| Secondary properties | Property groups that are secondary. All other properties are primary. |
| Hide secondary values … | On: non-matching or sold-out secondary values are removed. Off: they are greyed out. |
| Hide secondary properties in the product filter … | On (default): secondary properties are not shown in the product filter of category listings and search results. |
| Exclude secondary values from the product search | On (default): searching for a secondary value does not find the product. Only the "All sales channels" value is used, because the search index is shared. |

Clear the cache after changing the configuration. After changing the search setting, rebuild the
search index (**Settings → Search → Rebuild search index**, or `bin/console dal:refresh:index`).

The search setting only matters if your search configuration indexes property or option names.

## How it works

- `ProductConfiguratorLoader` is decorated. It is used by the product detail route, the CMS buy box
  element and the Store API, so all of them get the adjusted configurator. Since Shopware 6.7.15
  `loadFromCombinations()` is decorated as well.
- `FindProductVariantRoute` is decorated to choose the variant when a value is switched. Like the
  core lookup, it respects "Hide products after clearance" (sold-out closeout variants are never picked).
- `ProductSearchKeywordAnalyzer` is decorated to leave secondary values out of the search keywords.
- A subscriber removes secondary groups from the property filter of listings and search results.
- No data is written; the plugin only changes how variants are offered.

## Development

```bash
composer install
composer test       # PHPUnit
composer phpstan    # PHPStan level 8
bin/build-zip.sh    # builds SecondaryVariantProperties-<version>.zip from HEAD
```

### Releasing

1. Set the new version in `composer.json` and add a `# <version>` section to `CHANGELOG.md`
   (English) and `CHANGELOG_de-DE.md` (German).
2. Commit, then tag and push: `git tag v<version> && git push origin v<version>`.

The release workflow checks the tag against `composer.json`, builds the zip and publishes a GitHub
release with the changelog section as release notes.

## Roadmap

- Distinguish "not available in this combination" from "sold out" in the storefront texts.

## License

[MIT](LICENSE)
