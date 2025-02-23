---
title: Defining settings
layout: default
parent: Usage
nav_order: 1
---

# Defining settings

Settings are defined as classes, which contain the parameters as properties. The classes are marked with the `#[Settings]` attribute, which makes them
managable by the settings-bundle. Besides the attribute, the class is basically just a normal PHP class, which can contain any kind of methods and properties.
Only classes with the `#[Settings]` attribute and which are contained in on of the configured settings directories will be usable by the settings-bundle. By default, this means that you should put them into the `src/Settings` directory of your symfony project (or a subfolder of it).

Settings classes should be suffixed by `Settings` (e.g. `MySettings`), but this is not required.

Settings classes *must not* be final or contain final properties, methods as then no lazy loading proxy classes can be generated for them.

The properties of the class, which should be filled by the settings-bundle, are marked with the `#[SettingsParameter]` attribute. This attribute contains information about how the data of the parameter should be mapped to normalized data for the storage adapter and how the parameter should be rendered in forms, etc.

```php
<?php
// src/Settings/TestSettings.php

namespace App\Settings;

use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\ParameterTypes\StringType;
use Jbtronics\SettingsBundle\ParameterTypes\IntType;
use Symfony\Component\Validator\Constraints as Assert;


#[Settings] // The settings attribute makes a simple class to settings
class TestSettings {
    use SettingsTrait; // Disable constructor and __clone methods

    //The property is public here for simplicity, but it can also be protected or private
    #[SettingsParameter(type: StringType::class, label: 'My String', description: 'This value is shown as help in forms.')]
    public string $myString = 'default value'; // The default value can be set right here in most cases

    #[SettingsParameter(type: IntType::class, label: 'My Integer', description: 'This value is shown as help in forms.')]
    #[Assert\Range(min: 5, max: 10,)] // You can use symfony/validator to restrict possible values
    public ?int $myInt = null;
}
```

The parameter values are filled by the settings-bundle via reflection. Therefore the properties can be either public, where you access the properties directly, or protected/private, where you have to use the getter/setter methods. Please note that the properties get accessed directly via reflection, so that the getter/setter methods are not called.

The only useful way to retrieve an instance of a settings class is via the SettingsManager. You can not instantiate the class directly, as it would not be initialized correctly. Therefore you should add the `SettingsTrait` to your settings class, which disables the constructor, `__clone` method, etc. so that you can not instantiate the class directly by accident. If you need to perform some more complex initialization of your settings class, see below how to do that properly.

## Defining default values for parameters

The default values for parameters can be set directly in the property declaration in most cases (by directly assigning the value in the declaration e.g. `private int $property = 4;`).

If you require more complex initialization, which can not be done directly in the declaration (e.g. create an object), your settings class can implement the `ResettableSettingsInterface` interface and the `resetToDefaultValues()` method. This method will be called by the settings-bundle everytime a new instance of the settings class is created or the settings are reset to default values. It is called after all properties have been initialized/reset to the default values.

```php
<?php
// src/Settings/ResettableSettings.php

namespace App\Settings;

use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\ParameterTypes\StringType;
use Jbtronics\SettingsBundle\ParameterTypes\IntType;
use Symfony\Component\Validator\Constraints as Assert;
use Jbtronics\SettingsBundle\Settings\ResettableSettingsInterface;


#[Settings] // The settings attribute makes a simple class to settings
class ResettableSettings implements ResettableSettings
{
    use SettingsTrait; 

    #[SettingsParameter(type: StringType::class, label: 'My String', description: 'This value is shown as help in forms.')]
    public string $myString; // We set the default value later

    public function resetToDefaultValues(): void
    {
        //Reset all properties without default values:
        $this->myString = 'default value';
    }
}
```

## Settings validation

Settings-bundle integrates with `symfony/validator`, so you can use the normal validation constraints at parameter properties to validate the values. The validation is performed, when the settings are saved via the `SettingsManagerInterface::save()` method. If the validation fails, an `SettingsNotValidExcpetion` is thrown and the settings are not saved.

**Attention:** Please note that the validation is only performed, when the settings are saved. That means that you can set an invalid value to a parameter, and other parts of your application might already use this invalid value, before the validation is performed. Therefore you should always validate the settings after changing them in a way, so that they could have become invalid.

You can pass the settings instance to the `validate` method of the `SettingsValidatorInterface` service to check if the settings are valid. If the method returns an empty array the settings are valid, otherwise the array contains the validation errors.

## Embedded settings

For better organisation of related settings, you can embed settings classes into other settings classes. This is done by defining a property of the embedded settings class and marking it with the `#[EmbeddedSettings]` attribute. The Settings Manager automatically injects the (lazy loaded) embedded settings instance into the property, so that it can be used like a normal settings instance.

```php
<?php

namespace App\Settings;

use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\EmbeddedSettings;

#[Settings]
class EmbeddedTestSettings 
{
    use SettingsTrait;

    //You can still define normal parameters in the embedded settings class
    #[SettingsParameter()]
    public string $myString = 'default value'; // The default value can be set right here in most cases

    #[EmbeddedSettings]
    public TestSettings $testSettings; //This will be automatically filled with an instance of TestSettings
}
```

The bundle can handle complex and even circular nesting of embedded settings classes. However, you should try to avoid repetitive and circular nesting, as it makes using the form generation very hard and you have to carefully set the correct groups for form generation.

## Attributes reference

### #[Settings]

The `#[Settings]` attribute marks a class as settings class. It is required for all settings classes.

The attribute has the following parameters:

* `name` (optional): A short name for the settings class. This name can be used to retrieve the settings class via the `SettingsManagerInterface::get()` method. If not set, the name will be generated from the class name by removing the `Settings` suffix and converting the class name to lowercase (e.g. `TestSettings` -> `test`).
* `storageAdapter` (optional): The class name of the storageAdapter service which should be used to store the settings (e.g. `InMemoryStorageAdapter::class`). If none is set, the global configured storage adapter will be used.
* `storageAdapterOptions` (optional): An array of options, which is passed to the storage adapter. The available options depend on the storage adapter. See the documentation of the storage adapter for more information.
* `groups` (optional): The default groups of parameters in this settings class. This can be used to only render subsets of the parameters in forms, etc. The groups are used as default groups for the parameters, if they are not explicitly set. The groups can be overridden by the `groups` option of the `#[SettingsParameter]` attribute.
* `version` (optional): The expected version of this settings class. Must be an int greater 0. If set, settings from older versions of this class will be migrated to the current version. See the documentation about versioning and migrations for more information. If set to null, then the settings class is not versioned and no migrations are performed.
* `migrationService` (optional): The class name of the service, which should be used to perform the migration. This value is required if `version` is set. See the documentation about versioning and migrations for more information. 

### #[SettingsParameter]

The `#[SettingsParameter]` attribute marks a property as settings parameter. It is required for all properties, which should be managed by the settings-bundle.

The attribute has the following parameters:

* `type` (required): The class name of the parameter type, which should be used to handle the parameter (e.g. `StringType::class`).
* `name` (optional): The name of the parameter, by which it should be identified internally (this will be the key in the normalized data, etc.) If not set, this will default to the name of the property.
* `label` (optional): A string or translation key, which can be used as user friendly label for the parameter, when showing it to user (e.g. in forms). This should be just a few words maximum.
* `description` (optional): A string or translation key, which can be used as user friendly description for the parameter, when showing it to user (e.g. in forms). Unlike the label this can be a longer text giving more information about the parameter.
* `options` (optional): An array of extra options, which is passed to the parameter type. The available options depend on the parameter type. See the documentation of the parameter type for more information.
* `formType` (optional): The (symfony) form type, which should be used to render the parameter in forms. This overrides any default values given by the parameter type.
* `formOptions` (optional): An array of options, which is passed to the form type. This overrides the defaults defined by the defaults defined by the parameter type. The available options depend on the form type. See the documentation of the form type for more information.
* `nullable` (optional): Override the behavior if the parameter is considered nullable. Normally this is derived automatically from the declared property type.
* `groups` (optional): The groups this parameter belongs to. This can be used to only render certain subsets of the parameters in forms, etc. This must be an array of strings, or null if this parameter should not belong to any group.

### #[EmbeddedSettings]

* `target`(optional): The class name of the settings class, which should be embedded. If not set, the target class is derived automatically from the property type.
* `groups` (optional): The groups this embedded settings class belongs to. This can be used to only render certain subsets of the parameters in forms, etc.