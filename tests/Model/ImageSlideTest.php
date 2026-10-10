<?php

namespace Dynamic\Carousel\Test\Model;

use Dynamic\Carousel\Model\ImageSlide;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\LinkField\Models\ExternalLink;
use SilverStripe\LinkField\Models\Link;
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

    /**
     * Duplicating a slide must fork its Link rather than share the original's row (#54),
     * while the Image stays shared: Files are not cloned.
     */
    public function testDuplicateForksTheLinkAndSharesTheImage()
    {
        $link = ExternalLink::create();
        $link->ExternalUrl = 'https://example.com/original';
        $link->write();

        $image = Image::create();
        $image->write();

        $slide = $this->objFromFixture(ImageSlide::class, 'one');
        $slide->ElementLinkID = $link->ID;
        $slide->ImageID = $image->ID;
        $slide->write();

        $linkCount = Link::get()->count();
        $copy = $slide->duplicate();

        $this->assertNotSame(0, (int)$copy->ElementLinkID);
        $this->assertNotSame((int)$slide->ElementLinkID, (int)$copy->ElementLinkID);
        $this->assertSame($linkCount + 1, Link::get()->count());
        $this->assertSame('https://example.com/original', $copy->ElementLink()->ExternalUrl);
        $this->assertSame((int)$slide->ImageID, (int)$copy->ImageID);

        $copy->ElementLink()->ExternalUrl = 'https://example.com/copy';
        $copy->ElementLink()->write();
        $this->assertSame('https://example.com/original', Link::get()->byID($link->ID)->ExternalUrl);
    }
}
