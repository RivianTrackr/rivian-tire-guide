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

    /**
     * Default records requested per size.
     *
     * A real retailer catalog carries hundreds of tires in a popular fitment.
     * The original 100 quietly discarded the rest — CJ reports how many matched
     * and the adapter ignored it — so tires that plainly exist at a retailer
     * never reached the queue. The limit is now high enough to cover a size in
     * one request, and a shortfall is reported rather than passed over.
     */
    const DEFAULT_LIMIT = 1000;

    /**
     * Wall-clock budget for a whole sweep, in seconds.
     *
     * One request per size at up to REQUEST_TIMEOUT each can outlast PHP's
     * execution limit on a web-triggered cron. Rather than being killed
     * mid-run, the sweep stops when the budget is spent and names the sizes it
     * didn't reach — a partial result that says so beats a silent truncation.
     *
     * Sized for a real fitment list rather than the five sizes first assumed;
     * a guide covering a dozen sizes was having most of them skipped.
     * Overridable in settings for hosts with a tighter execution limit.
     */
    const SWEEP_BUDGET = 240;

    /**
     * Most pages to pull for a single size before moving on.
     *
     * A keyword search is a relevance ranking, not a filter: asking CJ for
     * "255/65R19" reports over five thousand matches, almost none of them that
     * fitment. Paging to the bitter end would spend the whole budget on one
     * size, so a size stops here and says how much it left behind.
     */
    const DEFAULT_MAX_PAGES = 10;

    /**
     * Records requested for one targeted lookup.
     *
     * This was 50, on the reasoning that a keyword naming a tire is a precise
     * query whose answer is a handful of listings or none. The probe disproved
     * that: CJ scores a keyword and returns a ranking, so a model search comes
     * back with thousands of loosely related products in no particular
     * fitment. A live run made the cost plain — 99 searches returned 4,924
     * products, an average of 49.7 each, which is every single one truncated
     * at the cap with nothing said about it.
     *
     * That is the same silent ceiling the sweep carried at 100 records until
     * 1.63.1, reintroduced here by a comment that was never rechecked against
     * how the API actually behaves. It matches the sweep's limit now, and a
     * shortfall is reported rather than passed over.
     */
    const TARGETED_LIMIT = 1000;

    /**
     * Wall-clock budget for the targeted pass, in seconds.
     *
     * Separate from the sweep's, and spent after it, so a slow sweep degrades
     * the targeted pass rather than cancelling it.
     */
    const TARGETED_BUDGET = 120;

    /** Where the next targeted pass resumes in the uncovered list. */
    const TARGETED_CURSOR_OPTION = 'rtg_cj_targeted_cursor';

    /**
     * Option holding where the next sweep should start in the size list.
     *
     * A sweep that can't finish inside its budget would otherwise cover the
     * same leading sizes every run and never reach the rest. Starting where the
     * last run stopped means coverage completes across successive runs.
     */
    const CURSOR_OPTION = 'rtg_cj_sweep_cursor';

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
    const DEFAULT_QUERY = 'query ShoppingProducts($companyId: ID!, $partnerIds: [ID!], $keywords: [String!]!, $limit: Int!, $offset: Int, $googleProductCategoryNames: [String!]) {
  shoppingProducts(
    companyId: $companyId
    partnerIds: $partnerIds
    keywords: $keywords
    limit: $limit
    offset: $offset
    googleProductCategoryNames: $googleProductCategoryNames
  ) {
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
     * Google product categories to restrict the search to.
     *
     * Left blank by default, and that is deliberate — this filter is a trap.
     *
     * It looked like the answer to a keyword search returning thousands of
     * products that are not tires. But the retailers do not populate the field
     * consistently: SimpleTire tags its tires
     * "Vehicles & Parts > … > Motor Vehicle Tires > Automotive Tires" (id 6093),
     * while Tire Rack sends no category at all. A category filter is a
     * server-side WHERE, so applying one excludes every product that declares
     * no category — which would silently drop Tire Rack in its entirety, the
     * very retailer whose missing listings prompted the investigation.
     *
     * It would also look like a success: the match counts would collapse,
     * exactly as a working filter would make them.
     *
     * Pagination already reaches a size's whole match set, so this is an
     * optimization the feature does not need. It stays configurable for a
     * catalog where every advertiser does tag its products, and warns in the
     * admin about what it costs.
     *
     * @return string[]|null Category names, or null to apply no filter.
     */
    public static function get_category_names() {
        $settings = get_option( 'rtg_settings', array() );
        $raw      = trim( (string) ( $settings['cj_category_names'] ?? '' ) );

        if ( '' === $raw ) {
            return null;
        }

        $names = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ), 'strlen' ) );

        return $names ?: null;
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

        $products  = array();
        $failures  = array();
        $skipped   = array();
        $truncated = array();
        $started   = microtime( true );

        $settings  = get_option( 'rtg_settings', array() );
        $budget    = isset( $settings['cj_sweep_budget'] )
            ? max( 15, min( 600, intval( $settings['cj_sweep_budget'] ) ) )
            : self::SWEEP_BUDGET;
        $max_pages = isset( $settings['cj_max_pages'] )
            ? max( 1, min( 50, intval( $settings['cj_max_pages'] ) ) )
            : self::DEFAULT_MAX_PAGES;
        $limit     = isset( $settings['cj_limit'] )
            ? max( 1, min( 1000, intval( $settings['cj_limit'] ) ) )
            : self::DEFAULT_LIMIT;

        $queue = array_values( array_filter( array_map( 'trim', (array) $sizes ), 'strlen' ) );
        if ( empty( $queue ) ) {
            return array();
        }

        // Start where the last sweep stopped. A run that can't finish inside
        // its budget would otherwise cover the same leading sizes every time
        // and never reach the rest; rotating means coverage completes over
        // successive runs instead of never.
        $cursor = intval( get_option( self::CURSOR_OPTION, 0 ) );
        if ( $cursor < 0 || $cursor >= count( $queue ) ) {
            $cursor = 0;
        }

        $ordered     = array_merge( array_slice( $queue, $cursor ), array_slice( $queue, 0, $cursor ) );
        $next_cursor = $cursor;

        foreach ( $ordered as $index => $size ) {
            // Stop before starting a size that would overrun the budget, so
            // the run ends by choice rather than by being killed.
            if ( $index > 0 && ( microtime( true ) - $started ) > $budget ) {
                $skipped     = array_slice( $ordered, $index );
                $next_cursor = ( $cursor + $index ) % count( $queue );
                break;
            }

            $offset    = 0;
            $collected = 0;
            $total     = null;
            $pages     = 0;

            do {
                $result = $this->query_keyword( $size, $offset );

                if ( '' !== $result['error'] ) {
                    $failures[] = $size . ': ' . $result['error'];
                    break;
                }

                $total    = $result['total_count'];
                $returned = count( $result['products'] );

                foreach ( $result['products'] as $product ) {
                    // A product can match several sizes' keywords; key by
                    // external ID so it reaches the queue once.
                    $products[ $product['external_id'] ] = $product;
                }

                $collected += $returned;
                $offset    += $limit;
                $pages++;

                // An empty page means the result set is exhausted whatever the
                // reported total claims.
                if ( 0 === $returned ) {
                    break;
                }

                if ( ( microtime( true ) - $started ) > $budget ) {
                    break;
                }
            } while ( $pages < $max_pages && ( null === $total || $collected < $total ) );

            if ( null !== $total && $collected < $total ) {
                $truncated[ $size ] = array(
                    'received' => $collected,
                    'total'    => $total,
                );
            }

            $next_cursor = ( $cursor + $index + 1 ) % count( $queue );
        }

        update_option( self::CURSOR_OPTION, $next_cursor, false );

        if ( ! empty( $skipped ) ) {
            $failures[] = sprintf(
                'Time budget reached — %s not checked this run; the next run starts there.',
                implode( ', ', $skipped )
            );
        }

        if ( ! empty( $truncated ) ) {
            $parts = array();
            foreach ( $truncated as $size => $counts ) {
                $parts[] = sprintf( '%s (%s of %s)', $size, number_format( $counts['received'] ), number_format( $counts['total'] ) );
            }

            $failures[] = sprintf(
                'Not every match was read: %s. A keyword search ranks rather than filters, so most of that is not tires — set a Google product category to narrow it.',
                implode( ', ', $parts )
            );
        }

        if ( ! empty( $failures ) ) {
            $this->last_error = implode( ' | ', $failures );
        }

        return array_values( $products );
    }

    /**
     * Look a specific tire up by name, rather than sweeping its fitment.
     *
     * The sweep asks CJ for a bare size, and CJ answers with a relevance
     * ranking rather than a filter — over five thousand products for a single
     * fitment, most of which are not that size and many of which are not
     * tires. Paging ten deep covers ten thousand of those, which sounds
     * generous until a real guide tire turns out to rank below it: a live run
     * held exactly one Michelin listing in 305/45R22 across sixteen thousand
     * stored products, for a fitment Tire Rack demonstrably sells several of.
     *
     * A keyword naming the brand, model and size is a different question
     * entirely, and one request answers it. So tires the sweep failed to find
     * are asked for directly, by name.
     *
     * Rotated and budgeted like the sweep: a run takes as many as it can and
     * the next starts where this one stopped, so a long uncovered list is
     * worked through over successive runs instead of the same leading entries
     * being retried forever.
     *
     * Results are returned per term rather than pooled, because "the request
     * came back with products" is not evidence the tire was found. A live run
     * had all 111 terms answer and not one guide tire match: CJ ranks a
     * multi-word keyword the same way it ranks a bare size, so every term drew
     * a few dozen loosely related products. Keeping the term attached lets the
     * caller check each answer against what it asked for.
     *
     * A callback may be given to consume each term's products as they arrive.
     * At a thousand records a term and a hundred terms, holding every response
     * until the end would mean a hundred thousand products in memory to keep
     * the few dozen that matter.
     *
     * @param string[]      $terms   Search terms, e.g. "Michelin Defender LTX M/S2".
     * @param callable|null $on_term Receives ( $term, $products ) per answer.
     *                               Responses are not retained when given.
     * @return array {
     *     @type array  $by_term   Term => products, when no callback was given.
     *     @type int    $checked   Terms actually queried.
     *     @type int    $pending   Terms left for the next run.
     *     @type int    $capped    Terms whose answer was cut off by the limit.
     *     @type int    $deepest   Largest match count any term reported.
     *     @type string $error     Failure text, or ''.
     * }
     */
    public function fetch_terms( $terms, $on_term = null ) {
        $result = array(
            'by_term' => array(),
            'checked' => 0,
            'pending' => 0,
            'capped'  => 0,
            'deepest' => 0,
            'error'   => '',
        );

        $terms = array_values( array_filter( array_map( 'trim', (array) $terms ), 'strlen' ) );

        if ( ! self::is_configured() || empty( $terms ) ) {
            return $result;
        }

        $settings = get_option( 'rtg_settings', array() );

        if ( isset( $settings['cj_targeted_enabled'] ) && ! $settings['cj_targeted_enabled'] ) {
            return $result;
        }

        $budget = isset( $settings['cj_targeted_budget'] )
            ? max( 15, min( 600, intval( $settings['cj_targeted_budget'] ) ) )
            : self::TARGETED_BUDGET;

        $limit = isset( $settings['cj_targeted_limit'] )
            ? max( 1, min( 1000, intval( $settings['cj_targeted_limit'] ) ) )
            : self::TARGETED_LIMIT;

        $cursor = intval( get_option( self::TARGETED_CURSOR_OPTION, 0 ) );
        if ( $cursor < 0 || $cursor >= count( $terms ) ) {
            $cursor = 0;
        }

        $ordered  = array_merge( array_slice( $terms, $cursor ), array_slice( $terms, 0, $cursor ) );
        $started  = microtime( true );
        $failures = array();
        $stopped  = null;

        foreach ( $ordered as $index => $term ) {
            if ( $index > 0 && ( microtime( true ) - $started ) > $budget ) {
                $stopped = $index;
                break;
            }

            $response = $this->query_keyword( $term, 0, $limit );
            $result['checked']++;

            if ( '' !== $response['error'] ) {
                // One bad term must not end the pass; the rest are independent.
                if ( count( $failures ) < 3 ) {
                    $failures[] = $term . ': ' . $response['error'];
                }
                continue;
            }

            // How deep the ranking runs, and whether this answer was cut off.
            // Without it a truncated answer is indistinguishable from a
            // complete one, which is exactly how a 50-record cap went a whole
            // release without being noticed.
            $total = $response['total_count'];
            if ( null !== $total ) {
                $result['deepest'] = max( $result['deepest'], intval( $total ) );

                if ( count( $response['products'] ) < intval( $total ) ) {
                    $result['capped']++;
                }
            }

            if ( is_callable( $on_term ) ) {
                call_user_func( $on_term, $term, $response['products'] );
            } else {
                $result['by_term'][ $term ] = $response['products'];
            }
        }

        $next_cursor = null === $stopped
            ? 0
            : ( $cursor + $stopped ) % count( $terms );

        update_option( self::TARGETED_CURSOR_OPTION, $next_cursor, false );

        if ( null !== $stopped ) {
            $result['pending'] = count( $ordered ) - $stopped;
        }

        if ( ! empty( $failures ) ) {
            $result['error'] = implode( ' | ', $failures );
        }

        return $result;
    }

    /**
     * Run one keyword query and normalize the products it returns.
     *
     * @param string   $keyword Search keyword — a tire size, or a tire's full name.
     * @param int      $offset  Records to skip, for paging through a large match set.
     * @param int|null $limit   Records to request; the configured sweep limit when null.
     * @return array { products: array[], total_count: int|null, error: string, raw: array }
     */
    public function query_keyword( $keyword, $offset = 0, $limit = null ) {
        if ( null === $limit ) {
            $settings = get_option( 'rtg_settings', array() );
            $limit    = intval( $settings['cj_limit'] ?? self::DEFAULT_LIMIT );
        }

        $limit = max( 1, min( 1000, intval( $limit ) ) );

        $advertisers = self::get_advertisers();

        $body = wp_json_encode( array(
            'query'     => self::get_query_document(),
            'variables' => array(
                'companyId'                  => self::get_company_id(),
                'partnerIds'                 => array_keys( $advertisers ),
                'keywords'                   => array( $keyword ),
                'limit'                      => $limit,
                'offset'                     => max( 0, intval( $offset ) ),
                // Null means "don't filter". A keyword search ranks by
                // relevance rather than filtering, so without a category the
                // response is mostly products that aren't tires at all.
                'googleProductCategoryNames' => self::get_category_names(),
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
                'products'    => array(),
                'total_count' => null,
                'error'       => $response->get_error_message(),
                'raw'         => array(),
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
                'products'    => array(),
                'total_count' => null,
                'error'       => 'HTTP ' . $code . ' — the token was rejected. Check the PAT and that it belongs to company ' . self::get_company_id() . '.',
                'raw'         => $this->last_response,
            );
        }

        if ( 200 !== $code ) {
            return array(
                'products'    => array(),
                'total_count' => null,
                'error'       => 'HTTP ' . $code,
                'raw'         => $this->last_response,
            );
        }

        if ( ! is_array( $decoded ) ) {
            return array(
                'products'    => array(),
                'total_count' => null,
                'error'       => 'Response was not valid JSON.',
                'raw'         => $this->last_response,
            );
        }

        // GraphQL reports schema and permission problems in a 200 response.
        if ( ! empty( $decoded['errors'] ) ) {
            return array(
                'products'    => array(),
                'total_count' => null,
                'error'       => self::describe_graphql_errors( $decoded['errors'] ),
                'raw'         => $this->last_response,
            );
        }

        $nodes = self::extract_result_list( $decoded['data'] ?? array() );

        // CJ reports how many products matched. Comparing it against what came
        // back is the only way to know the response was capped — without it a
        // truncated page is indistinguishable from a complete one.
        $total = self::extract_total_count( $decoded['data'] ?? array() );

        $products = array();
        foreach ( $nodes as $node ) {
            $mapped = self::map_product( $node, $advertisers );
            if ( '' !== $mapped['external_id'] ) {
                $products[] = $mapped;
            }
        }

        return array(
            'products'    => $products,
            'total_count' => $total,
            'error'       => '',
            'raw'         => $this->last_response,
        );
    }

    /**
     * Find the match count CJ reports alongside a result list.
     *
     * @param mixed $data GraphQL `data` payload.
     * @return int|null Total matches, or null when the response doesn't say.
     */
    public static function extract_total_count( $data ) {
        if ( ! is_array( $data ) ) {
            return null;
        }

        foreach ( $data as $key => $value ) {
            if ( is_int( $value ) && in_array( (string) $key, array( 'totalCount', 'total', 'totalResults' ), true ) ) {
                return $value;
            }

            if ( is_array( $value ) ) {
                $found = self::extract_total_count( $value );
                if ( null !== $found ) {
                    return $found;
                }
            }
        }

        return null;
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
    public function test_connection( $keyword = '' ) {
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

        $keyword = trim( (string) $keyword );

        if ( '' === $keyword ) {
            $sizes   = RTG_Admin::get_dropdown_options( 'sizes' );
            $keyword = $sizes[0] ?? '275/65R18';
        }

        $result = $this->query_keyword( $keyword, 0, self::TARGETED_LIMIT );

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

        // Titles, not just a JSON dump. What a keyword actually returns is the
        // question this button answers, and reading that off three raw nodes
        // hides the shape of the answer.
        $titles = array();
        foreach ( array_slice( $result['products'], 0, 25 ) as $product ) {
            $titles[] = trim( sprintf(
                '%s%s',
                $product['title'],
                '' !== $product['advertiser_name'] ? '   [' . $product['advertiser_name'] . ']' : ''
            ) );
        }

        return array(
            'ok'            => true,
            'message'       => sprintf(
                'Connected. Showing %s of %s match(es) for keyword "%s".',
                number_format( $count ),
                null === $result['total_count'] ? 'an unreported number' : number_format( $result['total_count'] ),
                $keyword
            ),
            'product_count' => $count,
            'titles'        => $titles,
            'sample'        => array_slice( $result['products'], 0, 2 ),
            'body'          => 0 === $count ? ( $this->last_response['body'] ?? '' ) : '',
        );
    }
}
