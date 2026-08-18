<?php

namespace HWS\BaseTools\AcfFields;

final class AcfModule {
    public static function register(): void {
        UserProfile2025Migration::register();
        QuoteRepeaterMigration::register();
    }
}
