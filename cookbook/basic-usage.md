# Basic Usage

Get started with the PHP-CS-Fixer configuration package for modern PHP projects.

## Creating a Configuration

There are two ways to create PHP-CS-Fixer configurations:

### Using a Preset (Recommended)

The package includes opinionated presets with predefined rule sets:

```php
<?php

use Cline\PhpCsFixer\ConfigurationFactory;
use Cline\PhpCsFixer\Preset\Standard;

return ConfigurationFactory::createFromPreset(new Standard());
```

### Using Custom Rules

For complete control, create a configuration from raw rules:

```php
<?php

use Cline\PhpCsFixer\ConfigurationFactory;

return ConfigurationFactory::createFromRules([
    '@PSR12' => true,
    'strict_comparison' => true,
    'declare_strict_types' => true,
    // ... additional rules
]);
```

## Available Presets

The package includes several presets:

- **Standard** - Complete rule set for PHP 8.4+ projects (recommended)
- **PHPDoc** - PHPDoc formatting and standards
- **PHPUnit** - PHPUnit test formatting
- **Ordered** - Import and ordering rules

### Using Multiple Presets

Presets can be combined:

```php
<?php

use Cline\PhpCsFixer\ConfigurationFactory;
use Cline\PhpCsFixer\Preset\Standard;

return ConfigurationFactory::createFromPreset(new Standard());
```

The `Standard` preset already includes `PHPDoc`, `PHPUnit`, and `Ordered` presets.

## Overriding Preset Rules

You can override specific rules when using a preset:

```php
<?php

use Cline\PhpCsFixer\ConfigurationFactory;
use Cline\PhpCsFixer\Preset\Standard;

return ConfigurationFactory::createFromPreset(
    new Standard(),
    [
        'single_line_throw' => true, // Override to allow single-line throws
        'final_class' => false,      // Disable final class enforcement
    ]
);
```

## Customizing the Finder

By default, the configuration excludes common directories. To customize:

```php
<?php

use Cline\PhpCsFixer\ConfigurationFactory;
use Cline\PhpCsFixer\Preset\Standard;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'storage', 'bootstrap/cache'])
    ->notName('*.blade.php');

$config = ConfigurationFactory::createFromPreset(new Standard());
$config->setFinder($finder);

return $config;
```

The default finder automatically excludes:
- `bootstrap/cache`
- `build`
- `node_modules`
- `storage`
- `*.blade.php` files
- IDE helper files

## Running PHP-CS-Fixer

### Check for Issues

```bash
vendor/bin/php-cs-fixer check --diff
```

### Fix Issues

```bash
vendor/bin/php-cs-fixer fix
```

### With Custom Config Path

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
```

## Configuration File Location

Place your configuration in the project root as `.php-cs-fixer.dist.php`:

```php
<?php

use Cline\PhpCsFixer\ConfigurationFactory;
use Cline\PhpCsFixer\Preset\Standard;

return ConfigurationFactory::createFromPreset(new Standard());
```

## Composer Scripts

Add convenient scripts to your `composer.json`:

```json
{
    "scripts": {
        "lint": "vendor/bin/php-cs-fixer fix",
        "test:lint": "vendor/bin/php-cs-fixer check --diff"
    }
}
```

Then run:

```bash
composer lint        # Fix all issues
composer test:lint   # Check without fixing
```

## PHP Version Requirements

The `Standard` preset requires PHP 8.4+. The configuration will throw a `RuntimeException` if your PHP version is below the preset's target version:

```php
if (PHP_VERSION_ID < $preset->targetPhpVersion()) {
    throw new RuntimeException(
        'Current PHP version is less than targeted PHP version.'
    );
}
```

## Custom Fixers

This package registers several custom fixers automatically:

- Naming convention fixers (Abstract, Interface, Trait, Exception)
- Import FQCN fixers (new, attributes, static calls, properties)
- Architecture fixers (namespace, author tags, version tags)
- Code quality fixers (duplicate docblocks, readonly classes, variable case)

See [Custom Fixers](custom-fixers.md) for detailed documentation.
