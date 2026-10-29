=== ShapeLMS ===
Contributors: shapekode22
Tags: lms, courses, elearning, quiz, education
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast, modern learning management system. Build courses with lessons and quizzes, enroll students, track progress and sell courses.

== Description ==

ShapeLMS turns your WordPress site into an online school. It is built on custom database tables so it stays fast with thousands of students, and every feature is available in the free plugin.

**Course building**

* Drag-and-drop course builder with topics, lessons and quizzes
* Video lessons (YouTube, Vimeo, self-hosted, embed), attachments and free preview lessons
* Course level, duration, requirements, outcomes, target audience and intro video
* Course categories and tags
* Gutenberg blocks and Elementor widgets

**Quizzes**

* Single choice, multiple choice, true/false, short answer and fill-in-the-blank questions
* Time limits, attempt limits, pass marks, random order and instant or end-of-quiz feedback
* Server-side grading; answers are never sent to the browser before submission

**Students**

* Enrollment, progress tracking, resume where you left off
* Sequential learning (lock lessons until the previous one is done) and prerequisite courses
* Front-end Sign in, Sign up, forgot-password and reset-password pages, so students never see wp-login.php
* Student dashboard with courses, quiz attempts, wishlist, purchases and profile
* Course reviews, per-lesson Q&A and announcements

**Instructors**

* Instructor registration with admin approval
* Instructor dashboard with courses, students, Q&A and announcements
* Multiple instructors per course

**Selling**

* Free courses, or paid courses through the built-in checkout (Stripe and PayPal) or WooCommerce

**Core**

* Email notifications with editable templates
* Reports: enrollments, completions and revenue
* Course archive with search, category, level, price and sort filters
* REST API, WP-CLI commands, course import/export and a six-course sample pack
* GDPR export and erasure, translation-ready, RTL-ready, works with any theme

== External services ==

ShapeLMS only connects to third-party services when you enable them or add their content. No data is sent to the plugin author.

**Stripe** (optional, Settings → Selling). When Stripe is enabled, the checkout page loads Stripe.js from https://js.stripe.com and, when a student pays, the plugin sends the order amount, currency, order number, the buyer's billing email, your site name and site URL to the Stripe API to create and confirm the payment. Card details are entered in Stripe's own form and never reach your server. Stripe may also call your site's webhook endpoint. [Terms](https://stripe.com/legal/ssa) · [Privacy](https://stripe.com/privacy)

**PayPal** (optional, Settings → Selling). When PayPal is enabled, the checkout page loads the PayPal JS SDK from https://www.paypal.com and, when a student pays, the plugin sends your PayPal API credentials to obtain an access token (cached), then the order amount, currency, order number and your site name to the PayPal Orders API to create and capture the payment, and calls PayPal's webhook verification endpoint to validate notifications. [Terms](https://www.paypal.com/legalhub/home) · [Privacy](https://www.paypal.com/privacy)

**YouTube** (when an instructor adds a YouTube lesson or intro video). The video is embedded from https://www.youtube-nocookie.com; the visitor's browser loads the player from YouTube, which receives the visitor's IP address and browser data. [Terms](https://www.youtube.com/t/terms) · [Privacy](https://policies.google.com/privacy)

**Vimeo** (when an instructor adds a Vimeo lesson or intro video). The video is embedded from https://player.vimeo.com with "do not track" enabled; Vimeo receives the visitor's IP address and browser data. [Terms](https://vimeo.com/terms) · [Privacy](https://vimeo.com/privacy)

**Other video providers.** If an instructor pastes a URL from another provider that WordPress supports for oEmbed, your server requests the embed code from that provider (cached for a day) and the visitor's browser loads the player from it. The provider receives your server's IP address and the video URL, and the visitor's IP address and browser data.

**Course import.** Only when an administrator imports a course JSON file that references image URLs: your server downloads each image into the media library. The host of each image receives your server's IP address and your site URL (WordPress's standard HTTP user agent). No other data is sent.

**Sample courses.** If you install the sample course pack (ShapeLMS → Tools → Import / Export), its lessons and intro videos are embedded from YouTube and Vimeo. Visitors who open them connect to those services as described above.

== Source code ==

The JavaScript for the admin app, course builder and blocks is shipped unminified in the `src/` folder; the built files are in `assets/build/`. To rebuild: `npm install && npm run build`.

== Credits ==

The course builder uses [dnd kit](https://github.com/clauderic/dnd-kit) (MIT license) for drag and drop.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate it. The setup wizard opens automatically. Activation creates five pages: Dashboard, Checkout, Become an Instructor, Sign in and Sign up. You can assign other pages in ShapeLMS → Settings → General.
3. Create your first course under ShapeLMS → Courses → New course.

== Frequently Asked Questions ==

= Does the free version limit the number of courses or students? =

No. There are no limits and no locked features.

= Can I use my own theme? =

Yes. Templates can be overridden by copying them to `your-theme/shapelms/`.

= What happens when I delete the plugin? =

Nothing is deleted unless ShapeLMS → Settings → General → "Delete all data on uninstall" is checked. When it is, courses, enrollments, progress, orders and the plugin's pages and settings are removed.

= Does it work with WooCommerce? =

Yes. Choose WooCommerce as the commerce engine in Settings → Selling and link each course to a product.

== Screenshots ==

1. Course builder
2. Course page
3. Lesson player
4. Quiz
5. Student dashboard
6. Admin reports

== Changelog ==

= 1.0.0 =
* Initial release.
