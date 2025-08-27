<?php

namespace Dynamic\Carousel\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Director;
use Dynamic\Carousel\Model\Slide;
use Dynamic\Carousel\Model\CarouselSlideJoin;

/**
 * Class \Dynamic\Carousel\Task\MigrateCarouselSlidesToManyManyThroughTask
 *
 * Migrates existing carousel slide relationships from the old many_many structure
 * with polymorphic parent fields to the new many_many through structure.
 *
 * This task should be run after upgrading to the new many_many through relationship structure.
 */
class MigrateCarouselSlidesToManyManyThroughTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'migrate-carousel-slides-to-many-many-through';

    /**
     * @var string
     */
    protected $title = 'Migrate Carousel Slides to Many-Many Through';

    /**
     * @var string
     */
    protected $description = 'Migrates existing carousel slide relationships from the old many_many structure with polymorphic parent fields to the new many_many through structure.';

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return void
     */
    public function run($request)
    {
        $this->printLine('Starting migration of carousel slides to many-many through structure...');

        // Check if we're running in CLI mode for better output
        $isCLI = Director::is_cli();

        try {
            // Step 1: Check if old tables/fields exist
            $this->printLine('Step 1: Checking for existing data structure...');

            if (!$this->checkOldStructureExists()) {
                $this->printLine('No old structure found or migration already completed. Nothing to migrate.');
                return;
            }

            // Step 2: Migrate existing many_many relationships
            $this->printLine('Step 2: Migrating existing many_many relationships...');
            $migratedFromManyMany = $this->migrateManyManyRelationships();

            // Step 3: Migrate slides with polymorphic parent fields
            $this->printLine('Step 3: Migrating slides with polymorphic parent fields...');
            $migratedFromPolymorphic = $this->migratePolymorphicParentFields();

            // Step 4: Clean up old data (optional - commented out for safety)
            $this->printLine('Step 4: Migration completed successfully!');
            $this->printLine("Migrated {$migratedFromManyMany} relationships from many_many table");
            $this->printLine("Migrated {$migratedFromPolymorphic} relationships from polymorphic parent fields");

            $totalMigrated = $migratedFromManyMany + $migratedFromPolymorphic;
            $this->printLine("Total relationships migrated: {$totalMigrated}");

            if ($totalMigrated > 0) {
                $this->printLine('');
                $this->printLine('IMPORTANT: Please run dev/build after this migration to update the database schema.');
                $this->printLine('');
                $this->printLine('Optional cleanup (run manually if desired):');
                $this->printLine('- Remove ParentClass and ParentID columns from Dynamic_Slide table');
                $this->printLine('- Drop old many_many join tables if they exist');
            }

        } catch (\Exception $e) {
            $this->printLine('ERROR: Migration failed with exception: ' . $e->getMessage());
            $this->printLine('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Check if the old structure exists
     *
     * @return bool
     */
    private function checkOldStructureExists(): bool
    {
        $slideTableExists = DB::get_schema()->hasTable('Dynamic_Slide');

        if (!$slideTableExists) {
            return false;
        }

        // Check if old fields exist
        $hasParentClass = DB::get_schema()->hasField('Dynamic_Slide', 'ParentClass');
        $hasParentID = DB::get_schema()->hasField('Dynamic_Slide', 'ParentID');

        // Check for old many_many tables (these might have various names based on the extension usage)
        $possibleManyManyTables = [
            'Dynamic_CarouselPageExtension_Slides',
            'Page_Slides',
            'SiteTree_Slides'
        ];

        $hasManyManyTable = false;
        foreach ($possibleManyManyTables as $tableName) {
            if (DB::get_schema()->hasTable($tableName)) {
                $hasManyManyTable = true;
                $this->printLine("Found old many_many table: {$tableName}");
                break;
            }
        }

        return $hasParentClass || $hasParentID || $hasManyManyTable;
    }

    /**
     * Migrate existing many_many relationships to the new through structure
     *
     * @return int Number of relationships migrated
     */
    private function migrateManyManyRelationships(): int
    {
        $migratedCount = 0;

        // Find possible many_many tables
        $possibleManyManyTables = [
            'Dynamic_CarouselPageExtension_Slides',
            'Page_Slides',
            'SiteTree_Slides'
        ];

        foreach ($possibleManyManyTables as $tableName) {
            if (DB::get_schema()->hasTable($tableName)) {
                $this->printLine("Migrating from table: {$tableName}");

                $query = "SELECT * FROM `{$tableName}`";
                $results = DB::query($query);

                foreach ($results as $row) {
                    // Determine the parent class and ID based on the table structure
                    $parentID = null;
                    $parentClass = null;
                    $slideID = null;
                    $sortOrder = 0;

                    // Handle different possible column names
                    if (isset($row['PageID'])) {
                        $parentID = $row['PageID'];
                        $parentClass = 'Page'; // Default, might need adjustment
                    } elseif (isset($row['SiteTreeID'])) {
                        $parentID = $row['SiteTreeID'];
                        $parentClass = 'SilverStripe\\CMS\\Model\\SiteTree';
                    } elseif (isset($row['Dynamic_CarouselPageExtensionID'])) {
                        $parentID = $row['Dynamic_CarouselPageExtensionID'];
                        $parentClass = 'Page'; // This will need to be determined dynamically
                    }

                    if (isset($row['Dynamic_SlideID'])) {
                        $slideID = $row['Dynamic_SlideID'];
                    } elseif (isset($row['SlideID'])) {
                        $slideID = $row['SlideID'];
                    }

                    if (isset($row['SortOrder'])) {
                        $sortOrder = $row['SortOrder'];
                    }

                    if ($parentID && $slideID) {
                        // Determine the actual parent class by querying the object
                        if ($parentClass === 'Page') {
                            $parentObject = DataObject::get_by_id('SilverStripe\\CMS\\Model\\SiteTree', $parentID);
                            if ($parentObject) {
                                $parentClass = get_class($parentObject);
                            }
                        }

                        // Check if this relationship already exists in the new structure
                        $existingJoin = CarouselSlideJoin::get()->filter([
                            'ParentID' => $parentID,
                            'ParentClass' => $parentClass,
                            'SlideID' => $slideID
                        ])->first();

                        if (!$existingJoin) {
                            $join = CarouselSlideJoin::create();
                            $join->ParentID = $parentID;
                            $join->ParentClass = $parentClass;
                            $join->SlideID = $slideID;
                            $join->SortOrder = $sortOrder;
                            $join->write();

                            $migratedCount++;
                            $this->printLine("  Migrated: Parent {$parentClass}#{$parentID} -> Slide#{$slideID}");
                        }
                    }
                }
            }
        }

        return $migratedCount;
    }

    /**
     * Migrate slides with polymorphic parent fields to the new through structure
     *
     * @return int Number of relationships migrated
     */
    private function migratePolymorphicParentFields(): int
    {
        $migratedCount = 0;

        // Check if the old polymorphic fields still exist
        if (!DB::get_schema()->hasField('Dynamic_Slide', 'ParentClass') ||
            !DB::get_schema()->hasField('Dynamic_Slide', 'ParentID')) {
            $this->printLine('No polymorphic parent fields found in slides.');
            return 0;
        }

        // Query slides that have parent information
        $query = "
            SELECT `ID`, `ParentClass`, `ParentID`
            FROM `Dynamic_Slide`
            WHERE `ParentClass` IS NOT NULL
            AND `ParentClass` != ''
            AND `ParentID` > 0
        ";

        $results = DB::query($query);

        foreach ($results as $row) {
            $slideID = $row['ID'];
            $parentClass = $row['ParentClass'];
            $parentID = $row['ParentID'];

            // Check if this relationship already exists in the new structure
            $existingJoin = CarouselSlideJoin::get()->filter([
                'ParentID' => $parentID,
                'ParentClass' => $parentClass,
                'SlideID' => $slideID
            ])->first();

            if (!$existingJoin) {
                $join = CarouselSlideJoin::create();
                $join->ParentID = $parentID;
                $join->ParentClass = $parentClass;
                $join->SlideID = $slideID;
                $join->SortOrder = 0; // Default sort order, can be adjusted manually later
                $join->write();

                $migratedCount++;
                $this->printLine("  Migrated: Parent {$parentClass}#{$parentID} -> Slide#{$slideID}");
            }
        }

        return $migratedCount;
    }

    /**
     * Print a line with appropriate formatting for CLI vs web
     *
     * @param string $message
     */
    private function printLine($message)
    {
        if (Director::is_cli()) {
            echo $message . PHP_EOL;
        } else {
            echo $message . "<br>" . PHP_EOL;
        }
    }
}
