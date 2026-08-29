import { access, mkdir, readFile, writeFile } from 'node:fs/promises'
import { chromium } from '@playwright/test'

async function loadQaEnvironment() {
  try {
    const contents = await readFile('scripts/qa/.env.qa', 'utf8')
    for (const line of contents.split(/\r?\n/)) {
      const trimmed = line.trim()
      if (!trimmed || trimmed.startsWith('#') || !trimmed.includes('=')) continue

      const separator = trimmed.indexOf('=')
      const key = trimmed.slice(0, separator).trim()
      let value = trimmed.slice(separator + 1).trim()
      if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
        value = value.slice(1, -1)
      }
      if (process.env[key] === undefined) process.env[key] = value
    }
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
  }
}

await loadQaEnvironment()

const BASE_URL = process.env.QA_BASE_URL || 'https://staging.home.nectoria.com'
const OUTPUT_DIR = process.env.QA_OUTPUT_DIR || 'storage/app/qa'
const RUN_ID = process.env.QA_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-')
const RUN_ENABLED = process.env.QA_RUN === '1'
const CAPTURE_SCREENSHOTS = process.env.QA_CAPTURE_SCREENSHOTS !== '0'
const EMAIL = process.env.QA_EMAIL
const PASSWORD = process.env.QA_PASSWORD
const PROTECTED_ORGANIZATION = process.env.QA_PROTECTED_ORGANIZATION || 'Helvetia Full E2E Sarl'

const phasePaths = {
  0: ['/login', '/signup', '/forgot-password'],
  1: ['/accounting/opening-balances', '/accounting/journal-entries'],
  2: ['/contacts', '/invoices', '/expenses', '/banking'],
  3: ['/payroll/employees', '/payroll/salary-slips'],
  4: ['/reports/vat', '/reconciliation'],
  5: ['/organizations', '/settings'],
  6: ['/accounting/year-end-closing', '/accounting/archives'],
  7: ['/accounting/fiscal-years', '/reports/profit-and-loss'],
  8: ['/accounting/archives', '/reports/profit-and-loss'],
  9: ['/billing'],
  10: ['/dashboard', '/reports/profit-and-loss', '/reports/balance-sheet', '/reports/aging'],
}

function assertStagingUrl(url) {
  const parsed = new URL(url)
  if (parsed.protocol !== 'https:' || !parsed.hostname.endsWith('.nectoria.com') || parsed.hostname !== 'staging.home.nectoria.com') {
    throw new Error(`Refusing non-staging URL: ${url}`)
  }
}

function assertSafeConfiguration() {
  assertStagingUrl(BASE_URL)
  if (PROTECTED_ORGANIZATION.trim().length < 8) {
    throw new Error('QA_PROTECTED_ORGANIZATION must identify the protected tenant')
  }
  if (RUN_ENABLED && (!EMAIL || !PASSWORD)) {
    throw new Error('QA_EMAIL and QA_PASSWORD are required when QA_RUN=1')
  }
}

function result(phase, name, status, details = {}) {
  return { phase, name, status, ...details }
}

async function browserExecutablePath() {
  if (process.env.QA_BROWSER_PATH) return process.env.QA_BROWSER_PATH

  const resolvedPath = chromium.executablePath()
  const candidates = [
    resolvedPath,
    resolvedPath
      .replace('chromium_headless_shell-', 'chromium-')
      .replace('/headless_shell', '/chrome'),
  ]

  for (const candidate of candidates) {
    try {
      await access(candidate)
      return candidate
    } catch {
      continue
    }
  }

  throw new Error(`No usable Chromium executable found. Set QA_BROWSER_PATH (Playwright resolved ${resolvedPath})`)
}

async function createContext(browser) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } })
  const page = await context.newPage()
  const consoleErrors = []
  const requestFailures = []

  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text())
  })
  page.on('requestfailed', request => {
    requestFailures.push({ url: request.url(), error: request.failure()?.errorText || null })
  })

  return { context, page, consoleErrors, requestFailures }
}

async function checkPage(page, path, screenshotDirectory) {
  const startedAt = Date.now()
  const response = await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' })
  const body = await page.locator('body').innerText().catch(() => '')

  const screenshotPath = `${screenshotDirectory}/phase-${path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'root'}.png`
  if (CAPTURE_SCREENSHOTS) await page.screenshot({ path: screenshotPath, fullPage: true })

  return {
    path,
    httpStatus: response?.status() ?? null,
    url: page.url(),
    title: await page.title(),
    heading: await page.locator('h1,h2,h3').first().textContent().catch(() => null),
    bodySample: body.slice(0, 280),
    durationMs: Date.now() - startedAt,
    ...(CAPTURE_SCREENSHOTS ? { screenshotPath } : {}),
  }
}

async function login(page) {
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' })
  await page.locator('#email').fill(EMAIL)
  await page.locator('#password').fill(PASSWORD)
  const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/login'))
  await page.getByRole('button', { name: /Sign in|Connexion|Se connecter/ }).click()
  const response = await responsePromise
  await page.waitForURL(url => !url.toString().endsWith('/login'), { timeout: 30000 })

  return { httpStatus: response.status(), url: page.url() }
}

async function responsiveCheck(page) {
  const checks = []
  for (const width of [375, 768, 1440]) {
    await page.setViewportSize({ width, height: 812 })
    await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle' })
    const dimensions = await page.evaluate(() => ({
      viewport: document.documentElement.clientWidth,
      scrollWidth: document.documentElement.scrollWidth,
      bodyScrollWidth: document.body.scrollWidth,
    }))
    checks.push({ width, ...dimensions, overflow: dimensions.scrollWidth > dimensions.viewport || dimensions.bodyScrollWidth > dimensions.viewport })
  }
  return checks
}

function markdownReport(report) {
  const failures = report.results.filter(item => item.status === 'fail')
  const skipped = report.results.filter(item => item.status === 'skip')
  const lines = [
    '# Staging QA Runner Report',
    '',
    `- Run: \`${report.runId}\``,
    `- Date: ${report.startedAt}`,
    `- Base URL: ${report.baseUrl}`,
    `- Mode: ${report.mode}`,
    `- Result: **${failures.length ? 'FAIL' : 'PASS WITH COVERAGE NOTES'}**`,
    '',
    `## Summary`,
    '',
    `- Passed: ${report.results.filter(item => item.status === 'pass').length}`,
    `- Failed: ${failures.length}`,
    `- Skipped: ${skipped.length}`,
    `- Console errors: ${report.consoleErrors.length}`,
    `- Request failures: ${report.requestFailures.length}`,
    '',
    '## Phase Status',
    '',
    '| Phase | Status |',
    '|---:|---|',
    ...Array.from({ length: 11 }, (_, phase) => {
      const phaseResults = report.results.filter(item => item.phase === phase)
      const status = phaseResults.some(item => item.status === 'fail') ? 'FAIL' : phaseResults.some(item => item.status === 'pass') ? 'PASS' : 'SKIP'
      return `| ${phase} | ${status} |`
    }),
    '',
    '## Findings',
    '',
    failures.length ? failures.map(item => `- **${item.name}**: ${item.error || 'failed'}`).join('\n') : '- No application failures detected.',
    '',
    '## Coverage Notes',
    '',
    '- Destructive actions and tenant creation are disabled unless explicitly implemented by a phase adapter.',
    '- Raw evidence is in the adjacent JSON file; secrets are never written to either artifact.',
  ]
  return lines.join('\n')
}

async function main() {
  assertSafeConfiguration()
  await mkdir(OUTPUT_DIR, { recursive: true })

  const report = {
    runId: RUN_ID,
    startedAt: new Date().toISOString(),
    baseUrl: BASE_URL,
    mode: RUN_ENABLED ? 'staging-safe-smoke' : 'dry-run',
    protectedOrganization: '[redacted]',
    results: [],
    consoleErrors: [],
    requestFailures: [],
  }

  if (!RUN_ENABLED) {
    report.results.push(result(0, 'runner configuration', 'skip', { reason: 'Set QA_RUN=1 to execute staging checks' }))
  } else {
    const browser = await chromium.launch({
      headless: process.env.QA_HEADLESS !== '0',
      executablePath: await browserExecutablePath(),
    })
    const { context, page, consoleErrors, requestFailures } = await createContext(browser)
    const screenshotDirectory = `${OUTPUT_DIR}/screenshots-${RUN_ID}`
    await mkdir(screenshotDirectory, { recursive: true })
    try {
      const auth = await login(page)
      report.results.push(result(0, 'authenticated login', auth.httpStatus === 302 ? 'pass' : 'fail', auth))

      for (const [phase, paths] of Object.entries(phasePaths)) {
        for (const path of paths) {
          try {
            const checked = await checkPage(page, path, screenshotDirectory)
            const status = checked.httpStatus === 200 ? 'pass' : 'fail'
            report.results.push(result(Number(phase), `GET ${path}`, status, checked))
          } catch (error) {
            report.results.push(result(Number(phase), `GET ${path}`, 'fail', { error: error.message }))
          }
        }
      }

      const responsive = await responsiveCheck(page)
      report.results.push(result(10, 'responsive overflow', responsive.every(check => !check.overflow) ? 'pass' : 'fail', { checks: responsive }))
    } finally {
      report.consoleErrors = consoleErrors
      report.requestFailures = requestFailures
      await context.close()
      await browser.close()
    }
  }

  report.finishedAt = new Date().toISOString()
  const jsonPath = `${OUTPUT_DIR}/staging-qa-${RUN_ID}.json`
  const markdownPath = `${OUTPUT_DIR}/staging-qa-${RUN_ID}.md`
  await writeFile(jsonPath, `${JSON.stringify(report, null, 2)}\n`)
  await writeFile(markdownPath, `${markdownReport(report)}\n`)

  const failures = report.results.filter(item => item.status === 'fail')
  console.log(JSON.stringify({ runId: report.runId, mode: report.mode, passed: report.results.filter(item => item.status === 'pass').length, failed: failures.length, skipped: report.results.filter(item => item.status === 'skip').length, jsonPath, markdownPath }))
  if (failures.length) process.exitCode = 1
}

main().catch(error => {
  console.error(JSON.stringify({ error: error.message }))
  process.exitCode = 1
})