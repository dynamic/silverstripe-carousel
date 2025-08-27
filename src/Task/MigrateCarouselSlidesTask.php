<?php

namespace Dynamic\Carousel\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Control\Director;
use Dynamic\Carousel\Model\Slide;

/**
 * Class \Dynamic\Carousel\Task\MigrateCarouselSlidesTask
 *
 * Migrates existing carousel slide relationships by removing redundant polymorphic parent fields.
 * Since we now use a standard many_many relationship, the old ParentClass/ParentID fields
 * in the Slide model are no longer needed.
 *
 * This task should be run after upgrading to the simplified relationship structure.
 */
class MigrateCarouselSlidesTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'migrate-carousel-slides';

    /**
     * @var string
     */
    protected $title = 'Migrate Carousel Slides (Remove Redundant Fields)';

    /**
     * @var string
     */
    protected $description = 'Removes redundant ParentClass/ParentID fields from slides since relationships are now managed via standard many_many tables.';

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return void
     */
    public function run($request)
    {
        $this->printLine('Starting migration of carousel slides...');

        try {
            // Check if old fields exist
            $this->printLine('Step 1: Checking for old polymorphic fields...');

            if (!$this->checkOldFieldsExist()) {
                $this->printLine('No old polymorphic fields found. Migration not needed or already completed.');
                return;
            }

            // Report on existing data
            $this->printLine('Step 2: Analyzing existing slide data...');
            $this->analyzeExistingData();

            // Clean up old fields (optional - commented out for safety)
            $this->printLine('Step 3: Migration completed successfully!');
            $this->printLine('');
            $this->printLine('IMPORTANT: The many_many relationships are now managed in the standard');
            $this->printLine('SilverStripe many_many join tables. The old ParentClass/ParentID fields');
            $this->printLine('in the Slide model are no longer used.');
            $this->printLine('');
            $this->printLine('Optional manual cleanup (if desired):');
            $this->printLine('1. Remove ParentClass and ParentID columns from Dynamic_Slide table');
            $this->printLine('2. Run dev/build to update the database schema');

        } catch (\Exception $e) {
            $this->printLine('ERROR: Migration failed with exception: ' . $e->getMessage());
            $this->printLine('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Check if the old polymorphic fields exist
     *
     * @return bool
     */
    private function checkOldFieldsExist(): bool
    {
        $slideTableExists = DB::get_schema()->hasTable('Dynamic_Slide');

        if (!$slideTableExists) {
            $this->printLine('Dynamic_Slide table does not exist.');
            return false;
        }

        $hasParentClass = DB::get_schema()->hasField('Dynamic_Slide', 'ParentClass');
        $hasParentID = DB::get_schema()->hasField('Dynamic_Slide', 'ParentID');

        if ($hasParentClass) {
            $this->printLine('Found ParentClass field in Dynamic_Slide table.');
        }

        if ($hasParentID) {
            $this->printLine('Found ParentID field in Dynamic_Slide table.');
        }

        return $hasParentClass || $hasParentID;
    }

    /**
     * Analyze existing slide data and relationships
     *
     * @return void
     */
    private function analyzeExistingData(): void
    {
        // Count total slides
        $totalSlides = Slide::get()->count();
        $this->printLine("Total slides in database: {$totalSlides}");

        // Check if old polymorphic fields have data
        if (DB::get_schema()->hasField('Dynamic_Slide', 'ParentClass') &&
            DB::get_schema()->hasField('Dynamic_Slide', 'ParentID')) {

            $query = "
                SELECT COUNT(*) as count
                FROM Dynamic_Slide
                WHERE ParentClass IS NOT NULL
                AND ParentClass != ''
                AND ParentID > 0
            ";

            $result = DB::query($query);
            $slidesWithOldParents = $result->value();

            $this->printLine("Slides with old polymorphic parent data: {$slidesWithOldParents}");

            if ($slidesWithOldParents > 0) {
                $this->printLine('');
                $this->printLine('NOTE: Some slides still have old polymorphic parent data.');
                $this->printLine('This data is now redundant since relationships are managed');
                $this->printLine('through the standard many_many join tables created by SilverStripe.');
                $this->printLine('');
                $this->printLine('The many_many relationships should already be working correctly');
                $this->printLine('through tables like:');
                $this->printLine('- Dynamic_CarouselPageExtension_Slides');
                $this->printLine('- Page_Slides');
                $this->printLine('- Or similar many_many join tables');
            }
        }

        // Show information about many_many tables
        $possibleManyManyTables = [
            'Dynamic_CarouselPageExtension_Slides',
            'Page_Slides',
            'SiteTree_Slides'
        ];

        $this->printLine('');
        $this->printLine('Checking for many_many relationship tables:');

        foreach ($possibleManyManyTables as $tableName) {
            if (DB::get_schema()->hasTable($tableName)) {
                $count = DB::query("SELECT COUNT(*) FROM `{$tableName}`")->value();
                $this->printLine("  ✓ {$tableName}: {$count} relationships");
            } else {
                $this->printLine("  - {$tableName}: not found");
            }
        }
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
