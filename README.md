# Silverstripe Carousel

A versatile carousel module for Silverstripe websites, featuring support for images and videos. The default template utilizes Bootstrap classes for seamless integration.

[![CI](https://github.com/dynamic/silverstripe-carousel/workflows/CI/badge.svg)](https://github.com/dynamic/silverstripe-carousel/actions)  
[![Latest Stable Version](https://poser.pugx.org/dynamic/silverstripe-carousel/v/stable)](https://packagist.org/packages/dynamic/silverstripe-carousel)  
[![Total Downloads](https://poser.pugx.org/dynamic/silverstripe-carousel/downloads)](https://packagist.org/packages/dynamic/silverstripe-carousel)  
[![License](https://poser.pugx.org/dynamic/silverstripe-carousel/license)](https://packagist.org/packages/dynamic/silverstripe-carousel)  

[![Sponsor](https://img.shields.io/badge/Sponsor-Dynamic-brightgreen)](https://github.com/sponsors/dynamic)

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Adding a Carousel to a Page](#adding-a-carousel-to-a-page)
  - [Working with Images and Videos](#working-with-images-and-videos)
- [Customization](#customization)
  - [Creating Custom Templates](#creating-custom-templates)
- [Other Modules Using Silverstripe Carousel](#other-modules-using-silverstripe-carousel)
- [Maintainers](#maintainers)
- [Bugtracker](#bugtracker)
- [Development and Contribution](#development-and-contribution)
- [License](#license)

## Requirements

- Silverstripe CMS ^5.0
- Bootstrap 5 (for default templates)

## Installation

Install via Composer:

```sh
composer require dynamic/silverstripe-carousel
```

Run a dev/build to regenerate the manifest:

```sh
./vendor/bin/sake dev/build
```

## Configuration

Apply the `CarouselPageExtension` to your desired page types in your YAML configuration:

```yaml
Page:
  extensions:
    - Dynamic\Carousel\Extension\CarouselPageExtension
```

After applying the extension, run a dev/build to update the database schema.

## Usage

### Adding a Carousel to a Page

To display the carousel, include the following template in your page layout:

```ss
<% include Dynamic/Carousel/Includes/Carousel %>
```

Ensure that your template has access to the `$Carousel` variable, which contains the carousel data.

### Working with Images and Videos

The module supports two types of content:

- **Images**: For displaying images.
- **Videos**: For embedding videos.

To add these:

1. In the CMS, navigate to the page where you've enabled the carousel.
2. Click on the "Carousel" tab.
3. Use the "Add Slide" button to add either an Image or Video.
4. For Images:
   - Upload or select an image from the files.
   - Optionally, add a caption or link.
5. For Videos:
   - Provide the video URL (supports platforms like YouTube and Vimeo).
   - Optionally, add a caption.

Repeat these steps to add multiple images or videos as needed.

## Customization

### Creating Custom Templates

If you're not using Bootstrap or wish to customize the carousel's appearance:

1. **Locate the Default Template**  
   The default template is located at:  
   ```
   templates/Dynamic/Carousel/Includes/Carousel.ss
   ```

2. **Copy to Your Theme**  
   Copy the `Carousel.ss` file to your theme's directory, maintaining the folder structure:  
   ```
   themes/your-theme/templates/Dynamic/Carousel/Includes/Carousel.ss
   ```

3. **Modify the Template**  
   Edit the copied `Carousel.ss` to fit your design requirements. You can:  
   - Change the HTML structure  
   - Update CSS classes  
   - Add or remove elements as needed  

4. **Include Necessary Assets**  
   Ensure that any required JavaScript or CSS for your custom carousel implementation is included in your project.  
   If you're using a different frontend framework, include its assets accordingly.

For more information on custom templates, refer to the [Silverstripe CMS Documentation](https://docs.silverstripe.org/en/5/developer_guides/templates/).

## Other Modules Using Silverstripe Carousel

The Silverstripe Carousel module is used in other projects to extend functionality, such as:

- [Silverstripe Elemental Carousel](https://github.com/dynamic/silverstripe-elemental-carousel) - Integrates carousel functionality with Silverstripe Elemental Blocks.

## Maintainers

- [Dynamic](http://www.dynamicagency.com) (<dev@dynamicagency.com>)

## Bugtracker

Bugs are tracked in the issues section of this repository. Before submitting an issue, please review existing issues to ensure yours is unique.

If the issue appears to be new:

- Create a new issue.
- Describe the steps required to reproduce your issue and the expected outcome. Unit tests, screenshots, and screencasts can help here.
- Provide details about your environment:
  - Silverstripe version
  - Browser and version
  - PHP version
  - Operating system
  - Any installed Silverstripe modules

**Security Issues:**  
Please report security issues to the module maintainers directly. Avoid filing security issues in the bugtracker.

## Development and Contribution

We welcome contributions! Please ensure you raise a pull request and discuss with the module maintainers.

## License

This module is licensed under the BSD-3-Clause License. See the [LICENSE](LICENSE.md) file for details.
