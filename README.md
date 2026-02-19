# HWS Base Tools

**A comprehensive WordPress plugin for website optimization, debugging, and management.**

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)]()

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [Dashboard](#dashboard)
- [Shortcodes](#shortcodes)
- [Automated Tasks (Cron Jobs)](#automated-tasks-cron-jobs)
- [GitHub Auto-Updater](#github-auto-updater)
- [Configuration](#configuration)
- [Requirements](#requirements)
- [Changelog](#changelog)

---

## Overview

HWS Base Tools is an all-in-one WordPress plugin designed for developers and site administrators. It provides a centralized dashboard for debugging, system monitoring, automated maintenance tasks, and dynamic content shortcodes.

**Key Benefits:**
- 🔧 Centralized debugging and error log management
- ⚡ Automated database and log maintenance
- 🏷️ Dynamic shortcodes for founder/company information
- 🔄 GitHub-based auto-updates with version history
- 📊 Comprehensive system health monitoring

---

## Features

### 🖥️ Admin Dashboard
- **System Summary** — PHP version, WordPress version, memory limits, debug status
- **Error Log Viewer** — Tabbed interface for Fatal/Syntax errors, debug.log, and error_log
- **Real-time Log Search** — Search and highlight matches with keyboard navigation
- **WP-Config Editor** — Toggle debug constants directly from the dashboard
- **Plugin Health Monitor** — Track active/inactive plugins and updates

### 🏷️ Dynamic Shortcodes
- `[founder id="..."]` — Display founder/user information
- `[company id="..."]` — Display company/organization information
- `[website_url]` — Output the site URL
- `[website_content id="..."]` — Display website settings content
- `[display_year]` — Current year (useful for copyright)

### 🔄 Automated Maintenance
- **Log File Cleaner** — Auto-delete logs exceeding size limits
- **Backup Cleaner** — Remove old backup files automatically
- **Elementor DB Updater** — Auto-run Elementor database updates

### 📦 GitHub Integration
- **Auto-Updates** — Check for updates from GitHub repository
- **Version History** — Download any tagged release
- **Direct Install** — Update directly from GitHub with proper folder naming
- **30-Minute Cache** — Fast update detection

---

## Installation

### Method 1: Direct Upload
1. Download the latest release ZIP from [GitHub Releases](https://github.com/mikeyperes/hws-base-tools/releases)
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin

### Method 2: Manual Installation
```bash
cd wp-content/plugins/
git clone https://github.com/mikeyperes/hws-base-tools.git
```

### Method 3: From Plugin Dashboard
Once installed, use the **Plugin Info** panel to:
- Force update checks
- Download specific versions
- Update directly from GitHub

---

## Dashboard

Access the dashboard at **WordPress Admin → HWS Base Tools**

### Dashboard Panels

#### 📊 System Summary
Displays critical system information at a glance:
- PHP Version & Memory Limit
- WordPress Version & Debug Status
- Active Theme & Child Theme Detection
- Database Size & Post Counts

#### 🔴 Error Logs
Three-tab interface for viewing logs:

| Tab | Description |
|-----|-------------|
| **Fatal & Syntax** | Combined fatal/syntax errors from both logs |
| **debug.log** | WordPress debug log (wp-content/debug.log) |
| **error_log** | Server error log (site root) |

**Search Features:**
- Real-time highlighting as you type
- `Enter` / `Shift+Enter` — Navigate between matches
- `Escape` — Clear search
- Match counter with current position

#### ⚙️ WP-Config Settings
Toggle these constants without editing files:
- `WP_DEBUG` — Enable/disable debug mode
- `WP_DEBUG_LOG` — Enable/disable debug logging
- `WP_DEBUG_DISPLAY` — Show/hide errors on screen
- `SCRIPT_DEBUG` — Use unminified scripts
- `DISABLE_WP_CRON` — Disable WordPress cron

#### 🔌 Plugin Info
- Current version vs. latest GitHub version
- **Force Update Check** — Clear caches and check immediately
- **Update Now** — Direct install from GitHub
- **Version History** — Download any tagged release

---

## Shortcodes

### Founder Shortcode
Display information about the designated "founder" user.

```
[founder id="attribute"]
```

| Attribute | Description |
|-----------|-------------|
| `title` / `name` | Display name |
| `first_name` | First name |
| `last_name` | Last name |
| `email` | Email address |
| `biography` | ACF biography field (with fallbacks) |
| `avatar` | Avatar URL (200px) |
| `website` | Website URL |
| `url_facebook` | Facebook URL (from ACF urls group) |
| `url_twitter` | Twitter/X URL |
| `url_linkedin` | LinkedIn URL |
| `url_instagram` | Instagram URL |
| `url_youtube` | YouTube URL |
| `additional_public_email` | Public contact email |
| `additional_public_phone` | Public phone number |
| `additional_title` | Professional title |

**Example:**
```html
<p>Contact [founder id="first_name"] at [founder id="additional_public_email"]</p>
```

### Company Shortcode
Display information about the designated "company" user.

```
[company id="attribute"]
```

Supports the same attributes as `[founder]`.

**Example:**
```html
<footer>
    <p>© [display_year] [company id="name"]</p>
    <p>Phone: [company id="additional_public_phone"]</p>
    <p>Email: [company id="additional_public_email"]</p>
</footer>
```

### Education Shortcode
Display education history from the user's repeater field.

```
[founder id="education"]
[founder id="education" format="json"]
[founder id="education" index="0"]
[founder id="education" index="0" field="college"]
[founder id="education" field="college"]
```

| Attribute | Description |
|-----------|-------------|
| `format` | Output format: `html` (default), `json`, `array` |
| `index` | Specific entry index (0-based) |
| `field` | Specific field: `college`, `wiki_url`, `year`, `designation`, `major` |

**Examples:**
```html
<!-- Display all education as HTML -->
[founder id="education"]

<!-- Get first college name -->
[founder id="education" index="0" field="college"]

<!-- Get all college names (comma-separated) -->
[founder id="education" field="college"]

<!-- Get as JSON for JavaScript -->
[founder id="education" format="json"]
```

**HTML Output CSS Classes:**
- `.hws-education-list` — Container for all entries
- `.hws-education-entry` — Single education entry
- `.hws-education-college` — College/university name
- `.hws-education-degree` — Degree container
- `.hws-education-designation` — Degree type (B.S., M.A., etc.)
- `.hws-education-major` — Field of study
- `.hws-education-year` — Graduation year

### SameAs Shortcode
Display Schema.org sameAs URLs for structured data.

```
[founder id="sameas"]
[founder id="sameas" format="json"]
[founder id="sameas" format="ul"]
```

| Format | Description |
|--------|-------------|
| `text` | Newline-separated URLs (default) |
| `json` | JSON array |
| `ul` | HTML unordered list with links |
| `array` | Serialized PHP array |

**Examples:**
```html
<!-- For Schema.org JSON-LD -->
<script type="application/ld+json">
{
  "@type": "Person",
  "sameAs": [founder id="sameas" format="json"]
}
</script>

<!-- Display as link list -->
[founder id="sameas" format="ul"]
```

### Website Content Shortcode
Display content from ACF website settings.

```
[website_content id="field_name"]
```

### Website URL Shortcode
Output the site URL.

```
[website_url]
```

### Display Year Shortcode
Output the current 4-digit year.

```
[display_year]
```

---

## Automated Tasks (Cron Jobs)

### 📁 Log File Cleaner

**Purpose:** Automatically delete debug.log and error_log when they exceed size limits.

| Setting | Default | Range |
|---------|---------|-------|
| Enabled | ✅ Yes | Toggle |
| Interval | 5 days | 1-30 days |
| Size Limit | 10 MB | 1-500 MB |

**Dashboard Location:** HWS Base Tools → Log Cleaner panel

### 📦 Backup Cleaner

**Purpose:** Remove old backup files (*.sql, *.zip, *.tar.gz) from common backup locations.

| Setting | Default |
|---------|---------|
| Enabled | ✅ Yes |
| Retention | 5 days |
| Schedule | Daily |

**Scans these directories:**
- `/wp-content/backups/`
- `/wp-content/uploads/backups/`
- `/wp-content/ai1wm-backups/`
- `/wp-content/updraft/`

### ⚡ Elementor Database Updater

**Purpose:** Automatically run Elementor database updates to prevent the "Database Update Required" notice.

| Setting | Default | Range |
|---------|---------|-------|
| Enabled | ✅ Yes | Toggle |
| Interval | 3 days | 1-14 days |

**Dashboard Location:** HWS Base Tools → Elementor Database Auto-Updater panel

**Panel Shows:**
- Elementor Version vs. DB Version comparison
- Last run timestamp and report
- Cron status with next scheduled run
- Manual "Run Now" button

---

## GitHub Auto-Updater

The plugin includes a custom GitHub-based update system that integrates with WordPress's native updater.

### Features

- **Automatic Checks** — Polls GitHub every 30 minutes for new versions
- **WordPress Integration** — Updates appear in Dashboard → Updates
- **Version Comparison** — Compares local version against GitHub releases
- **Proper Folder Naming** — Handles GitHub's `-main` suffix automatically

### Manual Controls

| Button | Action |
|--------|--------|
| **Force Update Check** | Clears all caches, checks GitHub immediately |
| **Update Now from GitHub** | Downloads and installs latest version directly |
| **Load Versions** | Fetches all tagged releases from GitHub |
| **Download Selected Version** | Downloads any historical version |

### Configuration

Edit these values in `initialization.php` → `Config` class:

```php
public static $plugin_folder_name = "hws-base-tools";
public static $github_repo = "mikeyperes/hws-base-tools";
public static $github_branch = "main";
```

---

## Configuration

### Config Class

All plugin configuration is centralized in the `Config` class (`initialization.php`):

```php
class Config {
    // Dashboard settings
    public static $settings_page_name = "HWS Base Tools";
    public static $settings_page_capability = "manage_options";
    public static $settings_page_slug = "hws-core-tools";
    
    // Plugin identification
    public static $plugin_folder_name = "hws-base-tools";
    public static $github_repo = "mikeyperes/hws-base-tools";
    public static $github_branch = "main";
}
```

### ACF Requirements

The shortcodes require Advanced Custom Fields (ACF) with specific field configurations:

**Website Settings (Options Page):**
- `website` → Group
  - `founder` → User field (return format: array)
  - `company` → User field (return format: array)

**User Fields:**
- `biography` → Textarea/WYSIWYG
- `website` → URL
- `urls` → Group containing platform URLs
- `additional` → Group
  - `public_email` → Email
  - `public_phone` → Text
  - `title` → Text

---

## Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| WordPress | 5.0+ | 6.0+ |
| PHP | 7.4+ | 8.0+ |
| ACF | 5.0+ | 6.0+ (for shortcodes) |

**Optional:**
- Elementor (for DB auto-updater feature)
- WP-Cron enabled (or server cron configured)

---

## File Structure

```
hws-base-tools/
├── initialization.php              # Main plugin file, Config class
├── README.md                       # This file
│
├── settings-dashboard.php          # Main dashboard UI
├── settings-dashboard-*.php        # Dashboard panel modules
│   ├── settings-dashboard-plugin-info.php
│   ├── settings-dashboard-system-checks.php
│   ├── settings-dashboard-config.php
│   ├── settings-dashboard-backups.php
│   ├── settings-dashboard-log-delete-cron.php
│   ├── settings-dashboard-elementor-db-cron.php
│   ├── settings-dashboard-snippets.php
│   └── ...
│
├── snippet-*.php                   # Feature modules
│   ├── snippet-website-settings-functionality.php  # Shortcodes
│   ├── snippet-login-mask.php
│   ├── snippet-comments.php
│   └── ...
│
├── register-acf-*.php              # ACF field registrations
├── GitHub_Updater.php              # GitHub update integration
├── helper.php                      # Utility functions
├── safe-wrappers.php               # Safe AJAX/shell utilities
│
└── smp-core/                       # Scale My Podcast integrations
    └── ...
```

---

## Changelog

### v10.5 (Latest)
- ✨ **NEW:** Version history now shows commits (not just tags)
- ✨ **NEW:** Color-coded admin cards (🔵 Blue = Company, 🟢 Green = Founder)
- ✨ **NEW:** Education repeater for users (college, wiki_url, year, designation, major)
- ✨ **NEW:** SameAs field for Schema.org structured data
- ✨ **NEW:** Shortcodes for education and sameAs data with multiple formats

### v10.4
- 📝 Added comprehensive README documentation for GitHub

### v10.3
- ✨ **NEW:** Elementor Database Auto-Updater with cron scheduling
- 🔧 Fixed log search to properly find matches in all content
- 📦 Added version history dropdown for downloading older releases

### v10.2
- 🔍 Rewrote log search with reliable regex-based highlighting
- 🐛 Fixed "No matches" bug when matches clearly existed

### v10.1
- ✨ Added real-time log search with keyboard navigation
- 🎨 Search highlighting with current match indicator

### v10.0
- 🔧 **CRITICAL:** Fixed shortcode loading order for Elementor compatibility
- 📜 Added version history panel with tag selection
- 🔄 Shortcodes now load before any guards/hooks

### v9.9
- 🐛 Fixed shortcodes not rendering on frontend
- 📍 Moved shortcode registration outside `init` hook

### v9.8
- ✨ Added Config class to eliminate hardcoded values
- ⚡ Reduced GitHub cache from 6 hours to 30 minutes
- 🔄 Added direct update functionality with folder name handling

---

## Support

**Repository:** [github.com/mikeyperes/hws-base-tools](https://github.com/mikeyperes/hws-base-tools)

**Issues:** [GitHub Issues](https://github.com/mikeyperes/hws-base-tools/issues)

---

## License

Proprietary - All Rights Reserved

**Author:** Michael Peres  
**Website:** [michaelperes.com](https://michaelperes.com)
