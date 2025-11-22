# Custom Fixers

This package provides custom PHP-CS-Fixer fixers that enforce architectural patterns and naming conventions beyond standard PHP-CS-Fixer rules.

## Naming Convention Fixers

### AbstractNameFixer

Enforces that abstract classes follow the `Abstract*` naming pattern.

```php
// ❌ Before
abstract class BaseRepository {}
abstract class RepositoryBase {}

// ✅ After
abstract class AbstractRepository {}
```

**Rule Key:** `Architecture/abstract_name_fixer`

### InterfaceNameFixer

Enforces that interfaces follow the `*Interface` naming pattern.

```php
// ❌ Before
interface Repository {}
interface IRepository {}

// ✅ After
interface RepositoryInterface {}
```

**Rule Key:** `Architecture/interface_name_fixer`

### TraitNameFixer

Enforces that traits follow standard trait naming conventions.

```php
// ❌ Before
trait TimestampsTrait {}

// ✅ After
trait Timestamps {}
```

**Rule Key:** `Architecture/trait_name_fixer`

### ExceptionNameFixer

Enforces that exceptions follow the `*Exception` naming pattern.

```php
// ❌ Before
class InvalidInput {}
class ValidationError {}

// ✅ After
class InvalidInputException {}
class ValidationException {}
```

**Rule Key:** `Architecture/exception_name_fixer`

### VariableCaseFixer

Enforces camelCase naming for variables.

```php
// ❌ Before
$user_name = 'John';
$UserEmail = 'john@example.com';

// ✅ After
$userName = 'John';
$userEmail = 'john@example.com';
```

**Rule Key:** `Architecture/variable_case_fixer`

## Import Fixers

### ImportFqcnInNewFixer

Automatically imports fully qualified class names in `new` expressions.

```php
// ❌ Before
$user = new \App\Models\User();

// ✅ After
use App\Models\User;

$user = new User();
```

**Rule Key:** `Architecture/import_fqcn_in_new_fixer`

### ImportFqcnInAttributeFixer

Automatically imports fully qualified class names in attributes.

```php
// ❌ Before
#[\App\Attributes\Cached(ttl: 3600)]
class UserRepository {}

// ✅ After
use App\Attributes\Cached;

#[Cached(ttl: 3600)]
class UserRepository {}
```

**Rule Key:** `Architecture/import_fqcn_in_attribute_fixer`

### ImportFqcnInStaticCallFixer

Automatically imports fully qualified class names in static method calls.

```php
// ❌ Before
$value = \App\Services\Cache::get('key');

// ✅ After
use App\Services\Cache;

$value = Cache::get('key');
```

**Rule Key:** `Architecture/import_fqcn_in_static_call_fixer`

### ImportFqcnInPropertyFixer

Automatically imports fully qualified class names in property type declarations.

```php
// ❌ Before
class UserController
{
    private \App\Services\UserService $userService;
}

// ✅ After
use App\Services\UserService;

class UserController
{
    private UserService $userService;
}
```

**Rule Key:** `Architecture/import_fqcn_in_property_fixer`

## Code Quality Fixers

### FinalReadonlyClassFixer

Automatically adds `final` modifier to `readonly` classes.

```php
// ❌ Before
readonly class User {}

// ✅ After
final readonly class User {}
```

**Rule Key:** `Architecture/final_readonly_class_fixer`

### RedundantReadonlyPropertyFixer

Removes redundant `readonly` modifiers from properties in `readonly` classes.

```php
// ❌ Before
readonly class User
{
    public readonly string $name;
}

// ✅ After
readonly class User
{
    public string $name;
}
```

**Rule Key:** `Architecture/redundant_readonly_property_fixer`

### DuplicateDocBlockAfterAttributesFixer

Removes duplicate PHPDoc blocks that appear after PHP attributes.

```php
// ❌ Before
#[Route('/users')]
/**
 * @param string $id
 */
public function show(string $id) {}

// ✅ After
#[Route('/users')]
public function show(string $id) {}
```

**Rule Key:** `Architecture/duplicate_docblock_after_attributes_fixer`

### PsalmImmutableOnReadonlyClassFixer

Adds `@psalm-immutable` annotation to `readonly` classes.

```php
// ❌ Before
readonly class User {}

// ✅ After
/**
 * @psalm-immutable
 */
readonly class User {}
```

**Rule Key:** `Architecture/psalm_immutable_on_readonly_class_fixer`

## Documentation Fixers

### AuthorTagFixer

Enforces consistent `@author` tag format in PHPDoc blocks.

```php
// ❌ Before
/**
 * @author John Doe
 */

// ✅ After
/**
 * @author Brian Faust <brian@cline.sh>
 */
```

**Rule Key:** `Architecture/author_tag_fixer`

### VersionTagFixer

Enforces consistent `@version` tag format in PHPDoc blocks.

```php
// ❌ Before
/**
 * @version 1.0
 */

// ✅ After
/**
 * @version 1.0.0
 */
```

**Rule Key:** `Architecture/version_tag_fixer`

### NamespaceFixer

Enforces consistent namespace declarations.

**Rule Key:** `Architecture/namespace_fixer`

## Formatting Fixers

### NewArgumentNewlineFixer

Enforces newlines for constructor arguments in `new` expressions.

```php
// ❌ Before
$user = new User('John', 'Doe', 'john@example.com', 25);

// ✅ After
$user = new User(
    'John',
    'Doe',
    'john@example.com',
    25,
);
```

**Rule Key:** `Architecture/new_argument_newline_fixer`

## Enabling Custom Fixers

All custom fixers are automatically registered when using `ConfigurationFactory::createFromPreset()`. To enable specific fixers:

```php
use Cline\PhpCsFixer\ConfigurationFactory;
use Cline\PhpCsFixer\Preset\Standard;

return ConfigurationFactory::createFromPreset(
    new Standard(),
    [
        'Architecture/abstract_name_fixer' => true,
        'Architecture/interface_name_fixer' => true,
        'Architecture/exception_name_fixer' => true,
    ]
);
```

Some fixers are disabled by default in the Standard preset:

```php
// Commented out in Standard preset (disabled by default):
// 'Architecture/abstract_name_fixer' => true,
// 'Architecture/exception_name_fixer' => true,
// 'Architecture/interface_name_fixer' => true,
// 'Architecture/trait_name_fixer' => true,
// 'Architecture/version_tag_fixer' => true,
// 'Architecture/final_readonly_class_fixer' => true,
```

Enable them explicitly if needed:

```php
return ConfigurationFactory::createFromPreset(
    new Standard(),
    [
        'Architecture/abstract_name_fixer' => true,
        'Architecture/exception_name_fixer' => true,
    ]
);
```
