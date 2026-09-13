<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$rtg_settings    = get_option( 'rtg_settings', array() );
$rtg_theme       = $rtg_settings['theme_colors'] ?? array();
$review_slug     = $rtg_settings['tire_review_slug'] ?? 'tire-review';
$rtg_var_map     = array(
    'accent'       => '--rtg-accent',
    'accent_hover' => '--rtg-accent-hover',
    'bg_primary'   => '--rtg-bg-primary',
    'bg_card'      => '--rtg-bg-card',
    'bg_input'     => '--rtg-bg-input',
    'bg_deep'      => '--rtg-bg-deep',
    'text_primary' => '--rtg-text-primary',
    'text_light'   => '--rtg-text-light',
    'text_muted'   => '--rtg-text-muted',
    'text_heading' => '--rtg-text-heading',
    'border'       => '--rtg-border',
    'star_filled'  => '--rtg-star-filled',
    'star_user'    => '--rtg-star-user',
    'star_empty'   => '--rtg-star-empty',
);
$rtg_css_vars = '';
foreach ( $rtg_var_map as $key => $prop ) {
    if ( ! empty( $rtg_theme[ $key ] ) ) {
        $safe_color = sanitize_hex_color( $rtg_theme[ $key ] );
        if ( $safe_color ) {
            $rtg_css_vars .= $prop . ':' . $safe_color . ';';
        }
    }
}

// OG meta — tire-specific when deep-linked.
$og_title       = 'Review a Tire — Rivian Tire Guide';
$og_description = 'Share your experience with tires on your Rivian. Select a tire and write a review to help fellow Rivian owners.';
$og_image       = '';
$og_url         = home_url( '/' . sanitize_title( $review_slug ) . '/' );

$preselected_id = isset( $_GET['tire'] ) ? sanitize_text_field( wp_unslash( $_GET['tire'] ) ) : '';
if ( $preselected_id && preg_match( '/^[A-Za-z0-9_-]+$/', $preselected_id ) ) {
    $og_tire = RTG_Database::get_tire( $preselected_id );
    if ( $og_tire ) {
        $brand = $og_tire['brand'] ?? '';
        $model = $og_tire['model'] ?? '';
        $size  = $og_tire['size'] ?? '';
        $og_title = 'Review ' . trim( "$brand $model" );
        if ( $size ) {
            $og_title .= " ($size)";
        }
        $og_title .= ' — Rivian Tire Guide';
        $og_description = "Share your experience with the $brand $model" . ( $size ? " ($size)" : '' ) . ' on your Rivian.';
        $og_image = ! empty( $og_tire['image'] ) ? esc_url( $og_tire['image'] ) : '';
        $og_url = add_query_arg( 'tire', rawurlencode( $preselected_id ), $og_url );
    }
}

// Find the tire guide page URL for back links.
$tire_guide_url = home_url( '/' );
$guide_pages = get_posts( array(
    'post_type'   => 'page',
    'post_status' => 'publish',
    's'           => '[rivian_tire_guide]',
    'numberposts' => 1,
    'fields'      => 'ids',
) );
if ( ! empty( $guide_pages ) ) {
    $tire_guide_url = get_permalink( $guide_pages[0] );
}
?>
<?php if ( $rtg_css_vars ) : ?>
<style>.rv-root{<?php echo $rtg_css_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized hex above ?>}</style>
<?php endif; ?>

<div class="rtg-embed rv-root">
  <!-- Content -->
  <div class="rv-page">
    <nav class="rv-breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
      <span aria-hidden="true">&rsaquo;</span>
      <a href="<?php echo esc_url( $tire_guide_url ); ?>">Tire Guide</a>
      <span aria-hidden="true">&rsaquo;</span>
      <span aria-current="page">Write a Review</span>
    </nav>

    <h1 class="rv-title">Review a Tire</h1>
    <p class="rv-subtitle" id="rvSubtitle">No account needed. Pick the tire you drove on, rate it, and it goes live after a quick check.</p>

    <!-- Tire search -->
    <div class="rv-search-wrap">
      <svg class="rv-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <label for="rvTireSearch" class="rv-sr-only">Search for a tire</label>
      <input type="text" class="rv-search" id="rvTireSearch" placeholder="Search by brand, model, or size..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="rvDropdown" aria-haspopup="listbox" />
      <div class="rv-dropdown" id="rvDropdown" role="listbox" aria-label="Matching tires"></div>
    </div>

    <!-- Landing: a way in that is not a blank search box -->
    <div class="rv-landing" id="rvLanding">
      <div class="rv-landing-head">
        <span class="rv-eyebrow">Or start from your Rivian</span>
        <div class="rv-seg" id="rvVehicleSwitch" role="radiogroup" aria-label="Your Rivian"></div>
      </div>
      <div class="rv-popular" id="rvPopular"></div>
      <div class="rv-welcome" id="rvWelcome" hidden>
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span id="rvWelcomeText"></span>
        <button type="button" class="rv-link-btn" id="rvWelcomeClear">Not you? Clear</button>
      </div>
    </div>

    <!-- Selected tire display -->
    <div class="rv-tire-card" id="rvTireCard">
      <div class="rv-tire-img" id="rvTireImg"></div>
      <div class="rv-tire-info">
        <div class="rv-tire-brand" id="rvTireBrand"></div>
        <div class="rv-tire-model" id="rvTireModel"></div>
        <div class="rv-tire-chips">
          <span class="rv-chip rv-chip-size" id="rvTireSize"></span>
          <span class="rv-chip" id="rvTireCategory"></span>
        </div>
        <div class="rv-tire-rating-row">
          <div class="rv-tire-stars" id="rvTireStars"></div>
          <span class="rv-tire-rating-text" id="rvTireRatingText"></span>
        </div>
        <div class="rv-tire-change">
          <button type="button" class="rv-link-btn rv-link-muted" id="rvChangeTire">Change tire</button>
          <a class="rv-link-btn" id="rvTirePageLink" href="#" hidden>See its page</a>
        </div>
      </div>
    </div>

    <!-- Review form -->
    <form class="rv-form" id="rvForm" novalidate>
      <div class="rv-form-header">
        <h2 id="rvFormTitle">Write Your Review</h2>
        <span class="rv-form-time">About 2 minutes</span>
      </div>

      <div class="rv-banner rv-banner-signed" id="rvSignedBanner" hidden>
        <span id="rvSignedText"></span>
        <span class="rv-banner-aside">No name or email to type</span>
      </div>
      <div class="rv-banner rv-banner-existing" id="rvExistingBanner" role="status" aria-live="polite" hidden></div>

      <!-- 1. Overall rating -->
      <section class="rv-section">
        <div class="rv-section-head"><span class="rv-step">1</span><span class="rv-section-title">Overall rating</span></div>
        <div class="rv-stars-section">
          <div class="rv-stars-select" id="rvStarsSelect" role="radiogroup" aria-label="Overall rating"></div>
          <span class="rv-star-text" id="rvStarText">Select a rating</span>
          <span class="rv-hint">Tap a star. This is the only required part.</span>
          <div class="rv-field-error" id="rvStarError" role="alert"></div>
        </div>
      </section>

      <!-- 2. Detail ratings -->
      <section class="rv-section">
        <div class="rv-section-head"><span class="rv-step">2</span><span class="rv-section-title">Rate the details</span><span class="rv-section-hint">optional, anything you skip is left blank</span></div>
        <div class="rv-axes" id="rvAxes"></div>
      </section>

      <!-- 3. Setup -->
      <section class="rv-section">
        <div class="rv-section-head"><span class="rv-step">3</span><span class="rv-section-title">Your setup</span><span class="rv-section-hint">optional, but it makes the review count for more</span></div>
        <div class="rv-grid-2">
          <div class="rv-field">
            <span class="rv-label" id="rvVehicleLabel">Which Rivian</span>
            <div class="rv-seg rv-seg-full" id="rvVehiclePick" role="radiogroup" aria-labelledby="rvVehicleLabel"></div>
          </div>
          <div class="rv-field">
            <label class="rv-label" for="rvMiles">Miles on this set</label>
            <input type="text" inputmode="numeric" id="rvMiles" class="rv-input" placeholder="e.g. 6,400" maxlength="9" autocomplete="off" />
          </div>
        </div>
        <label class="rv-owner" for="rvOwner">
          <span class="rv-toggle"><input type="checkbox" id="rvOwner" /><span class="rv-toggle-track" aria-hidden="true"><span class="rv-toggle-knob"></span></span></span>
          <span class="rv-owner-text">
            <span class="rv-owner-title">I own this tire <i class="fa-solid fa-certificate" aria-hidden="true"></i></span>
            <span class="rv-hint" id="rvOwnerHint">Adds a verified-owner badge to your review. Tied to your email, no account needed.</span>
          </span>
        </label>
      </section>

      <!-- 4. Words -->
      <section class="rv-section">
        <div class="rv-section-head"><span class="rv-step">4</span><span class="rv-section-title">In your words</span></div>
        <div class="rv-field">
          <label class="rv-label" for="rvReviewTitle">Title <span class="rv-label-hint" id="rvTitleHint">optional</span></label>
          <input type="text" id="rvReviewTitle" class="rv-input" placeholder="Sum up your experience..." maxlength="200" />
        </div>
        <div class="rv-field">
          <label class="rv-label" for="rvReviewText">Review <span class="rv-label-hint" id="rvTextHint">optional</span></label>
          <textarea id="rvReviewText" class="rv-textarea" placeholder="How does it handle, how loud is it, how is it wearing, did your range change?" maxlength="5000" rows="5"></textarea>
          <div class="rv-under">
            <span class="rv-hint">Useful to mention: what you replaced, how many miles, what surprised you.</span>
            <span class="rv-char-count" id="rvCharCount">0/5000</span>
          </div>
          <div class="rv-field-error" id="rvWordsError" role="alert"></div>
        </div>
      </section>

      <!-- 5. About you (guests) -->
      <section class="rv-section rv-guest-section" id="rvGuestSection">
        <div class="rv-section-head"><span class="rv-step">5</span><span class="rv-section-title">About you</span></div>
        <div class="rv-grid-2">
          <div class="rv-field">
            <label class="rv-label" for="rvGuestName">Name <span class="rv-label-hint">shown with your review</span></label>
            <input type="text" id="rvGuestName" class="rv-input" placeholder="How it appears on the review" maxlength="100" autocomplete="name" />
            <div class="rv-field-error" id="rvNameError" role="alert"></div>
          </div>
          <div class="rv-field">
            <label class="rv-label" for="rvGuestEmail">Email</label>
            <input type="email" id="rvGuestEmail" class="rv-input" placeholder="you@example.com" maxlength="254" autocomplete="email" />
            <span class="rv-hint">Only used to tell you when the review is live. Never shown.</span>
            <div class="rv-field-error" id="rvEmailError" role="alert"></div>
          </div>
        </div>
        <div class="rv-under rv-guest-under">
          <span class="rv-hint" id="rvRememberNote" hidden>Remembered from your last review on this device.</span>
          <span class="rv-hint">Have an account? <a id="rvSignIn" href="<?php echo esc_url( wp_login_url() ); ?>">Sign in</a></span>
        </div>
      </section>

      <input type="text" name="website" class="rv-hp" tabindex="-1" autocomplete="off" />

      <!-- Submit -->
      <div class="rv-form-footer">
        <div class="rv-footer-text">
          <span class="rv-footer-note" id="rvFooterNote">Goes live after a quick check, usually the same day. We email you when it is up.</span>
          <div class="rv-error" id="rvError" role="alert"></div>
        </div>
        <button type="submit" class="rv-btn-submit" id="rvSubmitBtn">Submit Review</button>
      </div>
    </form>

    <!-- Success state -->
    <div class="rv-success" id="rvSuccess">
      <div class="rv-success-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
      <div class="rv-success-title" id="rvSuccessTitle">Review Submitted!</div>
      <div class="rv-success-text" id="rvSuccessText">Thanks for sharing your experience.</div>
      <div class="rv-success-recap" id="rvSuccessRecap" hidden></div>
      <div class="rv-success-actions">
        <a href="#" class="rv-btn rv-btn-primary" id="rvSuccessTireLink" hidden>See the tire page</a>
        <button type="button" class="rv-btn" id="rvReviewAnother">Review another tire</button>
        <a href="<?php echo esc_url( $tire_guide_url ); ?>" class="rv-btn">Browse tires</a>
      </div>
    </div>
  </div>
</div>
<?php
// End tire review content partial.
