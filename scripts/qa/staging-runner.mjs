import { access, mkdir, readFile, writeFile } from 'node:fs/promises'
import { execFile } from 'node:child_process'
import { promisify } from 'node:util'
import { chromium } from '@playwright/test'

const execFileAsync = promisify(execFile)

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
const CREATE_ACCOUNT = process.env.QA_CREATE_ACCOUNT === '1'
const CLEANUP_ORGANIZATION = process.env.QA_CLEANUP_ORGANIZATION !== '0'
const EMAIL = process.env.QA_EMAIL
const PASSWORD = process.env.QA_PASSWORD
const ACCOUNT_NAME = process.env.QA_ACCOUNT_NAME || `Gäld QA ${RUN_ID}`
const MAILPIT_URL = process.env.QA_MAILPIT_URL
const SSH_TARGET = process.env.QA_SSH_TARGET || 'build-remote'
const PLAN = process.env.QA_PLAN || 'free'
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
  if (RUN_ENABLED && !CREATE_ACCOUNT && (!EMAIL || !PASSWORD)) {
    throw new Error('QA_EMAIL and QA_PASSWORD are required when QA_RUN=1')
  }
  if (CLEANUP_ORGANIZATION && !CREATE_ACCOUNT) {
    throw new Error('QA_CLEANUP_ORGANIZATION requires QA_CREATE_ACCOUNT=1')
  }
  if (CLEANUP_ORGANIZATION && SSH_TARGET !== 'build-remote') {
    throw new Error('QA cleanup is restricted to the build-remote staging host')
  }
  if (!['free', 'business'].includes(PLAN)) {
    throw new Error('QA_PLAN must be free or business')
  }
}

function result(phase, name, status, details = {}) {
  return { phase, name, status, ...details }
}

function generatedAccountEmail() {
  return `gaeld-qa-${RUN_ID}@example.test`
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
  if (!page.url().endsWith('/login')) return { httpStatus: 302, url: page.url() }

  await page.locator('#email').fill(EMAIL)
  await page.locator('#password').fill(PASSWORD)
  const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/login'))
  await page.getByRole('button', { name: /Sign in|Connexion|Se connecter/ }).click()
  const response = await responsePromise
  await page.waitForURL(url => !url.toString().endsWith('/login'), { timeout: 30000 })

  return { httpStatus: response.status(), url: page.url() }
}

async function createAccount(page) {
  const email = generatedAccountEmail()
  const password = `Qa-${RUN_ID.replace(/[^a-zA-Z0-9]/g, '')}-Aa1!`

  await page.goto(`${BASE_URL}/signup`, { waitUntil: 'networkidle' })
  const cookieButton = page.getByRole('button', { name: /Accept|Accepter|Agree|J'accepte/ })
  if (await cookieButton.count()) await cookieButton.first().click()
  await page.locator('button[role="radio"]').filter({ hasText: PLAN === 'business' ? 'Business' : 'Free' }).first().click()
  await page.locator('#signup-name').fill(ACCOUNT_NAME)
  await page.locator('#signup-org-name').fill(`${ACCOUNT_NAME} ${RUN_ID} Organization`)
  await page.locator('#signup-email').fill(email)
  await page.locator('#signup-password').fill(password)
  await page.locator('#signup-password-confirmation').fill(password)
  await page.locator('input[type="checkbox"]').last().check()
  const submitResponse = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/signup'), { timeout: 30000 })
  await page.getByRole('button', { name: /Create.*account|Start.*trial|Créer.*compte|Commencer.*essai/ }).click()
  const response = await submitResponse
  await page.waitForURL(url => /\/email\/verify|\/welcome|\/billing/.test(new URL(url.toString()).pathname) || new URL(url.toString()).hostname.endsWith('stripe.com'), { timeout: 30000 })
  await page.waitForLoadState('domcontentloaded')

  return {
    httpStatus: response.status(),
    email,
    password,
    plan: PLAN,
    url: page.url(),
    checkoutUrl: response.headers()['x-inertia-location'] || null,
    needsVerification: page.url().includes('/email/verify') || await page.getByRole('heading', { name: /Verify your email|Vérifiez votre adresse/ }).count() > 0,
  }
}

async function completeStripeCheckout(page, checkoutUrl = null) {
  if (checkoutUrl) await page.goto(checkoutUrl, { waitUntil: 'domcontentloaded' })

  const checkoutHost = new URL(page.url()).hostname
  if (checkoutHost !== 'checkout.stripe.com') throw new Error(`Unexpected Stripe checkout host: ${checkoutHost}`)

  const cardMethod = page.locator('input[name="payment-method-accordion-item-title"][value="card"]')
  await cardMethod.waitFor({ state: 'attached', timeout: 30000 })
  await cardMethod.check({ force: true })
  await page.waitForTimeout(500)

  const fillStripeField = async (name, autocomplete, title, value) => {
    const direct = page.locator(`input[name="${name}"], input[autocomplete="${autocomplete}"]`).first()
    if (await direct.count()) {
      await direct.fill(value)
      return
    }

    const frame = page.frameLocator(`iframe[title*="${title}" i]`).locator('input').first()
    await frame.fill(value)
  }

  await fillStripeField('cardNumber', 'cc-number', 'card number', '4242424242424242')
  await fillStripeField('cardExpiry', 'cc-exp', 'expiration', '12/34')
  await fillStripeField('cardCvc', 'cc-csc', 'CVC', '123')
  const nameField = page.locator('input[autocomplete="cc-name"], input[name="billingName"]').first()
  if (await nameField.count()) await nameField.fill(ACCOUNT_NAME)

  const submit = page.getByRole('button', { name: /Start trial|Démarrer la période d'essai|Pay|Payer/ }).last()
  await submit.click()
  await page.waitForURL(url => {
    const parsed = new URL(url.toString())
    return parsed.hostname === new URL(BASE_URL).hostname && /\/welcome|\/email\/verify|\/billing/.test(parsed.pathname)
  }, { timeout: 90000 })
  await page.waitForLoadState('domcontentloaded')

  return { httpStatus: 200, url: page.url() }
}

async function completeOnboarding(page) {
  if (!page.url().includes('/welcome')) {
    await page.goto(`${BASE_URL}/welcome`, { waitUntil: 'networkidle' })
  }

  await page.getByRole('button', { name: /SME \/ Agency|PME \/ Agence/ }).click()
  await page.getByRole('button', { name: /^Next$|^Suivant$/ }).click()
  await page.locator('#legal_name').fill(`${ACCOUNT_NAME} Legal`)
  await page.locator('#address').fill('Rue du Lac 12')
  await page.locator('#city').fill('Lausanne')
  await page.locator('#postal_code').fill('1003')
  await page.locator('#canton').selectOption('VD')
  await page.locator('#vat_number').fill('CHE-123.456.789 MWST')
  await page.getByRole('button', { name: /^Next$|^Suivant$/ }).click()
  await page.locator('fieldset').nth(2).locator('input[type="checkbox"]').check()
  await page.locator('#fiscal_year_name').fill('2026')
  await page.locator('#fiscal_year_start').fill('2026-01-01')
  await page.locator('#fiscal_year_end').fill('2026-12-31')
  await page.getByRole('button', { name: /^Next$|^Suivant$/ }).click()
  await page.locator('fieldset').nth(3).locator('input[type="checkbox"]').check()
  await page.locator('#bank_account_name').fill('Compte principal CHF')
  await page.locator('#bank_name').fill('PostFinance')
  await page.locator('#iban').fill('CH9300762011623852957')
  const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/welcome'), { timeout: 30000 })
  await page.getByRole('button', { name: /Finish setup|Terminer la configuration/ }).click()
  const response = await responsePromise
  await page.waitForLoadState('networkidle')
  const dashboard = await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle' })
  const dashboardText = await page.locator('body').innerText()
  const fiscalYearPage = await page.goto(`${BASE_URL}/accounting/fiscal-years`, { waitUntil: 'networkidle' })
  const fiscalYearText = await page.locator('body').innerText()
  const bankingPage = await page.goto(`${BASE_URL}/banking`, { waitUntil: 'networkidle' })
  const bankingText = await page.locator('body').innerText()

  return {
    httpStatus: response.status(),
    url: page.url(),
    dashboardStatus: dashboard?.status() ?? null,
    fiscalYearStatus: fiscalYearPage?.status() ?? null,
    bankStatus: bankingPage?.status() ?? null,
    company: dashboardText.includes(ACCOUNT_NAME),
    fiscalYear: fiscalYearText.includes('2026'),
    bank: bankingText.includes('Compte principal CHF'),
  }
}

async function exerciseOpeningBalanceContract(page) {
  await page.goto(`${BASE_URL}/accounting/opening-balances`, { waitUntil: 'networkidle' })
  const balanceInputs = page.locator('input[id^="balance_"]')
  const inputCount = await balanceInputs.count()
  if (inputCount === 0) throw new Error('Opening balances page exposed no balance inputs')

  await balanceInputs.first().fill('100.00')
  const form = page.locator('form').first()
  const rejectedResponse = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/accounting/opening-balances'), { timeout: 30000 })
  await form.getByRole('button', { name: /Record opening balances|Enregistrer les soldes/ }).click()
  const rejected = await rejectedResponse
  await page.waitForLoadState('networkidle')
  const rejectionText = await page.locator('body').innerText()
  const rejectedUnbalanced = rejected.status() === 302
    && (rejected.headers()['location'] || '').includes('/accounting/opening-balances')
    && /Opening balances|Soldes d'ouverture|Eröffnungsbilanz|saldi di apertura/i.test(rejectionText)

  await page.locator('#allow_contra').check()
  const acceptedResponse = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/accounting/opening-balances'), { timeout: 30000 })
  await form.getByRole('button', { name: /Record opening balances|Enregistrer les soldes/ }).click()
  const accepted = await acceptedResponse
  await page.waitForLoadState('networkidle')

  return {
    httpStatusRejected: rejected.status(),
    httpStatusAccepted: accepted.status(),
    rejectedUnbalanced,
    acceptedRedirect: accepted.status() === 302
      && (accepted.headers()['location'] || page.url()).includes('/accounting/journal-entries'),
  }
}

async function selectFormOption(page, id, label) {
  const field = page.locator(`#${id}`)
  const tagName = await field.evaluate(element => element.tagName)
  if (tagName === 'SELECT') {
    await field.selectOption({ label })
    return
  }

  await field.click()
  const search = page.locator('[role="listbox"] input').last()
  if (await search.count()) await search.fill(label)
  await page.getByRole('option', { name: label, exact: true }).click()
}

async function submitAndCapturePost(page, button, path) {
  let request
  try {
    ;[request] = await Promise.all([
      page.waitForRequest(candidate => candidate.method() !== 'GET', { timeout: 30000 }),
      button.click(),
    ])
  } catch (error) {
    throw new Error(`Expected POST ${path}: ${error.message}`)
  }
  const requestPath = new URL(request.url()).pathname
  if (request.method() !== 'POST' || requestPath !== path) {
    throw new Error(`Expected POST ${path}, observed ${request.method()} ${requestPath}`)
  }

  const response = await request.response()
  if (!response) throw new Error(`POST ${path} completed without a response`)

  return response
}

function dateAfterDays(days) {
  const date = new Date()
  date.setUTCDate(date.getUTCDate() + days)
  return date.toISOString().slice(0, 10)
}

async function exerciseDailyOperations(page) {
  const customerName = `QA Customer ${RUN_ID}`
  const invoiceDescription = `QA invoice ${RUN_ID}`
  const expenseDescription = `QA expense ${RUN_ID}`

  await page.goto(`${BASE_URL}/contacts/create`, { waitUntil: 'networkidle' })
  await page.locator('#name').fill(customerName)
  await page.locator('#email').fill(`customer-${RUN_ID}@example.test`)
  await page.locator('#address').fill('Rue de la Gare 1')
  await page.locator('#city').fill('Lausanne')
  await page.locator('#postal_code').fill('1003')
    const contactResponse = await submitAndCapturePost(
      page,
      page.locator('form').last().getByRole('button', { name: /Create contact|Créer le contact/i }),
    '/contacts',
  )
  await page.waitForLoadState('networkidle')
  const contactCreated = contactResponse.status() === 302 && (await page.locator('body').innerText()).includes(customerName)

  await page.goto(`${BASE_URL}/invoices/create`, { waitUntil: 'networkidle' })
  await selectFormOption(page, 'customer_id', customerName)
  await page.locator('#issue_date').fill(dateAfterDays(0))
  await page.locator('#due_date').fill(dateAfterDays(30))
  await page.locator('#line-desc-0').fill(invoiceDescription)
  await page.locator('#line-qty-0').fill('1')
  await page.locator('#line-price-0').fill('250.00')
    const invoiceResponse = await submitAndCapturePost(
      page,
      page.getByRole('button', { name: /Create & Finalize|Create and finalize|Créer et finaliser/i }),
    '/invoices',
  )
  await page.waitForLoadState('networkidle')
  const invoiceUrl = page.url()
  const invoiceCreated = invoiceResponse.status() === 302 && invoiceUrl.includes('/invoices/')

  await page.goto(`${BASE_URL}/expenses/create`, { waitUntil: 'networkidle' })
  await page.locator('#amount').fill('125.50')
  await page.locator('#date').fill(dateAfterDays(0))
  await selectFormOption(page, 'payment_method', 'Card')
  const category = page.locator('#category')
  const categoryTag = await category.evaluate(element => element.tagName)
  if (categoryTag === 'SELECT') {
    const categoryOption = await category.locator('option:not([value=""])').first().getAttribute('value')
    await category.selectOption(categoryOption)
  } else {
    await category.click()
    await page.getByRole('option').first().click()
  }
  await page.locator('#description').fill(expenseDescription)
    const expenseResponse = await submitAndCapturePost(
      page,
      page.getByRole('button', { name: /Create expense|Créer la dépense/i }),
    '/expenses',
  )
  await page.waitForLoadState('networkidle')
  const expenseCreated = expenseResponse.status() === 302 && page.url().includes('/expenses/')

  const invoiceList = await page.goto(`${BASE_URL}/invoices`, { waitUntil: 'networkidle' })
  const invoiceListText = await page.locator('body').innerText()
  const expenseList = await page.goto(`${BASE_URL}/expenses`, { waitUntil: 'networkidle' })
  const expenseListText = await page.locator('body').innerText()

  return {
    contact: { httpStatus: contactResponse.status(), created: contactCreated },
    invoice: { httpStatus: invoiceResponse.status(), created: invoiceCreated, listStatus: invoiceList?.status() ?? null, listed: invoiceListText.includes(invoiceDescription) },
    expense: { httpStatus: expenseResponse.status(), created: expenseCreated, listStatus: expenseList?.status() ?? null, listed: expenseListText.includes(expenseDescription) },
  }
}

async function cleanupAccount(email) {
  if (!CLEANUP_ORGANIZATION) return { status: 'skip', reason: 'QA_CLEANUP_ORGANIZATION=0' }
  if (!email || !email.startsWith('gaeld-qa-') || !email.endsWith('@example.test')) {
    throw new Error('Refusing cleanup for an email outside the generated QA namespace')
  }

  const php = `<?php require "current/vendor/autoload.php"; $app=require "current/bootstrap/app.php"; $app->make("Illuminate\\Contracts\\Console\\Kernel")->bootstrap(); $email=${JSON.stringify(email)}; $user=\\App\\Domains\\Users\\Models\\User::where("email",$email)->first(); if(!$user){echo "NO_USER\\n"; exit;} $orgs=$user->organizations()->get(); foreach($orgs as $org){ if(!str_contains($org->name, ${JSON.stringify(RUN_ID)})){fwrite(STDERR,"REFUSING_ORG\\n"); exit(2);} $org->delete(); } $user->delete(); echo "CLEANED\\n";`;
  const encoded = Buffer.from(php).toString('base64')
  const remoteCommand = `cd ~/gaeld_app && printf '%s' '${encoded}' | base64 -d | /usr/bin/php8.4`
  const { stdout } = await execFileAsync('ssh', ['-o', 'BatchMode=yes', '-o', 'ConnectTimeout=20', SSH_TARGET, remoteCommand], { maxBuffer: 1024 * 1024 })
  const output = stdout.trim()

  if (output !== 'CLEANED' && output !== 'NO_USER') {
    throw new Error(`Unexpected cleanup result: ${output}`)
  }

  return { status: output === 'CLEANED' ? 'pass' : 'skip', result: output }
}

async function verifyAccountFromMailpit(page, email) {
  if (!MAILPIT_URL) return { status: 'skip', reason: 'QA_MAILPIT_URL is not configured' }

  const deadline = Date.now() + 30000
  let verificationUrl = null
  while (!verificationUrl && Date.now() < deadline) {
    const messagesResponse = await fetch(`${MAILPIT_URL}/api/v1/messages?limit=100`)
    if (!messagesResponse.ok) throw new Error(`Mailpit returned HTTP ${messagesResponse.status}`)
    const messages = await messagesResponse.json()
    const message = messages.messages?.find(item => item.To?.some(recipient => recipient.Address === email))
    if (message) {
      const detailResponse = await fetch(`${MAILPIT_URL}/api/v1/message/${message.ID}`)
      if (!detailResponse.ok) throw new Error(`Mailpit message returned HTTP ${detailResponse.status}`)
      const detail = await detailResponse.json()
      verificationUrl = detail.Text?.match(new RegExp(`${BASE_URL.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/email/verify/[^\\s<]+`))?.[0] || null
    }
    if (!verificationUrl) await new Promise(resolve => setTimeout(resolve, 1000))
  }
  if (!verificationUrl) return { status: 'fail', reason: 'Verification email was not found in Mailpit' }

  const response = await page.goto(verificationUrl, { waitUntil: 'networkidle' })
  return { status: response?.status() === 302 || response?.status() === 200 ? 'pass' : 'fail', httpStatus: response?.status() ?? null }
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
    accountMode: CREATE_ACCOUNT ? 'ephemeral-signup' : 'existing-account',
    protectedOrganization: '[redacted]',
    results: [],
    consoleErrors: [],
    requestFailures: [],
    cleanup: null,
  }

  if (!RUN_ENABLED) {
    report.results.push(result(0, 'runner configuration', 'skip', { reason: 'Set QA_RUN=1 to execute staging checks' }))
  } else {
    let createdAccount = CREATE_ACCOUNT ? { email: generatedAccountEmail() } : null
    const browser = await chromium.launch({
      headless: process.env.QA_HEADLESS !== '0',
      executablePath: await browserExecutablePath(),
    })
    const { context, page, consoleErrors, requestFailures } = await createContext(browser)
    const screenshotDirectory = `${OUTPUT_DIR}/screenshots-${RUN_ID}`
    await mkdir(screenshotDirectory, { recursive: true })
    try {
      const auth = CREATE_ACCOUNT ? await createAccount(page) : await login(page)
      if (CREATE_ACCOUNT) createdAccount = auth
      const signupSucceeded = auth.httpStatus === 302 || auth.httpStatus === 409
      if (CREATE_ACCOUNT && auth.plan === 'business' && (auth.checkoutUrl || new URL(auth.url).hostname === 'checkout.stripe.com')) {
        const checkout = await completeStripeCheckout(page, auth.checkoutUrl)
        auth.url = checkout.url
        auth.checkoutCompleted = true
        auth.needsVerification = page.url().includes('/email/verify')
      }
      report.results.push(result(0, CREATE_ACCOUNT ? 'ephemeral account signup' : 'authenticated login', signupSucceeded ? 'pass' : 'fail', {
        httpStatus: auth.httpStatus,
        url: auth.url,
        needsVerification: auth.needsVerification || false,
      }))
      if (CREATE_ACCOUNT && auth.needsVerification) {
        const verification = await verifyAccountFromMailpit(page, auth.email)
        report.results.push(result(0, 'email verification', verification.status, verification))
        if (verification.status === 'pass') {
          const relogin = await login(page)
          report.results.push(result(0, 'login after verification', relogin.httpStatus === 302 ? 'pass' : 'fail', relogin))
        }
      }

      if (CREATE_ACCOUNT && page.url().includes('/email/verify')) {
        report.results.push(result(0, 'authenticated account access', 'fail', { error: 'Account remained unverified after Mailpit verification' }))
      }

      if (CREATE_ACCOUNT && !page.url().includes('/email/verify')) {
        try {
          const onboarding = await completeOnboarding(page)
          report.results.push(result(0, 'onboarding wizard', onboarding.company && onboarding.fiscalYear && onboarding.bank ? 'pass' : 'fail', onboarding))
        } catch (error) {
          report.results.push(result(0, 'onboarding wizard', 'fail', { error: error.message }))
        }

        try {
          const openingBalances = await exerciseOpeningBalanceContract(page)
          report.results.push(result(1, 'opening balance hybrid contract', openingBalances.rejectedUnbalanced && openingBalances.acceptedRedirect ? 'pass' : 'fail', openingBalances))
        } catch (error) {
          report.results.push(result(1, 'opening balance hybrid contract', 'fail', { error: error.message }))
        }

        try {
          const operations = await exerciseDailyOperations(page)
          const operationsPassed = operations.contact.created
            && operations.invoice.created
            && operations.invoice.listed
            && operations.expense.created
            && operations.expense.listed
          report.results.push(result(2, 'daily operations UI workflow', operationsPassed ? 'pass' : 'fail', operations))
        } catch (error) {
          report.results.push(result(2, 'daily operations UI workflow', 'fail', { error: error.message }))
        }
      }

      if (!CREATE_ACCOUNT || !page.url().includes('/email/verify')) {
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
      }
    } catch (error) {
      report.results.push(result(0, 'runner execution', 'fail', { error: error.message }))
    } finally {
      report.consoleErrors = consoleErrors
      report.requestFailures = requestFailures
      await context.close()
      await browser.close()
      if (CREATE_ACCOUNT) {
        try {
          report.cleanup = await cleanupAccount(createdAccount?.email)
        } catch (error) {
          report.cleanup = { status: 'fail', error: error.message }
          report.results.push(result(0, 'ephemeral account cleanup', 'fail', report.cleanup))
        }
      }
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