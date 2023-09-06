<?php

namespace Dynamic\Carousel\Test\Model;

use Dynamic\Carousel\Model\ImageSlide;
use SilverStripe\Forms\FieldList;
use SilverStripe\Dev\SapphireTest;

class ImageSlideTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'image-slide-test.yml';

    /**
     * Tests getCMSFields().
     */
    public function testGetCMSFields()
    {
        $object = $this->objFromFixture(ImageSlide::class, 'one');
        $fields = $object->getCMSFields();
        $this->assertInstanceOf(FieldList::class, $fields);
    }
}
