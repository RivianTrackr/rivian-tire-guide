<?php
/**
 * Executable contract check for RTG_Whats_New.
 *
 * Two promises, pinned on plain PHP with no WordPress and no database:
 *
 * 1. The parser reads the shape WHATS-NEW.md is written in, and the file
 *    that ships actually parses: a release with a bad heading or no bullets
 *    would silently vanish from the page, and nothing else would notice.
 * 2. The inline renderer treats the notes as text. Bold, code and links
 *    come through; a stray tag or a javascript: URL never becomes markup.
 * 3. A release ships with its note. The newest heading in WHATS-NEW.md must
 *    be the plugin's version, unless the CHANGELOG entry for that version
 *    says "Nothing visible to owners" in so many words. Forgetting the
 *    owner-facing note is a red build, not a quiet omission.
 *
 * Run with: php tests/contract/whats-new.php
 * Exits non-zero on failure, so CI gates on it.
 */

define( 'ABSPATH', __DIR__ );

function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $u ) { return preg_match( '#^https?://#i', $u ) ? htmlspecialchars( $u, ENT_QUOTES, 'UTF-8' ) : ''; }
function date_i18n( $f, $ts ) { return gmdate( $f, $ts ); }

require __DIR__ . '/../../includes/class-rtg-whats-new.php';

$failures = 0;
function check( $ok, $what ) {
    global $failures;
    echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
    if ( ! $ok ) {
        $failures++;
    }
}

// --- Parsing -----------------------------------------------------------

$sample = <<<'MD'
# What's new

Preamble that is not a release. Neither is this:
- a bullet above the first heading

## [1.92.0] - 2026-09-03

An intro line
that wraps.

- **A bold lead.** And its detail, with `code` and a [link](https://example.com/a?b=1&c=2).
- **Lead only.**
- Plain bullet without a lead
  that continues on an indented line.

### A sub-heading the format ignores

## 1.91.2 - 2026-09-02
- **Second release.** Detail.

## not-a-version - 2026-09-01
- swallowed into the release above? no: no heading matched, so it continues 1.91.2
MD;

$releases = RTG_Whats_New::parse( $sample );

check( 2 === count( $releases ), 'two releases parsed, preamble and sub-heading skipped' );
check( '1.92.0' === $releases[0]['version'] && '2026-09-03' === $releases[0]['date'], 'bracketed heading gives version and date' );
check( '1.91.2' === $releases[1]['version'], 'plain heading gives version' );
check( 'An intro line that wraps.' === $releases[0]['intro'], 'intro lines join into one paragraph' );
check( 3 === count( $releases[0]['items'] ), 'three bullets in the first release' );
check( 'A bold lead.' === $releases[0]['items'][0]['lead'], 'bold opening becomes the lead' );
check( 0 === strpos( $releases[0]['items'][0]['detail'], 'And its detail' ), 'text after the bold is the detail' );
check( 'Lead only.' === $releases[0]['items'][1]['lead'] && '' === $releases[0]['items'][1]['detail'], 'a bullet that is all bold has no detail' );
check( 'Plain bullet without a lead that continues on an indented line.' === $releases[0]['items'][2]['lead'], 'a plain bullet is all lead and its continuation joins it' );
check( 2 === count( $releases[1]['items'] ), 'a line under the last release that is not a heading stays with it' );
check( array() === RTG_Whats_New::parse( '' ), 'an empty file is no releases' );

// --- Rendering ---------------------------------------------------------

$view = RTG_Whats_New::to_view( $releases );
check( true === $view[0]['latest'] && false === $view[1]['latest'], 'only the first release is marked latest' );
check( 'Sep 3, 2026' === $view[0]['date_display'], 'the date reads as Sep 3, 2026' );
check( false !== strpos( $view[0]['items'][0]['detail'], '<code>code</code>' ), 'backticks render as code' );
check( false !== strpos( $view[0]['items'][0]['detail'], '<a href="https://example.com/a?b=1&amp;c=2">link</a>' ), 'a markdown link renders with an escaped href' );

$r = 'RTG_Whats_New::render_inline';
check( 'a &lt;b&gt; c' === $r( 'a <b> c' ), 'angle brackets are escaped' );
check( '<strong>x &amp; y</strong> z' === $r( '**x & y** z' ), 'bold renders and its text is escaped' );
check( 'click' === $r( '[click](javascript:void)' ), 'a javascript: link is not a link at all' );
check( 'x' === $r( '[x](ftp://example.com)' ), 'a non-http link is dropped to its text' );
check( '' === $r( '' ), 'empty in, empty out' );

$html = RTG_Whats_New::render_list( $view );
check( false !== strpos( $html, '<span class="rtg-wn-version">1.92.0</span>' ), 'the list carries the version chip' );
check( false !== strpos( $html, '<span class="rtg-wn-latest">Latest</span>' ), 'the newest release carries the Latest chip' );
check( 1 === substr_count( $html, 'rtg-wn-latest' ), 'and only the newest' );
check( false !== strpos( $html, '<li><strong>Lead only.</strong></li>' ), 'a lead-only bullet has no trailing space' );
check( false !== strpos( RTG_Whats_New::render_list( array() ), 'Nothing to report yet' ), 'no releases renders the empty line' );

// --- The file that ships ----------------------------------------------

$file     = __DIR__ . '/../../WHATS-NEW.md';
$shipped  = RTG_Whats_New::parse( (string) file_get_contents( $file ) );
$plugin   = (string) file_get_contents( __DIR__ . '/../../rivian-tire-guide.php' );
$declared = preg_match( "/define\( 'RTG_VERSION', '([^']+)' \)/", $plugin, $m ) ? $m[1] : '';

check( count( $shipped ) >= 5, 'WHATS-NEW.md parses to a real list (' . count( $shipped ) . ' releases)' );
check( '' !== $declared && version_compare( $shipped[0]['version'], $declared, '<=' ), 'the newest note (' . $shipped[0]['version'] . ') is not ahead of the plugin (' . $declared . ')' );

// The release rule: the current version has a note, or the changelog says
// out loud that owners would notice nothing. Either is a decision; silence is not.
$changelog = (string) file_get_contents( __DIR__ . '/../../CHANGELOG.md' );
$entry     = preg_match( '/^## \[' . preg_quote( $declared, '/' ) . '\][^\n]*\n(.*?)(?=^## \[|\z)/ms', $changelog, $m ) ? $m[1] : '';
$opted_out = false !== stripos( $entry, 'Nothing visible to owners' );
check( $shipped[0]['version'] === $declared || $opted_out, 'version ' . $declared . ' has a What\'s new note, or its changelog entry says "Nothing visible to owners"' );
check( '' !== $entry, 'version ' . $declared . ' has a changelog entry' );

$prev = null;
$ordered = true;
$all_have_items = true;
$dates_ok = true;
foreach ( $shipped as $rel ) {
    if ( null !== $prev && version_compare( $rel['version'], $prev, '>=' ) ) {
        $ordered = false;
    }
    $prev = $rel['version'];
    if ( empty( $rel['items'] ) ) {
        $all_have_items = false;
    }
    if ( ! strtotime( $rel['date'] ) ) {
        $dates_ok = false;
    }
}
check( $ordered, 'the notes are newest first with no repeated version' );
check( $all_have_items, 'every release has at least one bullet' );
check( $dates_ok, 'every release date is a real date' );

$nerdy = 0;
foreach ( $shipped as $rel ) {
    foreach ( $rel['items'] as $item ) {
        if ( preg_match( '/\bRTG_|\.php\b|\.js\b|PHPUnit|migration/i', $item['lead'] . ' ' . $item['detail'] ) ) {
            $nerdy++;
        }
    }
}
check( 0 === $nerdy, 'no note names a file, a class, a test or a migration' );

echo $failures ? "\n$failures failure(s)\n" : "\nAll checks passed\n";
exit( $failures ? 1 : 0 );
