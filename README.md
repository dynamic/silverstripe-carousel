# Silverstripe Carousel

A simple carousel for Silverstripe websites. The default template uses Bootstrap classes to power the carousel. If you are not using Bootstrap, you can use a custom template and include your own javascript.

![CI](https://github.com/dynamic/silverstripe-carousel/workflows/CI/badge.svg)
[![codecov](https://codecov.io/gh/dynamic/silverstripe-carousel/branch/master/graph/badge.svg)](https://codecov.io/gh/dynamic/silverstripe-carousel)

[![Latest Stable Version](https://poser.pugx.org/dynamic/silverstripe-carousel/v/stable)](https://packagist.org/packages/dynamic/silverstripe-carousel)
[![Total Downloads](https://poser.pugx.org/dynamic/silverstripe-carousel/downloads)](https://packagist.org/packages/dynamic/silverstripe-carousel)
[![Latest Unstable Version](https://poser.pugx.org/dynamic/silverstripe-carousel/v/unstable)](https://packagist.org/packages/dynamic/silverstripe-carousel)
[![License](https://poser.pugx.org/dynamic/silverstripe-carousel/license)](https://packagist.org/packages/dynamic/silverstripe-carousel)

## Requirements

- Silverstripe CMS ^5

## Installation

`composer require dynamic/silverstripe-carousel`

## License

See [License](LICENSE.md)

## Usage

To add a carousel to a page, apply `CarouselPageExtension` to a page type:

```yaml
Page:
  extensions:
    - Dynamic\Carousel\Extension\CarouselPageExtension
```

In your template, include the `Carousel` template:

```html
<% include Dynamic/Carousel/Carousel %> 
```

### Template Notes

The default template assumes you are using [Bootstrap 5](https://getbootstrap.com/), and requires no additional javascript. If you are not using Bootstrap, you can use a custom template and include your own javascript.

Note - if you are not including all of Bootstrap, you will need to manually include the carousel component.


## Maintainers
 *  [Dynamic](http://www.dynamicagency.com) (<dev@dynamicagency.com>)

## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over
existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version,
 Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.

## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
