<?php

namespace Dynamic\Carousel\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use DNADesign\Elemental\Forms\TextCheckboxGroupField;

/**
 * Class \Dynamic\Carousel\Model\Slide
 *
 * @property int $Version
 * @property string $Title
 * @property bool $ShowTitle
 * @property string $Content
 * @property string $ParentClass
 * @property int $ParentID
 * @method DataObject Parent()
 * @mixin Versioned
 */
class Slide extends DataObject
{
    /**
     * @var string
     * @config
     */
    private static $table_name = 'Dynamic_Slide';

    /**
     * @var string
     * @config
     */
    private static $singular_name = 'Slide';

    /**
     * @var string
     * @config
     */
    private static $plural_name = 'Slides';

    /**
     * @var array
     * @config
     */
    private static $db = [
        'Title' => 'Varchar',
        'ShowTitle' => 'Boolean',
        'Content' => 'HTMLText',
        'ParentClass' => 'Varchar',
    ];

    /**
     * @var array
     * @config
     */
    private static $has_one = [
        'Parent' => DataObject::class,
    ];

    /**
     * @var array
     * @config
     */
    private static $extensions = [
        Versioned::class,
    ];

    /**
     * @var array
     * @config
     */
    private static $summary_fields = [
        'Title' => 'Slide Title',
    ];

    /**
     * @var array
     * @config
     */
    private static $searchable_fields = [
        'ID' => [
            'field' => NumericField::class,
        ],
        'Title',
        'LastEdited',
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName([
                'ShowTitle',
                'ParentClass',
            ]);

            if (class_exists(TextCheckboxGroupField::class)) {
                $fields->replaceField(
                    'Title',
                    TextCheckboxGroupField::create('Title')
                );
            } else {
                $fields->insertAfter(
                    'Title',
                    CheckboxField::create('ShowTitle')
                );
            }
        });

        return parent::getCMSFields();
    }

    /**
     * Basic permissions, defaults to parent perms where possible.
     *
     * @param Member $member
     * @return boolean
     */
    public function canView($member = null): ?bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        if ($this->Parent()) {
            $parent = $this->Parent();
            return $parent->canView($member);
        }

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Basic permissions, defaults to parent perms where possible.
     *
     * @param Member $member
     *
     * @return boolean
     */
    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        if ($this->Parent()) {
            $parent = $this->Parent();
            return $parent->canEdit($member);
        }

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Basic permissions, defaults to page perms where possible.
     *
     * Uses archive not delete so that current stage is respected i.e if a
     * slide is not published, then it can be deleted by someone who doesn't
     * have publishing permissions.
     *
     * @param Member $member
     *
     * @return boolean
     */
    public function canDelete($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        if ($this->Parent()->exists()) {
            $parent = $this->Parent();
            if ($parent->hasExtension(Versioned::class)) {
                // @phpstan-ignore-next-line
                return $parent->canArchive($member);
            } else {
                return $parent->canDelete($member);
            }
        }

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Basic permissions, defaults to cms access perms where possible.
     *
     * @param Member $member
     * @param array $context
     *
     * @return boolean
     */
    public function canCreate($member = null, $context = array())
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }
}
