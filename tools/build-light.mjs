// Builds frontend/css/rtg-light.css: the guide's site light mode.
//
// RivianTrackr's color modes set data-rt-theme="light" on <html>. This script reads every
// front-end stylesheet and every <style> block in frontend/templates, and re-emits each rule
// that uses a dark ink-and-brass color under html[data-rt-theme="light"], with only the
// changed declarations mapped to the light palette. Pages without the attribute are untouched.
//
// Run by `npm run build`. Edit the dark CSS as usual and rebuild, never rtg-light.css itself.

import postcss from 'postcss';
import fs from 'fs';
import path from 'path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const A = 'html[data-rt-theme="light"]';

// Dark value -> light value, by role. Keys are 6-digit lowercase hex.
const MAP = {
	'0e1014': { all: '#f6f5f2' },
	'0b0d10': { all: '#eeece7' },
	'121418': { bg: '#eeece7', border: '#e3e0d9', text: '#53565c' },
	'16191e': { bg: '#ffffff', border: '#e3e0d9', text: '#1a1c20' },
	'1a1d22': { bg: '#f6f5f2', border: '#e3e0d9', text: '#1a1c20' },
	'1f2228': { bg: '#eeece7', border: '#e3e0d9', text: '#1a1c20' },
	'23262c': { bg: '#eeece7', border: '#e3e0d9', text: '#1a1c20' },
	'24272d': { bg: '#e3e0d9', border: '#ebe8e2', text: '#53565c' },
	'25282e': { bg: '#e8e5df', border: '#e3e0d9', text: '#1a1c20' },
	'2c2f34': { all: '#d9d5cd' },
	'3a3e45': { bg: '#ffffff', border: '#e3e0d9', text: '#53565c' },
	'4e525a': { bg: '#e3e0d9', border: '#cdc9c1', text: '#53565c' },
	'ece9e4': { all: '#1a1c20' },
	'f6f4f0': { all: '#121316' },
	'a6a39c': { all: '#53565c' },
	'a19e97': { all: '#53565c' },
	'99968f': { all: '#686b71' },
	'8f8c85': { all: '#686b71' },
	'79766f': { all: '#686b71' },
	'6f6e6b': { all: '#686b71' },
	'ffffff': { text: '#121316' },
	'fba919': { text: '#8a5800' },
	'ffbe4a': { text: '#6f4700' },
	'f0b429': { text: '#8a5800' },
	'4ade80': { text: '#157f3c' },
	'34d399': { text: '#0f7a4f' },
	'60a5fa': { text: '#1e5fb8' },
	'a78bfa': { text: '#6d3fd6' },
	'ef4444': { text: '#c4281c' },
};

// Token names that hold a fill color in both modes (buttons, stars). Their usages as text are
// swapped instead, see ACCENT_VARS.
const KEEP_VAR = /accent|star-filled|star-user/;
const ACCENT_VARS = /^var\(--rtg(?:-tp)?-accent(-hover)?(?:,\s*#[0-9a-fA-F]{3,8})?\)$/;

function role(prop) {
	const p = prop.toLowerCase();
	if (/shadow/.test(p)) return 'shadow';
	// Token names: --rtg-tp-card, --rtg-bg-deep, --rtg-tp-deep and the like are surfaces.
	if (p.startsWith('--') && /card|deep|surface|panel|page|raised/.test(p) && !/text|border/.test(p)) return 'bg';
	if (/background|(^|-)bg(-|$)|surface|input|overlay|fill-bg/.test(p)) return 'bg';
	if (/border|outline|divider|separator|line|rule/.test(p)) return 'border';
	return 'text';
}

// Every color declaration of a rule is re-emitted, changed or not, so the light rules keep
// the same specificity order as the originals and a specific rule still beats a generic one.
const COLOR_PROP = /^(color|background(-color|-image)?|border(-(top|right|bottom|left|block|inline)(-start|-end)?)?(-color)?|outline(-color)?|fill|stroke|box-shadow|text-decoration(-color)?|caret-color|accent-color|--.*)$/i;
const HAS_COLOR = /#[0-9a-f]{3,8}\b|rgba?\(|var\(|color-mix|currentcolor|inherit|transparent/i;

const hex6 = h => { h = h.slice(1).toLowerCase(); if (h.length === 3 || h.length === 4) h = h.slice(0, 3).split('').map(c => c + c).join(''); return h.slice(0, 6); };

function mapValue(value, prop) {
	if (prop.startsWith('--') && KEEP_VAR.test(prop)) return value;
	if (/^(color|fill|stroke)$/.test(prop) && ACCENT_VARS.test(value.trim())) {
		return value.includes('hover') ? '#6f4700' : '#8a5800';
	}
	const r = role(prop);
	let v = value.replace(/#[0-9a-fA-F]{3,8}\b/g, m => {
		const e = MAP[hex6(m)]; if (!e) return m;
		return e[r] || e.all || m;
	});
	v = v.replace(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)/g, (m, R, G, B, Al) => {
		const a = Al === undefined ? 1 : parseFloat(Al); const [x, y, z] = [R, G, B].map(Number);
		if (a === 0) return m;
		if (x === 0 && y === 0 && z === 0) return r === 'shadow' ? `rgba(20, 18, 12, ${Math.min(a, 0.1)})` : m;
		if (x === 255 && y === 255 && z === 255 && a < 1) return `rgba(20, 18, 12, ${Math.min(a, 0.12)})`;
		if (x < 70 && y < 70 && z < 80 && a < 1 && r !== 'shadow') return `rgba(20, 18, 12, ${Math.min(a, 0.08)})`;
		return m;
	});
	return v;
}

function prefix(sel) {
	return sel.split(',').map(s => {
		s = s.trim();
		if (/^:root\b/.test(s)) return A + s.slice(5);
		if (/^html\b/.test(s)) return A + s.slice(4);
		return `${A} ${s}`;
	}).join(',\n');
}

function transform(css, source) {
	const out = postcss.root();
	postcss.parse(css).walkRules(rule => {
		if (rule.parent.type === 'atrule' && /keyframes/i.test(rule.parent.name)) return;
		// Printing has its own palette
		for (let p = rule.parent; p; p = p.parent) if (p.type === 'atrule' && /print/.test(p.params)) return;
		const changed = [];
		rule.each(d => {
			if (d.type !== 'decl' || !COLOR_PROP.test(d.prop) || !HAS_COLOR.test(d.value)) return;
			changed.push(postcss.decl({ prop: d.prop, value: mapValue(d.value, d.prop), important: d.important }));
		});
		if (!changed.length) return;
		let node = postcss.rule({ selector: prefix(rule.selector) });
		changed.forEach(d => node.append(d));
		for (let p = rule.parent; p && p.type === 'atrule'; p = p.parent) { const w = postcss.atRule({ name: p.name, params: p.params }); w.append(node); node = w; }
		out.append(node);
	});
	return out.nodes.length ? `/* ${source} */\n${out.toString()}\n` : '';
}

const sources = [];
const cssDir = path.join(root, 'frontend/css');
for (const f of fs.readdirSync(cssDir).filter(f => f.endsWith('.css') && !f.endsWith('.min.css') && !f.startsWith('rtg-light'))) {
	sources.push([`frontend/css/${f}`, fs.readFileSync(path.join(cssDir, f), 'utf8')]);
}
const tplDir = path.join(root, 'frontend/templates');
for (const f of fs.readdirSync(tplDir).filter(f => f.endsWith('.php'))) {
	const php = fs.readFileSync(path.join(tplDir, f), 'utf8');
	for (const m of php.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)) {
		const css = m[1].replace(/<\?php[\s\S]*?\?>/g, '');
		if (css.trim()) sources.push([`frontend/templates/${f}`, css]);
	}
}

let out = `/* Rivian Tire Guide: site light mode. Generated by tools/build-light.mjs, do not edit. */\n\n`;
for (const [name, css] of sources) {
	try { out += transform(css, name); } catch (e) { console.warn(`build-light: skipped ${name}: ${e.message}`); }
}
fs.writeFileSync(path.join(cssDir, 'rtg-light.css'), out);
console.log(`build-light: frontend/css/rtg-light.css (${out.length} bytes, ${sources.length} sources)`);
