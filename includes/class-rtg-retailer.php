<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Who the "View Tire" button actually goes to.
 *
 * A synced price knows its advertiser (price_source). A manual link only
 * has its hostname — or, for an affiliate tracking link, the destination
 * tucked into a query parameter. Either way the button can say "View at
 * Tire Rack" instead of "View Tire", which is where the shopper is about
 * to be sent.
 *
 * @since 1.89.0
 */
class RTG_Retailer {

    /**
     * Hostnames the guide links to, as domain => display name.
     * Subdomains match ("www.tirerack.com", "shop.tirerack.com").
     */
    const NAMES = array(
        'tirerack.com'          => 'Tire Rack',
        'discounttire.com'      => 'Discount Tire',
        'simpletire.com'        => 'SimpleTire',
        'prioritytire.com'      => 'Priority Tire',
        'amazon.com'            => 'Amazon',
        'amzn.to'               => 'Amazon',
        'costco.com'            => 'Costco',
        'walmart.com'           => 'Walmart',
        'autozone.com'          => 'AutoZone',
        'pepboys.com'           => 'Pep Boys',
        'ntb.com'               => 'NTB',
        'evsportline.com'       => 'EV Sportline',
        'tsportline.com'        => 'T Sportline',
        'goodyear.com'          => 'Goodyear',
        'michelinman.com'       => 'Michelin',
        'bfgoodrichtires.com'   => 'BFGoodrich',
        'bridgestonetire.com'   => 'Bridgestone',
        'firestone.com'         => 'Firestone',
        'continental-tires.com' => 'Continental',
        'pirelli.com'           => 'Pirelli',
        'yokohamatire.com'      => 'Yokohama',
        'toyo.com'              => 'Toyo',
        'falkentire.com'        => 'Falken',
        'nittotire.com'         => 'Nitto',
        'hankooktire.com'       => 'Hankook',
        'kumhotire.com'         => 'Kumho',
        'nexentire.com'         => 'Nexen',
        'generaltire.com'       => 'General',
        'coopertire.com'        => 'Cooper',
        'sumitomotire.com'      => 'Sumitomo',
        'riviantrackr.com'      => 'RivianTrackr',
    );

    /**
     * Query parameters affiliate networks carry the destination in.
     */
    const DESTINATION_PARAMS = array( 'url', 'u', 'murl', 'dest', 'destination' );

    /**
     * The retailer's display name for a tire, or '' when it can't be told.
     *
     * @param array $tire Row with price_source and link.
     * @return string
     */
    public static function label( $tire ) {
        $source = trim( (string) ( $tire['price_source'] ?? '' ) );
        if ( '' !== $source ) {
            return $source;
        }
        return self::name_from_url( (string) ( $tire['link'] ?? '' ) );
    }

    /**
     * The retailer behind a URL, following one hop into an affiliate link.
     *
     * @param string $url
     * @return string Display name, or '' when the host isn't a known retailer.
     */
    public static function name_from_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return '';
        }

        $name = self::name_from_host( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( '' !== $name ) {
            return $name;
        }

        // An affiliate tracking link: the destination rides a query parameter.
        parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );
        foreach ( self::DESTINATION_PARAMS as $key ) {
            $inner = isset( $params[ $key ] ) && is_string( $params[ $key ] ) ? $params[ $key ] : '';
            if ( '' !== $inner ) {
                $name = self::name_from_host( (string) wp_parse_url( $inner, PHP_URL_HOST ) );
                if ( '' !== $name ) {
                    return $name;
                }
            }
        }

        return '';
    }

    /**
     * @param string $host
     * @return string
     */
    private static function name_from_host( $host ) {
        $host = strtolower( trim( (string) $host ) );
        if ( '' === $host ) {
            return '';
        }
        foreach ( self::NAMES as $domain => $name ) {
            if ( $host === $domain || substr( $host, -( strlen( $domain ) + 1 ) ) === '.' . $domain ) {
                return $name;
            }
        }
        return '';
    }
}
