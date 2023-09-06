<?php

namespace Dynamic\Carousel\Model;

use SilverStripe\Assets\Image;

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
}
