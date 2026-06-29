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
 * @method \SilverStripe\ORM\ManyManyThroughList Parents()
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
     * @param bool $includerelations
     * @return array
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);

        $labels['Title'] = _t(__CLASS__ . '.TitleLabel', 'Title');
        $labels['Image'] = _t(__CLASS__ . '.ImageLabel', 'Image');
        $labels['Content'] = _t(__CLASS__ . '.ContentLabel', 'Description');

        return $labels;
    }

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
                        ->setTitle($this->fieldLabel('Title'))
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
     * Returns true when the slide has any overlay content to display.
     * Gates the carousel-caption overlay block in templates (TopTitle, Title, Content, Link).
     *
     * Note: "TopTitle" is added via CustomStylesExtension (wired by the consuming recipe/app
     * YAML). We probe it defensively with hasField() so the carousel module itself stays
     * free of a hard dependency on essentials-tools.
     */
    public function ShowContent(): bool
    {
        $owner = $this->getOwner();
        $hasTopTitle = $owner->hasField('TopTitle') && (bool) $owner->TopTitle;

        return $hasTopTitle || ($owner->Title && $owner->ShowTitle) || (bool) $owner->Content;
    }

    /**
     * @deprecated Use ShowContent() instead. Kept for any unknown third-party consumers.
     */
    public function ShowCaption(): bool
    {
        return $this->ShowContent();
    }

    /**
     * Basic permissions - simplified since slides no longer have direct parent references
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

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Basic permissions - simplified since slides no longer have direct parent references
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

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Basic permissions - simplified since slides no longer have direct parent references
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
