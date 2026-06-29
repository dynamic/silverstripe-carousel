<?php

namespace Dynamic\Carousel\Test\Stubs;

use SilverStripe\Core\Extension;

class TopTitleStubExtension extends Extension
{
    private static array $db = [
        'TopTitle' => 'Varchar(255)',
    ];
}
