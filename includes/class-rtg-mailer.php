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
