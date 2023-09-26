<?php

namespace Dynamic\Carousel\Extension;

use SilverStripe\View\SSViewer;
use Dynamic\Carousel\Model\Slide;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Class \Dynamic\Carousel\Extension\CarouselPageExtension
 *
 * @property CarouselPageExtension $owner
 * @property string $Controls
 * @property string $Indicators
 * @property string $Transitions
 * @property string $Autoplay
 * @property int $Interval
 * @method ManyManyList|Slide[] Slides()
 */
class CarouselPageExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = [
        'Controls' => 'Enum("Off,On", "On")',
        'Indicators' => 'Enum("Off,On", "On")',
        'Transitions' => 'Enum("Slide,Fade", "Slide")',
        'Autoplay' => 'Enum("Off,Autoplay after interaction,On","Off")',
        'Interval' => 'Int'
    ];

    /**
     * @var array
     */
    private static $many_many = [
        'Slides' => 'Dynamic\\Carousel\\Model\\Slide',
    ];

    /**
     * @var array
     */
    private static $many_many_extraFields = [
        'Slides' => [
            'SortOrder' => 'Int',
        ],
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'Interval' => 5
    ];

    /**
     * @param \SilverStripe\Forms\FieldList $fields
     * @return void
     */
    public function updateCMSFields(\SilverStripe\Forms\FieldList $fields)
    {
        $grid = GridField::create(
            'Slides',
            'Slides',
            $this->owner->Slides(),
            GridFieldConfig_RelationEditor::create()
        );
        $grid->getConfig()
            ->removeComponentsByType([
                GridFieldAddNewButton::class,
                GridFieldAddExistingAutocompleter::class,
                GridFieldFilterHeader::class,
            ])
            ->addComponents([
                $multiClass = GridFieldAddNewMultiClass::create(),
                new GridFieldOrderableRows('SortOrder'),
                new GridFieldAddExistingSearchButton(),
            ]);

        $fields->addFieldsToTab('Root.Carousel', [
            $grid,
        ]);
    }

    /**
     * @return \SilverStripe\Forms\FieldList
     */
    public function updateSettingsFields(&$fields)
    {
        $fields->addFieldsToTab(
            'Root.Settings',
            [
                CompositeField::create(
                    $this->getCarouselSettings(),
                )
                    ->setTitle('Carousel Settings')
                    ->setName('CarouselSettings'),
            ]
        );
    }

    public function getCarouselSettings()
    {
        return FieldList::create(
            DropdownField::create('Controls', 'Show Controls', $this->owner->dbObject('Controls')->enumValues())
                ->setDescription('Previous/next arrows. Hidden if only one slide'),
            DropdownField::create('Indicators', 'Show Indicators', $this->owner->dbObject('Indicators')->enumValues())
                ->setDescription(' Let users jump directly to a particular slide. Hidden if only one slide'),
            DropdownField::create('Transitions', 'Transitions', $this->owner->dbObject('Transitions')->enumValues()),
            DropdownField::create('Autoplay', 'Autoplay', $this->owner->dbObject('Autoplay')->enumValues()),
            NumericField::create('Interval')
                ->setDescription('Time in seconds'),
        );
    }

    /**
     * @return string
     */
    public function onBeforeWrite()
    {
        if (!$this->owner->Interval || $this->owner->Interval < 0) {
            $this->owner->Interval = self::$defaults['Interval'];
        }
        parent::onBeforeWrite();
    }

    /**
     * @return string
     */
    public function IntervalInMilliseconds(): int
    {
        $interval = $this->owner->Interval;
        if (!$this->owner->Interval || $this->owner->Interval < 0) {
            $interval = self::$defaults['Inverval'];
        }
        return (int) $interval * 1000;
    }

    /**
     * Disable rewrite hash links for this page type
     *
     * @return void
     */
    public function contentcontrollerInit()
    {
        SSViewer::setRewriteHashLinksDefault(false);
    }
}
