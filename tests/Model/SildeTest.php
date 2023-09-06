<?php

namespace Dynamic\Carousel\Test\Model;

use Dynamic\Carousel\Model\Slide;
use SilverStripe\Forms\FieldList;
use SilverStripe\Dev\SapphireTest;

class SildeTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'slide-test.yml';

    /**
     * Tests getCMSFields().
     */
    public function testGetCMSFields()
    {
        $object = $this->objFromFixture(Slide::class, 'one');
        $fields = $object->getCMSFields();
        $this->assertInstanceOf(FieldList::class, $fields);
    }
}
