<?php

namespace Dynamic\Carousel\Model;

use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\LinkField\ORM\DBLink;
use SilverStripe\LinkField\Form\LinkField;

/**
 * Class \Dynamic\Carousel\Model\ImageSlide
 *
 * @property string $DbLink
 * @property int $ImageID
 * @method Image Image()
 */
class ImageSlide extends Slide
{
    /**
     * @var string
     */
    private static $table_name = 'Dynamic_ImageSlide';

    /**
     * @var string
     */
    private static $singular_name = 'Image Slide';

    /**
     * @var string
     */
    private static $plural_name = 'Image Slides';

    private static $db = [
        'DbLink' => DBLink::class
    ];

    /**
     * @var string[]
     */
    private static $has_one = [
        'Image' => Image::class,
    ];

    /**
     * @var string[]
     */
    private static $owns = [
        'Image',
    ];

    private static $hide_ancestor = Slide::class;

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    $fields->dataFieldByName('Image')
                        ->setFolderName('Uploads/Carousel/Slides'),
                    LinkField::create('DbLink')
                        ->setTitle('Link'),
                ],
                'Content'
            );
        });

        return parent::getCMSFields();
        ;
    }
}
