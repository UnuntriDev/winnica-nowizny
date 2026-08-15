import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const baseUrl = (process.env.WINNICA_TEST_URL || 'http://localhost:8080').replace(/\/$/, '');

const homepageResponse = await fetch(`${baseUrl}/`, { redirect: 'manual' });
assert.equal(homepageResponse.status, 200, 'homepage should return 200');
const homepage = await homepageResponse.text();

assert.match(homepage, /<main id="main"/, 'homepage should contain the main landmark');
assert.match(homepage, /class="no-js"/, 'raw HTML should start in the no-js state');
assert.match(homepage, /assets\/dist\/assets\/main-[^"]+\.js/, 'built JavaScript should be loaded');
assert.match(homepage, /assets\/dist\/assets\/styles-[^"]+\.css/, 'built CSS should be loaded');
// Hours used to be withheld from the schema while unconfirmed. They are
// confirmed now, so the check flips: the structured data has to stay in step
// with the visible text, and the seasonal entries only mean something if their
// validity window carries the current year.
const schemaBlocks = [...homepage.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)]
  .map(match => JSON.parse(match[1]));
const winery = schemaBlocks
  .flatMap(block => block['@graph'] || [block])
  .find(node => Array.isArray(node['@type']) ? node['@type'].includes('Winery') : node['@type'] === 'Winery');
assert.ok(winery, 'schema should describe the winery');
assert.deepEqual(
  winery.geo,
  { '@type': 'GeoCoordinates', latitude: 49.796594, longitude: 20.6073976 },
  'schema should publish the confirmed winery coordinates',
);
const webpageSchema = schemaBlocks
  .flatMap(block => block['@graph'] || [block])
  .find(node => node['@type'] === 'WebPage');
assert.equal(
  webpageSchema?.description,
  'Winnica Nowizny to rodzinna winnica w Małopolsce, w Połomiu Małym. Poznaj nasze wina, degustacje, regionalną kuchnię i zabytkową piwnicę.',
  'WebPage schema and the approved meta description should stay in sync',
);
const hours = winery.openingHoursSpecification;
assert.ok(Array.isArray(hours) && hours.length > 0, 'opening hours should be published in schema');
const currentYear = String(new Date().getFullYear());
for (const entry of hours) {
  assert.equal(entry['@type'], 'OpeningHoursSpecification', 'each entry should be an OpeningHoursSpecification');
  assert.ok(Array.isArray(entry.dayOfWeek) && entry.dayOfWeek.length > 0, 'each entry should name its days');
  assert.match(entry.opens, /^\d{2}:\d{2}$/, 'opening time should be HH:MM');
  assert.match(entry.closes, /^\d{2}:\d{2}$/, 'closing time should be HH:MM');
  assert.ok(
    entry.validFrom.startsWith(currentYear) && entry.validThrough.startsWith(currentYear),
    'seasonal hours should be dated to the current year, not a hardcoded past one',
  );
}
assert.match(homepage, /rel=["'][^"']*icon/, 'WordPress site icon should be rendered');
assert.match(
  homepage,
  /<link\b[^>]*rel=["']icon["'][^>]*type=["']image\/png["'][^>]*site-icon\.png[^>]*>/i,
  'PNG favicon fallback should be rendered alongside the WordPress site icon',
);

const titleTags = homepage.match(/<title\b[^>]*>[\s\S]*?<\/title>/gi) || [];
assert.equal(titleTags.length, 1, 'homepage should render exactly one title element');
assert.equal(
  titleTags[0].replace(/<[^>]+>/g, '').trim(),
  'Winnica Nowizny | Winnica w Małopolsce',
  'homepage title should match the approved SEO title',
);

const descriptionTags = homepage.match(/<meta\b(?=[^>]*\bname=["']description["'])[^>]*>/gi) || [];
assert.equal(descriptionTags.length, 1, 'homepage should render exactly one meta description');
assert.ok(
  descriptionTags[0].includes('Winnica Nowizny to rodzinna winnica w Małopolsce, w Połomiu Małym. Poznaj nasze wina, degustacje, regionalną kuchnię i zabytkową piwnicę.'),
  'homepage meta description should match the approved SEO description',
);
assert.match(
  homepage,
  /Winnica Nowizny to rodzinna winnica w Małopolsce, położona w Połomiu Małym, w gminie Iwkowa, na Pogórzu Rożnowskim\./,
  'homepage should contain the approved natural non-brand SEO sentence',
);

// Exercise the rejected-submission path without creating a message or sending mail.
// This is the regression test for the former attribute-injection path.
const hiddenInputs = new Map();
for (const tag of homepage.match(/<input\b[^>]*>/gi) || []) {
  const name = tag.match(/\bname="([^"]+)"/i)?.[1];
  const value = tag.match(/\bvalue="([^"]*)"/i)?.[1] || '';
  if (name) hiddenInputs.set(name, value.replaceAll('&amp;', '&'));
}
const formAction = homepage.match(/<form\b[^>]*class="[^"]*contact-form[^"]*"[^>]*action="([^"]+)"/i)?.[1]
  || homepage.match(/<form\b[^>]*action="([^"]+)"[^>]*class="[^"]*contact-form/i)?.[1];
assert.ok(formAction, 'contact form action should be present');
assert.ok(hiddenInputs.get('winnica_contact_nonce'), 'contact nonce should be present');
assert.ok(hiddenInputs.get('contact_started'), 'signed contact start token should be present');

const xssProbe = '" onfocus="window.__auditXss=1';
const invalidBody = new URLSearchParams({
  action: hiddenInputs.get('action') || 'winnica_contact_form',
  winnica_contact_nonce: hiddenInputs.get('winnica_contact_nonce'),
  contact_started: hiddenInputs.get('contact_started'),
  website: '',
  contact_name: xssProbe,
  contact_email: 'nie-email',
  contact_topic: '',
  contact_message: 'x',
});
const invalidResponse = await fetch(formAction.replaceAll('&amp;', '&'), {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded' },
  body: invalidBody,
  redirect: 'follow',
});
assert.equal(invalidResponse.status, 200, 'invalid form should return to the page');
assert.match(invalidResponse.url, /[?&]contact=validation\b/, 'invalid form should use validation status');
const invalidPage = await invalidResponse.text();
assert.match(invalidPage, /value="&quot; onfocus=&quot;window.__auditXss=1"/, 'restored value must be escaped');
assert.doesNotMatch(invalidPage, /value="" onfocus="window\.__auditXss=1"/, 'restored value must not inject an attribute');
assert.match(invalidPage, /class="form-field-error/, 'invalid fields should have field-level errors');
assert.match(invalidPage, /aria-describedby="[^"]*contact-email-error/, 'field errors should be connected with ARIA');

const healthResponse = await fetch(`${baseUrl}/wp-json/winnica/v1/health`);
assert.equal(healthResponse.status, 200, 'health endpoint should return 200');
const health = await healthResponse.json();
assert.deepEqual(Object.keys(health).sort(), ['service', 'status']);
assert.equal(health.status, 'ok');

const wineRestResponse = await fetch(`${baseUrl}/wp-json/wp/v2/wino`);
assert.equal(wineRestResponse.status, 404, 'private wines should not have a public REST route');

const debugLogResponse = await fetch(`${baseUrl}/wp-content/debug.log`, { redirect: 'manual' });
assert.notEqual(debugLogResponse.status, 200, 'debug.log must not be publicly readable');

const directThemeResponse = await fetch(`${baseUrl}/wp-content/themes/winnica-nowizny/`, {
  redirect: 'manual',
});
assert.notEqual(directThemeResponse.status, 500, 'direct theme URL must not execute into a fatal error');

const manifestPath = resolve('assets/dist/.vite/manifest.json');
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
for (const entryName of ['src/js/main.js', 'src/css/main.css']) {
  const entry = manifest[entryName];
  assert.ok(entry?.file, `${entryName} should exist in the manifest`);
  const asset = await readFile(resolve('assets/dist', entry.file), 'utf8');
  assert.doesNotMatch(asset, /__VITE_ASSET__/, `${entryName} must not contain unresolved Vite tokens`);
}

console.log(`Smoke tests passed for ${baseUrl}`);
