<?php

namespace Dynamic\Carousel\Test\Stubs;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;

/**
 * Stands in for the `augmentSQL` extensions a real site has on its carousel owners. Subsites'
 * `SiteTreeSubsites::augmentSQL()` narrowing every SiteTree read to the current subsite is the case
 * that matters for UnusedSlideCleanupTask: run from `sake`, carousel rows belonging to other
 * subsites simply vanish from an ORM read of the owner table while staying in the stage table, and
 * an owner that is not read is an owner whose join rows get thrown away as stale.
 *
 * While a list of IDs is set, the rows are hidden from single-table ORM reads of the extended class
 * and from nothing else - the stage tables are untouched, which is exactly the difference between
 * reading through the ORM and reading the table.
 */
class HiddenOwnerReadStubExtension extends Extension implements TestOnly
{
    /**
     * IDs to hide from the ORM for the duration of a test. Empty means the filter is off, so the
     * extension is inert for every other test that reads the extended class.
     *
     * @var array<int,int>
     */
    public static array $hiddenIDs = [];

    public function augmentSQL(SQLSelect $query, ?DataQuery $dataQuery = null): void
    {
        if (self::$hiddenIDs === []) {
            return;
        }

        // Name the alias this query actually uses, which is the draft table for a draft read and
        // the _Live one for a live read (Versioned's own augmentSQL may or may not have run yet;
        // its renameTable rewrites text added before it either way). Only single-table reads are
        // touched, so a joined read can never be filtered by an ambiguous column.
        $from = array_keys($query->getFrom());

        if (count($from) !== 1) {
            return;
        }

        $query->addWhere(sprintf(
            '"%s"."ID" NOT IN (%s)',
            $from[0],
            implode(',', array_map('intval', self::$hiddenIDs))
        ));
    }
}
