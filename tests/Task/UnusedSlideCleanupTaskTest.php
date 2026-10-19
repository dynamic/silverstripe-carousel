<?php

namespace Dynamic\Carousel\Test\Task;

use Dynamic\Carousel\Extension\CarouselPageExtension;
use Dynamic\Carousel\Model\ImageSlide;
use Dynamic\Carousel\Model\Slide;
use Dynamic\Carousel\Task\UnusedSlideCleanupTask;
use Dynamic\Carousel\Test\Stubs\HiddenOwnerReadStubExtension;
use Dynamic\Carousel\Test\Test\Model\TestCarousel;
use ReflectionMethod;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Slides that no carousel references any more (issue #59): listed by a dry run, archived only when
 * --archive is passed, and never on either stage does a slide another carousel still lists get
 * caught up in it.
 */
class UnusedSlideCleanupTaskTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestCarousel::class,
    ];

    protected static $required_extensions = [
        TestCarousel::class => [
            CarouselPageExtension::class,
            HiddenOwnerReadStubExtension::class,
        ],
    ];

    /**
     * The task as sake registers it, which is what the issue's usage line documents.
     */
    public function testRegistersUnderTheDocumentedCommandName(): void
    {
        $this->assertSame('tasks:carousel-unused-slide-cleanup', UnusedSlideCleanupTask::getName());

        $names = array_map(
            static fn ($option): string => $option->getName(),
            (new UnusedSlideCleanupTask())->getOptions()
        );
        $this->assertContains('archive', $names);
    }

    /**
     * One slide in two carousels, one slide in one carousel, one slide nowhere: the dry run names
     * only the last one, and writes nothing.
     */
    public function testDryRunListsOnlyTheOrphanAndChangesNothing(): void
    {
        [$shared, $used, $orphan] = $this->createFixtures();

        [$code, $output] = $this->runTask(false);

        $this->assertSame(0, $code);
        $this->assertSame([$orphan->ID], $this->listedSlideIDs($output));
        $this->assertStringContainsString($orphan->Title, $output);
        $this->assertStringContainsString('Dry run: 1 unused slide found', $output);
        $this->assertStringContainsString('Draft stage: 1 unused slide found.', $output);
        $this->assertStringContainsString('Live stage: 1 unused slide found.', $output);

        // A dry run must not touch a single row, on either stage.
        $this->assertSlideOnBothStages($shared->ID);
        $this->assertSlideOnBothStages($used->ID);
        $this->assertSlideOnBothStages($orphan->ID);
    }

    /**
     * The archive half of the issue's done-when: only the orphan goes, from draft and live.
     */
    public function testArchiveRunRemovesOnlyTheOrphanFromBothStages(): void
    {
        [$shared, $used, $orphan] = $this->createFixtures();

        [$code, $output] = $this->runTask(true);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Archived 1 unused slide.', $output);
        $this->assertSlideGoneFromBothStages($orphan->ID);
        $this->assertSlideOnBothStages($shared->ID);
        $this->assertSlideOnBothStages($used->ID);
    }

    /**
     * Nothing to clean up is a clean, quiet run - not an error, and not a fatal.
     */
    public function testNoUnusedSlidesIsANoOpInBothModes(): void
    {
        $slide = $this->createSlide('Shared slide');
        $this->createCarousel('First carousel', [$slide], true);
        $this->createCarousel('Second carousel', [$slide], true);

        [$dryCode, $dryOutput] = $this->runTask(false);
        $this->assertSame(0, $dryCode);
        $this->assertStringContainsString('Dry run: 0 unused slides found, nothing archived.', $dryOutput);
        $this->assertSame([], $this->listedSlideIDs($dryOutput));

        [$archiveCode, $archiveOutput] = $this->runTask(true);
        $this->assertSame(0, $archiveCode);
        $this->assertStringContainsString('Archived 0 unused slides.', $archiveOutput);
        $this->assertSlideOnBothStages($slide->ID);
    }

    /**
     * A slide that was never published exists on draft only, and still has to be found and cleared.
     */
    public function testDraftOnlyOrphanIsFoundAndArchived(): void
    {
        $orphan = $this->createSlide('Never published');

        [$code, $output] = $this->runTask(false);

        $this->assertSame(0, $code);
        $this->assertSame([$orphan->ID], $this->listedSlideIDs($output));
        $this->assertStringContainsString('Draft stage: 1 unused slide found.', $output);
        $this->assertStringContainsString('Live stage: 0 unused slides found.', $output);

        [$code, $output] = $this->runTask(true);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Archived 1 unused slide.', $output);
        $this->assertSlideGoneFromBothStages($orphan->ID);
    }

    /**
     * The mirror case: someone deleted the draft row and left the published one. There is no draft
     * record to archive, so the task has to archive the live record it found.
     */
    public function testLiveOnlyOrphanIsArchived(): void
    {
        $orphan = $this->createSlide('Draft row deleted');
        $orphan->publishSingle();
        $orphan->deleteFromStage(Versioned::DRAFT);

        $this->assertNull(
            Versioned::get_by_stage(Slide::class, Versioned::DRAFT)->byID($orphan->ID),
            'Setup: the orphan must be off draft'
        );

        [$code, $output] = $this->runTask(true);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Archived 1 unused slide.', $output);
        $this->assertSlideGoneFromBothStages($orphan->ID);
    }

    /**
     * The case that motivated the issue: archiving a carousel leaves its join rows behind (only the
     * Slide was ever the question in #55), so "has a join row" cannot mean "is used". A slide whose
     * only link sits on an archived carousel is an orphan, and is found as one - including one with
     * no title at all, which still has to be identifiable in the listing.
     */
    public function testSlidesLeftBehindByAnArchivedCarouselAreFound(): void
    {
        $abandoned = $this->createSlide('');
        $carousel = $this->createCarousel('Doomed carousel', [$abandoned], true);
        $carousel->doArchive();

        $this->assertSlideOnBothStages($abandoned->ID, 'Setup: #55 leaves the slide behind');

        [$code, $output] = $this->runTask(false);

        $this->assertSame(0, $code);
        $this->assertSame([$abandoned->ID], $this->listedSlideIDs($output));
        $this->assertStringContainsString('(no title)', $output);

        [$code, $output] = $this->runTask(true);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Archived 1 unused slide.', $output);
        $this->assertSlideGoneFromBothStages($abandoned->ID);
    }

    /**
     * The other half of the reference rule: a join table cannot say which stage linked a slide, so
     * a slide a draft-only carousel lists is still in use and must survive even though nothing on
     * live would miss it.
     */
    public function testSlideOnADraftOnlyCarouselIsNeverTouched(): void
    {
        $slide = $this->createSlide('On an unpublished carousel');
        $carousel = $this->createCarousel('Never published carousel', [$slide], false);
        $slide->publishSingle();

        $this->assertNull(
            Versioned::get_by_stage(TestCarousel::class, Versioned::LIVE)->byID($carousel->ID),
            'Setup: the carousel must be off live'
        );

        [$code, $output] = $this->runTask(true);

        $this->assertSame(0, $code);
        $this->assertSame([], $this->listedSlideIDs($output));
        $this->assertStringContainsString('Archived 0 unused slides.', $output);
        $this->assertSlideOnBothStages($slide->ID);
    }

    /**
     * The mirror of the draft-only case above: the carousel's draft row is gone and its published
     * row is what still lists the slide. Nothing on draft would miss that slide, so an owner check
     * that only read the draft table would archive a slide a live carousel is still showing. This
     * is the test that fails if the live half of ownerIDsOnAnyStage() is dropped.
     */
    public function testSlideOnALiveOnlyCarouselIsNeverTouched(): void
    {
        $slide = $this->createSlide('Slide a published-only carousel lists');
        $carousel = $this->createCarousel('Carousel with its draft row deleted', [$slide], true);
        $carousel->deleteFromStage(Versioned::DRAFT);

        $this->assertNull(
            Versioned::get_by_stage(TestCarousel::class, Versioned::DRAFT)->byID($carousel->ID),
            'Setup: the carousel must be off draft'
        );
        $this->assertNotNull(
            Versioned::get_by_stage(TestCarousel::class, Versioned::LIVE)->byID($carousel->ID),
            'Setup: the carousel must still be on live'
        );

        [$code, $output] = $this->runTask(true);

        $this->assertSame(0, $code);
        $this->assertSame([], $this->listedSlideIDs($output));
        $this->assertStringContainsString('Archived 0 unused slides.', $output);
        $this->assertSlideOnBothStages($slide->ID);
    }

    /**
     * The owner check has to see every row of the owner's stage tables, not merely the rows the ORM
     * hands back. An `augmentSQL` filter on the owner class - Subsites restricting SiteTree to the
     * current subsite is the real one - hides a carousel from `Versioned::get_by_stage()` while
     * leaving it in the table, and a carousel that is not read has its join rows discarded as stale,
     * so `--archive` removes slides a published carousel still shows. Reading the tables directly is
     * what makes an extension on a project's own owner class unable to widen this task's blast radius.
     */
    public function testOwnerHiddenFromTheORMByAnAugmentSQLFilterStillCountsAsAReference(): void
    {
        $slide = $this->createSlide('Slide on a carousel the ORM cannot read');
        $carousel = $this->createCarousel('Carousel hidden by an augmentSQL filter', [$slide], true);

        $this->assertNotNull(
            Versioned::get_by_stage(TestCarousel::class, Versioned::DRAFT)->byID($carousel->ID),
            'Setup: the carousel is readable before the filter goes on'
        );

        // The row stays in the stage table; no ORM read of the class returns it any more.
        HiddenOwnerReadStubExtension::$hiddenIDs = [$carousel->ID];

        try {
            $this->assertNull(
                Versioned::get_by_stage(TestCarousel::class, Versioned::DRAFT)->byID($carousel->ID),
                'Setup: the ORM must not be able to read the carousel the filter hides'
            );
            $this->assertNull(
                Versioned::get_by_stage(TestCarousel::class, Versioned::LIVE)->byID($carousel->ID),
                'Setup: the filter hides both stages, the way Subsites hides a page'
            );
            $this->assertContains(
                $carousel->ID,
                array_map('intval', DB::query('SELECT "ID" FROM "Carousel_TestCarousel"')->column('ID')),
                'Setup: the carousel row is still in the stage table the task reads'
            );

            [$dryCode, $dryOutput] = $this->runTask(false);

            $this->assertSame(0, $dryCode);
            $this->assertSame(
                [],
                $this->listedSlideIDs($dryOutput),
                'A carousel the ORM hides is still a carousel, so its slide is not unused'
            );

            [$code, $output] = $this->runTask(true);

            $this->assertSame(0, $code);
            $this->assertStringContainsString('Archived 0 unused slides.', $output);
            $this->assertSlideOnBothStages($slide->ID);
        } finally {
            HiddenOwnerReadStubExtension::$hiddenIDs = [];
        }
    }

    /**
     * The per-row owner check, on its own rather than through the "no owner at all" shortcut: one
     * join table holding rows for both an archived carousel and a live one. Only the archived
     * carousel's rows are stale, and the other carousel's slide has to stay - which is what a
     * per-row test that only ever exercises the empty-owner early exit cannot show.
     */
    public function testStaleRowsAndLiveRowsInTheSameJoinTableAreToldApart(): void
    {
        $abandoned = $this->createSlide('Slide of the archived carousel');
        $kept = $this->createSlide('Slide of the live carousel');
        $neverLinked = $this->createSlide('Slide no carousel ever listed');

        $doomed = $this->createCarousel('Carousel archived after the fact', [$abandoned], true);
        $this->createCarousel('Carousel still live', [$kept], true);
        $doomed->doArchive();
        $neverLinked->publishSingle();

        [$dryCode, $dryOutput] = $this->runTask(false);

        $this->assertSame(0, $dryCode);
        $this->assertSame([$abandoned->ID, $neverLinked->ID], $this->listedSlideIDs($dryOutput));
        $this->assertStringNotContainsString($kept->Title, $dryOutput);

        [$code, $output] = $this->runTask(true);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Archived 2 unused slides.', $output);
        $this->assertSlideGoneFromBothStages($abandoned->ID);
        $this->assertSlideGoneFromBothStages($neverLinked->ID);
        $this->assertSlideOnBothStages($kept->ID);
    }

    /**
     * A carousel join table that is not there is a part of the scan that failed, not a relation with
     * nothing to report: every slide it used to link would look unused. So it warns on a dry run and
     * an archive pass refuses - fail closed. A `many_many` redefined with a `through` class reports
     * that class name where a table name is expected, which is how a site reaches this.
     */
    public function testMissingJoinTableIsReportedAndStopsAnArchivePass(): void
    {
        $slide = $this->createSlide('Slide behind a join table that cannot be read');
        $this->createCarousel('Carousel whose join table is missing', [$slide], true);

        [$dryCode, $dryOutput] = $this->runTask(false, $this->missingJoinTableTask());

        $this->assertSame(0, $dryCode);
        $this->assertStringContainsString(
            'WARNING: could not read the Carousel_NoSuchJoinTable carousel join table',
            $dryOutput
        );
        $this->assertStringNotContainsString('Refusing to archive', $dryOutput);

        [$code, $output] = $this->runTask(true, $this->missingJoinTableTask());

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Refusing to archive', $output);
        $this->assertStringNotContainsString('Archived', $output);
        $this->assertSlideOnBothStages($slide->ID, 'An unreadable join table must not cost a slide its row');
    }

    /**
     * A site that has the module but no carousel owner configured yet is a scan with nothing to
     * scan: it says so, archives nothing, and does not fail.
     */
    public function testSiteWithNoCarouselOwnerReportsNothingToScan(): void
    {
        $orphan = $this->createSlide('Orphan in a site with no carousels');
        $orphan->publishSingle();

        $task = new class extends UnusedSlideCleanupTask {
            protected function relations(): array
            {
                return [];
            }
        };

        [$code, $output] = $this->runTask(true, $task);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('so there is nothing to scan.', $output);
        $this->assertStringNotContainsString('Archived', $output);
        $this->assertSlideOnBothStages($orphan->ID);
    }

    /**
     * An owner this run could not read contributes no references, which makes the slides behind it
     * look unused. A dry run can still print that list and warn about it; an archive pass must not
     * act on a reference set it knows to be incomplete, and must not report success either.
     *
     * Here the unreadable owner is a relation naming a class that cannot be resolved at all, so the
     * failure is raised for real, inside ownerIDsOnAnyStage().
     */
    public function testUnreadableOwnerRelationIsReportedAndStopsAnArchivePass(): void
    {
        $slide = $this->createSlide('Slide behind an unreadable owner');
        $this->createCarousel('Readable carousel', [$slide], true);

        [$dryCode, $dryOutput] = $this->runTask(false, $this->unreadableRelationTask());

        $this->assertSame(0, $dryCode);
        $this->assertStringContainsString('WARNING: could not read owners of', $dryOutput);
        $this->assertStringNotContainsString('Refusing to archive', $dryOutput);
        $this->assertSame(
            [$slide->ID],
            $this->listedSlideIDs($dryOutput),
            'An owner that could not be read makes its slides look unused'
        );

        // A fresh instance: the refusal must be this run's, not one inherited from the dry run.
        [$code, $output] = $this->runTask(true, $this->unreadableRelationTask());

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Refusing to archive', $output);
        $this->assertStringNotContainsString('Archived', $output);
        $this->assertSlideOnBothStages($slide->ID, 'An incomplete scan must not archive a slide in use');
    }

    /**
     * The same refusal for an owner that resolves but cannot be read at all (a missing table, a
     * connection blip): the double leaves the task in exactly the state ownerIDsOnAnyStage()'s
     * catch leaves it in, and the slide its carousel still lists has to survive the run.
     */
    public function testArchiveRefusesWhenAnOwnerCouldNotBeRead(): void
    {
        $slide = $this->createSlide('Slide a carousel still lists');
        $this->createCarousel('Carousel that could not be read', [$slide], true);

        [$dryCode, $dryOutput] = $this->runTask(false, $this->unreadableOwnerRowsTask());

        $this->assertSame(0, $dryCode);
        $this->assertStringContainsString('WARNING: could not read owners of', $dryOutput);
        $this->assertStringNotContainsString('Refusing to archive', $dryOutput);

        [$code, $output] = $this->runTask(true, $this->unreadableOwnerRowsTask());

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Refusing to archive', $output);
        $this->assertStringNotContainsString('Archived', $output);
        $this->assertSlideOnBothStages($slide->ID, 'A slide a carousel still lists must survive an aborted run');
    }

    /**
     * A relation naming an owner class that resolves to nothing, so reading its rows throws inside
     * ownerIDsOnAnyStage() exactly as a broken or unmigrated owner class does in a real project.
     */
    private function unreadableRelationTask(): UnusedSlideCleanupTask
    {
        return new class extends UnusedSlideCleanupTask {
            protected function relations(): array
            {
                $broken = [];

                foreach (parent::relations() as $relation) {
                    $broken['unreadable.' . $relation['join']] = array_merge($relation, [
                        'parentClass' => 'Dynamic\Carousel\Test\Task\UnreadableCarouselOwner',
                    ]);
                }

                return $broken;
            }
        };
    }

    /**
     * An owner that resolves but whose rows cannot be read: exactly the state
     * ownerIDsOnAnyStage()'s own catch leaves behind, one reportable line and no IDs.
     */
    private function unreadableOwnerRowsTask(): UnusedSlideCleanupTask
    {
        return new class extends UnusedSlideCleanupTask {
            protected function ownerIDsOnAnyStage(string $class): array
            {
                $this->skipped[] = sprintf('could not read owners of %s: simulated', $class);

                return [];
            }
        };
    }

    /**
     * A relation whose join table does not exist, which is what a `many_many` with a `through` class
     * looks like from this side: manyManyComponent() hands back the through class's name there.
     */
    private function missingJoinTableTask(): UnusedSlideCleanupTask
    {
        return new class extends UnusedSlideCleanupTask {
            protected function relations(): array
            {
                $broken = [];

                foreach (parent::relations() as $relation) {
                    $broken['missing.' . $relation['childField']] = array_merge($relation, [
                        'join' => 'Carousel_NoSuchJoinTable',
                    ]);
                }

                return $broken;
            }
        };
    }

    /**
     * One slide in two carousels, one in one carousel, one in none - all published, all on both
     * stages.
     *
     * @return array{0:Slide,1:Slide,2:Slide} shared, single-owner, orphan
     */
    private function createFixtures(): array
    {
        $shared = $this->createSlide('Shared slide');
        $used = $this->createSlide('Single owner slide');
        $orphan = $this->createSlide('Orphaned slide');

        $this->createCarousel('First carousel', [$shared], true);
        $this->createCarousel('Second carousel', [$shared], true);
        $this->createCarousel('Third carousel', [$used], true);

        $orphan->publishSingle();

        return [$shared, $used, $orphan];
    }

    private function createSlide(string $title): Slide
    {
        $slide = ImageSlide::create();
        $slide->Title = $title;
        $slide->write();

        return $slide;
    }

    /**
     * @param array<int,Slide> $slides
     */
    private function createCarousel(string $title, array $slides, bool $publish): TestCarousel
    {
        $carousel = TestCarousel::create();
        $carousel->Title = $title;
        $carousel->write();

        foreach ($slides as $index => $slide) {
            $carousel->Slides()->add($slide, ['SortOrder' => $index + 1]);
        }

        if ($publish) {
            $carousel->publishRecursive();
        }

        return $carousel;
    }

    /**
     * Run the task the way sake does - its own option definition, its own execute() - and hand
     * back the exit code plus everything it printed.
     *
     * @return array{0:int,1:string}
     */
    private function runTask(bool $archive, ?UnusedSlideCleanupTask $task = null): array
    {
        $task = $task ?? new UnusedSlideCleanupTask();
        $input = new ArrayInput($archive ? ['--archive' => true] : [], new InputDefinition($task->getOptions()));
        $buffer = new BufferedOutput();
        $output = new PolyOutput(
            PolyOutput::FORMAT_ANSI,
            OutputInterface::VERBOSITY_NORMAL,
            false,
            $buffer
        );

        $method = new \ReflectionMethod(UnusedSlideCleanupTask::class, 'execute');
        $code = $method->invoke($task, $input, $output);

        return [$code, $buffer->fetch()];
    }

    /**
     * The slide IDs the task actually listed, read out of its own listing lines so a passing test
     * cannot be an accidental substring match.
     *
     * @return array<int,int>
     */
    private function listedSlideIDs(string $output): array
    {
        preg_match_all('/^\s+- #(\d+) /m', $output, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    private function assertSlideOnBothStages(int $slideID, string $message = ''): void
    {
        $this->assertNotNull(
            Versioned::get_by_stage(Slide::class, Versioned::DRAFT)->byID($slideID),
            $message ?: "Slide {$slideID} must still be on draft"
        );
        $this->assertNotNull(
            Versioned::get_by_stage(Slide::class, Versioned::LIVE)->byID($slideID),
            $message ?: "Slide {$slideID} must still be on live"
        );
    }

    private function assertSlideGoneFromBothStages(int $slideID): void
    {
        $this->assertNull(
            Versioned::get_by_stage(Slide::class, Versioned::DRAFT)->byID($slideID),
            "Slide {$slideID} must be off draft"
        );
        $this->assertNull(
            Versioned::get_by_stage(Slide::class, Versioned::LIVE)->byID($slideID),
            "Slide {$slideID} must be off live"
        );
    }
}
