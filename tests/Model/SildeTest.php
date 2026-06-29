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

    /**
     * ShowContent() truth table.
     */
    public function testShowContentReturnsFalseWhenNothingSet(): void
    {
        $slide = Slide::create();
        $slide->Title = '';
        $slide->ShowTitle = false;
        $slide->Content = '';

        $this->assertFalse($slide->ShowContent(), 'ShowContent() should be false when no overlay content is set');
    }

    public function testShowContentReturnsTrueWhenTitleAndShowTitle(): void
    {
        $slide = Slide::create();
        $slide->Title = 'My Title';
        $slide->ShowTitle = true;
        $slide->Content = '';

        $this->assertTrue($slide->ShowContent(), 'ShowContent() should be true when Title and ShowTitle are set');
    }

    public function testShowContentReturnsFalseWhenTitleSetButShowTitleFalse(): void
    {
        $slide = Slide::create();
        $slide->Title = 'My Title';
        $slide->ShowTitle = false;
        $slide->Content = '';

        $this->assertFalse($slide->ShowContent(), 'ShowContent() should be false when Title is set but ShowTitle is false');
    }

    public function testShowContentReturnsTrueWhenContentSet(): void
    {
        $slide = Slide::create();
        $slide->Title = '';
        $slide->ShowTitle = false;
        $slide->Content = '<p>Some content</p>';

        $this->assertTrue($slide->ShowContent(), 'ShowContent() should be true when Content is set');
    }

    /**
     * ShowCaption() is a deprecated proxy — must return same value as ShowContent().
     */
    public function testShowCaptionProxiesToShowContent(): void
    {
        $slide = Slide::create();
        $slide->Title = 'Title';
        $slide->ShowTitle = true;
        $slide->Content = '';

        $this->assertSame($slide->ShowContent(), $slide->ShowCaption());
    }
}
