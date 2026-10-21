# ShapeLMS — project rules

WordPress LMS plugin. Free core on wordpress.org (`shapelms`). Pro plugin (`shapelms-pro`) and add-ons come later and must never ship code inside this folder.

## Stack
- PHP 7.4+ (tested on 8.1–8.5), namespace `ShapeLMS\`, PSR-4 from `includes/`, Composer autoload with a built-in fallback.
- WordPress 6.6+, WooCommerce optional.
- Admin app and course builder: React with `@wordpress/scripts`, `@wordpress/components`, `@wordpress/data`. JS source in `src/`, built to `assets/build/`.
- Front end: PHP templates in `templates/` (overridable from `theme/shapelms/`) + small vanilla JS in `assets/js/`.
- Action Scheduler is used when present (WooCommerce ships it); otherwise WP-Cron.

## Architecture rules
- Content = post types: `shapelms_course`, `shapelms_topic`, `shapelms_lesson`, `shapelms_quiz`, `shapelms_question`. Topics have `post_parent` = course; lessons and quizzes have `post_parent` = topic; `menu_order` gives the order.
- High-volume data = custom tables `{$wpdb->prefix}shapelms_*` with versioned migrations in `includes/Database/Migrations/`. Never store enrollments, progress or attempts in post meta or user meta.
- Every feature is a REST endpoint under `/wp-json/shapelms/v1/`. React talks only to REST.
- Business logic lives in `includes/Services/`; controllers and templates stay thin.
- Fire typed events through `ShapeLMS\Core\Events` (`shapelms_course_enrolled`, `shapelms_lesson_completed`, `shapelms_course_completed`, `shapelms_quiz_finished`, `shapelms_order_completed`). Every event is also written to the `activity_log` table.
- Modules register on the `shapelms_register_modules` filter; each can be switched on/off from Settings → Modules.
- Free must be fully usable (wp.org Guideline 2: no trialware, no locked features, no nag screens).

## Security and output
- No inline CSS and no inline JS. Everything is enqueued from files in `assets/`. Data goes to JS through `data-*` attributes on the root element, never `wp_add_inline_script` or `wp_localize_script`.
- Sanitize all input, escape all output, nonce + capability check on every write, `$wpdb->prepare` for every query with values.
- Uploaded files live in a protected folder and are served through a permission-checked endpoint.

## Code standards
- WordPress Coding Standards (`composer lint`), PHPStan level 6 (`composer phpstan`).
- Prefix everything: functions `shapelms_`, hooks `shapelms_`, options `shapelms_`, CSS `.shapelms-`, constants `SHAPELMS_`.
- Every user-facing string translatable with text domain `shapelms`.
- Assets load only on ShapeLMS screens and LMS front-end pages.
- PHPUnit tests for services and repositories in `tests/phpunit/`.

## When you finish a task
- Run `composer lint`, `composer phpstan`, `npm run build`.
- List what changed, what to check manually, and any open questions.
