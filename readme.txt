=== ShapeLMS – Courses, Quizzes & Online Learning ===
Contributors: themewant
Tags: lms, courses, elearning, quiz, education
Requires at least: 6.5
Tested up to: 6.8
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

ShapeLMS only connects to third-party services when you enable them in Settings → Selling:

* **Stripe** – when Stripe is enabled, payment details are sent to Stripe to process card payments, and the Stripe.js script is loaded on the checkout page. [Terms](https://stripe.com/legal) · [Privacy](https://stripe.com/privacy)
* **PayPal** – when PayPal is enabled, order details are sent to PayPal to process payments, and the PayPal JS SDK is loaded on the checkout page. [Terms](https://www.paypal.com/legalhub/home) · [Privacy](https://www.paypal.com/privacy)
* **Video embeds** – lessons that use YouTube, Vimeo or embed codes load the video from that provider.

No data is sent to the plugin author.

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
