=== ShapeLMS ===
Contributors: themewant
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
* REST API, WP-CLI commands and course import/export
* GDPR export and erasure, translation-ready, RTL-ready, works with any theme

== External services ==

ShapeLMS only connects to third-party services when you enable them or add their content. No data is sent to the plugin author.

**Stripe** (optional, Settings → Selling). When Stripe is enabled, the checkout page loads Stripe.js from https://js.stripe.com and, when a student pays, the plugin sends the order amount, currency, order number, the buyer's billing email, your site name and site URL to the Stripe API to create and confirm the payment. Card details are entered in Stripe's own form and never reach your server. Stripe may also call your site's webhook endpoint. [Terms](https://stripe.com/legal/ssa) · [Privacy](https://stripe.com/privacy)

**PayPal** (optional, Settings → Selling). When PayPal is enabled, the checkout page loads the PayPal JS SDK from https://www.paypal.com and, when a student pays, the plugin sends the order amount, currency, order number and your site name to the PayPal Orders API to create and capture the payment, and calls PayPal's webhook verification endpoint to validate notifications. [Terms](https://www.paypal.com/legalhub/home) · [Privacy](https://www.paypal.com/privacy)

**YouTube** (when an instructor adds a YouTube lesson or intro video). The video is embedded from https://www.youtube-nocookie.com; the visitor's browser requests the video ID from YouTube, which receives the visitor's IP address and browser data. [Terms](https://www.youtube.com/t/terms) · [Privacy](https://policies.google.com/privacy)

**Vimeo** (when an instructor adds a Vimeo lesson or intro video). The video is embedded from https://player.vimeo.com with "do not track" enabled; Vimeo receives the visitor's IP address and browser data. [Terms](https://vimeo.com/terms) · [Privacy](https://vimeo.com/privacy)

**Other video providers.** If an instructor pastes a URL from another oEmbed provider, WordPress contacts that provider to fetch the embed, and the visitor's browser loads the player from it.

**Course import.** When an administrator imports a course JSON file that references image URLs, the plugin downloads those images into the media library.

== Source code ==

The JavaScript for the admin app, course builder and blocks is shipped unminified in the `src/` folder; the built files are in `assets/build/`. To rebuild: `npm install && npm run build`.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate it. The setup wizard opens automatically.
3. Create your first course under ShapeLMS → Add New Course.

== Frequently Asked Questions ==

= Does the free version limit the number of courses or students? =

No. There are no limits and no locked features.

= Can I use my own theme? =

Yes. Templates can be overridden by copying them to `your-theme/shapelms/`.

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
