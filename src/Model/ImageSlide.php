<?php

namespace Dynamic\Carousel\Model;

use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\LinkField\Form\LinkField;
use SilverStripe\LinkField\Models\Link;

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
    private static $has_one = [
        'Image' => Image::class,
        'ElementLink' => Link::class,
    ];

    /**
     * @var array
     * @config
     */
    private static $owns = [
        'Image',
        'ElementLink',
    ];

    /**
     * @var string
     * @config
     */
    private static $hide_ancestor = Slide::class;

    /**
     * @param bool $includerelations
     * @return array
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);

        $labels['ElementLink'] = _t(__CLASS__ . '.ElementLinkLabel', 'Link');

        return $labels;
    }

    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->replaceField(
                'ElementLinkID',
                $link = LinkField::create('ElementLink')
                    ->setTitle($this->fieldLabel('ElementLink'))
            );

            $fields->addFieldsToTab(
                'Root.Main',
                [
                    // @phpstan-ignore-next-line
                    $fields->dataFieldByName('Image')
                        ->setFolderName('Uploads/Carousel/Slides'),
                    $link
                ],
                'Content'
            );
        });

        return parent::getCMSFields();
        ;
    }
}
