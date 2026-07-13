#!/usr/bin/env node
// Minimal chromium-cli-alike REPL driver for headless verification of this
// Laravel + Blade + Alpine app. No custom app code depends on this — it's
// agent tooling for driving the running app in a real browser.
//
// Usage: node driver.mjs --session <name> < script.txt
//
// Commands (one per line):
//   nav <url>
//   wait-for text=<text>            (or) wait-for <css-selector>
//   click <css-selector>
//   fill <css-selector> <value...>
//   set-input-file <css-selector> <path>
//   press <key>
//   screenshot [name]
//   screenshot-element <css-selector> [name]
//   text-content <css-selector>
//   console --errors
//   eval <js-expression>
//   sleep <ms>
//
// Screenshots land in chromium_cli/sessions/<name>/screenshots/, with the
// latest also copied to screenshots/screenshot.png.

import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import readline from 'readline';

const args = process.argv.slice(2);
const sessionIdx = args.indexOf('--session');
const sessionName = sessionIdx >= 0 ? args[sessionIdx + 1] : 'default';

const sessionDir = path.join('chromium_cli', 'sessions', sessionName);
const screenshotsDir = path.join(sessionDir, 'screenshots');
fs.mkdirSync(screenshotsDir, { recursive: true });

const consoleErrors = [];

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const context = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await context.newPage();
page.on('console', (msg) => {
  if (msg.type() === 'error') consoleErrors.push(msg.text());
});
page.on('pageerror', (err) => consoleErrors.push(String(err)));

let shotCount = 0;

function parseArgs(str) {
  return str.trim().split(/\s+/);
}

async function runLine(raw) {
  const line = raw.trim();
  if (!line || line.startsWith('#')) return;
  const sp = line.indexOf(' ');
  const cmd = sp === -1 ? line : line.slice(0, sp);
  const rest = sp === -1 ? '' : line.slice(sp + 1).trim();

  switch (cmd) {
    case 'nav': {
      await page.goto(rest, { waitUntil: 'domcontentloaded', timeout: 30000 });
      console.log(`[nav] ${rest} -> ${page.url()}`);
      break;
    }
    case 'wait-for': {
      let locator;
      if (rest.startsWith('text=')) {
        locator = page.getByText(rest.slice(5), { exact: false });
      } else {
        locator = page.locator(rest);
      }
      await locator.first().waitFor({ state: 'visible', timeout: 15000 });
      console.log(`[wait-for] ok: ${rest}`);
      break;
    }
    case 'click': {
      await page.locator(rest).first().click({ timeout: 10000 });
      console.log(`[click] ${rest}`);
      break;
    }
    case 'fill': {
      const parts = parseArgs(rest);
      const selector = parts[0];
      const value = rest.slice(selector.length).trim();
      await page.locator(selector).first().fill(value, { timeout: 10000 });
      console.log(`[fill] ${selector} = ${value}`);
      break;
    }
    case 'set-input-file': {
      const parts = parseArgs(rest);
      const selector = parts[0];
      const filePath = rest.slice(selector.length).trim();
      await page.locator(selector).first().setInputFiles(filePath);
      console.log(`[set-input-file] ${selector} = ${filePath}`);
      break;
    }
    case 'press': {
      await page.keyboard.press(rest);
      console.log(`[press] ${rest}`);
      break;
    }
    case 'screenshot': {
      shotCount += 1;
      const name = rest || `shot-${shotCount}`;
      const file = path.join(screenshotsDir, `${name}.png`);
      await page.screenshot({ path: file, fullPage: true });
      const latest = path.join(screenshotsDir, 'screenshot.png');
      fs.copyFileSync(file, latest);
      console.log(`[screenshot] ${file}`);
      break;
    }
    case 'screenshot-element': {
      const parts = parseArgs(rest);
      const selector = parts[0];
      const name = parts[1] || `element-${++shotCount}`;
      const file = path.join(screenshotsDir, `${name}.png`);
      await page.locator(selector).first().screenshot({ path: file });
      console.log(`[screenshot-element] ${file}`);
      break;
    }
    case 'console': {
      if (rest.includes('--errors')) {
        if (consoleErrors.length === 0) {
          console.log('[console] no errors');
        } else {
          console.log('[console] errors:');
          consoleErrors.forEach((e) => console.log('  ' + e));
        }
      }
      break;
    }
    case 'eval': {
      const result = await page.evaluate(rest);
      console.log('[eval]', result);
      break;
    }
    case 'sleep': {
      await new Promise((r) => setTimeout(r, Number(rest)));
      break;
    }
    case 'text-content': {
      const text = await page.locator(rest).first().textContent();
      console.log('[text-content]', text);
      break;
    }
    default:
      console.log(`[?] unknown command: ${cmd}`);
  }
}

const rl = readline.createInterface({ input: process.stdin });
for await (const line of rl) {
  try {
    await runLine(line);
  } catch (err) {
    console.error(`[error] "${line}" ->`, err.message);
  }
}

await browser.close();
