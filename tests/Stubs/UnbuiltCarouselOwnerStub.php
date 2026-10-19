<?php

namespace Dynamic\Carousel\Test\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * A carousel owner class that resolves as a class but whose stage table this run cannot find, which
 * is what a renamed `$table_name` or a half-applied /dev/build leaves a real project looking like
 * from the task's side. UnusedSlideCleanupTaskTest drops its table for one test, because the test
 * database otherwise builds a table for every class in the manifest - including this one.
 */
class UnbuiltCarouselOwnerStub extends DataObject implements TestOnly
{
    private static $table_name = 'Carousel_StubOwnerWithoutTable';

    private static $db = [
        'Title' => 'Varchar',
    ];
}
