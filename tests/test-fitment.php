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
                array( 'vehicle' => 'R1', 'floor' => 116, 'ok' => false ),
                array( 'vehicle' => 'R2', 'floor' => 112, 'ok' => true ),
            ),
            $out
        );
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
