# UXPA Magazine Core Utility & Protection

A consolidated custom utility plugin built for **UXPA Magazine** to replace multiple administrative plugin codebases and run high-efficiency, early-exit security firewall routines.

## Features

1. **Early-Exit Bot Firewall**: Intercepts and terminates author enumeration scans (`?author=N`) before WordPress runs heavy database tasks, returning `403 Forbidden` early.
2. **Bulk Date Updater**: Randomizes publication/modification dates of posts, pages, custom post types, and comments within a custom range (accessible under **Settings > Bulk Post Update Date**).
3. **Taxonomy Terms Order**: Enables hierarchical drag-and-drop sorting of categories and taxonomies (accessible under eligible post types > **Taxonomy Order** and settings at **Settings > Taxonomy Terms Order**).
4. **Taxonomy Switcher**: Provides a fast SQL-based utility to bulk switch terms from one taxonomy to another (accessible under **Tools > Taxonomy Switcher**).
5. **Taxonomy List Shortcode**: Renders terms of any taxonomy dynamically on pages via the `[taxonomy_list]` shortcode.

## Replaced Plugins

This plugin replaces the following independent extensions (which should remain deactivated):
- `bulk-post-update-date`
- `taxonomy-terms-order`
- `taxonomy-switcher`
- `taxonomy-list`

## Installation

1. Copy this folder `uxpa-magazine-core-utility-protection` into `wp-content/plugins/`.
2. Activate the plugin in the WordPress Dashboard or run:
   ```bash
   wp plugin activate uxpa-magazine-core-utility-protection
   ```
3. Deactivate the replaced plugins to avoid conflicts.
