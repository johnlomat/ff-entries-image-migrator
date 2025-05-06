# Fluent Forms Entries Image Migrator

A WordPress plugin that processes external image URLs from Fluent Forms submissions and imports them into the WordPress media library.

## Description

This plugin works with the Fluent Forms plugin to automatically process external image URLs submitted through forms and migrate them to your own server. It can:

- Process external image URLs from new form submissions automatically
- Process existing submissions with external images in batches through an admin interface
- Handle CSV imports
- Track processing progress with a visual progress bar

## Installation

1. Upload the `ff-entries-image-migrator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure that Fluent Forms is installed and activated

## Configuration

The plugin automatically detects the current form ID from the URL when you're viewing a Fluent Forms entries page (e.g., `?page=fluent_forms&route=entries&form_id=5`).

The plugin scans all fields in your form submissions to detect image URLs automatically.

## Usage

After installing and configuring the plugin:

1. Navigate to your Fluent Forms entries page for the configured form
2. You'll see a "Process Images to Media Library" button
3. Click this button to begin processing existing submissions
4. You can adjust the batch size to optimize processing speed vs. server load

## Requirements

- WordPress 5.0 or later
- PHP 7.2 or later
- Fluent Forms plugin

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support, please contact the plugin author at https://johnlomat.vercel.app/

## Changelog

### 1.0.0
- Initial release
