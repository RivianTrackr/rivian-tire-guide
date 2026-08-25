<?php
/**
 * Tests for model aliases, end to end.
 *
 * An alias exists so a retailer's spelling of a model matches a guide tire
 * without renaming what readers see. Everything that keys on the model —
 * the guide index, matched-candidate lookup, uncovered-terms, presence —
 * goes through match_keys_for_tire(), so these prove the alias actually
 * flows through storage and back out of each consumer.
 */
class Test_RTG_Model_Aliases extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        RTG_Activator::activate();
    }

    private function make_tire( $overrides = array() ) {
        $data = array_merge( array(
            'tire_id'       => 'alias-' . wp_rand( 1000, 9999 ),
            'brand'         => 'Nitto',
            'model'         => 'Ridge Grappler',
            'model_aliases' => "Ridge Grappler LT",
            'size'          => '275/65R20',
            'price'         => 390.00,
        ), $overrides );

        RTG_Database::insert_tire( $data );

        return $data;
    }

    /**
     * The alias round-trips through the database.
     */
    public function test_aliases_survive_storage() {
        $tire = $this->make_tire();

        $stored = RTG_Database::get_tire( $tire['tire_id'] );

        $this->assertSame( "Ridge Grappler LT", $stored['model_aliases'] );
    }

    /**
     * A tire answers to its own model and to each alias.
     */
    public function test_a_tire_answers_to_every_spelling() {
        $keys = RTG_Catalog_Sync::match_keys_for_tire( array(
            'brand'         => 'Nitto',
            'model'         => 'Ridge Grappler',
            'model_aliases' => "Ridge Grappler LT\nRidge Grappler A/T",
            'size'          => '275/65R20',
        ) );

        $this->assertContains( RTG_Catalog_Sync::match_key( 'Nitto', 'Ridge Grappler', '275/65R20' ), $keys );
        $this->assertContains( RTG_Catalog_Sync::match_key( 'Nitto', 'Ridge Grappler LT', '275/65R20' ), $keys );
        $this->assertContains( RTG_Catalog_Sync::match_key( 'Nitto', 'Ridge Grappler A/T', '275/65R20' ), $keys );
    }

    /**
     * An alias that squashes to the primary model adds nothing — "M/S 2" and
     * "M/S2" are one spelling to the matcher.
     */
    public function test_duplicate_spellings_collapse() {
        $keys = RTG_Catalog_Sync::match_keys_for_tire( array(
            'brand'         => 'Michelin',
            'model'         => 'Defender LTX M/S2',
            'model_aliases' => "Defender LTX M/S 2",
            'size'          => '305/45R22',
        ) );

        $this->assertCount( 1, $keys );
    }

    /**
     * The guide index maps every spelling to the tire, so a sweep product
     * under the retailer's name matches on ingest.
     */
    public function test_the_guide_index_carries_alias_keys() {
        $tire  = $this->make_tire();
        $index = RTG_Catalog_Sync::build_guide_index();

        $alias_key = RTG_Catalog_Sync::match_key( 'Nitto', 'Ridge Grappler LT', '275/65R20' );

        $this->assertArrayHasKey( $alias_key, $index );
        $this->assertSame( $tire['tire_id'], $index[ $alias_key ] );
    }

    /**
     * The end that matters: a candidate stored under the retailer's spelling
     * reaches the tire's price sync, which is what "covered" means.
     */
    public function test_a_candidate_under_the_alias_reaches_the_tire() {
        $tire = $this->make_tire();

        RTG_Candidates::upsert( array(
            'source'          => 'cj',
            'advertiser_id'   => '1463221',
            'advertiser_name' => 'The Tire Rack',
            'external_id'     => 'alias-cand-1',
            'brand'           => 'Nitto',
            'model'           => 'Ridge Grappler LT',
            'size'            => '275/65R20',
            'price'           => 401.00,
            'match_key'       => RTG_Catalog_Sync::match_key( 'Nitto', 'Ridge Grappler LT', '275/65R20' ),
            'qualifies'       => 1,
        ) );

        $by_tire = RTG_Candidates::get_matched_by_tire();

        $this->assertArrayHasKey( $tire['tire_id'], $by_tire );
        $this->assertSame( 'Ridge Grappler LT', $by_tire[ $tire['tire_id'] ][0]['model'] );
    }

    /**
     * The edit form's save path keeps line structure while sanitizing.
     */
    public function test_aliases_from_the_form_keep_their_lines() {
        $tire = $this->make_tire( array( 'model_aliases' => "One\nTwo <script>x</script>" ) );

        $stored = RTG_Database::get_tire( $tire['tire_id'] );
        $lines  = explode( "\n", $stored['model_aliases'] );

        $this->assertCount( 2, $lines );
    }
}
