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
     * @config
     */
    private static $table_name = 'Dynamic_ImageSlide';

    /**
     * @var string
     * @config
     */
    private static $singular_name = 'Image Slide';

    /**
     * @var string
     * @config
     */
    private static $plural_name = 'Image Slides';

    /**
     * @var array
     * @config
     */
    private static $db = [
        'DbLink' => DBLink::class
    ];

    /**
     * @var array
     * @config
     */
    private static $has_one = [
        'Image' => Image::class,
    ];

    /**
     * @var array
     * @config
     */
    private static $owns = [
        'Image',
    ];

    /**
     * @var string
     * @config
     */
    private static $hide_ancestor = Slide::class;

    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    // @phpstan-ignore-next-line
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
