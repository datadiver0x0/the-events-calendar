<?php
// This is global bootstrap for autoloading
Codeception\Util\Autoload::addNamespace( 'Tribe__Events__WP_UnitTestCase', __DIR__ . '/_support' );
Codeception\Util\Autoload::addNamespace( 'Tribe\Events\Test', __DIR__ . '/_support' );
Codeception\Util\Autoload::addNamespace( 'Tribe\Events\Test', __DIR__ . '/_support/classes' );
Codeception\Util\Autoload::addNamespace( 'Tribe\Events\Test\Acceptance\Steps', __DIR__ . '/acceptance/_steps' );
