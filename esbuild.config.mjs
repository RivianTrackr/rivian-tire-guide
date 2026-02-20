import * as esbuild from 'esbuild';

const isWatch = process.argv.includes('--watch');

/** Shared options for JS builds. */
const jsDefaults = {
  bundle: false,
  platform: 'browser',
  target: ['es2020'],
  charset: 'utf8',
};

/** All build targets. */
const builds = [
  // --- Frontend JS (main guide) — bundled from ES modules ---
  {
    ...jsDefaults,
    bundle: true,
    entryPoints: ['frontend/js/rivian-tires.js'],
    outfile: 'frontend/js/rivian-tires.min.js',
    format: 'iife',
    minify: true,
    drop: ['console', 'debugger'],
  },
  // --- Frontend JS (shared utilities) ---
  {
    ...jsDefaults,
    entryPoints: ['frontend/js/rtg-shared.js'],
    outfile: 'frontend/js/rtg-shared.min.js',
    minify: true,
    drop: ['console', 'debugger'],
  },
  // --- Frontend JS (compare page) ---
  {
    ...jsDefaults,
    entryPoints: ['frontend/js/compare.js'],
    outfile: 'frontend/js/compare.min.js',
    minify: true,
    drop: ['console', 'debugger'],
  },
  // --- Frontend JS (user reviews) ---
  {
    ...jsDefaults,
    entryPoints: ['frontend/js/user-reviews.js'],
    outfile: 'frontend/js/user-reviews.min.js',
    minify: true,
    drop: ['console', 'debugger'],
  },
  // --- Frontend CSS ---
  {
    entryPoints: ['frontend/css/rivian-tires.css'],
    outfile: 'frontend/css/rivian-tires.min.css',
    minify: true,
    bundle: false,
  },
  // --- Admin JS ---
  {
    ...jsDefaults,
    entryPoints: ['admin/js/admin-scripts.js'],
    outfile: 'admin/js/admin-scripts.min.js',
    minify: true,
    drop: ['console', 'debugger'],
  },
  // --- Admin CSS ---
  {
    entryPoints: ['admin/css/admin-styles.css'],
    outfile: 'admin/css/admin-styles.min.css',
    minify: true,
    bundle: false,
  },
];

async function run() {
  if (isWatch) {
    const contexts = await Promise.all(builds.map(b => esbuild.context(b)));
    await Promise.all(contexts.map(ctx => ctx.watch()));
    console.log('Watching for changes...');
  } else {
    const results = await Promise.all(builds.map(b => esbuild.build(b)));
    console.log('Build complete:');
    builds.forEach((b, i) => {
      const src = Array.isArray(b.entryPoints) ? b.entryPoints[0] : b.entryPoints;
      console.log(`  ${src} → ${b.outfile}`);
    });
  }
}

run().catch((err) => {
  console.error('Build failed:', err);
  process.exit(1);
});
