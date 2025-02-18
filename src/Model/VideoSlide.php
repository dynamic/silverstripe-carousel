<?php

namespace Dynamic\Carousel\Model;

use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use nathancox\EmbedField\Forms\EmbedField;
use nathancox\EmbedField\Model\EmbedObject;
use UncleCheese\DisplayLogic\Forms\Wrapper;
use SilverStripe\AssetAdmin\Forms\UploadField;

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
    ];

    /**
     * @var string[]
     */
    private static $has_one = [
        'Video' => File::class,
        'EmbedVideo' => EmbedObject::class,
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
            // Remvove fields
            $fields->removeByName([
                'EmbedVideoID',
                'Video',
            ]);

            $fields->insertBefore(
                'Content',
                $fields->dataFieldByName('VideoType')
            );

            // Native video
            $uploadVideo = UploadField::create('Video', 'Upload video')
                ->setFolderName('Uploads/Carousel/Videos');

            $fields->insertAfter(
                'VideoType',
                $uploadVideo
            );

            // Embed video
            // @phpstan-ignore class.notFound
            $embedVideo = Wrapper::create(EmbedField::create('EmbedVideoID', 'Embed video'));

            $fields->insertAfter(
                'VideoType',
                $embedVideo
            );

            //
            $uploadVideo->displayIf("VideoType")->isEqualTo("Native");
            $embedVideo->displayIf("VideoType")->isEqualTo("Embed");
        });

        return parent::getCMSFields();
    }
}
