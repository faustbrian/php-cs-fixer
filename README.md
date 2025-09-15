# cline/php-cs-fixer

Opinionated PHP-CS-Fixer presets and custom fixers used at Cline. Ships with ready-to-use rule sets, a preconfigured finder, and several domain-specific fixers.

- Targets modern PHP (8.2+). Some presets target 8.4.
- Bundles and auto-registers community custom fixers.
- Provides additional "Architecture/*" fixers for naming, imports, and docs.

## Requirements

- PHP 8.2+
- Composer

## Installation

```
composer require --dev cline/php-cs-fixer
```

This package depends on `friendsofphp/php-cs-fixer` and popular custom fixer packs; Composer installs them automatically.

## Quick Start

Create a `.php-cs-fixer.php` at your project root:

```
<?php declare(strict_types=1);

use Cline\PhpCsFixer\ConfigurationFactory;
use Cline\PhpCsFixer\Preset\Standard; // or PHPDoc, PHPUnit, Ordered

$config = ConfigurationFactory::createFromPreset(new Standard());

/** @var PhpCsFixer\Finder $finder */
$finder = $config->getFinder();
$finder->in(__DIR__); // point to your source directories if needed

return $config;
```

Run it:

```
vendor/bin/php-cs-fixer fix --dry-run -v
vendor/bin/php-cs-fixer fix
```

## Presets

- Standard: Comprehensive rule set for application code. Targets PHP 8.4.
- PHPDoc: Focused rules for clean, consistent PHPDoc. Targets PHP 8.2.
- PHPUnit: Rules for modern PHPUnit tests. Targets PHP 8.2.
- Ordered: Enforces deterministic ordering (imports, attributes, class elements). Targets PHP 8.4.

Switch presets by swapping the class you pass to `createFromPreset`:

```
new Cline\PhpCsFixer\Preset\PHPDoc();
new Cline\PhpCsFixer\Preset\PHPUnit();
new Cline\PhpCsFixer\Preset\Ordered();
```

## Overriding Rules

You can override any rule when creating the config:

```
use Cline\PhpCsFixer\ConfigurationFactory;
use Cline\PhpCsFixer\Preset\Standard;

$config = ConfigurationFactory::createFromPreset(new Standard(), [
    'final_class' => false,
    'trailing_comma_in_multiline' => [ 'elements' => ['arrays', 'arguments'] ],
]);
```

## Custom Fixers

This package registers additional fixers automatically. They are available as rules under the `Architecture/*` namespace, for example:

- Architecture/abstract_name_fixer
- Architecture/author_tag_fixer
- Architecture/duplicate_docblock_after_attributes_fixer
- Architecture/exception_name_fixer
- Architecture/final_readonly_class_fixer
- Architecture/import_fqcn_in_attribute_fixer
- Architecture/import_fqcn_in_new_fixer
- Architecture/import_fqcn_in_property_fixer
- Architecture/import_fqcn_in_static_call_fixer
- Architecture/namespace_fixer
- Architecture/new_argument_newline_fixer
- Architecture/psalm_immutable_on_readonly_class_fixer
- Architecture/redundant_readonly_property_fixer
- Architecture/trait_name_fixer
- Architecture/variable_case
- Architecture/version_tag_fixer

Enable or configure them like any other rule:

```
$config = ConfigurationFactory::createFromPreset(new Standard(), [
    'Architecture/namespace_fixer' => true,
]);
```

## Finder Defaults

`ConfigurationFactory` sets up a `PhpCsFixer\Finder` with sensible defaults:

- Ignores VCS and dotfiles
- Excludes: `bootstrap/cache`, `build`, `node_modules`, `storage`
- Skips files: `_ide_helper.php`, `_ide_helper_*`, `.phpstorm.meta.php`, `*.blade.php`

You can still scope it as needed via `$config->getFinder()->in(...)` in your `.php-cs-fixer.php`.

## Contributing

Issues and PRs are welcome. Please keep changes focused and include rationale.

## License

MIT. See `LICENSE`.
