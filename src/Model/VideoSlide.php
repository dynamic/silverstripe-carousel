<?php

namespace Dynamic\Carousel\Model;

use Embed\Embed;
use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Class \Dynamic\Carousel\Model\VideoSlide
 *
 * @property string $VideoType
 * @property string $VideoEmbed
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
        'SourceURL' => 'Varchar(255)',
        'iFrameCSS' => 'Varchar',
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
     * @var array|string[]
     */
    private static array $allowed_embed_types = [
        'video',
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->dataFieldByName('Video')
                ->displayIf('VideoType')->isEqualTo('Native');

            $fields->dataFieldByName('SourceURL')
                ->displayIf('VideoType')->isEqualTo('Embed');

            $fields->dataFieldByName('iFrameCSS')
                ->displayIf('VideoType')->isEqualTo('Embed')
                ->setDescription('Add CSS classes to the iFrame');
        });


        return parent::getCMSFields();
    }

    public function getVideoEmbed()
    {
        if ($this->SourceURL) {
            $embed = new Embed();
            $info = $embed->get($this->SourceURL);

            $source = $this->parseEmbedSource($info->code->html);
            $parameters = $this->getEmbedParameters($info->providerName);

            $components = parse_url($source);
            parse_str($components['query'], $queryParams);
            $queryParams = array_merge($queryParams, $parameters);

            $components['query'] = http_build_query($queryParams);
            $newUrl = $components['scheme'] . '://' . $components['host'] . $components['path'] . '?' . $components['query'];

            $newIframe = str_replace($source, htmlspecialchars($newUrl), $info->code->html);
            $newIframe = preg_replace('/(width|height)="\d*"\s/', '', $newIframe);

            if ($this->iFrameCSS) {
                $newIframe = str_replace('<iframe ', '<iframe class="' . $this->iFrameCSS . '" ', $newIframe);
            }


            return DBField::create_field('HTMLText', $newIframe);
        }

        return null;
    }

    /**
     * @param $provider
     * @return string[]
     */
    protected function getEmbedParameters($provider): array
    {
        switch ($provider) {
            case 'Vimeo':
                $parameters = [
                    'autoplay' => '1',
                    'background' => '1', // Vimeo uses 'background' parameter for background videos
                    'loop' => '1',
                    'muted' => '1', // 'muted' for Vimeo instead of 'mute'
                    'playsinline' => '1',
                    'title' => '0',
                    'byline' => '0',
                    'portrait' => '0',
                ];
                break;
            case 'YouTube':
                $parameters = [
                    'autoplay' => '1',
                    'mute' => '1', // Necessary for autoplay in most browsers
                    'loop' => '1',
                    'playsinline' => '1',
                    'controls' => '0',
                    'frameborder' => '0',
                ];
                break;
            default:
                $parameters = [
                    'autoplay' => '1',
                    'mute' => '1', // Necessary for autoplay in most browsers
                ];
                break;
        }

        return $parameters;
    }

    /**
     * @param $code
     * @return string
     */
    protected function parseEmbedSource($code): string
    {
        preg_match('/src="([^"]+)"/', $code, $source);

        return html_entity_decode($source[1]);
    }
}
