<?php

namespace Dynamic\Carousel\Model;

use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;

/**
 * Class \Dynamic\Carousel\Model\VideoSlide
 *
 * @property string $VideoType
 * @property int $VideoID
 * @method File Video()
 */
class VideoSlide extends Slide
{
    /**
     * @var string
     */
    private static $table_name = 'Dynamic_VideoSlide';

    /**
     * @var string
     */
    private static $singular_name = 'Video Slide';

    /**
     * @var string
     */
    private static $plural_name = 'Video Slides';

    /**
     * @var string[]
     */
    private static $db = [
        'VideoType' => 'Enum(["Embed","Native"], "Embed")',
        'VideoURL' => 'Varchar(255)',
    ];

    /**
     * @var string[]
     */
    private static $has_one = [
        'Video' => File::class,
    ];

    /**
     * @var string[]
     */
    private static $owns = [
        'Video',
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $native = $fields->dataFieldByName('Video');
            $embed = $fields->dataFieldByName('VideoURL');

            $native->displayIf('VideoType')->isEqualTo('Native')->end();
            $embed->displayIf('VideoType')->isEqualTo('Embed')->end();
        });

        return parent::getCMSFields();
    }
}
