<?php

namespace Dynamic\Carousel\Extension;

use SilverStripe\View\SSViewer;
use Dynamic\Carousel\Model\Slide;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;

/**
 * Class \Dynamic\Carousel\Extension\CarouselPageExtension
 *
 * @property BlockPage|CampaignLandingPage|HomePage|CarouselPageExtension $owner
 * @method ManyManyList|Slide[] Slides()
 */
class CarouselPageExtension extends DataExtension
{
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

        $fields->addFieldToTab('Root.Hero', $grid);
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
