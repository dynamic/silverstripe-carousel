<?php

namespace Dynamic\Carousel\Test\Extension;

use Dynamic\Carousel\Model\ImageSlide;
use SilverStripe\Forms\FieldList;
use SilverStripe\Dev\SapphireTest;
use Dynamic\Carousel\Test\Test\Page\TestPage;
use Dynamic\Carousel\Extension\CarouselPageExtension;

class CarouselPageExtensionTest extends SapphireTest
{
    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        TestPage::class,
    ];

    /**
     * @var string
     */
    protected static $fixture_file = 'carousel.yml';

    /**
     * @var array
     */
    protected static $required_extensions = [
        TestPage::class => [
            CarouselPageExtension::class,
        ],
    ];

    /**
     * Tests updateCMSFields().
     */
    public function testUpdateCMSFields()
    {
        $object = TestPage::create();
        $fields = $object->getCMSFields();
        $this->assertFalse($object->exists());
        $this->assertNull($fields->dataFieldByName('Slides'));

        $object->write();
        $fields = $object->getCMSFields();
        $this->assertInstanceOf(FieldList::class, $fields);
        $this->assertNotNull($fields->dataFieldbyName('Slides'));
    }

    /**
     * Tests getSlides().
     */
    public function testGetSlides()
    {
        $object = TestPage::create();
        $object->write();
        $object->Slides()->add($this->objFromFixture(ImageSlide::class, 'one'));
        $this->assertEquals(1, $object->Slides()->count());
    }
}
