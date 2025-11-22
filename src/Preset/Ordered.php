<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\PhpCsFixer\Preset;

use Override;
use Spatie\LaravelData\Attributes\AutoClosureLazy;
use Spatie\LaravelData\Attributes\AutoInertiaDeferred;
use Spatie\LaravelData\Attributes\AutoInertiaLazy;
use Spatie\LaravelData\Attributes\AutoLazy;
use Spatie\LaravelData\Attributes\AutoWhenLoadedLazy;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromAuthenticatedUserProperty;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\FromContainerProperty;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\FromRouteParameterProperty;
use Spatie\LaravelData\Attributes\GetsCast;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\InjectsPropertyValue;
use Spatie\LaravelData\Attributes\LoadRelation;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\PropertyForMorph;
use Spatie\LaravelData\Attributes\Validation\Accepted;
use Spatie\LaravelData\Attributes\Validation\AcceptedIf;
use Spatie\LaravelData\Attributes\Validation\ActiveUrl;
use Spatie\LaravelData\Attributes\Validation\After;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Alpha;
use Spatie\LaravelData\Attributes\Validation\AlphaDash;
use Spatie\LaravelData\Attributes\Validation\AlphaNumeric;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Bail;
use Spatie\LaravelData\Attributes\Validation\Before;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\CurrentPassword;
use Spatie\LaravelData\Attributes\Validation\CustomValidationAttribute;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\DateEquals;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Declined;
use Spatie\LaravelData\Attributes\Validation\DeclinedIf;
use Spatie\LaravelData\Attributes\Validation\Different;
use Spatie\LaravelData\Attributes\Validation\Digits;
use Spatie\LaravelData\Attributes\Validation\DigitsBetween;
use Spatie\LaravelData\Attributes\Validation\Dimensions;
use Spatie\LaravelData\Attributes\Validation\Distinct;
use Spatie\LaravelData\Attributes\Validation\DoesntEndWith;
use Spatie\LaravelData\Attributes\Validation\DoesntStartWith;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\EndsWith;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Exclude;
use Spatie\LaravelData\Attributes\Validation\ExcludeIf;
use Spatie\LaravelData\Attributes\Validation\ExcludeUnless;
use Spatie\LaravelData\Attributes\Validation\ExcludeWith;
use Spatie\LaravelData\Attributes\Validation\ExcludeWithout;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Filled;
use Spatie\LaravelData\Attributes\Validation\GreaterThan;
use Spatie\LaravelData\Attributes\Validation\GreaterThanOrEqualTo;
use Spatie\LaravelData\Attributes\Validation\Image;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\InArray;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\IP;
use Spatie\LaravelData\Attributes\Validation\IPv4;
use Spatie\LaravelData\Attributes\Validation\IPv6;
use Spatie\LaravelData\Attributes\Validation\Json;
use Spatie\LaravelData\Attributes\Validation\LessThan;
use Spatie\LaravelData\Attributes\Validation\LessThanOrEqualTo;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Lowercase;
use Spatie\LaravelData\Attributes\Validation\MacAddress;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\MaxDigits;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\MimeTypes;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\MinDigits;
use Spatie\LaravelData\Attributes\Validation\MultipleOf;
use Spatie\LaravelData\Attributes\Validation\NotIn;
use Spatie\LaravelData\Attributes\Validation\NotRegex;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\ObjectValidationAttribute;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Attributes\Validation\Prohibited;
use Spatie\LaravelData\Attributes\Validation\ProhibitedIf;
use Spatie\LaravelData\Attributes\Validation\ProhibitedUnless;
use Spatie\LaravelData\Attributes\Validation\Prohibits;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\RequiredArrayKeys;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Attributes\Validation\RequiredUnless;
use Spatie\LaravelData\Attributes\Validation\RequiredWith;
use Spatie\LaravelData\Attributes\Validation\RequiredWithAll;
use Spatie\LaravelData\Attributes\Validation\RequiredWithout;
use Spatie\LaravelData\Attributes\Validation\RequiredWithoutAll;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Same;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Attributes\Validation\StartsWith;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\StringValidationAttribute;
use Spatie\LaravelData\Attributes\Validation\Timezone;
use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Attributes\Validation\Uppercase;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Attributes\Validation\ValidationAttribute;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithCastable;
use Spatie\LaravelData\Attributes\WithCastAndTransformer;
use Spatie\LaravelData\Attributes\WithoutValidation;
use Spatie\LaravelData\Attributes\WithTransformer;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.2
 */
final class Ordered implements PresetInterface
{
    #[Override()]
    public function name(): string
    {
        return 'Ordered';
    }

    #[Override()]
    public function rules(): array
    {
        return [
            'ordered_attributes' => [
                'order' => [
                    // Availability...
                    Nullable::class,
                    Required::class,
                    RequiredArrayKeys::class,
                    RequiredIf::class,
                    RequiredUnless::class,
                    RequiredWith::class,
                    RequiredWithAll::class,
                    RequiredWithout::class,
                    RequiredWithoutAll::class,
                    Sometimes::class,
                    // Complementary...
                    Accepted::class,
                    AcceptedIf::class,
                    ActiveUrl::class,
                    After::class,
                    AfterOrEqual::class,
                    Alpha::class,
                    AlphaDash::class,
                    AlphaNumeric::class,
                    ArrayType::class,
                    Bail::class,
                    Before::class,
                    BeforeOrEqual::class,
                    Between::class,
                    BooleanType::class,
                    Confirmed::class,
                    CurrentPassword::class,
                    CustomValidationAttribute::class,
                    Date::class,
                    DateEquals::class,
                    DateFormat::class,
                    Declined::class,
                    DeclinedIf::class,
                    Different::class,
                    Digits::class,
                    DigitsBetween::class,
                    Dimensions::class,
                    Distinct::class,
                    DoesntEndWith::class,
                    DoesntStartWith::class,
                    Email::class,
                    EndsWith::class,
                    Enum::class,
                    Exclude::class,
                    ExcludeIf::class,
                    ExcludeUnless::class,
                    ExcludeWith::class,
                    ExcludeWithout::class,
                    Exists::class,
                    File::class,
                    Filled::class,
                    GreaterThan::class,
                    GreaterThanOrEqualTo::class,
                    Image::class,
                    In::class,
                    InArray::class,
                    IntegerType::class,
                    IP::class,
                    IPv4::class,
                    IPv6::class,
                    Json::class,
                    LessThan::class,
                    LessThanOrEqualTo::class,
                    ListType::class,
                    Lowercase::class,
                    MacAddress::class,
                    Max::class,
                    MaxDigits::class,
                    Mimes::class,
                    MimeTypes::class,
                    Min::class,
                    MinDigits::class,
                    MultipleOf::class,
                    NotIn::class,
                    NotRegex::class,
                    Numeric::class,
                    ObjectValidationAttribute::class,
                    Password::class,
                    Present::class,
                    Prohibited::class,
                    ProhibitedIf::class,
                    ProhibitedUnless::class,
                    Prohibits::class,
                    Regex::class,
                    Rule::class,
                    Same::class,
                    Size::class,
                    StartsWith::class,
                    StringType::class,
                    StringValidationAttribute::class,
                    Timezone::class,
                    Ulid::class,
                    Unique::class,
                    Uppercase::class,
                    Url::class,
                    Uuid::class,
                    ValidationAttribute::class,
                    // Behavior...
                    AutoClosureLazy::class,
                    AutoInertiaDeferred::class,
                    AutoInertiaLazy::class,
                    AutoLazy::class,
                    AutoWhenLoadedLazy::class,
                    Computed::class,
                    DataCollectionOf::class,
                    FromAuthenticatedUser::class,
                    FromAuthenticatedUserProperty::class,
                    FromContainer::class,
                    FromContainerProperty::class,
                    FromRouteParameter::class,
                    FromRouteParameterProperty::class,
                    GetsCast::class,
                    Hidden::class,
                    InjectsPropertyValue::class,
                    LoadRelation::class,
                    MapInputName::class,
                    MapName::class,
                    MapOutputName::class,
                    MergeValidationRules::class,
                    PropertyForMorph::class,
                    WithCast::class,
                    WithCastable::class,
                    WithCastAndTransformer::class,
                    WithoutValidation::class,
                    WithTransformer::class,
                ],
                'sort_algorithm' => 'custom',
            ],
            'ordered_class_elements' => [
                'order' => [
                    'use_trait',
                    'case',
                    'constant_public',
                    'constant_protected',
                    'constant_private',
                    'property_public_static',
                    'property_public',
                    'property_public_readonly',
                    'property_protected_static',
                    'property_protected',
                    'property_protected_readonly',
                    'property_private_static',
                    'property_private',
                    'property_private_readonly',
                    'construct',
                    'destruct',
                    'magic',
                    'phpunit',
                    'method_public_static',
                    'method_public_abstract_static',
                    'method_public',
                    'method_public_abstract',
                    'method_protected_static',
                    'method_protected_abstract_static',
                    'method_protected',
                    'method_protected_abstract',
                    'method_private_static',
                    'method_private_abstract_static',
                    'method_private',
                    'method_private_abstract',
                ],
                'sort_algorithm' => 'none',
            ],
            'ordered_imports' => [
                'imports_order' => [
                    'class',
                    'const',
                    'function',
                ],
                'sort_algorithm' => 'alpha',
            ],
            'ordered_interfaces' => [
                'direction' => 'ascend',
                'order' => 'alpha',
            ],
            'ordered_traits' => false,
        ];
    }

    #[Override()]
    public function targetPhpVersion(): int
    {
        return 80_400;
    }
}
