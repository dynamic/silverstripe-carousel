<?php

namespace Dynamic\Carousel\Task;

use Dynamic\Carousel\Extension\CarouselPageExtension;
use Dynamic\Carousel\Model\Slide;
use ReflectionClass;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Lists Slides that no carousel references any more, and archives them when explicitly asked.
 *
 * Context (issue #55, shipped in 3.1.0): archiving or deleting a carousel no longer takes its
 * Slides with it, because a Slide can be in several carousels at once and only some of them may
 * have gone away. The leftover is invisible - there is no ModelAdmin for Slide - so it simply
 * accumulates, published on draft and live, unable to render anywhere. This task is the way to
 * find and clear those rows.
 *
 * How a Slide counts as "used", and why it is not just "has a row in a join table":
 *
 * - The owner classes are discovered at runtime. Every DataObject subclass carrying
 *   CarouselPageExtension is a carousel - ElementCarousel in a project that uses
 *   dynamic/silverstripe-elemental-carousel, any page type a project applies the extension to,
 *   and nothing is hard-coded here, so a project's own carousel types are covered without this
 *   module knowing about them. Each owner's Slides relation is resolved through the schema, which
 *   is what gives the join table and its child field; two owners that share one relation (a page
 *   type and its subclasses, for instance) share one join table and are only scanned once.
 * - A join row only counts when its carousel still exists. Versioned::doArchive() and
 *   DataObject::delete() leave the many_many rows behind - #57 changed what happens to the Slide,
 *   not what happens to the link table - so after a carousel is archived its stale rows would
 *   otherwise make every slide it ever held look permanently used, which is exactly the state this
 *   task exists to clean up. A row is therefore referenced only if its parent ID is still in the
 *   owner's stage table. Owners that are not versioned have one set of rows; versioned owners
 *   count on either stage, because the join table itself is not versioned and cannot tell the two
 *   apart - a slide a draft-only carousel still lists is not safe to archive either.
 * - Both halves of that check are raw SQL, and deliberately so. An ORM read of the owner table goes
 *   through augmentSQL, so an extension on a project's own carousel owner - Subsites scoping
 *   SiteTree to the current subsite, for one - can drop a carousel out of the read while it sits
 *   untouched in the table. An owner that drops out has its join rows discarded as stale, and the
 *   slides a published carousel is showing get archived. Reading the stage tables directly is the
 *   one thing an extension cannot filter.
 *
 * Safety:
 *
 * - Dry run by default. Without --archive this task only reads, and says so.
 * - A half-read scan never writes. A carousel owner that cannot be inspected leaves its slides out
 *   of the reference set, so they look unused: --archive refuses to act on an incomplete set and
 *   exits non-zero. Listing is still safe, so a dry run only warns. A carousel join table that does
 *   not exist counts as half-read too, rather than as a relation with nothing in it - and so does
 *   the stage table of an owner class.
 * - Archive, never delete. Versioned::doArchive() drops the row from draft and live and keeps a
 *   deleted version, so an archiving mistake is recoverable from the Archived stage (or
 *   through _Versions) rather than being data loss.
 * - Nothing is written to the join tables. A stale link row left by an archived carousel stays
 *   where it is: it is harmless while the carousel stays archived, and removing it would destroy
 *   the only record of which slides that carousel had if anyone ever restores it.
 *
 * Usage: sake tasks:carousel-unused-slide-cleanup
 *        sake tasks:carousel-unused-slide-cleanup --archive
 *        (also reachable in a browser at dev/tasks/carousel-unused-slide-cleanup)
 */
class UnusedSlideCleanupTask extends BuildTask
{
    /**
     * silverstripe/framework ^6 resolves a task's name through PolyCommand::$commandName; the
     * SS4/SS5 BuildTask::$segment spelling is read by nothing, so without this the task would
     * register under its fully-qualified class name instead of the name documented above.
     */
    protected static string $commandName = 'carousel-unused-slide-cleanup';

    protected string $title = 'Unused carousel slide cleanup';

    protected static string $description = 'Lists Slides that no carousel references. Dry run by '
        . 'default; pass --archive to archive the slides it lists.';

    /**
     * The relation name every carousel owner uses for its slides.
     */
    protected const RELATION = 'Slides';

    /**
     * @var array<string,array{join:string,parentField:string,childField:string,parentClass:string}>|null
     */
    protected ?array $cachedRelations = null;

    /**
     * Parts of the scan that failed, each as one reportable line. Kept for the whole run rather
     * than emptied as it is reported, because its emptiness is what tells --archive whether the
     * reference set can be trusted.
     *
     * @var array<int,string>
     */
    protected array $skipped = [];

    /**
     * How many of $skipped have already been printed, so a second flush reports only the new lines.
     */
    protected int $reportedSkipped = 0;

    /**
     * @return array<int,InputOption>
     */
    public function getOptions(): array
    {
        return [
            new InputOption(
                'archive',
                null,
                InputOption::VALUE_NONE,
                'Archive each unused slide on both stages instead of only listing it.'
            ),
        ];
    }

    /**
     * @param InputInterface $input
     * @param PolyOutput $output
     * @return int
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        // TaskRunner / HttpRequestInput normalise a query-string flag to a real bool before it
        // reaches a task, so a plain cast is enough to read the flag back.
        $archive = (bool) $input->getOption('archive');

        return Versioned::withVersionedMode(function () use ($output, $archive): int {
            $relations = $this->relations();

            if ($relations === []) {
                $output->writeln(
                    'No class carrying ' . CarouselPageExtension::class . ' has a ' . self::RELATION
                    . ' relation, so there is nothing to scan.'
                );
                $this->flushSkipped($output);

                return Command::SUCCESS;
            }

            $referenced = $this->referencedSlideIDs($relations);
            $this->flushSkipped($output);
            $output->writeln(sprintf(
                'Scanned %d carousel %s table%s: %d slide%s referenced.',
                count($relations),
                self::RELATION,
                count($relations) === 1 ? '' : 's',
                count($referenced),
                count($referenced) === 1 ? ' is' : 's are'
            ));

            $unused = $this->collectUnused($referenced, $output);

            if (!$archive) {
                $rerunHint = $unused === [] ? '' : sprintf(
                    ' Re-run with --archive to archive %s.',
                    count($unused) === 1 ? 'it' : 'them'
                );
                $output->writeln(sprintf(
                    'Dry run: %d unused slide%s found, nothing archived.%s',
                    count($unused),
                    count($unused) === 1 ? '' : 's',
                    $rerunHint
                ));
                $this->flushSkipped($output);

                return Command::SUCCESS;
            }

            if ($this->scanIsIncomplete()) {
                // The listing above is what a dry run should print, but it was built from reference
                // data this run could not finish reading: a slide held by an owner that could not
                // be inspected looks unused here and is not. Refuse the write half.
                $output->writeln(
                    'Refusing to archive: the reference set above is incomplete because a carousel '
                    . 'owner could not be read. Fix the warnings above and re-run.'
                );
                $this->flushSkipped($output);

                return Command::FAILURE;
            }

            $code = $this->archiveUnused($unused, $output);
            $this->flushSkipped($output);

            return $code;
        });
    }

    /**
     * Every carousel join table that can hold a Slide, keyed by a string identifying the relation.
     *
     * Discovered from the schema rather than a list of classes, so a project's own page types and
     * elements are covered. Owners that share one relation collapse to one entry, and owners whose
     * relation does not point at a Slide subclass (a project that redefines Slides for something
     * else) are skipped rather than guessed about.
     *
     * @return array<string,array{join:string,parentField:string,childField:string,parentClass:string}>
     */
    protected function relations(): array
    {
        if ($this->cachedRelations !== null) {
            return $this->cachedRelations;
        }

        $schema = DataObject::getSchema();
        $relations = [];

        foreach (ClassInfo::subclassesFor(DataObject::class) as $class) {
            try {
                // An abstract class owns no rows, so it contributes no join table worth scanning.
                // Ruled out here rather than through singleton(), which cannot construct one and so
                // would report it as an owner this run could not read - and after the refusal below,
                // an unreadable owner now stops an archive pass.
                if ((new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                // Statically, for the same reason ownerIDsOnAnyStage() reads Versioned statically:
                // building a singleton of every DataObject class in the manifest means one unrelated
                // class whose constructor throws is reported as an unreadable carousel owner, which
                // stops an archive pass on a site where the scan is in fact complete.
                if (!DataObject::has_extension($class, CarouselPageExtension::class)) {
                    continue;
                }

                $component = $schema->manyManyComponent($class, self::RELATION);
            } catch (Throwable $e) {
                $this->skipped[] = sprintf('could not inspect %s: %s', $class, $e->getMessage());

                continue;
            }

            if (!$component) {
                continue;
            }

            $childClass = $component['childClass'] ?? null;

            if (!is_string($childClass) || !class_exists($childClass) || !is_a($childClass, Slide::class, true)) {
                continue;
            }

            $relation = [
                'join' => $component['join'],
                'parentField' => $component['parentField'],
                'childField' => $component['childField'],
                'parentClass' => $component['parentClass'],
            ];

            $relations[$relation['join'] . '.' . $relation['childField'] . '.' . $relation['parentClass']] = $relation;
        }

        return $this->cachedRelations = $relations;
    }

    /**
     * Slide IDs a carousel still references, as a set keyed by ID.
     *
     * One set covers both stages: the many_many join tables are not versioned, so there is no
     * per-stage reference to look up. Rows whose carousel no longer exist are not references -
     * see the class docblock.
     *
     * @param array<string,array{join:string,parentField:string,childField:string,parentClass:string}> $relations
     * @return array<int,true>
     */
    protected function referencedSlideIDs(array $relations): array
    {
        $referenced = [];

        foreach ($relations as $relation) {
            if (!DB::get_schema()->hasTable($relation['join'])) {
                // Not a silent skip: an unreadable join table means every slide it linked looks
                // unused, which is the one thing an archive pass must not act on. A many_many
                // redefined as one with a `through` class is the realistic way to get here -
                // manyManyComponent() reports that class name as 'join', and it is not a table.
                $this->skipped[] = sprintf(
                    'could not read the %s carousel join table: it does not exist',
                    $relation['join']
                );

                continue;
            }

            $ownerIDs = $this->ownerIDsOnAnyStage($relation['parentClass']);

            if ($ownerIDs === []) {
                // Every row in this table belongs to a carousel that has gone away.
                continue;
            }

            $result = DB::query(sprintf(
                'SELECT DISTINCT "%s", "%s" FROM "%s"',
                $relation['parentField'],
                $relation['childField'],
                $relation['join']
            ));

            foreach ($result as $row) {
                $ownerID = (int) $row[$relation['parentField']];

                if (isset($ownerIDs[$ownerID])) {
                    $referenced[(int) $row[$relation['childField']]] = true;
                }
            }
        }

        return $referenced;
    }

    /**
     * IDs of every row of this owner class that exists on any stage, as a set keyed by ID.
     *
     * A versioned owner counts on draft or on live: the join table cannot say which stage linked
     * the row, so anything still listed by either stage is a live reference. An owner whose own
     * stage table is missing (a renamed $table_name, a half-applied /dev/build) is a failed part of
     * the scan rather than an owner with no rows, so it is recorded and --archive refuses; only a
     * missing _Live table is read as simply having no live rows.
     *
     * The IDs come off the stage tables with raw SQL, the same way the join rows are read, and not
     * through the ORM. An `augmentSQL` extension on the owner class - Subsites limiting SiteTree
     * reads to the current subsite is the case that matters - would narrow an ORM read, and a
     * carousel that simply dropped out of $ids has its join rows thrown away as stale, so its
     * slides get archived while a published carousel still shows them. Reading every row of the
     * stage table cannot miss an owner, and over-counting is the safe direction anyway: more
     * references means fewer slides archived.
     *
     * @param string $class
     * @return array<int,true>
     */
    protected function ownerIDsOnAnyStage(string $class): array
    {
        $ids = [];

        try {
            $table = DataObject::getSchema()->baseDataTable($class);

            if (!is_string($table) || $table === '') {
                $this->skipped[] = sprintf('could not read owners of %s: it has no base data table', $class);

                return [];
            }

            // The owner's own table has to be there. Without it nothing about this owner is known,
            // and an owner that is not read has its join rows discarded as stale - which is slides a
            // real carousel is showing, archived. Recorded rather than returned empty.
            if (!DB::get_schema()->hasTable($table)) {
                $this->skipped[] = sprintf(
                    'could not read owners of %s: the %s table does not exist',
                    $class,
                    $table
                );

                return [];
            }

            $ids = $this->mergeIDs($ids, $this->queryIDs($table));

            // Statically, not through singleton(): manyManyComponent() reports the class the
            // extension was applied to, and a project can apply it to an abstract base whose
            // concrete subclasses own all the rows. singleton() cannot build that base, and reading
            // an owner is supposed to be safe on the class name alone.
            if (DataObject::has_extension($class, Versioned::class)) {
                $liveTable = $table . '_Live';

                // A missing _Live table is not a half-read scan: no live table means no live rows.
                if (DB::get_schema()->hasTable($liveTable)) {
                    $ids = $this->mergeIDs($ids, $this->queryIDs($liveTable));
                }
            }

            return $ids;
        } catch (Throwable $e) {
            $this->skipped[] = sprintf('could not read owners of %s: %s', $class, $e->getMessage());

            return [];
        }
    }

    /**
     * Every ID in one stage table of one owner class, read without the ORM so no extension can
     * filter an owner out of the set.
     *
     * @param string $table
     * @return array<int,true>
     */
    protected function queryIDs(string $table): array
    {
        $result = DB::query(sprintf('SELECT "ID" FROM "%s"', $table));
        $ids = [];

        foreach ($result->column('ID') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    /**
     * Union of two ID sets, keyed by ID.
     *
     * @param array<int,true> $ids
     * @param array<int,true> $more
     * @return array<int,true>
     */
    protected function mergeIDs(array $ids, array $more): array
    {
        foreach ($more as $id => $present) {
            $ids[$id] = true;
        }

        return $ids;
    }

    /**
     * Whether any carousel owner went unread this run. That is what makes an archive pass unsafe:
     * the owner's slides are missing from the reference set, so they look unused.
     */
    protected function scanIsIncomplete(): bool
    {
        return $this->skipped !== [];
    }

    /**
     * The Slides on each stage that no carousel references. Each stage names its own slides, so a
     * slide on both is listed twice - once per stage, which is what the issue asks for - while the
     * returned set is keyed by Slide ID so it is archived once.
     *
     * @param array<int,true> $referenced
     * @param PolyOutput $output
     * @return array<int,Slide>
     */
    protected function collectUnused(array $referenced, PolyOutput $output): array
    {
        $unused = [];

        foreach ([Versioned::DRAFT, Versioned::LIVE] as $stage) {
            $stageSlides = Versioned::get_by_stage(Slide::class, $stage);
            $orphanIDs = array_values(array_diff($stageSlides->column('ID'), array_keys($referenced)));
            sort($orphanIDs);

            $output->writeln(sprintf(
                '%s stage: %d unused slide%s found.',
                $this->stageLabel($stage),
                count($orphanIDs),
                count($orphanIDs) === 1 ? '' : 's'
            ));

            foreach ($orphanIDs as $orphanID) {
                $slide = $stageSlides->byID((int) $orphanID);

                if (!$slide) {
                    continue;
                }

                $output->writeln(sprintf('  - #%d %s "%s"', $slide->ID, $slide->ClassName, $this->describe($slide)));
                $unused[(int) $slide->ID] = $slide;
            }
        }

        return $unused;
    }

    /**
     * Archive each unused slide, then re-read both stages and report what is left.
     *
     * Draft first: a slide on both stages is archived through its draft record, which is the
     * record Versioned::doArchive() versions, unpublishes and removes. A live-only slide - one
     * whose draft row someone deleted - has no draft record to archive, so it is loaded from live,
     * where doArchive() unpublishes it.
     *
     * @param array<int,Slide> $unused
     * @param PolyOutput $output
     * @return int
     */
    protected function archiveUnused(array $unused, PolyOutput $output): int
    {
        $archived = 0;
        $failed = 0;

        foreach ($unused as $slide) {
            $target = Versioned::get_by_stage(Slide::class, Versioned::DRAFT)->byID($slide->ID)
                ?? Versioned::get_by_stage(Slide::class, Versioned::LIVE)->byID($slide->ID);

            if (!$target) {
                // Went away between the scan and this pass; nothing to archive.
                continue;
            }

            try {
                $target->doArchive();
                $archived++;
            } catch (Throwable $e) {
                $failed++;
                $output->writeln(sprintf('  - FAILED to archive #%d: %s', $slide->ID, $e->getMessage()));
            }
        }

        $output->writeln(sprintf(
            'Archived %d unused slide%s.',
            $archived,
            $archived === 1 ? '' : 's'
        ));

        if ($failed > 0) {
            $output->writeln(sprintf('WARNING: %d slide%s could not be archived.', $failed, $failed === 1 ? '' : 's'));
        }

        $remaining = $this->countRemaining();

        if ($remaining > 0) {
            $output->writeln(sprintf(
                'WARNING: %d unused slide%s still on draft or live after archiving.',
                $remaining,
                $remaining === 1 ? ' is' : 's are'
            ));
        }

        return ($failed > 0 || $remaining > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Slides still unreferenced on either stage, counted read-only after an archive pass.
     *
     * @return int
     */
    protected function countRemaining(): int
    {
        $remaining = 0;

        $referenced = array_keys($this->referencedSlideIDs($this->relations()));

        foreach ([Versioned::DRAFT, Versioned::LIVE] as $stage) {
            $remaining += count(array_diff(
                Versioned::get_by_stage(Slide::class, $stage)->column('ID'),
                $referenced
            ));
        }

        return $remaining;
    }

    /**
     * Print the parts of this run's scan that failed and have not been printed yet.
     *
     * Nothing is forgotten here: $skipped keeps growing, because its emptiness is what
     * scanIsIncomplete() reads to decide whether an archive pass may write at all. Clearing it to
     * "reset" the warnings would silently re-enable archiving on a half-read reference set.
     *
     * One unreadable class in a site's class manifest must not stop the scan, but it must not pass
     * silently either: a skipped carousel owner means slides it holds look unused.
     */
    protected function flushSkipped(PolyOutput $output): void
    {
        foreach (array_slice($this->skipped, $this->reportedSkipped) as $skipped) {
            $output->writeln('WARNING: ' . $skipped);
        }

        $this->reportedSkipped = count($this->skipped);
    }

    /**
     * The name an editor knows this stage by. Versioned::DRAFT is spelled 'Stage' in the database
     * and in the API, which is not what the CMS calls it.
     */
    protected function stageLabel(string $stage): string
    {
        return $stage === Versioned::DRAFT ? 'Draft' : $stage;
    }

    /**
     * A slide's title, or a marker when it has none, so a titleless row is still identifiable.
     *
     * @param Slide $slide
     * @return string
     */
    protected function describe(Slide $slide): string
    {
        $title = trim((string) $slide->Title);

        return $title === '' ? '(no title)' : $title;
    }
}
