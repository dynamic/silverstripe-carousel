<?php

namespace Dynamic\Carousel\Test\Model;

use Dynamic\Carousel\Model\VideoSlide;
use SilverStripe\Forms\FieldList;
use SilverStripe\Dev\SapphireTest;

class VideoSlideTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'video-slide-test.yml';

    /**
     * Tests getCMSFields().
     */
    public function testGetCMSFields()
    {
        $object = $this->objFromFixture(VideoSlide::class, 'one');
        $fields = $object->getCMSFields();
        $this->assertInstanceOf(FieldList::class, $fields);
    }
}
