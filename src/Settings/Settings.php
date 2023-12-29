<?php

namespace Jbtronics\SettingsBundle\Settings;

/**
 * This attribute marks a class as a settings class, whose values are managed by the UserConfigBundle.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Settings
{
    public function __construct(
        public readonly string|null $name = null,
        public readonly string|null $storageAdapter = null
    )
    {

    }
}