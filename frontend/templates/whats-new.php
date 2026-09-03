<?php
/**
 * "What's new" page: CONTENT partial, rendered inside the active theme via
 * RTG_Theme_Render. No document shell and no global resets; everything is
 * scoped under .rtg-wn so it doesn't fight the theme. The release list
 * markup comes from RTG_Whats_New::render_list(), the same view data the
 * guide's modal renders, so the two can't drift.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$rtg_wn_view      = RTG_Whats_New::to_view( RTG_Whats_New::get_releases() );
$rtg_wn_guide_url = RTG_Tire_Page::guide_url();
?>
<style>
.rtg-wn { --rtg-accent: #fba919; --rtg-bg-card: #16191e; --rtg-bg-deep: #121418; --rtg-border: #3a3e45; --rtg-text: #ece9e4; --rtg-text-heading: #f6f4f0; --rtg-text-muted: #a19e97; --rtg-positive: #4ade80;
  max-width: 760px; margin: 0 auto; padding: 8px 0 40px; color: var(--rtg-text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; line-height: 1.55; }
.rtg-wn * { box-sizing: border-box; }
.rtg-wn a { color: var(--rtg-accent); text-decoration: none; }
.rtg-wn a:hover { text-decoration: underline; }
.rtg-wn-crumb { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--rtg-text-muted); margin-bottom: 20px; }
.rtg-wn-crumb i { font-size: 12px; }
.rtg-wn-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: var(--rtg-accent); margin: 0 0 6px; }
.rtg-wn h1 { font-size: 32px; font-weight: 600; line-height: 1.2; color: var(--rtg-text-heading); margin: 0 0 10px; }
.rtg-wn-lede { font-size: 15px; color: var(--rtg-text-muted); margin: 0 0 28px; max-width: 560px; }
.rtg-wn-release { background: var(--rtg-bg-card); border: 1px solid var(--rtg-border); border-radius: 12px; padding: 20px 24px; margin-bottom: 16px; }
.rtg-wn-release-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.rtg-wn-version { display: inline-flex; align-items: center; height: 30px; padding: 0 12px; border-radius: 20px; font-size: 12px; font-weight: 700; font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace; color: var(--rtg-accent); background: color-mix(in srgb, var(--rtg-accent) 12%, var(--rtg-bg-deep)); border: 1px solid color-mix(in srgb, var(--rtg-accent) 30%, transparent); }
.rtg-wn-date { font-size: 13px; font-weight: 500; color: var(--rtg-text-muted); }
.rtg-wn-latest { display: inline-flex; align-items: center; height: 22px; padding: 0 8px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: #15130e; background: var(--rtg-positive); }
.rtg-wn-intro { font-size: 15px; color: var(--rtg-text-heading); margin: 0 0 12px; }
.rtg-wn-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.rtg-wn-list li { position: relative; padding-left: 18px; font-size: 15px; }
.rtg-wn-list li::before { content: ""; position: absolute; left: 4px; top: 10px; width: 6px; height: 6px; border-radius: 50%; background: var(--rtg-accent); }
.rtg-wn-list strong { color: var(--rtg-text-heading); font-weight: 600; }
.rtg-wn code { font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace; font-size: 13px; padding: 1px 6px; border-radius: 4px; background: var(--rtg-bg-deep); border: 1px solid var(--rtg-border); }
.rtg-wn-empty { color: var(--rtg-text-muted); }
.rtg-wn-foot { margin-top: 24px; font-size: 13px; color: var(--rtg-text-muted); }
@media (max-width: 600px) {
  .rtg-wn h1 { font-size: 26px; }
  .rtg-wn-release { padding: 16px; border-radius: 10px; }
  .rtg-wn-list li, .rtg-wn-intro, .rtg-wn-lede { font-size: 14px; }
}
@media (prefers-reduced-motion: reduce) { .rtg-wn * { transition: none !important; } }
</style>
<div class="rtg-wn">
  <a class="rtg-wn-crumb" href="<?php echo esc_url( $rtg_wn_guide_url ); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to the Tire Guide</a>
  <p class="rtg-wn-eyebrow">Rivian Tire Guide</p>
  <h1>What's new</h1>
  <p class="rtg-wn-lede">Every change you can see in the guide, newest first, in plain language. Bigger ideas we are still working on are on the way.</p>
  <?php echo RTG_Whats_New::render_list( $rtg_wn_view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while rendering ?>
  <p class="rtg-wn-foot">Spotted something off, or have an idea for the guide? Leave a review on any tire, or reach us through the site.</p>
</div>
