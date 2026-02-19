<?php
/**
 * Inline SVG icon system — replaces Font Awesome CDN dependency.
 *
 * All icons are simple SVG paths rendered inline. This eliminates
 * the ~60 KB external CSS + web font download entirely.
 *
 * @since 1.15.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Icons {

    /**
     * SVG icon definitions: name → [ viewBox, path(s) ].
     *
     * Icons are based on common UI patterns (MIT-compatible).
     */
    private static $icons = array(
        // -- UI / Navigation --
        'arrow-left'          => array( '0 0 24 24', '<path d="M19 12H5m0 0l6-6m-6 6l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'arrow-up-right'      => array( '0 0 24 24', '<path d="M7 17L17 7m0 0H7m10 0v10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'xmark'               => array( '0 0 24 24', '<path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'chevron-down'        => array( '0 0 24 24', '<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'chevron-left'        => array( '0 0 24 24', '<path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'chevron-right'       => array( '0 0 24 24', '<path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'check'               => array( '0 0 24 24', '<path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'share'               => array( '0 0 24 24', '<circle cx="18" cy="5" r="3" fill="currentColor"/><circle cx="6" cy="12" r="3" fill="currentColor"/><circle cx="18" cy="19" r="3" fill="currentColor"/><path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" stroke="currentColor" stroke-width="1.5" fill="none"/>' ),
        'print'               => array( '0 0 24 24', '<path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><rect x="6" y="14" width="12" height="8" rx="1" fill="none" stroke="currentColor" stroke-width="2"/>' ),

        // -- Filtering & Search --
        'sliders'             => array( '0 0 24 24', '<path d="M4 21v-7m0-4V3m8 18v-9m0-4V3m8 18v-3m0-4V3M2 14h4M10 8h4M18 16h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>' ),
        'magnifying-glass'    => array( '0 0 24 24', '<circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' ),
        'rotate-left'         => array( '0 0 24 24', '<path d="M2.5 2v6h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.51 15A8.5 8.5 0 1020.49 9H4.51" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' ),
        'dollar-sign'         => array( '0 0 24 24', '<path d="M12 2v20m5-17H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'snowflake'           => array( '0 0 24 24', '<path d="M12 2v20M17 5l-5 5-5-5M17 19l-5-5-5 5M2 12h20M5 7l5 5-5 5M19 7l-5 5 5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),

        // -- Objects / Categories --
        'building'            => array( '0 0 24 24', '<rect x="4" y="2" width="16" height="20" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M9 22V18h6v4M9 6h.01M15 6h.01M9 10h.01M15 10h.01M9 14h.01M15 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' ),
        'circle'              => array( '0 0 24 24', '<circle cx="12" cy="12" r="10" fill="currentColor"/>' ),
        'tags'                => array( '0 0 24 24', '<path d="M9 5H2v7l9 9 7-7-9-9z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M6 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 5h5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>' ),
        'ruler'               => array( '0 0 24 24', '<path d="M16 3l5 5-14 14-5-5L16 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8 15l2 2M11 12l2 2M14 9l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' ),
        'image'               => array( '0 0 24 24', '<rect x="3" y="3" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/><path d="M21 15l-5-5L5 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'scale-balanced'      => array( '0 0 24 24', '<path d="M12 3v18m-9-8l3-8 3 8a5 5 0 01-6 0zm12 0l3-8 3 8a5 5 0 01-6 0zM5 3h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),

        // -- Info & Status --
        'circle-info'         => array( '0 0 24 24', '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4m0-4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' ),
        'circle-check'        => array( '0 0 24 24', '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'triangle-exclamation' => array( '0 0 24 24', '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 9v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' ),

        // -- Actions --
        'heart'               => array( '0 0 24 24', '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z" fill="currentColor"/>' ),
        'heart-outline'       => array( '0 0 24 24', '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z" fill="none" stroke="currentColor" stroke-width="2"/>' ),
        'message'             => array( '0 0 24 24', '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>' ),
        'pen-to-square'       => array( '0 0 24 24', '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>' ),

        // -- Media --
        'circle-play'         => array( '0 0 24 24', '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><polygon points="10,8 16,12 10,16" fill="currentColor"/>' ),
        'newspaper'           => array( '0 0 24 24', '<path d="M19 5v14H5V5h14m0-2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2z" fill="currentColor"/><path d="M14 17H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" fill="currentColor" opacity=".3"/>' ),

        // -- Compare page section icons --
        'gauge-high'          => array( '0 0 24 24', '<path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' ),
        'weight-hanging'      => array( '0 0 24 24', '<circle cx="12" cy="5" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 21l2-13h10l2 13H5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>' ),
        'truck'               => array( '0 0 24 24', '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7V8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="5.5" cy="18.5" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="18.5" cy="18.5" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/>' ),
        'cart-shopping'       => array( '0 0 24 24', '<circle cx="9" cy="21" r="1" fill="currentColor"/><circle cx="20" cy="21" r="1" fill="currentColor"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' ),
    );

    /**
     * Render an inline SVG icon.
     *
     * @param string $name   Icon name (e.g. 'heart', 'arrow-left').
     * @param int    $size   Icon size in pixels (default 16).
     * @param string $class  Additional CSS class(es).
     * @param bool   $hidden Whether to add aria-hidden="true" (default true).
     * @return string SVG markup.
     */
    public static function render( $name, $size = 16, $class = '', $hidden = true ) {
        if ( ! isset( self::$icons[ $name ] ) ) {
            return '';
        }

        list( $viewBox, $paths ) = self::$icons[ $name ];
        $cls  = 'rtg-icon' . ( $class ? ' ' . esc_attr( $class ) : '' );
        $aria = $hidden ? ' aria-hidden="true"' : '';

        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="%s"%s>%s</svg>',
            $cls,
            (int) $size,
            (int) $size,
            esc_attr( $viewBox ),
            $aria,
            $paths
        );
    }

    /**
     * Return the full icon map as a JS-ready JSON object for use in frontend scripts.
     *
     * @return string JSON-encoded map of icon definitions.
     */
    public static function get_js_icon_map() {
        $map = array();
        foreach ( self::$icons as $name => $def ) {
            $map[ $name ] = array(
                'viewBox' => $def[0],
                'paths'   => $def[1],
            );
        }
        return wp_json_encode( $map );
    }
}
