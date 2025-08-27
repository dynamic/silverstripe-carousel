<?php

namespace Dynamic\Carousel\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Class \Dynamic\Carousel\Model\CarouselSlideJoin
 *
 * @property int $SortOrder
 * @property int $ParentID
 * @property string $ParentClass
 * @property int $SlideID
 * @method DataObject Parent()
 * @method Slide Slide()
 * @mixin Versioned
 */
class CarouselSlideJoin extends DataObject
{
    /**
     * @var string
     * @config
     */
    private static $table_name = 'Dynamic_CarouselSlideJoin';

    /**
     * @var string
     * @config
     */
    private static $singular_name = 'Carousel Slide Join';

    /**
     * @var string
     * @config
     */
    private static $plural_name = 'Carousel Slide Joins';

    /**
     * @var array
     * @config
     */
    private static $db = [
        'SortOrder' => 'Int',
    ];

    /**
     * @var array
     * @config
     */
    private static $has_one = [
        'Parent' => DataObject::class,  // Polymorphic parent (Pages, DataObjects with CarouselPageExtension)
        'Slide' => Slide::class,
    ];

    /**
     * @var array
     * @config
     */
    private static $extensions = [
        Versioned::class,
    ];

    /**
     * @var string
     * @config
     */
    private static $default_sort = 'SortOrder ASC';

    /**
     * @var array
     * @config
     */
    private static $summary_fields = [
        'Parent.Title' => 'Parent',
        'Slide.Title' => 'Slide',
        'SortOrder' => 'Sort Order',
    ];

    /**
     * @var array
     * @config
     */
    private static $searchable_fields = [
        'Parent.Title',
        'Slide.Title',
        'SortOrder',
    ];

    /**
     * @return string
     */
    public function getTitle()
    {
        $parent = $this->Parent();
        $slide = $this->Slide();

        $parentTitle = $parent && $parent->exists() ? $parent->Title : 'Unknown Parent';
        $slideTitle = $slide && $slide->exists() ? $slide->Title : 'Unknown Slide';

        return sprintf('%s - %s', $parentTitle, $slideTitle);
    }

    /**
     * Basic permissions, defaults to parent perms where possible.
     *
     * @param \SilverStripe\Security\Member $member
     * @return boolean
     */
    public function canView($member = null): ?bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        if ($this->Parent()) {
            return $this->Parent()->canView($member);
        }

        return parent::canView($member);
    }

    /**
     * Basic permissions, defaults to parent perms where possible.
     *
     * @param \SilverStripe\Security\Member $member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        if ($this->Parent()) {
            return $this->Parent()->canEdit($member);
        }

        return parent::canEdit($member);
    }

    /**
     * Basic permissions, defaults to parent perms where possible.
     *
     * @param \SilverStripe\Security\Member $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        if ($this->Parent()) {
            return $this->Parent()->canDelete($member);
        }

        return parent::canDelete($member);
    }

    /**
     * Basic permissions, defaults to parent perms where possible.
     *
     * @param \SilverStripe\Security\Member $member
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member, $context);
        if ($extended !== null) {
            return $extended;
        }

        return parent::canCreate($member, $context);
    }
}
