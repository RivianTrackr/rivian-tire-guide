<?php
/**
 * Tests for RTG_Fitment — the load-index rule applied to a tire.
 *
 * The rule the guide's tooltip has always stated (R1 needs 116, R2 needs
 * 112) is judged here for the tire page and, through the localized floors,
 * for the guide cards and the compare page. Pure of the database: the size
 * map and the floors are passed in.
 */
class Test_RTG_Fitment extends WP_UnitTestCase {

    private $map    = array( 'R1' => array( '275/65R20', '275/50R22' ), 'R2' => array( '235/60R19', '255/50R20' ) );
    private $floors = array( 'R1' => 116, 'R2' => 112 );

    public function test_parse_load_index_reads_the_stored_forms() {
        $this->assertSame( 116, RTG_Fitment::parse_load_index( '116' ) );
        $this->assertSame( 116, RTG_Fitment::parse_load_index( '116T' ) );
        $this->assertSame( 121, RTG_Fitment::parse_load_index( '121/118' ), 'the single-tire figure of an LT pair' );
        $this->assertSame( 116, RTG_Fitment::parse_load_index( '116 (2756 lb)' ) );
        $this->assertSame( 0, RTG_Fitment::parse_load_index( '' ) );
        $this->assertSame( 0, RTG_Fitment::parse_load_index( null ) );
        $this->assertSame( 0, RTG_Fitment::parse_load_index( 'n/a' ) );
        $this->assertSame( 0, RTG_Fitment::parse_load_index( '9999' ), 'outside the tire load-index range' );
    }

    public function test_a_named_vehicle_is_judged_alone() {
        $tire = array( 'load_index' => '110', 'size' => '275/65R20' );

        $this->assertSame(
            array( array( 'vehicle' => 'R1', 'floor' => 116 ) ),
            RTG_Fitment::shortfalls( $tire, $this->map, $this->floors, 'R1' )
        );
        $this->assertSame( array(), RTG_Fitment::shortfalls( array( 'load_index' => '116', 'size' => '275/65R20' ), $this->map, $this->floors, 'R1' ) );
    }

    public function test_without_a_vehicle_only_fitting_vehicles_are_judged() {
        $this->assertSame(
            array( array( 'vehicle' => 'R1', 'floor' => 116 ) ),
            RTG_Fitment::shortfalls( array( 'load_index' => '110', 'size' => '275/65R20' ), $this->map, $this->floors )
        );
        $this->assertSame(
            array( array( 'vehicle' => 'R2', 'floor' => 112 ) ),
            RTG_Fitment::shortfalls( array( 'load_index' => '110', 'size' => '235/60r19 ' ), $this->map, $this->floors ),
            'size matching ignores case and whitespace'
        );
        $this->assertSame(
            array(),
            RTG_Fitment::shortfalls( array( 'load_index' => '90', 'size' => '205/55R16' ), $this->map, $this->floors ),
            'a size no vehicle takes raises nothing'
        );
    }

    public function test_a_shared_size_can_fall_short_for_both() {
        $map = array( 'R1' => array( '275/50R20' ), 'R2' => array( '275/50R20' ) );
        $out = RTG_Fitment::shortfalls( array( 'load_index' => '108', 'size' => '275/50R20' ), $map, $this->floors );

        $this->assertSame( array( 'R1', 'R2' ), array_column( $out, 'vehicle' ) );
        $this->assertSame( 'Load index 108 is below the R1 (116) and R2 (112) minimums.', RTG_Fitment::describe( '108', $out ) );
    }

    public function test_an_unknown_load_index_is_never_a_warning() {
        $this->assertSame( array(), RTG_Fitment::shortfalls( array( 'load_index' => '', 'size' => '275/65R20' ), $this->map, $this->floors, 'R1' ) );
        $this->assertSame( array(), RTG_Fitment::verdicts( array( 'load_index' => '', 'size' => '275/65R20' ), $this->map, $this->floors ) );
    }

    public function test_verdicts_list_every_fitting_vehicle_pass_or_fail() {
        $map = array( 'R1' => array( '275/50R20' ), 'R2' => array( '275/50R20' ), 'R3' => array( '245/45R18' ) );
        $out = RTG_Fitment::verdicts( array( 'load_index' => '114', 'size' => '275/50R20' ), $map, array( 'R1' => 116, 'R2' => 112, 'R3' => 100 ) );

        $this->assertSame(
            array(
                array( 'vehicle' => 'R1', 'floor' => 116, 'ok' => false, 'third_party' => false, 'note' => '' ),
                array( 'vehicle' => 'R2', 'floor' => 112, 'ok' => true, 'third_party' => false, 'note' => '' ),
            ),
            $out
        );
    }

    // --- Third-party wheel sizes ---

    private $third_party = array(
        'R2' => array( '245/60R18' => array( 'wheel' => '18" aftermarket wheels', 'note' => 'Needs an 8.5-inch or wider wheel.' ) ),
    );

    public function test_a_verdict_says_when_the_size_is_third_party() {
        $map = array( 'R1' => array( '275/65R18' ), 'R2' => array( '245/60R18', '255/50R20' ) );
        $out = RTG_Fitment::verdicts( array( 'load_index' => '113', 'size' => '245/60R18' ), $map, $this->floors, $this->third_party );

        $this->assertSame(
            array( array( 'vehicle' => 'R2', 'floor' => 112, 'ok' => true, 'third_party' => true, 'note' => 'Needs an 8.5-inch or wider wheel.' ) ),
            $out
        );
        $this->assertFalse( RTG_Fitment::verdicts( array( 'load_index' => '113', 'size' => '255/50R20' ), $map, $this->floors, $this->third_party )[0]['third_party'], 'a factory size' );
    }

    public function test_third_party_fits_judge_the_chosen_vehicle_or_all() {
        $tire = array( 'size' => ' 245/60r18 ' );
        $this->assertSame(
            array( array( 'vehicle' => 'R2', 'wheel' => '18" aftermarket wheels', 'note' => 'Needs an 8.5-inch or wider wheel.' ) ),
            RTG_Fitment::third_party_fits( $tire, $this->third_party, 'R2' ),
            'case and whitespace do not matter'
        );
        $this->assertSame( array(), RTG_Fitment::third_party_fits( $tire, $this->third_party, 'R1' ) );
        $this->assertSame( array( 'R2' ), array_column( RTG_Fitment::third_party_fits( $tire, $this->third_party ), 'vehicle' ) );
        $this->assertSame( array(), RTG_Fitment::third_party_fits( array( 'size' => '255/50R20' ), $this->third_party ) );
        $this->assertSame( array(), RTG_Fitment::third_party_fits( $tire, array() ) );
    }

    public function test_describe_third_party() {
        $this->assertSame( 18, RTG_Fitment::rim_inches( '245/60R18' ) );
        $this->assertSame( 0, RTG_Fitment::rim_inches( 'odd' ) );
        $this->assertSame(
            'Fits R2 on 3rd-party 18" wheels only. Not a factory size, so fitment may vary.',
            RTG_Fitment::describe_third_party( '245/60R18', array( array( 'vehicle' => 'R2' ) ) )
        );
        $this->assertSame(
            'Fits R1 and R2 on 3rd-party 18" wheels only. Not a factory size, so fitment may vary.',
            RTG_Fitment::describe_third_party( '245/60R18', array( array( 'vehicle' => 'R1' ), array( 'vehicle' => 'R2' ) ) )
        );
        $this->assertSame( '', RTG_Fitment::describe_third_party( '245/60R18', array() ) );
    }

    public function test_the_third_party_map_is_built_from_wheel_rows() {
        $wheels = array(
            array( 'name' => '20" All-Terrain', 'stock_size' => '255/55R20', 'alt_sizes' => '265/55R20', 'vehicles' => 'R2', 'source' => 'oem', 'fitment_note' => '' ),
            array( 'name' => '18" aftermarket wheels', 'stock_size' => '245/60R18', 'alt_sizes' => '255/65R18, 255/55r20', 'vehicles' => 'R2', 'source' => 'third_party', 'fitment_note' => 'Fitment may vary.' ),
            array( 'name' => '18" aftermarket R1', 'stock_size' => '275/65R18', 'alt_sizes' => '', 'vehicles' => 'R1T, R1S', 'source' => 'third_party', 'fitment_note' => '' ),
            array( 'name' => '18" All-Terrain', 'stock_size' => '275/65R18', 'alt_sizes' => '', 'vehicles' => 'R1T', 'source' => '', 'fitment_note' => '' ),
        );
        $map = RTG_Database::build_third_party_size_map( $wheels );

        $this->assertSame(
            array(
                'R2' => array(
                    '245/60R18' => array( 'wheel' => '18" aftermarket wheels', 'note' => 'Fitment may vary.' ),
                    '255/65R18' => array( 'wheel' => '18" aftermarket wheels', 'note' => 'Fitment may vary.' ),
                ),
            ),
            $map,
            'a factory listing wins (255/55R20, any case), and R1 has none because a factory R1T wheel carries 275/65R18'
        );
        $this->assertSame( array(), RTG_Database::build_third_party_size_map( array() ) );
    }

    public function test_wheel_source_normalizes_to_two_values() {
        $this->assertSame( 'third_party', RTG_Database::normalize_wheel_source( 'third_party' ) );
        $this->assertSame( 'third_party', RTG_Database::normalize_wheel_source( ' Aftermarket ' ) );
        $this->assertSame( 'oem', RTG_Database::normalize_wheel_source( 'oem' ) );
        $this->assertSame( 'oem', RTG_Database::normalize_wheel_source( '' ) );
        $this->assertSame( 'oem', RTG_Database::normalize_wheel_source( 'anything else' ) );
        $this->assertTrue( RTG_Database::is_third_party_wheel( array( 'source' => 'third_party' ) ) );
        $this->assertFalse( RTG_Database::is_third_party_wheel( array() ) );
    }

    public function test_describe_one_vehicle() {
        $this->assertSame(
            'Load index 110 is below the R1 minimum of 116.',
            RTG_Fitment::describe( '110', array( array( 'vehicle' => 'R1', 'floor' => 116 ) ) )
        );
        $this->assertSame( '', RTG_Fitment::describe( '116', array() ) );
    }

    public function test_floors_come_from_the_qualifier_settings() {
        // No wheels in a fresh test database: no vehicles, so no floors —
        // the same source the discovery qualifier gates on.
        $this->assertSame( RTG_Tire_Qualifier::get_vehicle_minimums(), RTG_Fitment::floors() );
    }
}
