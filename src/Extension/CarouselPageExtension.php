<?php

namespace Dynamic\Carousel\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\View\SSViewer;
use Dynamic\Carousel\Model\Slide;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\GridField\GridField;
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
class CarouselPageExtension extends Extension
{
    /**
     * @var array
     * @config
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
     * @config
     */
    private static $many_many = [
        'Slides' => Slide::class,
    ];

    /**
     * @var array
     * @config
     */
    private static $many_many_extraFields = [
        'Slides' => [
            'SortOrder' => 'Int',
        ],
    ];

    /**
     * @var array
     * @config
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
        if ($this->getOwner()->exists()) {
            $fields->removeByName('Slides');

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
    }

    /**
     * @param \SilverStripe\Forms\FieldList $fields
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

    /**
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCarouselSettings()
    {
        return FieldList::create(
            DropdownField::create('Controls', 'Show Controls', $this->getOwner()->dbObject('Controls')->enumValues())
                ->setDescription('Previous/next arrows. Hidden if only one slide'),
            DropdownField::create('Indicators', $this->getOwner()->dbObject('Indicators')->enumValues())
                ->setTitle('Show Indicators')
                ->setDescription(' Let users jump directly to a particular slide. Hidden if only one slide'),
            DropdownField::create('Transitions', $this->getOwner()->dbObject('Transitions')->enumValues())
                ->setTitle('Transitions'),
            DropdownField::create('Autoplay', 'Autoplay', $this->getOwner()->dbObject('Autoplay')->enumValues()),
            NumericField::create('Interval')
                ->setDescription('Time in seconds'),
        );
    }

    /**
     * @return void
     */
    public function onBeforeWrite()
    {
        if (!$this->getOwner()->Interval || $this->getOwner()->Interval < 0) {
            $this->getOwner()->Interval = self::$defaults['Interval'];
        }
        parent::onBeforeWrite();
    }

    /**
     * @return int
     */
    public function IntervalInMilliseconds(): int
    {
        $interval = $this->getOwner()->Interval;
        if (!$this->getOwner()->Interval || $this->getOwner()->Interval < 0) {
            $interval = self::$defaults['Interval'];
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
