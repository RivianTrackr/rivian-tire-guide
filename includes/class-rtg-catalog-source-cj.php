<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Catalog source backed by CJ Affiliate's Product Search GraphQL API.
 *
 * Both retailers the guide links to run their affiliate programs on CJ, so one
 * adapter covers them. The `shoppingProducts` query returns products only from
 * advertisers the publisher has joined and requires a keyword, which suits this
 * job exactly: the keyword is a tire size, and the guide has five of them, so a
 * full sweep is five requests.
 *
 * Two things about this class are deliberately soft. The GraphQL document is
 * overridable from settings, and the response mapping accepts several plausible
 * field names per value. CJ's schema reference is behind a JavaScript-rendered
 * portal, so the shipped document is a best effort; when it doesn't match, the
 * Test Connection button shows CJ's own error text — which names the offending
 * field — and the query can be corrected without a code change.
 *
 * Credentials are never stored in this file. The token is read from the
 * RTG_CJ_PAT constant in wp-config.php when present, falling back to the
 * setting, and the company ID is entered by the admin.
 *
 * @since 1.60.0
 */
class RTG_Catalog_Source_CJ implements RTG_Catalog_Source {

    /** CJ's GraphQL endpoint. */
    const ENDPOINT = 'https://ads.api.cj.com/query';

    /** HTTP timeout per request, in seconds. */
    const REQUEST_TIMEOUT = 30;

    /** Default records requested per size. */
    const DEFAULT_LIMIT = 100;

    /**
     * Wall-clock budget for a whole sweep, in seconds.
     *
     * Five sizes at up to REQUEST_TIMEOUT each can outlast PHP's execution
     * limit on a web-triggered cron. Rather than being killed mid-run, the
     * sweep stops when the budget is spent and names the sizes it didn't
     * reach — a partial result that says so beats a silent truncation.
     */
    const SWEEP_BUDGET = 45;

    /**
     * Advertisers to search, as CJ advertiser ID => display name.
     *
     * These are public directory identifiers, not credentials.
     */
    const DEFAULT_ADVERTISERS = array(
        '1463221' => 'Tire Rack',
        '5660604' => 'SimpleTire',
    );

    /**
     * Default GraphQL document.
     *
     * Overridable from settings so a schema mismatch is a settings edit rather
     * than a plugin release.
     */
    const DEFAULT_QUERY = 'query ShoppingProducts($companyId: ID!, $partnerIds: [ID!], $keywords: [String!]!, $limit: Int!) {
  shoppingProducts(companyId: $companyId, partnerIds: $partnerIds, keywords: $keywords, limit: $limit) {
    totalCount
    count
    resultList {
      id
      advertiserId
      advertiserName
      title
      description
      brand
      link
      imageLink
      availability
      gtin
      mpn
      price { amount currency }
    }
  }
}';

    /** @var string Last failure reason, '' when the last fetch succeeded. */
    private $last_error = '';

    /** @var array Raw diagnostics from the most recent request, for Test Connection. */
    private $last_response = array();

    /**
     * @return string Source slug stored on candidate rows.
     */
    public function get_slug() {
        return 'cj';
    }

    /**
     * @return string Human-readable source name.
     */
    public function get_label() {
        return 'CJ Affiliate';
    }

    /**
     * @return string Last error message, or ''.
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * @return array Diagnostics from the most recent request.
     */
    public function get_last_response() {
        return $this->last_response;
    }

    // --- Configuration ---

    /**
     * Resolve the personal access token.
     *
     * The wp-config constant wins, so a site can keep the token out of the
     * database entirely:
     *
     *     define( 'RTG_CJ_PAT', '...' );
     *
     * @return string Token, or '' when unconfigured.
     */
    public static function get_pat() {
        if ( defined( 'RTG_CJ_PAT' ) && '' !== (string) RTG_CJ_PAT ) {
            return (string) RTG_CJ_PAT;
        }

        $settings = get_option( 'rtg_settings', array() );
        return trim( (string) ( $settings['cj_pat'] ?? '' ) );
    }

    /**
     * @return bool Whether the token comes from wp-config rather than the database.
     */
    public static function pat_is_constant() {
        return defined( 'RTG_CJ_PAT' ) && '' !== (string) RTG_CJ_PAT;
    }

    /**
     * @return string CJ company ID (CID), or '' when unconfigured.
     */
    public static function get_company_id() {
        $settings = get_option( 'rtg_settings', array() );
        return trim( (string) ( $settings['cj_company_id'] ?? '' ) );
    }

    /**
     * Advertisers to search.
     *
     * @return array Advertiser ID => display name.
     */
    public static function get_advertisers() {
        $settings = get_option( 'rtg_settings', array() );
        $raw      = trim( (string) ( $settings['cj_advertisers'] ?? '' ) );

        if ( '' === $raw ) {
            return self::DEFAULT_ADVERTISERS;
        }

        // Stored one per line as "id" or "id|Name".
        $advertisers = array();
        foreach ( preg_split( '/[\r\n]+/', $raw ) as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                continue;
            }

            $parts = array_map( 'trim', explode( '|', $line, 2 ) );
            $id    = preg_replace( '/[^0-9]/', '', $parts[0] );

            if ( '' === $id ) {
                continue;
            }

            $advertisers[ $id ] = $parts[1] ?? self::DEFAULT_ADVERTISERS[ $id ] ?? ( 'Advertiser ' . $id );
        }

        return $advertisers ?: self::DEFAULT_ADVERTISERS;
    }

    /**
     * @return string GraphQL document to send.
     */
    public static function get_query_document() {
        $settings = get_option( 'rtg_settings', array() );
        $custom   = trim( (string) ( $settings['cj_query'] ?? '' ) );

        return '' !== $custom ? $custom : self::DEFAULT_QUERY;
    }

    /**
     * @return bool Whether the source is enabled and has the credentials it needs.
     */
    public static function is_configured() {
        $settings = get_option( 'rtg_settings', array() );

        if ( isset( $settings['cj_enabled'] ) && ! $settings['cj_enabled'] ) {
            return false;
        }

        return '' !== self::get_pat() && '' !== self::get_company_id();
    }

    // --- Fetching ---

    /**
     * Fetch candidate products for every guide size.
     *
     * One request per size, scoped to the configured advertisers. A size whose
     * request fails is recorded and skipped rather than aborting the sweep —
     * four sizes' worth of results beats none.
     *
     * @param string[] $sizes Canonical sizes the guide cares about.
     * @return array[] Normalized products.
     */
    public function fetch( $sizes ) {
        $this->last_error    = '';
        $this->last_response = array();

        if ( ! self::is_configured() ) {
            $this->last_error = 'CJ is not configured — set the company ID and personal access token.';
            return array();
        }

        $products = array();
        $failures = array();
        $skipped  = array();
        $started  = microtime( true );

        $queue = array_values( array_filter( array_map( 'trim', (array) $sizes ), 'strlen' ) );

        foreach ( $queue as $index => $size ) {
            // Stop before starting a request that would overrun the budget,
            // so the run ends by choice rather than by being killed.
            if ( $index > 0 && ( microtime( true ) - $started ) > self::SWEEP_BUDGET ) {
                $skipped = array_slice( $queue, $index );
                break;
            }

            $result = $this->query_keyword( $size );

            if ( '' !== $result['error'] ) {
                $failures[] = $size . ': ' . $result['error'];
                continue;
            }

            foreach ( $result['products'] as $product ) {
                // Later sizes can return a product an earlier one already did;
                // key by external ID so it reaches the queue once.
                $products[ $product['external_id'] ] = $product;
            }
        }

        if ( ! empty( $skipped ) ) {
            $failures[] = sprintf(
                'Time budget reached — %s not checked this run.',
                implode( ', ', $skipped )
            );
        }

        if ( ! empty( $failures ) ) {
            $this->last_error = implode( ' | ', $failures );
        }

        return array_values( $products );
    }

    /**
     * Run one keyword query and normalize the products it returns.
     *
     * @param string $keyword Search keyword — a tire size.
     * @return array { products: array[], error: string, raw: array }
     */
    public function query_keyword( $keyword ) {
        $settings = get_option( 'rtg_settings', array() );
        $limit    = intval( $settings['cj_limit'] ?? self::DEFAULT_LIMIT );
        $limit    = max( 1, min( 1000, $limit ) );

        $advertisers = self::get_advertisers();

        $body = wp_json_encode( array(
            'query'     => self::get_query_document(),
            'variables' => array(
                'companyId'  => self::get_company_id(),
                'partnerIds' => array_keys( $advertisers ),
                'keywords'   => array( $keyword ),
                'limit'      => $limit,
            ),
        ) );

        $response = wp_remote_post( self::ENDPOINT, array(
            'timeout'   => self::REQUEST_TIMEOUT,
            'sslverify' => true,
            'headers'   => array(
                'Authorization' => 'Bearer ' . self::get_pat(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'body'      => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'products' => array(),
                'error'    => $response->get_error_message(),
                'raw'      => array(),
            );
        }

        $code    = wp_remote_retrieve_response_code( $response );
        $raw     = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $raw, true );

        // Record enough to diagnose a schema mismatch without ever holding the
        // token: the body is CJ's response, the request is not echoed back.
        $this->last_response = array(
            'http_code' => $code,
            'body'      => is_string( $raw ) ? substr( $raw, 0, 8000 ) : '',
        );

        if ( 401 === $code || 403 === $code ) {
            return array(
                'products' => array(),
                'error'    => 'HTTP ' . $code . ' — the token was rejected. Check the PAT and that it belongs to company ' . self::get_company_id() . '.',
                'raw'      => $this->last_response,
            );
        }

        if ( 200 !== $code ) {
            return array(
                'products' => array(),
                'error'    => 'HTTP ' . $code,
                'raw'      => $this->last_response,
            );
        }

        if ( ! is_array( $decoded ) ) {
            return array(
                'products' => array(),
                'error'    => 'Response was not valid JSON.',
                'raw'      => $this->last_response,
            );
        }

        // GraphQL reports schema and permission problems in a 200 response.
        if ( ! empty( $decoded['errors'] ) ) {
            return array(
                'products' => array(),
                'error'    => self::describe_graphql_errors( $decoded['errors'] ),
                'raw'      => $this->last_response,
            );
        }

        $nodes = self::extract_result_list( $decoded['data'] ?? array() );

        $products = array();
        foreach ( $nodes as $node ) {
            $mapped = self::map_product( $node, $advertisers );
            if ( '' !== $mapped['external_id'] ) {
                $products[] = $mapped;
            }
        }

        return array(
            'products' => $products,
            'error'    => '',
            'raw'      => $this->last_response,
        );
    }

    // --- Response handling ---

    /**
     * Flatten GraphQL errors into one readable line.
     *
     * CJ names the offending field ("Cannot query field X on type Y"), which is
     * exactly what's needed to correct the query document, so the text is passed
     * through rather than replaced with something friendlier.
     *
     * @param array $errors GraphQL errors array.
     * @return string Human-readable summary.
     */
    public static function describe_graphql_errors( $errors ) {
        $messages = array();

        foreach ( (array) $errors as $error ) {
            if ( is_string( $error ) ) {
                $messages[] = $error;
            } elseif ( is_array( $error ) && isset( $error['message'] ) ) {
                $messages[] = (string) $error['message'];
            }
        }

        return $messages
            ? 'GraphQL error: ' . implode( '; ', array_slice( $messages, 0, 3 ) )
            : 'GraphQL returned an unspecified error.';
    }

    /**
     * Find the product list inside a GraphQL data payload.
     *
     * The wrapper CJ uses around the results (resultList, products, edges) is
     * one of the details the portal wouldn't confirm, so rather than hard-coding
     * a path this walks the payload for the first list of objects. Predictable
     * in practice — a Product Search response contains exactly one such list.
     *
     * @param mixed $data GraphQL `data` payload.
     * @return array[] Product nodes, empty when none found.
     */
    public static function extract_result_list( $data ) {
        if ( ! is_array( $data ) ) {
            return array();
        }

        // A list of objects at this level is the result list.
        if ( self::is_list_of_objects( $data ) ) {
            return $data;
        }

        foreach ( $data as $value ) {
            if ( ! is_array( $value ) ) {
                continue;
            }

            $found = self::extract_result_list( $value );
            if ( ! empty( $found ) ) {
                return $found;
            }
        }

        return array();
    }

    /**
     * @param mixed $value Value to inspect.
     * @return bool Whether the value is a sequential array of associative arrays.
     */
    private static function is_list_of_objects( $value ) {
        if ( ! is_array( $value ) || empty( $value ) ) {
            return false;
        }

        if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
            return false;
        }

        foreach ( $value as $item ) {
            if ( ! is_array( $item ) || self::is_list_of_objects( $item ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read the first present value from a set of candidate paths.
     *
     * Paths may be dotted ("price.amount") to reach nested values. GraphQL
     * connections wrap each item in a "node", so that is unwrapped first.
     *
     * @param array    $node     Product node.
     * @param string[] $paths    Candidate paths, most preferred first.
     * @param mixed    $fallback Value when nothing matches.
     * @return mixed First non-empty value found, or $fallback.
     */
    public static function pluck( $node, $paths, $fallback = '' ) {
        if ( isset( $node['node'] ) && is_array( $node['node'] ) ) {
            $node = $node['node'];
        }

        foreach ( $paths as $path ) {
            $value = $node;

            foreach ( explode( '.', $path ) as $segment ) {
                if ( ! is_array( $value ) || ! isset( $value[ $segment ] ) ) {
                    $value = null;
                    break;
                }
                $value = $value[ $segment ];
            }

            if ( null !== $value && '' !== $value && ! is_array( $value ) ) {
                return $value;
            }
        }

        return $fallback;
    }

    /**
     * Normalize one CJ product node into the shape the pipeline expects.
     *
     * Each value accepts several field names, because the exact spelling CJ
     * uses could not be confirmed against the live schema. A wrong guess costs
     * a blank column in the review queue, not a failed run.
     *
     * @param array $node        Raw product node.
     * @param array $advertisers Advertiser ID => name, for filling in a missing name.
     * @return array Normalized product.
     */
    public static function map_product( $node, $advertisers = array() ) {
        if ( isset( $node['node'] ) && is_array( $node['node'] ) ) {
            $node = $node['node'];
        }

        $advertiser_id = (string) self::pluck( $node, array(
            'advertiserId', 'advertiser.id', 'partnerId', 'partner.id', 'companyId',
        ) );

        $advertiser_name = (string) self::pluck( $node, array(
            'advertiserName', 'advertiser.name', 'partnerName', 'partner.name',
        ) );

        if ( '' === $advertiser_name && isset( $advertisers[ $advertiser_id ] ) ) {
            $advertiser_name = $advertisers[ $advertiser_id ];
        }

        // The trackable URL is preferred over the advertiser's own product page,
        // since an untracked link earns no commission.
        $link = (string) self::pluck( $node, array(
            'linkCode.clickUrl', 'clickUrl', 'buyUrl', 'link', 'productUrl', 'url',
        ) );

        return array(
            'external_id'     => (string) self::pluck( $node, array(
                'id', 'productId', 'productID', 'sku', 'gtin', 'mpn',
            ) ),
            'title'           => (string) self::pluck( $node, array( 'title', 'name', 'productName' ) ),
            'brand'           => (string) self::pluck( $node, array( 'brand', 'manufacturer', 'manufacturerName' ) ),
            'size'            => (string) self::pluck( $node, array( 'size', 'tireSize' ) ),
            'load_index'      => (string) self::pluck( $node, array( 'loadIndex' ) ),
            'load_range'      => (string) self::pluck( $node, array( 'loadRange' ) ),
            'speed_rating'    => (string) self::pluck( $node, array( 'speedRating' ) ),
            'price'           => floatval( self::pluck( $node, array(
                'price.amount', 'price', 'salePrice.amount', 'salePrice', 'currentPrice',
            ), 0 ) ),
            'link'            => $link,
            'image'           => (string) self::pluck( $node, array(
                'imageLink', 'imageUrl', 'image', 'imageURL',
            ) ),
            'advertiser_id'   => $advertiser_id,
            'advertiser_name' => $advertiser_name,

            // The untouched node, kept alongside the normalized fields.
            // Without it the candidate row records only what the mapper chose
            // to keep, so a field the mapper ignores — `description` was the
            // one that bit — is unrecoverable afterwards and diagnosing a
            // parsing gap costs a live re-run instead of a database query.
            '_source_node'    => $node,
        );
    }

    /**
     * Run a single probe query for the admin's Test Connection button.
     *
     * Uses a real guide size so a success proves the whole path — auth, query
     * document, advertiser scope and mapping — rather than just reachability.
     *
     * @return array { ok: bool, message: string, product_count: int, sample: array, body: string }
     */
    public function test_connection() {
        if ( '' === self::get_pat() ) {
            return array(
                'ok'            => false,
                'message'       => 'No personal access token configured.',
                'product_count' => 0,
                'sample'        => array(),
                'body'          => '',
            );
        }

        if ( '' === self::get_company_id() ) {
            return array(
                'ok'            => false,
                'message'       => 'No company ID (CID) configured.',
                'product_count' => 0,
                'sample'        => array(),
                'body'          => '',
            );
        }

        $sizes   = RTG_Admin::get_dropdown_options( 'sizes' );
        $keyword = $sizes[0] ?? '275/65R18';
        $result  = $this->query_keyword( $keyword );

        if ( '' !== $result['error'] ) {
            return array(
                'ok'            => false,
                'message'       => $result['error'],
                'product_count' => 0,
                'sample'        => array(),
                'body'          => $this->last_response['body'] ?? '',
            );
        }

        $count = count( $result['products'] );

        return array(
            'ok'            => true,
            'message'       => sprintf( 'Connected. %d product(s) returned for keyword "%s".', $count, $keyword ),
            'product_count' => $count,
            'sample'        => array_slice( $result['products'], 0, 3 ),
            'body'          => 0 === $count ? ( $this->last_response['body'] ?? '' ) : '',
        );
    }
}
