<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles email notifications for the Rivian Tire Guide plugin.
 *
 * Uses WordPress wp_mail() so any SMTP plugin the site owner has
 * configured will be respected automatically.
 *
 * @since 1.19.0
 */
class RTG_Mailer {

    /**
     * Send an approval notification email to a reviewer.
     *
     * @param string $to_email   Recipient email address.
     * @param string $to_name    Recipient display name.
     * @param array  $review     Review row data (rating, review_title, review_text, tire_id).
     * @param array  $tire       Tire data (brand, model).
     * @return bool Whether the email was sent successfully.
     */
    public static function send_approval_notification( $to_email, $to_name, $review, $tire ) {
        if ( ! is_email( $to_email ) ) {
            return false;
        }

        $tire_name = trim( ( $tire['brand'] ?? '' ) . ' ' . ( $tire['model'] ?? '' ) );
        if ( empty( $tire_name ) ) {
            $tire_name = $review['tire_id'] ?? 'a tire';
        }

        $review_url = self::get_tire_guide_url( $review['tire_id'] ?? '' );

        $subject = 'Your review for ' . $tire_name . ' has been approved!';

        $stars_html = self::render_stars_html( (int) ( $review['rating'] ?? 0 ) );

        $review_snippet = '';
        if ( ! empty( $review['review_title'] ) ) {
            $review_snippet .= '<p style="margin: 0 0 8px 0; font-weight: 600; color: #1d1d1f;">' . esc_html( $review['review_title'] ) . '</p>';
        }
        if ( ! empty( $review['review_text'] ) ) {
            $text = mb_strlen( $review['review_text'] ) > 300
                ? mb_substr( $review['review_text'], 0, 300 ) . '...'
                : $review['review_text'];
            $review_snippet .= '<p style="margin: 0; color: #6e6e73; font-size: 14px;">' . esc_html( $text ) . '</p>';
        }

        $body = self::build_html_email(
            esc_html( $to_name ),
            esc_html( $tire_name ),
            $stars_html,
            $review_snippet,
            esc_url( $review_url )
        );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $site_name = get_bloginfo( 'name' );
        if ( $site_name ) {
            $from_email = get_option( 'admin_email' );
            $headers[]  = 'From: ' . $site_name . ' <' . $from_email . '>';
        }

        return wp_mail( $to_email, $subject, $body, $headers );
    }

    /**
     * Notify the site admin that a new guest review needs moderation.
     *
     * @param string $guest_name  Guest display name.
     * @param string $guest_email Guest email address.
     * @param array  $review      Review data (tire_id, rating, review_title, review_text).
     * @return bool Whether the email was sent successfully.
     */
    public static function send_admin_guest_review_notification( $guest_name, $guest_email, $review ) {
        $admin_email = get_option( 'admin_email' );
        if ( ! $admin_email ) {
            return false;
        }

        $tire_id   = $review['tire_id'] ?? '';
        $tire      = RTG_Database::get_tire( $tire_id );
        $tire_name = $tire ? trim( ( $tire['brand'] ?? '' ) . ' ' . ( $tire['model'] ?? '' ) ) : $tire_id;

        $subject = 'New guest review pending approval — ' . $tire_name;

        $stars_html = self::render_stars_html( (int) ( $review['rating'] ?? 0 ) );

        $review_snippet = '';
        if ( ! empty( $review['review_title'] ) ) {
            $review_snippet .= '<p style="margin: 0 0 8px 0; font-weight: 600; color: #1d1d1f;">' . esc_html( $review['review_title'] ) . '</p>';
        }
        if ( ! empty( $review['review_text'] ) ) {
            $text = mb_strlen( $review['review_text'] ) > 500
                ? mb_substr( $review['review_text'], 0, 500 ) . '...'
                : $review['review_text'];
            $review_snippet .= '<p style="margin: 0; color: #6e6e73; font-size: 14px;">' . esc_html( $text ) . '</p>';
        }

        $reviews_admin_url = admin_url( 'admin.php?page=rtg-reviews' );

        $site_name = esc_html( get_bloginfo( 'name' ) );

        $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; background-color: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f7; padding: 40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
  <tr>
    <td style="background-color: #1d1d1f; padding: 24px 32px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">' . $site_name . '</h1>
    </td>
  </tr>
  <tr>
    <td style="padding: 32px;">
      <h2 style="margin: 0 0 16px 0; color: #1d1d1f; font-size: 22px; font-weight: 600;">New guest review needs approval</h2>
      <p style="margin: 0 0 20px 0; color: #6e6e73; font-size: 16px; line-height: 1.5;">
        <strong style="color: #1d1d1f;">' . esc_html( $guest_name ) . '</strong> (' . esc_html( $guest_email ) . ') submitted a review for <strong style="color: #1d1d1f;">' . esc_html( $tire_name ) . '</strong>.
      </p>
      <div style="background-color: #f5f5f7; border-radius: 8px; padding: 20px; margin: 0 0 24px 0;">
        <div style="margin-bottom: 12px;">' . $stars_html . '</div>
        ' . $review_snippet . '
      </div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . esc_url( $reviews_admin_url ) . '" style="display: inline-block; background-color: #0071e3; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-size: 16px; font-weight: 600;">Review in Dashboard</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style="padding: 20px 32px; border-top: 1px solid #e5e5e5; text-align: center;">
      <p style="margin: 0; color: #86868b; font-size: 12px;">
        This email was sent because a guest submitted a tire review on ' . $site_name . '.
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>';

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $site_name ) {
            $headers[] = 'From: ' . $site_name . ' <' . $admin_email . '>';
        }

        return wp_mail( $admin_email, $subject, $body, $headers );
    }

    /**
     * Notify the site admin about broken affiliate links detected during a health check.
     *
     * @param array $results Link check results from RTG_Link_Checker::run().
     * @return bool Whether the email was sent successfully.
     */
    public static function send_broken_links_notification( $results ) {
        $admin_email = get_option( 'admin_email' );
        if ( ! $admin_email ) {
            return false;
        }

        $broken = $results['broken'] ?? array();
        if ( empty( $broken ) ) {
            return false;
        }

        $count   = count( $broken );
        $subject = sprintf( 'Tire Guide: %d broken affiliate %s detected', $count, $count === 1 ? 'link' : 'links' );

        $site_name        = esc_html( get_bloginfo( 'name' ) );
        $affiliate_admin  = admin_url( 'admin.php?page=rtg-affiliate-links' );

        // Build the broken links table rows.
        $rows_html = '';
        foreach ( $broken as $entry ) {
            $tire_name = esc_html( trim( ( $entry['brand'] ?? '' ) . ' ' . ( $entry['model'] ?? '' ) ) );
            $reason    = esc_html( $entry['reason'] ?? 'Unknown' );
            $status    = esc_html( $entry['status'] ?? '' );

            $status_label = 'Error';
            $status_color = '#ef4444';
            if ( $status === 'redirect_homepage' ) {
                $status_label = 'Redirect to Homepage';
                $status_color = '#f59e0b';
            } elseif ( $status === 'http_error' ) {
                $status_label = 'HTTP Error';
            }

            $rows_html .= '<tr>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #1d1d1f;">' . $tire_name . '</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; color: #fff; background: ' . $status_color . ';">' . $status_label . '</span>
                </td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 13px; color: #6e6e73;">' . $reason . '</td>
            </tr>';
        }

        $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; background-color: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f7; padding: 40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
  <tr>
    <td style="background-color: #1d1d1f; padding: 24px 32px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">' . $site_name . '</h1>
    </td>
  </tr>
  <tr>
    <td style="padding: 32px;">
      <h2 style="margin: 0 0 16px 0; color: #1d1d1f; font-size: 22px; font-weight: 600;">Broken Affiliate Links Detected</h2>
      <p style="margin: 0 0 20px 0; color: #6e6e73; font-size: 16px; line-height: 1.5;">
        The weekly link health check found <strong style="color: #1d1d1f;">' . esc_html( $count ) . ' broken ' . ( $count === 1 ? 'link' : 'links' ) . '</strong> out of ' . esc_html( $results['total'] ?? 0 ) . ' checked. These links may be redirecting visitors to the supplier homepage instead of the product page.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden; margin: 0 0 24px 0;">
        <thead>
          <tr style="background-color: #f5f5f7;">
            <th style="padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #1d1d1f; border-bottom: 1px solid #e5e5e5;">Tire</th>
            <th style="padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #1d1d1f; border-bottom: 1px solid #e5e5e5;">Status</th>
            <th style="padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #1d1d1f; border-bottom: 1px solid #e5e5e5;">Details</th>
          </tr>
        </thead>
        <tbody>
          ' . $rows_html . '
        </tbody>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . esc_url( $affiliate_admin ) . '" style="display: inline-block; background-color: #0071e3; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-size: 16px; font-weight: 600;">Update Links in Dashboard</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style="padding: 20px 32px; border-top: 1px solid #e5e5e5; text-align: center;">
      <p style="margin: 0; color: #86868b; font-size: 12px;">
        This automated check runs weekly. You can also run it manually from the Affiliate Links page in ' . $site_name . '.
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>';

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $site_name ) {
            $headers[] = 'From: ' . $site_name . ' <' . $admin_email . '>';
        }

        return wp_mail( $admin_email, $subject, $body, $headers );
    }

    /**
     * Notify the site admin about new ambiguous or unmatched Roamer tires.
     *
     * @param array $new_ambiguous Newly detected ambiguous tires.
     * @param array $new_unmatched Newly detected unmatched tires.
     * @return bool Whether the email was sent successfully.
     */
    public static function send_roamer_sync_notification( $new_ambiguous, $new_unmatched ) {
        $admin_email = get_option( 'admin_email' );
        if ( ! $admin_email ) {
            return false;
        }

        $amb_count   = count( $new_ambiguous );
        $unm_count   = count( $new_unmatched );
        $total_count = $amb_count + $unm_count;

        $parts = array();
        if ( $amb_count > 0 ) {
            $parts[] = $amb_count . ' ambiguous';
        }
        if ( $unm_count > 0 ) {
            $parts[] = $unm_count . ' unmatched';
        }

        $subject = 'Tire Guide: ' . implode( ' and ', $parts ) . ' new Roamer ' . ( $total_count === 1 ? 'tire' : 'tires' ) . ' detected';

        $site_name = esc_html( get_bloginfo( 'name' ) );
        $sync_url  = admin_url( 'admin.php?page=rtg-roamer-sync' );

        // Build table rows.
        $rows_html = '';
        foreach ( $new_ambiguous as $tire ) {
            $rows_html .= '<tr>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #1d1d1f;">' . esc_html( $tire['name'] ) . '</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">' . esc_html( $tire['size'] ) . '</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">' . esc_html( number_format( $tire['efficiency'], 2 ) ) . '</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">' . esc_html( number_format( floatval( $tire['total_km'] ?? 0 ), 0 ) ) . ' km</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; color: #fff; background: #f59e0b;">Ambiguous</span>
                </td>
            </tr>';
        }
        foreach ( $new_unmatched as $tire ) {
            $rows_html .= '<tr>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #1d1d1f;">' . esc_html( $tire['name'] ) . '</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">' . esc_html( $tire['size'] ) . '</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">' . esc_html( number_format( $tire['efficiency'], 2 ) ) . '</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">' . esc_html( number_format( floatval( $tire['total_km'] ?? 0 ), 0 ) ) . ' km</td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #e5e5e5; font-size: 14px;">
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; color: #fff; background: #94a3b8;">Unmatched</span>
                </td>
            </tr>';
        }

        $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; background-color: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f7; padding: 40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
  <tr>
    <td style="background-color: #1d1d1f; padding: 24px 32px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">' . $site_name . '</h1>
    </td>
  </tr>
  <tr>
    <td style="padding: 32px;">
      <h2 style="margin: 0 0 16px 0; color: #1d1d1f; font-size: 22px; font-weight: 600;">New Roamer Tires Need Attention</h2>
      <p style="margin: 0 0 20px 0; color: #6e6e73; font-size: 16px; line-height: 1.5;">
        The latest Roamer sync detected <strong style="color: #1d1d1f;">' . esc_html( $total_count ) . ' new ' . ( $total_count === 1 ? 'tire' : 'tires' ) . '</strong> that ' . ( $total_count === 1 ? 'needs' : 'need' ) . ' your review. Ambiguous tires matched multiple guide entries and need manual assignment. Unmatched tires have no match in your guide.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden; margin: 0 0 24px 0;">
        <thead>
          <tr style="background-color: #f5f5f7;">
            <th style="padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #1d1d1f; border-bottom: 1px solid #e5e5e5;">Tire</th>
            <th style="padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #1d1d1f; border-bottom: 1px solid #e5e5e5;">Size</th>
            <th style="padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #1d1d1f; border-bottom: 1px solid #e5e5e5;">mi/kWh</th>
            <th style="padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #1d1d1f; border-bottom: 1px solid #e5e5e5;">Distance</th>
            <th style="padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #1d1d1f; border-bottom: 1px solid #e5e5e5;">Status</th>
          </tr>
        </thead>
        <tbody>
          ' . $rows_html . '
        </tbody>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . esc_url( $sync_url ) . '" style="display: inline-block; background-color: #0071e3; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-size: 16px; font-weight: 600;">Review in Dashboard</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style="padding: 20px 32px; border-top: 1px solid #e5e5e5; text-align: center;">
      <p style="margin: 0; color: #86868b; font-size: 12px;">
        This notification is sent when new tires appear during a Roamer sync. You can disable it from the Roamer Sync settings in ' . $site_name . '.
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>';

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $site_name ) {
            $headers[] = 'From: ' . $site_name . ' <' . $admin_email . '>';
        }

        return wp_mail( $admin_email, $subject, $body, $headers );
    }

    /**
     * Get the tire guide page URL with a tire parameter.
     *
     * @param string $tire_id Tire identifier for deep linking.
     * @return string URL to the tire guide page.
     */
    private static function get_tire_guide_url( $tire_id = '' ) {
        $url = home_url( '/' );

        $guide_pages = get_posts( array(
            'post_type'   => 'page',
            'post_status' => 'publish',
            's'           => '[rivian_tire_guide]',
            'numberposts' => 1,
            'fields'      => 'ids',
        ) );

        if ( ! empty( $guide_pages ) ) {
            $url = get_permalink( $guide_pages[0] );
        }

        if ( ! empty( $tire_id ) ) {
            $url = add_query_arg( 'tire', $tire_id, $url );
        }

        return $url;
    }

    /**
     * Render star rating as HTML for email.
     *
     * @param int $rating Star rating 1-5.
     * @return string HTML string of stars.
     */
    private static function render_stars_html( $rating ) {
        $html = '';
        for ( $i = 1; $i <= 5; $i++ ) {
            $color = $i <= $rating ? '#f59e0b' : '#d2d2d7';
            $html .= '<span style="color: ' . $color . '; font-size: 20px;">&#9733;</span>';
        }
        return $html;
    }

    /**
     * Build the full HTML email body.
     *
     * @param string $name           Reviewer name (escaped).
     * @param string $tire_name      Tire brand + model (escaped).
     * @param string $stars_html     Star rating HTML.
     * @param string $review_snippet Review title/text HTML.
     * @param string $review_url     URL to the tire guide page (escaped).
     * @return string Full HTML email body.
     */
    private static function build_html_email( $name, $tire_name, $stars_html, $review_snippet, $review_url ) {
        $site_name = esc_html( get_bloginfo( 'name' ) );

        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; background-color: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f7; padding: 40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

  <!-- Header -->
  <tr>
    <td style="background-color: #1d1d1f; padding: 24px 32px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">' . $site_name . '</h1>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="padding: 32px;">
      <h2 style="margin: 0 0 16px 0; color: #1d1d1f; font-size: 22px; font-weight: 600;">Your review has been approved!</h2>
      <p style="margin: 0 0 20px 0; color: #6e6e73; font-size: 16px; line-height: 1.5;">
        Hi ' . $name . ', your review for <strong style="color: #1d1d1f;">' . $tire_name . '</strong> has been reviewed and approved. It\'s now live for the community to see!
      </p>

      <!-- Review Card -->
      <div style="background-color: #f5f5f7; border-radius: 8px; padding: 20px; margin: 0 0 24px 0;">
        <div style="margin-bottom: 12px;">' . $stars_html . '</div>
        ' . $review_snippet . '
      </div>

      <!-- CTA Button -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . $review_url . '" style="display: inline-block; background-color: #0071e3; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-size: 16px; font-weight: 600;">View Your Review</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="padding: 20px 32px; border-top: 1px solid #e5e5e5; text-align: center;">
      <p style="margin: 0; color: #86868b; font-size: 12px;">
        This email was sent because you submitted a tire review on ' . $site_name . '.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
    }
}
