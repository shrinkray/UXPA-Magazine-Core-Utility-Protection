# Changelog

All notable changes to the **UXPA Magazine Core Utility & Protection** plugin will be documented in this file.

## [1.1.15] - 2026-07-02

### Changed
- Added a replaces label under the Shortcode Info tab to explicitly state it replaces the legacy Taxonomy List plugin.

## [1.1.14] - 2026-07-02

### Changed
- Added a helper guide paragraph above the taxonomy selector table when a post type supports multiple taxonomies.

## [1.1.13] - 2026-07-02

### Changed
- Added a short descriptive usage instructions paragraph on the Taxonomy Order admin interface page.

## [1.1.12] - 2026-07-02

### Fixed
- Unslashed the $_POST['field'] input parameter inside the bulk date updater submit handler before sanitizing with sanitize_key().

## [1.1.11] - 2026-07-02

### Fixed
- Updated the admin_enqueue_scripts() hook guard inside the Taxonomy Order module to ensure assets load correctly on the new unified Term Ordering tab.

## [1.1.10] - 2026-07-02

### Changed
- Replaced wp_redirect() calls with wp_safe_redirect() to secure internal admin redirects and align with best practices.

## [1.1.9] - 2026-07-02

### Fixed
- Unslashed $_POST input parameters for Term Ordering settings before sanitization to prevent slash mismatches.

## [1.1.8] - 2026-07-02

### Changed
- Updated README.md features list and unified settings tabs ordering to match current codebase layout and new consolidated locations.

## [1.1.7] - 2026-07-02

### Fixed
- Unslashed and sanitized the tb_refresh $_POST nonce variable inside the bulk date updater before running wp_verify_nonce().

## [1.1.6] - 2026-07-02

### Fixed
- Hardened $_POST nonce validation checks in settings saving logic to check index existence and apply unslashing/sanitizing before verification.

## [1.1.5] - 2026-07-02

### Fixed
- Fixed typo in term ordering AJAX handler where `$item__` was referenced instead of loop variable `$item_`.

## [1.1.4] - 2026-07-02

### Fixed
- Implemented Singleton pattern via get_instance() for utility classes to prevent duplicate instantiation and redundant action hook registration.

## [1.1.3] - 2026-07-02

### Fixed
- Added wp_unslash() to all $_GET input parameters before sanitization to align with WordPress security guidelines and prevent mismatches.

## [1.1.2] - 2026-07-02

### Added
- Added a secondary sidebar guide column on the settings page to provide user documentation, warnings, and tips for each tab.

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
