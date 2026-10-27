=== Notification Master - Real-Time WordPress Notifications With Email, SMS, Webhooks & More ===
Contributors: notificationmaster
Donate link: https://notification-master.com
Tags: web push, email, notifications, sms, whatsapp
Stable tag: 1.8.0
Requires at least: 4.9
Tested up to: 7.1
Requires PHP: 7.4
License: GPL-3.0-or-later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Send push, email, and real-time notifications across 12+ channels like WhatsApp, Slack, and Discord. Boost engagement automatically.

== Description ==

**Transform Your WordPress Site with Professional Notification Management**

Notification Master is the most comprehensive WordPress notification plugin designed to dramatically increase user engagement and site activity. Whether you're running a blog, eCommerce store, or community site, our plugin ensures your users never miss important updates through intelligent, automated notifications.

**Why Choose Notification Master?**

✅ **12+ Notification Channels** - Reach users everywhere they are
✅ **Real-Time Browser Push Notifications** - Instant alerts even when users aren't on your site  
✅ **Advanced Automation** - Set up once, notify forever
✅ **Personalized Messages** - Dynamic content with smart merge tags
✅ **Zero Coding Required** - User-friendly interface for all skill levels
✅ **Mobile Optimized** - Perfect notifications on any device

🎥 **Watch Our Complete Setup Tutorial**
Learn how to maximize your WordPress notifications in under 10 minutes:

https://youtu.be/6gRbfHZzi1s?si=03krrf8eVNRbBI8I

## 🚀 Key Features & Notification Channels

### **Web Push Notifications (Browser Notifications)**
* Send instant browser alerts to subscribers even when they're not on your website
* Works on desktop and mobile browsers (Chrome, Firefox, Safari, Edge)
* Customizable notification appearance with your site branding
* Smart subscription management with floating buttons
* Welcome messages for new subscribers

### **Email Notification System**
* Rich HTML email templates with WordPress Classic Editor
* Multiple recipient management (roles, individual users, custom addresses)
* Email exclusion options for targeted messaging
* Test email functionality before sending
* Background processing for improved performance

### **Social Media & Communication Integrations**
* **Discord Notifications** - Keep your community updated instantly
* **Slack Integration** - Team notifications and updates
* **WhatsApp Notifications** - Direct messaging to users
* **Facebook Page Updates** - Automatic social media posting
* **Twitter/X Integration** - Auto-tweet important updates
* **Instagram Auto-Posting** - Visual content automation

### **Advanced Automation & Workflows**
* **Zapier Integration** - Connect with 5000+ apps and services
* **Make (Integromat)** - Advanced workflow automation
* **Webhook Support** - Custom integrations and API connections
* **Twilio SMS** - Text message notifications
* **Conditional Logic** - Smart notification rules

## 📋 Complete Trigger System - Never Miss Important Events

### **Content Management Triggers**
* **New Post Published** - Alert subscribers about fresh content
* **Post Updates** - Notify about content changes
* **Comment Activity** - New comments, approvals, replies
* **Media Management** - File uploads, updates, deletions
* **Draft & Review Process** - Editorial workflow notifications

### **User Activity Triggers**
* **User Registration** - Welcome new members
* **Profile Updates** - Account changes
* **Login/Logout Activity** - Security and engagement tracking
* **User Management** - Administrative notifications

### **WordPress System Triggers**
* **Plugin Management** - Installation, activation, updates
* **Theme Changes** - New themes, switches, updates
* **Taxonomy Management** - Categories, tags, custom taxonomies
* **Privacy Compliance** - GDPR data export/erasure notifications

### **WooCommerce Integration** (eCommerce Notifications)
* Order status changes and updates
* New customer registrations
* Product inventory alerts
* Payment confirmations
* Shipping notifications

## 🏷️ Smart Personalization with Merge Tags

Create highly personalized notifications using our extensive merge tag system:

**Content Tags:**
* `{{post.title}}` - Dynamic post titles
* `{{post.content}}` - Excerpt or full content
* `{{post_author.nickname}}` - Author information
* `{{post.permalink}}` - Direct links to content

**User Tags:**
* `{{user.email}}` - User email addresses
* `{{user.display_name}}` - Display names
* `{{user.role}}` - User roles and permissions

**System Tags:**
* `{{general.blogname}}` - Your website name
* `{{general.current_time}}` - Current date and time

**Advanced Custom Fields (ACF) Support:**
* Custom field values for personalized content
* Dynamic product information
* User meta data integration

## 📚 Comprehensive Documentation & Support

Access our complete knowledge base for detailed setup guides:

* **[Quick Start Guide](https://notification-master.com/docs/getting-started/)** - Get up and running in minutes
* **[Channel Configuration](https://notification-master.com/docs/settings/)** - Detailed integration setup
* **[Trigger Reference](https://notification-master.com/docs/triggers/)** - Complete trigger documentation
* **[Web Push Setup](https://notification-master.com/docs/web-push/)** - Browser notification configuration
* **[Email Templates](https://notification-master.com/docs/email/)** - Email design and customization
* **[Social Media Integration](https://notification-master.com/docs-category/integrations/)** - Complete social setup guides
* **[WooCommerce Notifications](https://notification-master.com/docs/woocommerce-triggers/)** - eCommerce-specific triggers
* **[API & Webhooks](https://notification-master.com/docs/webhook/)** - Advanced integrations

## 🤖 Works with AI Assistants (Claude and Cursor via MCP)

Notification Master registers its notification, trigger, channel, schedule and log operations with the WordPress Abilities API, which is part of WordPress core since 6.9. Install the free MCP Adapter plugin from the WordPress project and MCP clients that sign in with a WordPress Application Password (Claude Desktop, Claude Code, Cursor) can manage your notifications in plain English. Verified with Claude Code.

**What an AI assistant can do:**

* List notifications with their trigger, channels and on/off state
* Read one notification with its schedule, with webhook URLs, API secrets and recipient lists masked
* List triggers, merge tags, channels and starter templates
* Read settings without the push keys
* List scheduled sends and cancel one
* Read the send log, send statistics and the debug log
* Count push subscribers
* Create a notification from a starter template as a draft
* Enable or disable a notification
* Rename a notification or edit a connection's text fields

*"Which notifications fire when a post is published, and which ones are turned off?"*
*"Create a draft from the new comment template and leave it disabled."*
*"Show me the failed sends from the last 7 days."*

**What an AI assistant cannot do:**

* Send a queued notification now over MCP. That stays off by default, and even over REST it needs an explicit confirmation.
* Send test emails or broadcast push notifications.
* Delete notifications, logs or subscribers, import or export raw data, or change settings and integration credentials. None of these are exposed to agents.
* Use Pro channels, WooCommerce triggers or conditions on the free plan. The server refuses them.
* Act as anyone but an administrator. Every operation checks the administrator capability, and a filter can require more than that, never less.

**What you need:** WordPress 6.9 or newer, the free [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin (not bundled with Notification Master and not required), and a WordPress Application Password for a dedicated admin user (Users > Profile > Application Passwords). On the command line, `wp notification-master abilities` lists what registered. Nothing changes for sites that skip this: on older WordPress, or without the adapter, Notification Master runs exactly as before, and nothing leaves your server.

== Installation ==

### **Automatic Installation (Recommended)**
1. Login to your WordPress admin dashboard
2. Navigate to Plugins > Add New
3. Search for "Notification Master"
4. Click "Install Now" and then "Activate"

### **Manual Installation**
1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/notification-master` directory
3. Activate through the WordPress 'Plugins' menu

== Frequently Asked Questions ==

= What makes Notification Master different from other WordPress notification plugins? =

Notification Master offers the most comprehensive channel support (12+ integrations), advanced personalization with merge tags, and professional features like conditional logic, background processing, and responsive design optimization.

= Can I send notifications to multiple channels simultaneously? =

Yes! You can configure multiple notification channels for each trigger. For example, send a web push notification AND an email AND a Discord message when a new post is published.

= Do web push notifications work on mobile devices? =

Absolutely! Our web push notifications work perfectly on both desktop and mobile browsers, including Chrome, Firefox, Safari, and Edge.

= Is there a limit to how many notifications I can send? =

Notification Master doesn't impose artificial limits. However, some third-party services (like email providers or SMS services) may have their own rate limits.

= Can I customize the appearance of notifications? =

Yes! You can customize web push notification appearance, email templates with HTML, and personalize all content using our extensive merge tag system.

= Does it work with WooCommerce? =

Yes! We have dedicated WooCommerce triggers for order updates, customer registration, inventory alerts, and more eCommerce-specific events.

= Can I test notifications before sending them live? =

Absolutely! We provide test functionality for all notification types, so you can verify appearance and content before activating.

= Is Notification Master GDPR compliant? =

Yes! We include privacy triggers for personal data export/erasure and respect user subscription preferences and unsubscribe options.

= Does Notification Master work with Claude, Cursor or other AI assistants (MCP)? =

Yes, on WordPress 6.9 or newer with the free MCP Adapter plugin from the WordPress project. An assistant can list your notifications, triggers, channels and logs, create draft notifications from the starter templates, enable or disable a notification and cancel a scheduled send. Sending a queued notification right away is off MCP, and every call needs an administrator Application Password. Without the adapter nothing changes.

== Screenshots ==
1. Notification Master - Notifications Dashboard
2. Notification Master - Notification Configuration
3. Notification Master - Integration Settings
4. Notification Master - Merge Tags Selection

== Changelog ==

= 1.8.0 =
* Added: Support for AI assistants through the WordPress Abilities API and MCP. Assistants such as Claude or ChatGPT can list your notifications, triggers, channels, templates, scheduled sends and logs, create draft notifications from templates, turn notifications on or off and cancel scheduled sends. Your credentials and recipient lists are never shown to them.
* Added: WordPress 7.1 compatibility.
* Updated: Notification Master now requires PHP 7.4 or newer. Sites on older PHP keep running 1.7.1.
* Updated: The bundled Action Scheduler and Mobile Detect libraries, for full PHP 8.4 compatibility.

= 1.7.1 =
* Added: One-click starter templates. Install a ready-made set of notifications for common events (post/page published & updated, user login/logout/registration/password change, new comments, taxonomy terms, media, plugins and more) with Email and Web Push pre-configured and Discord/Webhook/Instagram added as ready-to-fill connections.
* Added: Import and Export. Back up, migrate, or share notifications as a JSON file from the Notifications screen. Imports are added as inactive drafts by default for safe review before activation.
* Added: New REST endpoints under `/ntfm/v1/notifications` for `templates`, `import`, and `export`.
* Added: WordPress 7.0 compatibility.

= 1.7.0 =
* Added: Per-connection scheduling. Each connection can now be delayed by a fixed amount of time (minutes, hours, or days) or scheduled relative to a date from a merge tag (e.g. send 1 hour before {{post.published_date}} or 7 days after {{comment.datetime}}).
* Added: Typed merge tags. Date-typed tags now expose a `type: datetime` flag so they can be selected as scheduling sources. Covers published/modified post dates, comment dates, attachment dates, and the current time/date general tags.
* Added: Configurable fallback behavior when a referenced date is in the past or the merge tag value is empty (send immediately or skip).
* Added: Pending notifications tab in the Notification Log page, showing scheduled notifications with options to send them immediately or cancel.
* Added: New REST endpoints under `/ntfm/v1/scheduled` for listing, cancelling, and dispatching scheduled notifications.

= 1.6.10 =
* Added: WordPress 6.9 compatibility.
* Added: New option to allow custom SVG icon for the notification floating button.

= 1.6.8 =
* Added: Review notice for users to leave a rating and review the plugin.

= 1.6.7 =
* Added: Changed admin notice text.

= 1.6.6 =
* Fixed: Plugin Title issue.

= 1.6.5 =
* Added: Smart auto-delete system for failed web push subscriptions to maintain clean subscriber lists and improve delivery rates.
* Added: Configurable failure threshold setting (1-50 attempts) before automatic subscription removal.
* Added: Success tracking that resets failure count when notifications are delivered successfully.
* Added: Automatic cleanup of old failed subscriptions with daily maintenance task.
* Enhanced: Web push notification reliability with intelligent subscription management.
* Enhanced: Database performance with new indexes for subscription status and failure tracking.

= 1.6.4 =
* Fixed: Small bug in readme.txt file.

= 1.6.3 =
* Enhanced: Fully optimized the responsive design of all settings pages for better mobile usability.

= 1.6.2 =
* Added: WhatsApp integration for sending notifications to WhatsApp.

= 1.6.1 =
* Added: ACF (Advanced Custom Fields) integration for sending notifications based on ACF fields.

= 1.6.0 =
* Added: Instagram integration for auto posting to Instagram.

= 1.5.0 =
* Added: Added twilio integration for sending SMS and WhatsApp notifications.
* Fixed: WooCommerce triggers option not working in some cases.

= 1.4.12 =
* Added: Responsive positioning for the floating button. Now you can change the button's position based on the device type (Desktop, Mobile, etc.).
* Added: Option to customize the floating button animation color.
* Fixed: Text alignment in the normal button; now properly centered.

= 1.4.11 =
* Fixed: Notification connections save issue.

= 1.4.10 =
* Fixed: Web push welcome message in auto mode.
* Fixed: Web push welcome message disable option not working.

= 1.4.9 =
* Added: Web push floating button hide on devices option.
* Added: Responsive options for web push floating button positions (top, right, bottom, left).
* Added: Welcome message for web push notifications.
* Enhanced: Users can now send web push notifications to all subscribers or specific users.

= 1.4.8 =
* Fixed: Resolved an issue where the email notification content, including HTML formatting, was not saved or displayed correctly.

= 1.4.7 =
* Enhanced: The background processing for notifications.  
* Fixed: Resolved a privacy issue where email notifications displayed all recipients' email addresses.

= 1.4.6 =
* Added: WordPress Classic Editor for email notifications, enabling users to add rich content, images, and more.
* Added: Test email notifications feature to verify email formatting and content.
* Added: Conditional logic for notifications, allowing users to send notifications based on specific conditions.
* Fixed: Post Updated trigger not working in some cases.

= 1.4.5 =
* Added: Push notifications floating button option.
* Added: Customization options for push notifications floating button and normal button.
* Added: Allow users to unsubscribe from push notifications.

= 1.4.4 =
* Added: Help links for the integrations.
* Improved: Integration settings UI.

= 1.4.3 =
* Improved: Dashboard and settings page UI.
* Fixed: Delete logs by selected IDs not working.

= 1.4.2 =
* Fixed: Web push keys generation not working in some cases.
* Fixed: Email notification email address not saved.

= 1.4.1 =
* Fixed: Web push notifications not working in some cases.

= 1.4.0 =
* Added: Web push notifications subscriptions management.

= 1.3.3 =
* Fixed: Some plugin trigger merge tags not working.
* Improved: Code Improvements.

= 1.3.2 =
* Fixed: Changelog issue.

= 1.3.1 =
* Fixed: WooCommerce product triggers conflict with custom post type triggers.
* Added: Current time and date merge tags for notifications.

= 1.3.0 =
* Added: WebPush notifications Feature.
* Improved: Settings page.

= 1.2.1 =
* Improved: Enhanced the dashboard navigation bar styling.

= 1.2.0 =
* Improved: Email notifications now support multiple recipients, allowing selection based on user roles, individual users, custom email addresses, or merge tags.
* Improved: Added the ability to exclude email addresses from notifications, with options to exclude based on user roles, individual users, custom email addresses, or merge tags.

= 1.1.4 =
* Feature: Added the ability to delete logs by selected IDs.
* Feature: Added the ability to delete notification logs by selected IDs.

= 1.1.3 =
* Fixed: Issue when updating notifications.

= 1.1.2 =
* Added: Background processing for notifications to improve performance.

= 1.1.1 =
* Fixed: Some Admin UI issues

= 1.1.0 =
* Added: Facebook integration
* Added: Twitter integration
* Added: Zapier integration
* Added: Slack integration
* Added: Make (formerly Integromat) integration
* Added: WooCommerce triggers

= 1.0.1 =
* Added: Support for WordPress 6.6
* Added: Documentation links

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.8.0 =
Requires PHP 7.4 or newer. Adds support for AI assistants (WordPress Abilities API and MCP) and WordPress 7.1 compatibility.

= 1.6.5 =
Notification Master 1.6.5 introduces an intelligent auto-delete system for failed web push subscriptions. This major enhancement automatically maintains clean subscriber lists by removing inactive subscriptions after configurable failure thresholds, significantly improving notification delivery rates and database performance. The system intelligently resets failure counts when notifications succeed, ensuring only truly inactive subscriptions are removed.