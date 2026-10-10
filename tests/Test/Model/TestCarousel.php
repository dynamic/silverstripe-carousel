<?php

namespace Dynamic\Carousel\Test\Test\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * A versioned, non-page owner for CarouselPageExtension, shaped like ElementCarousel.
 */
class TestCarousel extends DataObject implements TestOnly
{
    private static $table_name = 'Carousel_TestCarousel';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
