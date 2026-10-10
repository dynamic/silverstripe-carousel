<?php

namespace Dynamic\Carousel\Test\Extension;

use Dynamic\Carousel\Extension\CarouselPageExtension;
use Dynamic\Carousel\Model\ImageSlide;
use Dynamic\Carousel\Model\Slide;
use Dynamic\Carousel\Test\Test\Model\TestCarousel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

/**
 * Slides are shareable between carousels (the CMS offers "add existing"), so removing one
 * carousel must never delete a Slide another carousel still shows (issue #55).
 */
class CarouselSharedSlidesTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestCarousel::class,
    ];

    protected static $required_extensions = [
        TestCarousel::class => [
            Versioned::class,
            CarouselPageExtension::class,
        ],
    ];

    /**
     * Two published carousels sharing one published Slide.
     *
     * @return array{TestCarousel, TestCarousel, ImageSlide}
     */
    private function createSharedCarousels(): array
    {
        $slide = ImageSlide::create();
        $slide->Title = 'Shared slide';
        $slide->write();

        $first = TestCarousel::create();
        $first->Title = 'First carousel';
        $first->write();
        $first->Slides()->add($slide, ['SortOrder' => 1]);

        $second = TestCarousel::create();
        $second->Title = 'Second carousel';
        $second->write();
        $second->Slides()->add($slide, ['SortOrder' => 1]);

        $first->publishRecursive();
        $second->publishRecursive();

        $this->assertNotNull(
            Versioned::get_by_stage(Slide::class, Versioned::LIVE)->byID($slide->ID),
            'Setup: the shared slide must be published'
        );

        return [$first, $second, $slide];
    }

    private function assertSlideOnBothStages(int $slideID): void
    {
        $this->assertNotNull(
            Versioned::get_by_stage(Slide::class, Versioned::DRAFT)->byID($slideID),
            'The shared slide must survive on draft'
        );
        $this->assertNotNull(
            Versioned::get_by_stage(Slide::class, Versioned::LIVE)->byID($slideID),
            'The shared slide must survive on live'
        );
    }

    public function testArchivingACarouselKeepsSlidesSharedWithAnotherCarousel(): void
    {
        [$first, $second, $slide] = $this->createSharedCarousels();

        $first->doArchive();

        $this->assertSlideOnBothStages($slide->ID);
        $this->assertSame([$slide->ID], $second->Slides()->column('ID'));
    }

    public function testDeletingACarouselKeepsSlidesSharedWithAnotherCarousel(): void
    {
        [$first, $second, $slide] = $this->createSharedCarousels();

        $first->delete();

        $this->assertSlideOnBothStages($slide->ID);
        $this->assertSame([$slide->ID], $second->Slides()->column('ID'));
    }

    /**
     * The contract after #55: a carousel never deletes its slides, even unshared ones. An
     * unreferenced Slide stays in the database like a File.
     */
    public function testArchivingKeepsAnUnsharedSlide(): void
    {
        $slide = ImageSlide::create();
        $slide->Title = 'Only slide';
        $slide->write();

        $carousel = TestCarousel::create();
        $carousel->Title = 'Lone carousel';
        $carousel->write();
        $carousel->Slides()->add($slide, ['SortOrder' => 1]);
        $carousel->publishRecursive();

        $carousel->doArchive();

        $this->assertSlideOnBothStages($slide->ID);
    }

    /**
     * cascade_duplicates on a many_many re-links the same Slide rows rather than cloning
     * them, so every duplicated carousel shares its slides with the original. Archiving
     * the original must leave the copy's slides intact.
     */
    public function testArchivingTheOriginalKeepsTheDuplicatesSlides(): void
    {
        $slide = ImageSlide::create();
        $slide->Title = 'Original slide';
        $slide->write();

        $original = TestCarousel::create();
        $original->Title = 'Original carousel';
        $original->write();
        $original->Slides()->add($slide, ['SortOrder' => 1]);
        $original->publishRecursive();

        $copy = $original->duplicate();
        $copy->publishRecursive();
        $this->assertSame([$slide->ID], $copy->Slides()->column('ID'), 'Setup: the copy shares the slide');

        $original->doArchive();

        $this->assertSlideOnBothStages($slide->ID);
        $this->assertSame([$slide->ID], $copy->Slides()->column('ID'));
    }
}
