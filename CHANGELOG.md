# Changelog

All notable changes to the **UXPA Magazine Core Utility & Protection** plugin will be documented in this file.

## [1.1.1] - 2026-07-02

### Changed
- Reordered settings tabs, moving the Bot Firewall tab to the end of the list.
- Changed default active tab to Term Ordering.

## [1.1.0] - 2026-07-02

### Added
- Created a unified settings page under **Settings > UXPA Core Utility**.
- Added 5 settings tabs: Bot Firewall, Term Ordering, Term Switcher, Bulk Date Updater, and Shortcode Info.
- Implemented automatic redirects for old plugin settings URLs to point directly to their new tabs on the unified settings page.
- Renamed and refactored individual module render methods to integrate with the tabbed layout.
- Added parameter sanitization and security checks for the unified settings controller.

## [1.0.0] - 2026-07-02

### Added
- Created the main plugin entry point and activated DB checks.
- Implemented high-efficiency early-exit bot protection firewall to block user enumeration scans.
- Unified the **Bulk Post Update Date** settings page and randomizing engine.
- Added hierarchical drag-and-drop **Taxonomy Terms Order** query filters, walker class, and UI.
- Integrated the **Taxonomy Switcher** tool under the WP Tools menu.
- Re-registered the `[taxonomy_list]` shortcode helper.
- Bundled all CSS and JS assets locally inside `assets/` to drop remote/external dependencies.
