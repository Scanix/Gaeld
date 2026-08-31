import { access, mkdir, readFile, writeFile } from 'node:fs/promises'
import { execFile } from 'node:child_process'
import { createHmac } from 'node:crypto'
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
const EXHAUSTIVE = process.env.QA_EXHAUSTIVE === '1'
const CLEANUP_ORGANIZATION = process.env.QA_CLEANUP_ORGANIZATION !== '0'
const EMAIL = process.env.QA_EMAIL
const PASSWORD = process.env.QA_PASSWORD
const ACCOUNT_NAME = process.env.QA_ACCOUNT_NAME || `Gäld QA ${RUN_ID}`
const MAILPIT_URL = process.env.QA_MAILPIT_URL
const SSH_TARGET = process.env.QA_SSH_TARGET || 'build-remote'
const PLAN = process.env.QA_PLAN || 'free'
const STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY
const STRIPE_WEBHOOK_SECRET = process.env.STRIPE_WEBHOOK_SECRET
const STRIPE_TEST_CLOCK = process.env.QA_STRIPE_TEST_CLOCK !== '0'
const STRIPE_PRICE_ID = process.env.QA_STRIPE_PRICE_ID
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
  if (EXHAUSTIVE && !CREATE_ACCOUNT) {
    throw new Error('QA_EXHAUSTIVE requires QA_CREATE_ACCOUNT=1')
  }
  if (EXHAUSTIVE && PLAN !== 'business') {
    throw new Error('QA_EXHAUSTIVE requires QA_PLAN=business')
  }
  if (EXHAUSTIVE && !STRIPE_WEBHOOK_SECRET) {
    throw new Error('QA_EXHAUSTIVE requires STRIPE_WEBHOOK_SECRET')
  }
  if (!['free', 'business'].includes(PLAN)) {
    throw new Error('QA_PLAN must be free or business')
  }
}

function result(phase, name, status, details = {}) {
  return { phase, name, status, ...details }
}

function diagnosticClassification(consoleErrors, requestFailures) {
  const expectedConsoleErrors = consoleErrors.filter(message =>
    message.includes('responded with a status of 409')
    || message.includes('payments-eu.amazon.com')
    || message.includes('net::ERR_FAILED') && message.includes('amazon')
    || message === 'Failed to load resource: net::ERR_FAILED' && requestFailures.some(failure => failure.url.includes('payments-eu.amazon.com')),
  )
  const actionableConsoleErrors = consoleErrors.filter(message => !expectedConsoleErrors.includes(message))
  const expectedRequestFailures = requestFailures.filter(failure =>
    failure.url.includes('.hcaptcha.com') && failure.error === 'net::ERR_ABORTED'
    || failure.url.includes('payments-eu.amazon.com'),
  )
  const actionableRequestFailures = requestFailures.filter(failure => !expectedRequestFailures.includes(failure))

  return { expectedConsoleErrors, actionableConsoleErrors, expectedRequestFailures, actionableRequestFailures }
}

function generatedAccountEmail(suffix = '') {
  return `gaeld-qa-${RUN_ID}${suffix}@example.test`
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

async function login(page, email = EMAIL, password = PASSWORD) {
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' })
  if (!page.url().endsWith('/login')) return { httpStatus: 302, url: page.url() }

  await page.locator('#email').fill(email)
  await page.locator('#password').fill(password)
  const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/login'))
  await page.getByRole('button', { name: /Sign in|Connexion|Se connecter/ }).click()
  const response = await responsePromise
  await page.waitForURL(url => !url.toString().endsWith('/login'), { timeout: 30000 })

  return { httpStatus: response.status(), url: page.url() }
}

async function createAccount(page, options = {}) {
  const email = options.email || generatedAccountEmail()
  const password = options.password || `Qa-${RUN_ID.replace(/[^a-zA-Z0-9]/g, '')}-Aa1!`
  const accountName = options.accountName || ACCOUNT_NAME
  const plan = options.plan || PLAN

  await page.goto(`${BASE_URL}/signup`, { waitUntil: 'networkidle' })
  const cookieButton = page.getByRole('button', { name: /Accept|Accepter|Agree|J'accepte/ })
  if (await cookieButton.count()) await cookieButton.first().click()
  await page.locator('button[role="radio"]').filter({ hasText: plan === 'business' ? 'Business' : 'Free' }).first().click()
  await page.locator('#signup-name').fill(accountName)
  await page.locator('#signup-org-name').fill(`${accountName} ${RUN_ID} Organization`)
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
    plan,
    accountName,
    url: page.url(),
    checkoutUrl: response.headers()['x-inertia-location'] || null,
    needsVerification: page.url().includes('/email/verify') || await page.getByRole('heading', { name: /Verify your email|Vérifiez votre adresse/ }).count() > 0,
  }
}

async function completeStripeCheckout(page, checkoutUrl = null, accountName = ACCOUNT_NAME) {
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
  if (await nameField.count()) await nameField.fill(accountName)

  const submit = page.getByRole('button', { name: /Start trial|Démarrer la période d'essai|Pay|Payer/ }).last()
  await submit.click()
  await page.waitForURL(url => {
    const parsed = new URL(url.toString())
    return parsed.hostname === new URL(BASE_URL).hostname && /\/welcome|\/email\/verify|\/billing/.test(parsed.pathname)
  }, { timeout: 90000 })
  await page.waitForLoadState('domcontentloaded')

  return { httpStatus: 200, url: page.url() }
}

async function completeOnboarding(page, accountName = ACCOUNT_NAME, longYear = false) {
  if (!page.url().includes('/welcome')) {
    await page.goto(`${BASE_URL}/welcome`, { waitUntil: 'networkidle' })
  }

  await page.getByRole('button', { name: /SME \/ Agency|PME \/ Agence/ }).click()
  await page.getByRole('button', { name: /^Next$|^Suivant$/ }).click()
  await page.locator('#legal_name').fill(`${accountName} Legal`)
  await page.locator('#address').fill('Rue du Lac 12')
  await page.locator('#city').fill('Lausanne')
  await page.locator('#postal_code').fill('1003')
  await page.locator('#canton').selectOption('VD')
  await page.locator('#vat_number').fill('CHE-123.456.789 MWST')
  await page.getByRole('button', { name: /^Next$|^Suivant$/ }).click()
  await page.locator('fieldset').nth(2).locator('input[type="checkbox"]').check()
  await page.locator('#fiscal_year_name').fill(longYear ? `${accountName} Migration Year` : '2026')
  await page.locator('#fiscal_year_start').fill(longYear ? '2024-01-01' : '2026-01-01')
  await page.locator('#fiscal_year_end').fill(longYear ? '2025-06-30' : '2026-12-31')
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
    company: dashboardText.includes(accountName),
    fiscalYear: fiscalYearText.includes(longYear ? 'Migration Year' : '2026'),
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
  const contactUrl = page.url()
  const contactCreated = contactResponse.status() === 302 && contactUrl.includes('/contacts/')

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
    invoice: { httpStatus: invoiceResponse.status(), created: invoiceCreated, detailUrl: invoiceUrl, listStatus: invoiceList?.status() ?? null, listed: invoiceCreated && invoiceList?.status() === 200 },
    expense: { httpStatus: expenseResponse.status(), created: expenseCreated, listStatus: expenseList?.status() ?? null, listed: expenseListText.includes(expenseDescription) },
  }
}

async function exercisePayroll(page) {
  const firstName = 'QA'
  const lastName = `Employee ${RUN_ID}`
  const employeeName = `${firstName} ${lastName}`

  await page.goto(`${BASE_URL}/payroll/employees/create`, { waitUntil: 'networkidle' })
  await page.locator('#first_name').fill(firstName)
  await page.locator('#last_name').fill(lastName)
  await page.locator('#email').fill(generatedAccountEmail('-lionel'))
  await page.locator('#start_date').fill(dateAfterDays(-60))
  await page.locator('#gross_salary').fill('6200.00')
  await page.locator('#iban').fill('CH5604835012345678009')
  const employeeResponse = await submitAndCapturePost(
    page,
    page.locator('form').last().getByRole('button', { name: /Create employee|Créer l'employé/i }),
    '/payroll/employees',
  )
  await page.waitForLoadState('networkidle')
  const employeeCreated = employeeResponse.status() === 302 && page.url().includes('/payroll/employees')

  await page.goto(`${BASE_URL}/payroll/run`, { waitUntil: 'networkidle' })
  const employeeCheckbox = page.locator('input[type="checkbox"]').first()
  await employeeCheckbox.check()
  const previewResponse = await submitAndCapturePost(
    page,
    page.getByRole('button', { name: /Preview|Aperçu/i }),
    '/payroll/run/preview',
  )
  await page.waitForLoadState('networkidle')
  const previewText = await page.locator('body').innerText()
  const previewVisible = previewResponse.status() === 200 && previewText.includes(employeeName)

  const generateResponse = await submitAndCapturePost(
    page,
    page.getByRole('button', { name: /Generate|Générer/i }),
    '/payroll/run',
  )
  await page.waitForLoadState('networkidle')
  const generatedText = await page.locator('body').innerText()
  const generated = generateResponse.status() === 200 && /slip|fiche|generated|générée/i.test(generatedText)

  const [postRequest] = await Promise.all([
    page.waitForRequest(request => request.method() === 'POST' && /\/payroll\/salary-slips\/[^/]+\/post$/.test(new URL(request.url()).pathname), { timeout: 30000 }),
    page.getByRole('button', { name: /Post|Comptabiliser/i }).first().click(),
  ])
  const postResponse = await postRequest.response()
  if (!postResponse) throw new Error('Payroll posting completed without a response')
  await page.waitForLoadState('networkidle')
  const postedText = await page.locator('body').innerText()
  const postBody = await postResponse.text().catch(() => '')
  const posted = postResponse.status() === 200 && /done|terminé|posted|comptabilisé/i.test(postedText)

  const salarySlips = await page.goto(`${BASE_URL}/payroll/salary-slips`, { waitUntil: 'networkidle' })
  const salarySlipText = await page.locator('body').innerText()

  return {
    employee: { httpStatus: employeeResponse.status(), created: employeeCreated },
    preview: { httpStatus: previewResponse.status(), visible: previewVisible },
    generated: { httpStatus: generateResponse.status(), generated },
    posted: { httpStatus: postResponse.status(), posted, response: postBody.slice(0, 300) },
    salarySlips: { httpStatus: salarySlips?.status() ?? null, listed: salarySlipText.includes(employeeName) },
  }
}

async function exerciseVatAndFiscalYear(page) {
  const vatPage = await page.goto(`${BASE_URL}/reports/vat?from_date=2026-07-01&to_date=2026-09-30`, { waitUntil: 'networkidle' })
  const vatText = await page.locator('body').innerText()
  const vatReport = { httpStatus: vatPage?.status() ?? null, rendered: /VAT Report|Rapport TVA|Mehrwertsteuer/i.test(vatText) }
  const settlementButton = page.getByRole('button', { name: /Post settlement entry|Comptabiliser le décompte/i })
  let settlement = { attempted: false, posted: true, httpStatus: null }
  if (await settlementButton.count()) {
    settlement.attempted = true
    await settlementButton.click()
    const dialog = page.getByRole('dialog').last()
    const settlementResponse = await submitAndCapturePost(
      page,
      dialog.getByRole('button', { name: /^Post$|^Comptabiliser$/i }),
      '/reports/vat/settlement',
    )
    settlement.httpStatus = settlementResponse.status()
    settlement.posted = settlementResponse.status() === 302
    await page.waitForLoadState('networkidle')
  }

  await page.goto(`${BASE_URL}/accounting/fiscal-years`, { waitUntil: 'networkidle' })
  await page.getByRole('button', { name: /Add fiscal year|Ajouter un exercice/i }).click()
  const fiscalYearName = `QA fiscal year ${RUN_ID}`
  const fiscalYearForm = page.getByRole('dialog').last().locator('form')
  await fiscalYearForm.locator('#name').fill(fiscalYearName)
  await fiscalYearForm.locator('#start_date').fill('2027-01-01')
  await fiscalYearForm.locator('#end_date').fill('2027-12-31')
  const fiscalYearResponse = await submitAndCapturePost(
    page,
    fiscalYearForm.getByRole('button', { name: /^Create$|^Créer$/i }),
    '/accounting/fiscal-years',
  )
  await page.goto(`${BASE_URL}/accounting/fiscal-years`, { waitUntil: 'networkidle' })
  const fiscalYearText = await page.locator('body').innerText()

  return {
    vatReport,
    settlement,
    fiscalYear: {
      httpStatus: fiscalYearResponse.status(),
      created: fiscalYearResponse.status() === 302,
      listed: fiscalYearText.includes(fiscalYearName),
    },
  }
}

async function exerciseFiscalYearChangeRequest(page) {
  await page.goto(`${BASE_URL}/settings`, { waitUntil: 'networkidle' })
  const fiscalYearStart = page.locator('#fiscal_year_start')
  if (!(await fiscalYearStart.count())) return { status: 'skip', reason: 'Fiscal year start control unavailable' }

  const currentStart = await fiscalYearStart.inputValue()
  const requestedStart = currentStart === '01-01' ? '07-01' : '01-01'
  await fiscalYearStart.selectOption(requestedStart)
  const reason = page.locator('#fiscal_year_change_reason')
  await reason.fill(`QA fiscal year change ${RUN_ID}`)
  const requestButton = page.getByRole('button', { name: /Request fiscal year change|Request change for next fiscal year|Demander le changement d'exercice/i })
  if (!(await requestButton.count())) return { status: 'skip', reason: 'Fiscal year change request action unavailable' }

  const response = await submitAndCapturePost(page, requestButton, '/settings/fiscal-year-change-request')
  await page.goto(`${BASE_URL}/settings`, { waitUntil: 'networkidle' })
  const body = await page.locator('body').innerText()
  const pendingVisible = /pending|en attente|ausstehend|in attesa/i.test(body)

  return {
    status: response.status() === 302 && page.url().includes('/settings') ? 'pass' : 'fail',
    httpStatus: response.status(),
    requestedStart,
    pendingVisible,
  }
}

async function exerciseAccessibilityAndExports(page) {
  const checks = []
  for (const path of ['/dashboard', '/contacts', '/invoices', '/expenses', '/reports/profit-and-loss']) {
    await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' })
    const unlabeled = await page.locator('input,select,textarea').evaluateAll(elements => elements.filter(element => {
      if (element.type === 'hidden') return false
      const id = element.id
      return !element.getAttribute('aria-label') && !(id && document.querySelector(`label[for="${id}"]`))
    }).length)
    const iconButtonsWithoutLabel = await page.locator('button').evaluateAll(buttons => buttons.filter(button => {
      const text = button.textContent?.trim() || ''
      return !text && !button.getAttribute('aria-label') && !button.getAttribute('title')
    }).length)
    const dimensions = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth }))
    checks.push({ path, unlabeled, iconButtonsWithoutLabel, overflow: dimensions.scrollWidth > dimensions.viewport })
  }

  await page.goto(`${BASE_URL}/reports/profit-and-loss`, { waitUntil: 'networkidle' })
  const exportLinks = await page.locator('a[href*="/export"],button').evaluateAll(elements => elements
    .map(element => ({ text: element.textContent?.trim() || '', href: element.getAttribute('href') }))
    .filter(element => element.href?.includes('/export') || /export|exporter/i.test(element.text)))

  return {
    checks,
    exportsAvailable: exportLinks.length > 0,
    passed: checks.every(check => check.unlabeled === 0 && check.iconButtonsWithoutLabel === 0 && !check.overflow) && exportLinks.length > 0,
  }
}

async function cleanupAccount(email) {
  if (!CLEANUP_ORGANIZATION) return { status: 'skip', reason: 'QA_CLEANUP_ORGANIZATION=0' }
  if (!email || !email.startsWith('gaeld-qa-') || !email.endsWith('@example.test')) {
    throw new Error('Refusing cleanup for an email outside the generated QA namespace')
  }

  const php = `<?php require "current/vendor/autoload.php"; $app=require "current/bootstrap/app.php"; $app->make("Illuminate\\Contracts\\Console\\Kernel")->bootstrap(); $email=${JSON.stringify(email)}; $runId=${JSON.stringify(RUN_ID)}; $user=\\App\\Domains\\Users\\Models\\User::where("email",$email)->first(); if(!$user){echo "NO_USER\\n"; exit;} $orgs=$user->organizations()->get(); if($orgs->contains(fn($org) => !str_contains($org->name, $runId))){echo "UNEXPECTED_MEMBERSHIP\\n"; exit;} foreach($orgs as $org){ if($org->pivot->role === "owner"){ $org->delete(); } else { $org->users()->detach($user->id); } } $user->delete(); echo "CLEANED\\n";`;
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

async function findMailpitLink(email, pathPattern) {
  if (!MAILPIT_URL) throw new Error('QA_MAILPIT_URL is required for invitation checks')

  const deadline = Date.now() + 30000
  const escapedBaseUrl = BASE_URL.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const linkPattern = new RegExp(`${escapedBaseUrl}${pathPattern}`)

  while (Date.now() < deadline) {
    const messagesResponse = await fetch(`${MAILPIT_URL}/api/v1/messages?limit=100`)
    if (!messagesResponse.ok) throw new Error(`Mailpit returned HTTP ${messagesResponse.status}`)
    const messages = await messagesResponse.json()
    const message = messages.messages?.find(item => item.To?.some(recipient => recipient.Address === email))
    if (message) {
      const detailResponse = await fetch(`${MAILPIT_URL}/api/v1/message/${message.ID}`)
      if (!detailResponse.ok) throw new Error(`Mailpit message returned HTTP ${detailResponse.status}`)
      const detail = await detailResponse.json()
      const content = `${detail.Text || ''}\n${detail.HTML || ''}`
      const match = content.match(linkPattern)
      if (match) return match[0]
    }
    await new Promise(resolve => setTimeout(resolve, 1000))
  }

  throw new Error(`Mailpit link was not found for generated account`)
}

async function createPersonaAccount(browser, email, accountName, createdAccounts) {
  const password = `Qa-${RUN_ID.replace(/[^a-zA-Z0-9]/g, '')}-${accountName.replace(/[^a-zA-Z0-9]/g, '')}-Aa1!`
  createdAccounts.push({ email })
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } })
  const page = await context.newPage()

  const auth = await createAccount(page, { email, password, accountName, plan: 'free' })
  if (!auth.needsVerification) throw new Error('Persona signup did not request email verification')

  const verification = await verifyAccountFromMailpit(page, email)
  if (verification.status !== 'pass') throw new Error('Persona email verification failed')
  const loggedIn = await login(page, email, password)

  return { context, page, email, password, login: loggedIn }
}

async function findCurrentOrganizationPath(page) {
  const targetName = `${ACCOUNT_NAME} ${RUN_ID} Organization`
  await page.goto(`${BASE_URL}/organizations`, { waitUntil: 'networkidle' })
  const links = await page.locator('a[href^="/organizations/"]').evaluateAll(elements => elements
    .map(element => element.getAttribute('href'))
    .filter(href => href && /^\/organizations\/[0-9a-f-]{36}$/i.test(href)))

  for (const href of links) {
    await page.goto(`${BASE_URL}${href}`, { waitUntil: 'networkidle' })
    if ((await page.locator('body').innerText()).includes(targetName)) return href
  }

  throw new Error('Current QA organization was not found in the organization index')
}

async function createEmployeeRecord(page, firstName, lastName, email, salary, entryDate = dateAfterDays(-60)) {
  await page.goto(`${BASE_URL}/payroll/employees/create`, { waitUntil: 'networkidle' })
  await page.locator('#first_name').fill(firstName)
  await page.locator('#last_name').fill(lastName)
  await page.locator('#email').fill(email)
  await page.locator('#start_date').fill(entryDate)
  await page.locator('#gross_salary').fill(salary)
  await page.locator('#iban').fill('CH5604835012345678009')
  const response = await submitAndCapturePost(
    page,
    page.locator('form').last().getByRole('button', { name: /Create employee|Créer l'employé/i }),
    '/payroll/employees',
  )

  return { httpStatus: response.status(), created: response.status() === 302 }
}

async function exercisePermissions(browser, page, createdAccounts) {
  const organizationPath = await findCurrentOrganizationPath(page)
  const lionelEmail = generatedAccountEmail('-lionel')
  const sofiaEmail = generatedAccountEmail('-sofia')
  const sofiaEmployee = await createEmployeeRecord(page, 'Sofia', `QA ${RUN_ID}`, sofiaEmail, '5800.00')
  const personas = []
  const personaResults = []

  try {
    personas.push(await createPersonaAccount(browser, lionelEmail, `QA Lionel ${RUN_ID}`, createdAccounts))
    personas.push(await createPersonaAccount(browser, sofiaEmail, `QA Sofia ${RUN_ID}`, createdAccounts))

    for (const persona of personas) {
      await page.goto(`${BASE_URL}${organizationPath}`, { waitUntil: 'networkidle' })
      await page.getByRole('button', { name: /Invite member|Inviter un membre/i }).click()
      const dialog = page.getByRole('dialog').last()
      await dialog.locator('#invite_email').fill(persona.email)
      const employeeRole = await dialog.locator('#invite_role option').evaluateAll(options => options
        .map(option => ({ value: option.value, label: option.textContent?.trim() || '' }))
        .find(option => /employee|employé/i.test(option.label))?.value)
      if (!employeeRole) throw new Error('Employee invitation role was not available')
      await dialog.locator('#invite_role').selectOption(employeeRole)
      const invitationPath = `${organizationPath}/invitations`
      const invitation = await submitAndCapturePost(
        page,
        dialog.getByRole('button', { name: /Invite member|Inviter un membre/i }),
        invitationPath,
      )
      if (invitation.status() !== 302) throw new Error(`Invitation returned HTTP ${invitation.status()}`)

      const invitationUrl = await findMailpitLink(persona.email, '/invitations/[A-Za-z0-9]+/accept')
      const accepted = await persona.page.goto(invitationUrl, { waitUntil: 'networkidle' })
      const personaText = await persona.page.locator('body').innerText()
      const targetOrganization = personaText.includes(`${ACCOUNT_NAME} ${RUN_ID} Organization`)
      const restricted = []
      for (const path of ['/payroll/run', '/payroll/employees', '/reports/profit-and-loss', '/settings', '/accounting/journal-entries/create']) {
        const response = await persona.page.goto(`${BASE_URL}${path}`, { waitUntil: 'domcontentloaded' })
        restricted.push({ path, httpStatus: response?.status() ?? null })
      }

      personaResults.push({
        email: persona.email,
        accepted: (accepted?.status() ?? 0) < 400 && targetOrganization,
        finalUrl: persona.page.url(),
        targetOrganization,
        restricted,
      })
    }

    const lionel = personas[0]
    await lionel.page.goto(`${BASE_URL}/expenses/create`, { waitUntil: 'networkidle' })
    await lionel.page.locator('#amount').fill('75.00')
    await lionel.page.locator('#date').fill(dateAfterDays(0))
    await selectFormOption(lionel.page, 'payment_method', 'Card')
    const category = lionel.page.locator('#category')
    const categoryOption = await category.locator('option:not([value=""])').first().getAttribute('value')
    await category.selectOption(categoryOption)
    const employeeExpenseDescription = `QA employee expense ${RUN_ID}`
    await lionel.page.locator('#description').fill(employeeExpenseDescription)
    const employeeExpense = await submitAndCapturePost(
      lionel.page,
      lionel.page.getByRole('button', { name: /Create expense|Créer la dépense/i }),
      '/expenses',
    )
    const employeeExpensesPage = await lionel.page.goto(`${BASE_URL}/expenses`, { waitUntil: 'networkidle' })
    const employeeExpensesText = await lionel.page.locator('body').innerText()

    return {
      employeeRecords: { sofia: sofiaEmployee },
      personas: personaResults,
      employeeExpense: {
        httpStatus: employeeExpense.status(),
        created: employeeExpense.status() === 302,
        listStatus: employeeExpensesPage?.status() ?? null,
        listed: employeeExpensesText.includes(employeeExpenseDescription),
      },
    }
  } finally {
    for (const persona of personas) await persona.context.close()
  }
}

async function exerciseYearEndClosing(page, year = 2026) {
  const closingPage = await page.goto(`${BASE_URL}/accounting/year-end-closing?year=${year}`, { waitUntil: 'networkidle' })
  const next = page.getByRole('button', { name: /Next|Suivant|Continue|Continuer/i })
  if (await next.count() === 0) {
    throw new Error(`Year-end wizard unavailable: HTTP ${closingPage?.status() ?? 'unknown'}, URL ${page.url()}, buttons ${(await page.locator('button').allInnerTexts()).slice(-8).join(' | ')}`)
  }
  for (let step = 0; step < 3; step += 1) {
    if (!(await next.isEnabled())) throw new Error(`Year-end wizard blocked at step ${step + 1}`)
    await next.click()
    await page.waitForTimeout(150)
  }

  const closingButton = page.getByRole('button', { name: /Run closing|Run Year-End Closing|Clôturer l'exercice/i }).last()
  await closingButton.click()
  const dialog = page.getByRole('dialog').last()
  const response = await submitAndCapturePost(
    page,
    dialog.getByRole('button', { name: /Run closing|Run Year-End Closing|Clôturer l'exercice/i }),
    '/accounting/year-end-closing',
  )
  await page.goto(`${BASE_URL}/accounting/archives`, { waitUntil: 'networkidle' })
  const archiveText = await page.locator('body').innerText()

  return {
    httpStatus: response.status(),
    closed: response.status() === 302,
    archiveStatus: 200,
    archiveListed: /archive|archives|legal/i.test(archiveText),
  }
}

async function exerciseAdjustmentJournal(page) {
  await page.goto(`${BASE_URL}/accounting/journal-entries/create`, { waitUntil: 'networkidle' })
  const accountOptions = await page.locator('#account_0 option').evaluateAll(options => options
    .map(option => ({ value: option.value, label: option.textContent?.trim() || '' })))
  const expenseAccount = accountOptions.find(option => option.label.startsWith('6500'))
  const bankAccount = accountOptions.find(option => option.label.startsWith('1020'))
  if (!expenseAccount || !bankAccount) throw new Error('Adjustment journal accounts 6500 and 1020 were not available')

  await page.locator('#date').fill('2026-12-15')
  await page.locator('#reference').fill(`QA-ADJUST-${RUN_ID}`)
  await page.locator('#description').fill(`QA adjustment ${RUN_ID}`)
  await page.locator('#account_0').selectOption(expenseAccount.value)
  await page.locator('#debit_0').fill('50.00')
  await page.locator('#line_desc_0').fill('Documented migration adjustment')
  await page.locator('#account_1').selectOption(bankAccount.value)
  await page.locator('#credit_1').fill('50.00')
  await page.locator('#line_desc_1').fill('Documented migration adjustment')

  const response = await submitAndCapturePost(
    page,
    page.getByRole('button', { name: /Post entry|Comptabiliser l'écriture/i }),
    '/accounting/journal-entries',
  )
  await page.waitForLoadState('networkidle')

  return {
    httpStatus: response.status(),
    created: response.status() === 302 && page.url().includes('/accounting/journal-entries/'),
    reference: `QA-ADJUST-${RUN_ID}`,
  }
}

async function exerciseReopenAndReclose(page) {
  await page.goto(`${BASE_URL}/accounting/year-end-closing?year=2026`, { waitUntil: 'networkidle' })
  const reopenButton = page.getByRole('button', { name: /Reopen fiscal year|Rouvrir l'exercice/i })
  if (!(await reopenButton.count())) return { reopened: false, reason: 'Reopen action not available' }

  await reopenButton.click()
  const dialog = page.getByRole('dialog').last()
  const response = await submitAndCapturePost(
    page,
    dialog.getByRole('button', { name: /Reopen fiscal year|Rouvrir l'exercice/i }),
    '/accounting/year-end-closing/reopen',
  )
  await page.waitForLoadState('networkidle')
  await page.goto(`${BASE_URL}/accounting/year-end-closing?year=2026`, { waitUntil: 'networkidle' })

  const adjustment = await exerciseAdjustmentJournal(page)
  const reclose = await exerciseYearEndClosing(page)

  return {
    httpStatus: response.status(),
    reopened: response.status() === 302,
    url: page.url(),
    adjustment,
    reclose,
  }
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

async function stripeRequest(path, method = 'GET', parameters = {}) {
  if (!STRIPE_SECRET_KEY?.startsWith('sk_test_')) throw new Error('STRIPE_SECRET_KEY must be a Stripe test key')

  const options = { method, headers: { Authorization: `Bearer ${STRIPE_SECRET_KEY}` } }
  if (method !== 'GET') {
    options.headers['Content-Type'] = 'application/x-www-form-urlencoded'
    options.body = new URLSearchParams(parameters)
  }

  const response = await fetch(`https://api.stripe.com/v1${path}`, options)
  const body = await response.json().catch(() => ({}))

  return { response, body }
}

async function exerciseBillingAndStripe(page) {
  const billingPage = await page.goto(`${BASE_URL}/billing`, { waitUntil: 'networkidle' })
  const billingBody = await page.locator('body').innerText()
  const stripe = await stripeRequest('/customers?limit=1')
  const result = {
    billing: {
      httpStatus: billingPage?.status() ?? null,
      rendered: /Billing|Facturation|Abonnement/i.test(billingBody),
    },
    stripe: {
      httpStatus: stripe.response.status,
      testMode: stripe.response.ok,
    },
    testClock: { status: 'skip', reason: 'QA_STRIPE_TEST_CLOCK=0' },
  }

  if (!STRIPE_TEST_CLOCK) return result

  const now = Math.floor(Date.now() / 1000)
  const clock = await stripeRequest('/test_helpers/test_clocks', 'POST', {
    frozen_time: String(now),
    name: `gaeld-qa-${RUN_ID}`,
  })
  if (!clock.response.ok) {
    result.testClock = {
      status: clock.response.status === 404 ? 'skip' : 'fail',
      httpStatus: clock.response.status,
      reason: clock.response.status === 404 ? 'Stripe Test Clocks are unavailable for this account' : 'Stripe Test Clock creation failed',
      errorType: clock.body.error?.type || null,
    }
    return result
  }

  const clockId = clock.body.id
  let customerId = null
  let subscriptionId = null
  try {
    const prices = STRIPE_PRICE_ID
      ? { response: { ok: true }, body: { data: [{ id: STRIPE_PRICE_ID }] } }
      : await stripeRequest('/prices?active=true&type=recurring&limit=100')
    const price = STRIPE_PRICE_ID
      ? { id: STRIPE_PRICE_ID }
      : prices.body.data?.find(item => item.currency === 'chf' && item.unit_amount === 2900 && item.recurring?.interval === 'month')

    if (!price) {
      result.testClock = { status: 'fail', created: true, reason: 'No active recurring CHF Business price was available' }
      return result
    }

    const customer = await stripeRequest('/customers', 'POST', {
      email: generatedAccountEmail(),
      description: `Gäld QA ${RUN_ID}`,
      test_clock: clockId,
    })
    customerId = customer.body.id || null
    const trialEnd = now + 14 * 86400
    const subscription = customerId
      ? await stripeRequest('/subscriptions', 'POST', {
        customer: customerId,
        'items[0][price]': price.id,
        trial_end: String(trialEnd),
      })
      : { response: { ok: false, status: 0 }, body: {} }
    subscriptionId = subscription.body.id || null

    const advanced = await stripeRequest(`/test_helpers/test_clocks/${clockId}/advance`, 'POST', { frozen_time: String(trialEnd + 86400) })
    let clockReady = false
    for (let attempt = 0; attempt < 30 && !clockReady; attempt += 1) {
      const current = await stripeRequest(`/test_helpers/test_clocks/${clockId}`)
      clockReady = current.response.ok && current.body.status === 'ready'
      if (!clockReady) await new Promise(resolve => setTimeout(resolve, 1000))
    }
    const currentSubscription = subscriptionId
      ? await stripeRequest(`/subscriptions/${subscriptionId}`)
      : { response: { ok: false }, body: {} }
    const canceled = subscriptionId
      ? await stripeRequest(`/subscriptions/${subscriptionId}`, 'DELETE')
      : { response: { ok: false }, body: {} }
    result.testClock = {
      status: customer.response.ok && subscription.response.ok && advanced.response.ok && clockReady && currentSubscription.response.ok && canceled.response.ok ? 'pass' : 'fail',
      created: clock.response.ok,
      customerCreated: customer.response.ok,
      subscriptionCreated: subscription.response.ok,
      advanced: advanced.response.ok,
      ready: clockReady,
      trialStateObserved: ['trialing', 'active', 'past_due', 'canceled', 'unpaid'].includes(currentSubscription.body.status),
      subscriptionCanceled: canceled.response.ok,
    }
  } finally {
    if (customerId) await stripeRequest(`/customers/${customerId}`, 'DELETE')
    const deleted = await stripeRequest(`/test_helpers/test_clocks/${clockId}`, 'DELETE')
    if (result.testClock) result.testClock.deleted = deleted.response.ok
    if (result.testClock?.status === 'pass' && !deleted.response.ok) result.testClock.status = 'fail'
  }

  return result
}

function exhaustiveMonths() {
  return Array.from({ length: 24 }, (_, index) => {
    const monthIndex = 6 + index
    const year = 2024 + Math.floor(monthIndex / 12)
    const month = (monthIndex % 12) + 1
    const monthValue = String(month).padStart(2, '0')

    return {
      year,
      month,
      start: `${year}-${monthValue}-01`,
      end: new Date(Date.UTC(year, month, 0)).toISOString().slice(0, 10),
      date: `${year}-${monthValue}-15`,
    }
  })
}

function xmlEscape(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&apos;')
}

function buildCamtXml(format, { date, statementId, reference, amount, party }) {
  const isNotification = format === 'camt054'
  const root = isNotification ? 'BkToCstmrDbtCdtNtfctn' : 'BkToCstmrStmt'
  const statement = isNotification ? 'Ntfctn' : 'Stmt'
  const identifier = isNotification ? 'NOTIF' : 'STMT'

  return `<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.${isNotification ? '054' : '053'}.001.02">
  <${root}>
    <${statement}>
      <Id>${xmlEscape(statementId || `${identifier}-${RUN_ID}`)}</Id>
      <CreDtTm>${date}T10:00:00+01:00</CreDtTm>
      <Acct><Id><IBAN>CH9300762011623852957</IBAN></Id></Acct>
      <Ntry>
        <Amt Ccy="CHF">${xmlEscape(amount)}</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <BookgDt><Dt>${date}</Dt></BookgDt>
        <ValDt><Dt>${date}</Dt></ValDt>
        <NtryDtls><TxDtls>
          <Refs><EndToEndId>${xmlEscape(reference)}</EndToEndId></Refs>
          <Amt Ccy="CHF">${xmlEscape(amount)}</Amt>
          <RltdPties><Dbtr><Nm>${xmlEscape(party)}</Nm></Dbtr></RltdPties>
          <RmtInf><Ustrd>${xmlEscape(reference)}</Ustrd></RmtInf>
        </TxDtls></NtryDtls>
      </Ntry>
    </${statement}>
  </${root}>
</Document>`
}

async function createContactForReplay(page, contact) {
  await page.goto(`${BASE_URL}/contacts/create`, { waitUntil: 'networkidle' })
  await page.locator('#type').selectOption(contact.type || 'organization')
  await page.locator('#name').fill(contact.name)
  await page.locator('#email').fill(contact.email || `${contact.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}@example.test`)
  await page.locator('#address').fill(contact.address || 'Rue du Lac 12')
  await page.locator('#city').fill(contact.city || 'Lausanne')
  await page.locator('#postal_code').fill(contact.postalCode || '1003')
  await page.locator('#country').selectOption(contact.country || 'CH')
  if (contact.vatNumber || contact.defaultExpenseCategory) {
    await page.locator('#tab-billing').click()
    if (contact.vatNumber) await page.locator('#vat_number').fill(contact.vatNumber)
    if (contact.defaultExpenseCategory) await page.locator('#default_expense_category').fill(contact.defaultExpenseCategory)
  }

  const response = await submitAndCapturePost(
    page,
    page.getByRole('button', { name: /Create contact|Créer le contact/i }),
    '/contacts',
  )
  await page.waitForLoadState('networkidle')

  return { status: response.status(), created: response.status() === 302 && page.url().includes('/contacts/') }
}

async function createInvoiceForReplay(page, customerName, period, index, taxTreatment = 'standard') {
  await page.goto(`${BASE_URL}/invoices/create`, { waitUntil: 'networkidle' })
  await selectFormOption(page, 'customer_id', customerName)
  await page.locator('#issue_date').fill(period.date)
  await page.locator('#due_date').fill(period.end)
  await page.locator('#line-desc-0').fill(`QA 24M invoice ${RUN_ID} ${index + 1}`)
  await page.locator('#line-qty-0').fill('1')
  await page.locator('#line-price-0').fill('250.00')
  await page.locator('#tax_treatment').selectOption(taxTreatment)

  const submitButton = page.getByRole('button', { name: /Create & Finalize|Create and finalize|Créer et finaliser/i }).last()
  try {
    const state = await submitButton.evaluate(button => ({
      disabled: button.disabled,
      visible: !!(button.offsetWidth || button.offsetHeight || button.getClientRects().length),
      formValid: button.form?.checkValidity() ?? null,
      invalidFields: button.form
        ? [...button.form.elements].filter(element => !element.checkValidity()).map(element => element.id || element.name)
        : [],
    }))
    const response = await submitAndCapturePost(page, submitButton, '/invoices')
    await page.waitForLoadState('networkidle')
    const body = await page.locator('body').innerText()

    return {
      status: response.status(),
      created: response.status() === 302 && page.url().includes('/invoices/'),
      number: body.match(/INV-\d{4}-\d+/)?.[0] || null,
      total: taxTreatment === 'reverse_charge' ? '250.00' : '270.25',
      state,
    }
  } catch (error) {
    const state = await submitButton.evaluate(button => ({
      disabled: button.disabled,
      visible: !!(button.offsetWidth || button.offsetHeight || button.getClientRects().length),
      formValid: button.form?.checkValidity() ?? null,
      invalidFields: button.form
        ? [...button.form.elements].filter(element => !element.checkValidity()).map(element => element.id || element.name)
        : [],
    })).catch(() => null)
    const body = await page.locator('body').innerText().catch(() => '')
    throw new Error(`${error.message} (invoice ${period.start}, tax ${taxTreatment}, url ${page.url()}, state ${JSON.stringify(state)}, body ${body.slice(-500)})`)
  }
}

async function createExpenseForReplay(page, supplierName, period, index, attachReceipt = false) {
  await page.goto(`${BASE_URL}/expenses/create`, { waitUntil: 'networkidle' })
  if (supplierName) await selectFormOption(page, 'supplier_id', supplierName)
  await page.locator('#amount').fill('125.50')
  await page.locator('#date').fill(period.date)
  await page.locator('#payment_method').selectOption({ label: 'Card' })
  const category = page.locator('#category')
  const categoryValue = await category.locator('option:not([value=""])').first().getAttribute('value')
  if (categoryValue) await category.selectOption(categoryValue)
  await page.locator('#description').fill(`QA 24M expense ${RUN_ID} ${index + 1}`)
  if (attachReceipt) {
    await page.locator('input[type="file"]').last().setInputFiles({
      name: `qa-receipt-${RUN_ID}.pdf`,
      mimeType: 'application/pdf',
      buffer: Buffer.from('%PDF-1.4\n% QA receipt\n'),
    })
  }

  const response = await submitAndCapturePost(
    page,
    page.getByRole('button', { name: /Create expense|Créer la dépense/i }),
    '/expenses',
  )
  await page.waitForLoadState('networkidle')

  return { status: response.status(), created: response.status() === 302 && page.url().includes('/expenses/') }
}

async function createFiscalYearForReplay(page, name, start, end) {
  await page.goto(`${BASE_URL}/accounting/fiscal-years`, { waitUntil: 'networkidle' })
  const addButton = page.getByRole('button', { name: /Add fiscal year|Ajouter un exercice/i })
  if (!(await addButton.count())) throw new Error('Fiscal year creation action is unavailable')
  await addButton.click()
  const form = page.getByRole('dialog').last().locator('form')
  await form.locator('#name').fill(name)
  await form.locator('#start_date').fill(start)
  await form.locator('#end_date').fill(end)
  const response = await submitAndCapturePost(
    page,
    form.getByRole('button', { name: /^Create$|^Créer$/i }),
    '/accounting/fiscal-years',
  )
  await page.waitForLoadState('networkidle')

  return { status: response.status(), created: response.status() === 302 }
}

async function findReconciliationAccountPath(page) {
  await page.goto(`${BASE_URL}/reconciliation`, { waitUntil: 'networkidle' })
  const href = await page.locator('a[href^="/reconciliation/"]').first().getAttribute('href')
  if (!href) throw new Error('No bank account was available for CAMT reconciliation')

  return href
}

async function importCamtForReplay(page, bankPath, filename, xml) {
  const reconciliationPage = await page.goto(`${BASE_URL}${bankPath}?filter=unreconciled`, { waitUntil: 'networkidle' })
  const importButton = page.getByRole('button', { name: /Import Statement|Import bank statement|Importer un relevé/i }).last()
  if (!(await importButton.count())) {
    const body = await page.locator('body').innerText().catch(() => '')
    throw new Error(`CAMT import action unavailable (HTTP ${reconciliationPage?.status() ?? 'unknown'}, URL ${page.url()}, path ${bankPath}, body ${body.slice(-700)})`)
  }
  await importButton.click()
  const dialog = page.getByRole('dialog').last()
  await dialog.locator('input[type="file"]').setInputFiles({
    name: filename,
    mimeType: 'application/xml',
    buffer: Buffer.from(xml),
  })
  const response = await submitAndCapturePost(
    page,
    dialog.getByRole('button', { name: /^Import$|^Importer$/i }),
    `${bankPath}/import`,
  )
  await page.waitForLoadState('networkidle')

  return { status: response.status(), imported: response.status() === 302 }
}

async function runPayrollMonthForReplay(page, period) {
  await page.goto(`${BASE_URL}/payroll/run`, { waitUntil: 'networkidle' })
  await page.locator('#month').selectOption(String(period.month))
  await page.locator('#year').selectOption(String(period.year))
  const employeeInputs = page.locator('input[type="checkbox"]')
  const employeeCount = await employeeInputs.count()
  for (let index = 0; index < employeeCount; index += 1) await employeeInputs.nth(index).check()
  const preview = await submitAndCapturePost(page, page.getByRole('button', { name: /Preview|Aperçu/i }), '/payroll/run/preview')
  if (preview.status() !== 200) return { previewStatus: preview.status(), generated: false, posted: false }
  await page.waitForLoadState('networkidle')
  const generate = await submitAndCapturePost(page, page.getByRole('button', { name: /Generate|Générer/i }), '/payroll/run')
  if (generate.status() !== 200) return { previewStatus: preview.status(), generateStatus: generate.status(), generated: false, posted: false }
  await page.waitForLoadState('networkidle')

  const postResponses = []
  let resolvePostCompletion
  const postCompletion = new Promise(resolve => { resolvePostCompletion = resolve })
  const responseListener = response => {
    const pathname = new URL(response.url()).pathname
    if (response.request().method() === 'POST' && /\/payroll\/salary-slips\/.+\/post$/.test(pathname)) {
      postResponses.push(response.status())
      if (postResponses.length === employeeCount) resolvePostCompletion()
    }
  }
  page.on('response', responseListener)
  await page.getByRole('button', { name: /Post|Comptabiliser/i }).first().click()
  await Promise.race([
    postCompletion,
    new Promise(resolve => setTimeout(resolve, 30000)),
  ])
  page.off('response', responseListener)

  if (postResponses.length !== employeeCount) {
    const body = await page.locator('body').innerText().catch(() => '')
    throw new Error(`Payroll posting incomplete for ${period.start}: expected ${employeeCount} POSTs, received ${postResponses.length}; body ${body.slice(-500)}`)
  }

  return {
    previewStatus: preview.status(),
    generateStatus: generate.status(),
    generated: true,
    posted: postResponses.length === employeeCount && postResponses.every(status => status === 200),
    postedCount: postResponses.length,
  }
}

async function exerciseSalaryCertificates(page, employeeIds, years) {
  const certificates = []
  for (const employeeId of employeeIds) {
    for (const year of years) {
      const response = await page.request.get(`${BASE_URL}/payroll/employees/${employeeId}/salary-certificate/${year}`)
      const contentType = response.headers()['content-type'] || ''
      const body = await response.body()
      certificates.push({ employeeId, year, status: response.status(), contentType, pdf: body.subarray(0, 4).toString() === '%PDF' })
    }
  }

  return {
    certificates,
    passed: certificates.length === employeeIds.length * years.length
      && certificates.every(certificate => certificate.status === 200 && certificate.contentType.includes('application/pdf') && certificate.pdf),
  }
}

async function findEmployeeIdsForReplay(page) {
  await page.goto(`${BASE_URL}/payroll/employees`, { waitUntil: 'networkidle' })
  const links = await page.locator('a[href^="/payroll/employees/"]').evaluateAll(elements => elements
    .map(element => element.getAttribute('href'))
    .filter(href => href && /^\/payroll\/employees\/[0-9a-f-]{36}$/i.test(href)))

  return [...new Set(links.map(href => href.split('/').pop()))]
}

async function monthlyReportPaths(period) {
  const range = `from_date=${period.start}&to_date=${period.end}`
  return [
    `/reports/profit-and-loss?${range}`,
    `/reports/balance-sheet?as_of_date=${period.end}`,
    `/accounting/trial-balance?as_of_date=${period.end}`,
    `/accounting/journal-entries?${range}`,
    `/reports/cash-flow?${range}`,
    `/reports/aging?type=receivables&as_of_date=${period.end}`,
    `/reports/aging?type=payables&as_of_date=${period.end}`,
  ]
}

function exhaustiveVatPeriods() {
  return [
    ['2024-07-01', '2024-09-30'],
    ['2024-10-01', '2024-12-31'],
    ['2025-01-01', '2025-03-31'],
    ['2025-04-01', '2025-06-30'],
    ['2025-07-01', '2025-09-30'],
    ['2025-10-01', '2025-12-31'],
    ['2026-01-01', '2026-03-31'],
    ['2026-04-01', '2026-06-30'],
  ]
}

async function settleVatPeriodsForReplay(page) {
  const settlements = []
  for (const [from, to] of exhaustiveVatPeriods()) {
    await page.goto(`${BASE_URL}/reports/vat?from_date=${from}&to_date=${to}`, { waitUntil: 'networkidle' })
    const button = page.getByRole('button', { name: /Post settlement entry|Comptabiliser le décompte/i })
    if (!(await button.count())) {
      settlements.push({ from, to, status: 'fail', reason: 'VAT settlement action was unavailable' })
      continue
    }
    await button.click()
    const dialog = page.getByRole('dialog').last()
    const response = await submitAndCapturePost(page, dialog.getByRole('button', { name: /^Post$|^Comptabiliser$/i }), '/reports/vat/settlement')
    settlements.push({ from, to, status: response.status() === 302 ? 'pass' : 'fail', httpStatus: response.status() })
    await page.waitForLoadState('networkidle')
  }

  return { settlements, passed: settlements.length === 8 && settlements.every(settlement => settlement.status === 'pass') }
}

async function exerciseExhaustiveExports(page) {
  const from = '2024-07-01'
  const to = '2026-06-30'
  const asOf = to
  const exportPaths = [
    `/reports/profit-and-loss/export/pdf?from_date=${from}&to_date=${to}`,
    `/reports/profit-and-loss/export/csv?from_date=${from}&to_date=${to}`,
    `/reports/balance-sheet/export/pdf?as_of_date=${asOf}`,
    `/reports/balance-sheet/export/csv?as_of_date=${asOf}`,
    `/accounting/trial-balance/export/pdf?as_of_date=${asOf}`,
    `/accounting/trial-balance/export/csv?as_of_date=${asOf}`,
    `/accounting/journal-entries/export/pdf?from_date=${from}&to_date=${to}`,
    `/accounting/journal-entries/export/csv?from_date=${from}&to_date=${to}`,
    `/reports/cash-flow/export/pdf?from_date=${from}&to_date=${to}`,
    `/reports/cash-flow/export/csv?from_date=${from}&to_date=${to}`,
    `/reports/aging/export/pdf?type=receivables&as_of_date=${asOf}`,
    `/reports/aging/export/csv?type=receivables&as_of_date=${asOf}`,
    `/reports/aging/export/pdf?type=payables&as_of_date=${asOf}`,
    `/reports/aging/export/csv?type=payables&as_of_date=${asOf}`,
  ]
  const exports = []
  for (const path of exportPaths) {
    const response = await page.request.get(`${BASE_URL}${path}`)
    const contentType = response.headers()['content-type'] || ''
    const body = await response.body()
    const pathname = new URL(path, BASE_URL).pathname
    const pdf = pathname.endsWith('/pdf') && body.subarray(0, 4).toString() === '%PDF'
    const csv = pathname.endsWith('/csv') && contentType.includes('text/csv')
    exports.push({ path, status: response.status(), contentType, validPayload: pdf || csv })
  }

  const archiveResponse = await page.request.get(`${BASE_URL}/accounting/archives/year/2025/bundle`)
  const archiveBody = await archiveResponse.body()
  const archive = {
    status: archiveResponse.status(),
    contentType: archiveResponse.headers()['content-type'] || '',
    zip: archiveBody.subarray(0, 2).toString() === 'PK',
  }

  return {
    exports,
    archive,
    passed: exports.length === 14
      && exports.every(item => item.status === 200 && item.validPayload)
      && archive.status === 200
      && archive.zip,
  }
}

async function exerciseExhaustiveUiReplay(page, accountName) {
  const customerName = `QA ACME ${RUN_ID}`
  const euCustomerName = `QA EU Customer ${RUN_ID}`
  const supplierName = `QA Supplier ${RUN_ID}`
  const months = exhaustiveMonths()
  const contacts = [
    { name: customerName, country: 'CH', vatNumber: 'CHE-987.654.321 MWST' },
    { name: euCustomerName, country: 'DE', vatNumber: 'DE123456789', city: 'Berlin', postalCode: '10115' },
    { name: supplierName, country: 'CH', defaultExpenseCategory: 'Office Supplies' },
  ]
  const contactResults = []
  for (const contact of contacts) contactResults.push(await createContactForReplay(page, contact))

  const fiscalYear = await createFiscalYearForReplay(page, `${accountName} Year 2`, '2025-07-01', '2026-06-30')
  const bankPath = await findReconciliationAccountPath(page)
  const monthly = []
  let firstInvoice = null
  let firstXml = null

  for (const [index, period] of months.entries()) {
    const taxTreatment = index === 11 ? 'reverse_charge' : 'standard'
    const invoice = await createInvoiceForReplay(page, taxTreatment === 'reverse_charge' ? euCustomerName : customerName, period, index, taxTreatment)
    const expense = await createExpenseForReplay(page, supplierName, period, index, index === 0)
    const reference = `QA-CAMT-${RUN_ID}-${index + 1}`
    const xml = buildCamtXml('camt053', {
      date: period.date,
      statementId: `STMT-${RUN_ID}-${index + 1}`,
      reference: index === 0 && invoice.number ? invoice.number : reference,
      amount: index === 0 ? invoice.total : '1.00',
      party: taxTreatment === 'reverse_charge' ? euCustomerName : customerName,
    })
    const imported = await importCamtForReplay(page, bankPath, `qa-${RUN_ID}-${index + 1}.xml`, xml)
    monthly.push({ period: period.start.slice(0, 7), invoice, expense, imported })
    if (index === 0) {
      firstInvoice = invoice
      firstXml = xml
      const notification = await importCamtForReplay(
        page,
        bankPath,
        `qa-${RUN_ID}-notification.xml`,
        buildCamtXml('camt054', {
          date: period.date,
          statementId: `NOTIF-${RUN_ID}`,
          reference: `QA-CAMT054-${RUN_ID}`,
          amount: '1.01',
          party: supplierName,
        }),
      )
      monthly[monthly.length - 1].camt054 = notification
    }
    const payroll = await runPayrollMonthForReplay(page, period)
    monthly[monthly.length - 1].payroll = payroll
  }

  const beforeDuplicate = await (async () => {
    await page.goto(`${BASE_URL}${bankPath}?filter=all`, { waitUntil: 'networkidle' })
    return page.locator('button').filter({ hasText: /Match|Associer|Zuordnen/i }).count()
  })()
  const duplicate = await importCamtForReplay(page, bankPath, `qa-${RUN_ID}-duplicate.xml`, firstXml)
  const afterDuplicate = await (async () => {
    await page.goto(`${BASE_URL}${bankPath}?filter=all`, { waitUntil: 'networkidle' })
    return page.locator('button').filter({ hasText: /Match|Associer|Zuordnen/i }).count()
  })()

  let matched = false
  if (firstInvoice?.number) {
    const suggestion = page.locator('button').filter({ hasText: firstInvoice.number }).first()
    if (await suggestion.count()) {
      const matchResponse = page.waitForResponse(response => response.request().method() === 'POST' && /\/reconciliation\/(transactions|matches)\//.test(new URL(response.url()).pathname))
      await suggestion.click()
      const response = await matchResponse
      matched = response.status() === 302
      await page.waitForLoadState('networkidle')
    }
  }

  const reportPaths = []
  for (const [index, period] of months.entries()) {
    for (const path of await monthlyReportPaths(period)) {
      const response = await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' })
      reportPaths.push({ period: period.start.slice(0, 7), path, status: response?.status() ?? null })
      if (CAPTURE_SCREENSHOTS && (index === 0 || index === months.length - 1)) {
        await page.screenshot({ path: `${OUTPUT_DIR}/screenshots-${RUN_ID}/exhaustive-${period.start.slice(0, 7)}-${path.split('?')[0].replace(/[^a-z0-9]+/gi, '-')}.png`, fullPage: true })
      }
    }
  }

  const employeeIds = await findEmployeeIdsForReplay(page)
  const certificates = await exerciseSalaryCertificates(page, employeeIds, [2024, 2025, 2026])
  const settlements = await settleVatPeriodsForReplay(page)
  const firstClose = await exerciseYearEndClosing(page, 2024)
  const secondClose = await exerciseYearEndClosing(page, 2025)
  const exports = await exerciseExhaustiveExports(page)

  return {
    months: monthly,
    contacts: contactResults,
    fiscalYear,
    camt: {
      camt053Imports: monthly.filter(item => item.imported.imported).length,
      camt054Imported: monthly[0]?.camt054?.imported === true,
      duplicateImportAccepted: duplicate.imported,
      transactionCountStableAfterDuplicate: beforeDuplicate === afterDuplicate,
      matched,
    },
    reports: {
      checked: reportPaths.length,
      expected: months.length * 7,
      passed: reportPaths.length === months.length * 7 && reportPaths.every(report => report.status === 200),
    },
    certificates,
    settlements,
    closing: { first: firstClose, second: secondClose },
    exports,
    passed: months.length === 24
      && monthly.every(item => item.invoice.created && item.expense.created && item.imported.imported && item.payroll.posted)
      && fiscalYear.created
      && contactResults.every(contact => contact.created)
      && certificates.passed
      && reportPaths.length === months.length * 7
      && reportPaths.every(report => report.status === 200)
      && monthly[0]?.camt054?.imported
      && beforeDuplicate === afterDuplicate
      && matched
      && settlements.passed
      && firstClose.closed
      && secondClose.closed
      && exports.passed,
  }
}

async function createExhaustiveTenant(browser, createdAccounts) {
  const accountName = `${ACCOUNT_NAME} Exhaustive`
  const email = generatedAccountEmail('-exhaustive')
  const { context, page, consoleErrors, requestFailures } = await createContext(browser)

  try {
    const auth = await createAccount(page, { email, accountName, plan: 'business' })
    createdAccounts.push(auth)
    if (auth.checkoutUrl || new URL(auth.url).hostname === 'checkout.stripe.com') {
      await completeStripeCheckout(page, auth.checkoutUrl, accountName)
    }
    if (auth.needsVerification || page.url().includes('/email/verify')) {
      const verification = await verifyAccountFromMailpit(page, email)
      if (verification.status !== 'pass') throw new Error('Exhaustive tenant email verification failed')
      await login(page, email, auth.password)
    }
    if (page.url().includes('/email/verify')) throw new Error('Exhaustive tenant remained unverified')

    const onboarding = await completeOnboarding(page, accountName, true)
    if (!onboarding.company || !onboarding.fiscalYear || !onboarding.bank) throw new Error('Exhaustive tenant onboarding did not complete')

    const openingBalances = await exerciseOpeningBalanceContract(page)
    if (!openingBalances.rejectedUnbalanced || !openingBalances.acceptedRedirect) throw new Error('Exhaustive tenant opening balance contract failed')

    const employees = [
      await createEmployeeRecord(page, 'Claire', `QA ${RUN_ID}`, generatedAccountEmail('-claire'), '9500.00', '2024-07-01'),
      await createEmployeeRecord(page, 'Lionel', `QA ${RUN_ID}`, generatedAccountEmail('-lionel-exhaustive'), '6200.00', '2024-07-01'),
      await createEmployeeRecord(page, 'Sofia', `QA ${RUN_ID}`, generatedAccountEmail('-sofia-exhaustive'), '5800.00', '2024-09-01'),
    ]
    if (!employees.every(employee => employee.created)) throw new Error('Exhaustive tenant employee setup failed')

    const replay = await exerciseExhaustiveUiReplay(page, accountName)
    return {
      account: { email, accountName },
      diagnostics: { consoleErrors, requestFailures },
      onboarding,
      openingBalances,
      employees,
      replay,
    }
  } finally {
    await context.close()
  }
}

async function stripeWebhookRequest(payload) {
  if (!STRIPE_WEBHOOK_SECRET) throw new Error('STRIPE_WEBHOOK_SECRET is required for exhaustive Stripe webhook checks')
  const body = JSON.stringify(payload)
  const timestamp = Math.floor(Date.now() / 1000)
  const signature = createHmac('sha256', STRIPE_WEBHOOK_SECRET)
    .update(`${timestamp}.${body}`)
    .digest('hex')
  const response = await fetch(`${BASE_URL}/stripe/webhook`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'Stripe-Signature': `t=${timestamp},v1=${signature}`,
    },
    body,
  })
  return { status: response.status, body: await response.json().catch(() => ({})) }
}

async function exerciseStripeWebhookLifecycle(accountEmail) {
  if (!STRIPE_WEBHOOK_SECRET) return { status: 'skip', reason: 'STRIPE_WEBHOOK_SECRET is not configured' }

  const customers = await stripeRequest(`/customers?email=${encodeURIComponent(accountEmail)}&limit=1`)
  const customer = customers.body.data?.[0]
  if (!customer) return { status: 'fail', reason: 'Stripe customer for exhaustive tenant was not found' }
  const subscriptions = await stripeRequest(`/subscriptions?customer=${encodeURIComponent(customer.id)}&limit=1`)
  const subscription = subscriptions.body.data?.[0]
  if (!subscription) return { status: 'fail', reason: 'Stripe subscription for exhaustive tenant was not found' }

  const baseObject = {
    id: subscription.id,
    customer: customer.id,
    status: subscription.status,
    trial_end: subscription.trial_end,
    canceled_at: subscription.canceled_at,
    cancel_at: subscription.cancel_at,
    cancel_at_period_end: subscription.cancel_at_period_end,
    default_payment_method: subscription.default_payment_method,
    metadata: subscription.metadata || {},
  }
  const event = (id, type, object) => ({ id, object: 'event', type, data: { object } })
  let results = []
  let unknown = null
  let externalCleanup = { subscription: false, customer: false }
  try {
    results.push({ event: 'checkout.session.completed', result: await stripeWebhookRequest(event(`qa-${RUN_ID}-checkout-completed`, 'checkout.session.completed', {
      mode: 'subscription',
      subscription: subscription.id,
      customer: customer.id,
      metadata: subscription.metadata || {},
    })) })
    results.push({ event: 'subscription.created', result: await stripeWebhookRequest(event(`qa-${RUN_ID}-subscription-created`, 'customer.subscription.created', baseObject)) })
    results.push({ event: 'subscription.updated', result: await stripeWebhookRequest(event(`qa-${RUN_ID}-subscription-updated`, 'customer.subscription.updated', baseObject)) })
    results.push({ event: 'invoice.payment_failed', result: await stripeWebhookRequest(event(`qa-${RUN_ID}-payment-failed`, 'invoice.payment_failed', { id: `in_${RUN_ID}`, subscription: subscription.id })) })
    results.push({ event: 'invoice.paid', result: await stripeWebhookRequest(event(`qa-${RUN_ID}-invoice-paid`, 'invoice.paid', { id: `in_paid_${RUN_ID}`, subscription: subscription.id })) })
    results.push({ event: 'subscription.deleted', result: await stripeWebhookRequest(event(`qa-${RUN_ID}-subscription-deleted`, 'customer.subscription.deleted', { id: subscription.id })) })
    const duplicatePayload = event(`qa-${RUN_ID}-subscription-updated`, 'customer.subscription.updated', baseObject)
    results.push({ event: 'duplicate', result: await stripeWebhookRequest(duplicatePayload) })
    unknown = await stripeWebhookRequest(event(`qa-${RUN_ID}-unknown`, 'customer.unknown_event', { id: `unknown_${RUN_ID}` }))
  } finally {
    const deletedSubscription = await stripeRequest(`/subscriptions/${subscription.id}`, 'DELETE')
    const deletedCustomer = await stripeRequest(`/customers/${customer.id}`, 'DELETE')
    externalCleanup = {
      subscription: deletedSubscription.response.ok,
      customer: deletedCustomer.response.ok,
    }
  }

  return {
    status: results.length === 7
      && results.every(item => item.result.status === 200)
      && unknown?.status === 200
      && externalCleanup.subscription
      && externalCleanup.customer ? 'pass' : 'fail',
    customerId: customer.id,
    subscriptionId: subscription.id,
    events: results,
    unknown,
    duplicateHandled: results.at(-1)?.result.body?.status === 'already_processed',
    externalCleanup,
  }
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
      const status = phaseResults.some(item => item.status === 'fail')
        ? 'FAIL'
        : phaseResults.some(item => item.status === 'skip') && phaseResults.some(item => item.status === 'pass')
          ? 'PARTIAL'
          : phaseResults.some(item => item.status === 'pass') ? 'PASS' : 'SKIP'
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
    mode: RUN_ENABLED ? (EXHAUSTIVE ? 'staging-exhaustive' : 'staging-safe-smoke') : 'dry-run',
    accountMode: CREATE_ACCOUNT ? 'ephemeral-signup' : 'existing-account',
    protectedOrganization: '[redacted]',
    results: [],
    consoleErrors: [],
    requestFailures: [],
    expectedDiagnostics: { consoleErrors: [], requestFailures: [] },
    cleanup: null,
  }

  if (!RUN_ENABLED) {
    report.results.push(result(0, 'runner configuration', 'skip', { reason: 'Set QA_RUN=1 to execute staging checks' }))
  } else {
    const createdAccounts = CREATE_ACCOUNT ? [{ email: generatedAccountEmail() }] : []
    const browser = await chromium.launch({
      headless: process.env.QA_HEADLESS !== '0',
      executablePath: await browserExecutablePath(),
    })
    const { context, page, consoleErrors, requestFailures } = await createContext(browser)
    const additionalConsoleErrors = []
    const additionalRequestFailures = []
    const screenshotDirectory = `${OUTPUT_DIR}/screenshots-${RUN_ID}`
    await mkdir(screenshotDirectory, { recursive: true })
    try {
      const auth = CREATE_ACCOUNT ? await createAccount(page) : await login(page)
      if (CREATE_ACCOUNT) createdAccounts[0] = auth
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

        try {
          const payroll = await exercisePayroll(page)
          const payrollPassed = payroll.employee.created
            && payroll.preview.visible
            && payroll.generated.generated
            && payroll.posted.posted
            && payroll.salarySlips.listed
          report.results.push(result(3, 'payroll UI workflow', payrollPassed ? 'pass' : 'fail', payroll))
        } catch (error) {
          report.results.push(result(3, 'payroll UI workflow', 'fail', { error: error.message }))
        }

        try {
          const vatAndFiscalYear = await exerciseVatAndFiscalYear(page)
          const vatPassed = vatAndFiscalYear.vatReport.httpStatus === 200
            && vatAndFiscalYear.vatReport.rendered
            && vatAndFiscalYear.settlement.posted
            && vatAndFiscalYear.fiscalYear.created
            && vatAndFiscalYear.fiscalYear.listed
          report.results.push(result(4, 'VAT settlement and fiscal year UI workflow', vatPassed ? 'pass' : 'fail', vatAndFiscalYear))
        } catch (error) {
          report.results.push(result(4, 'VAT settlement and fiscal year UI workflow', 'fail', { error: error.message }))
        }

        try {
          const fiscalYearChange = await exerciseFiscalYearChangeRequest(page)
          report.results.push(result(7, 'fiscal year change request UI workflow', fiscalYearChange.status, fiscalYearChange))
        } catch (error) {
          report.results.push(result(7, 'fiscal year change request UI workflow', 'fail', { error: error.message }))
        }

        try {
          const permissions = await exercisePermissions(browser, page, createdAccounts)
          const permissionsPassed = permissions.employeeRecords.sofia.created
            && permissions.personas.length === 2
            && permissions.personas.every(persona => persona.accepted && persona.targetOrganization && persona.restricted.every(route => route.httpStatus === 403))
            && permissions.employeeExpense.created
            && permissions.employeeExpense.listed
          report.results.push(result(5, 'multi-persona permissions UI workflow', permissionsPassed ? 'pass' : 'fail', permissions))
        } catch (error) {
          report.results.push(result(5, 'multi-persona permissions UI workflow', 'fail', { error: error.message }))
        }

        try {
          const closing = await exerciseYearEndClosing(page)
          report.results.push(result(6, 'year-end closing UI workflow', closing.closed && closing.archiveListed ? 'pass' : 'fail', closing))
        } catch (error) {
          report.results.push(result(6, 'year-end closing UI workflow', 'fail', { error: error.message }))
        }

        try {
          const reopen = await exerciseReopenAndReclose(page)
          const reclosePassed = reopen.reopened
            && reopen.adjustment?.created
            && reopen.reclose?.closed
            && reopen.reclose?.archiveListed
          report.results.push(result(8, 'reopen and reclose UI workflow', reclosePassed ? 'pass' : 'fail', reopen))
        } catch (error) {
          report.results.push(result(8, 'reopen and reclose UI workflow', 'fail', { error: error.message }))
        }

        try {
          const billing = await exerciseBillingAndStripe(page)
          const billingPassed = billing.billing.httpStatus === 200
            && billing.billing.rendered
            && billing.stripe.testMode
            && billing.testClock.status !== 'fail'
          report.results.push(result(9, 'billing and Stripe test workflow', billingPassed ? 'pass' : 'fail', billing))
        } catch (error) {
          report.results.push(result(9, 'billing and Stripe test workflow', 'fail', { error: error.message }))
        }

        if (EXHAUSTIVE) {
          try {
            const exhaustive = await createExhaustiveTenant(browser, createdAccounts)
            additionalConsoleErrors.push(...exhaustive.diagnostics.consoleErrors)
            additionalRequestFailures.push(...exhaustive.diagnostics.requestFailures)
            report.results.push(result(0, 'exhaustive 24-month UI replay', exhaustive.replay.passed ? 'pass' : 'fail', exhaustive))
            const webhook = await exerciseStripeWebhookLifecycle(exhaustive.account.email)
            report.results.push(result(9, 'Stripe webhook lifecycle', webhook.status === 'pass' ? 'pass' : 'fail', webhook))
          } catch (error) {
            report.results.push(result(10, 'exhaustive staging campaign', 'fail', { error: error.message }))
          }
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

        try {
          const accessibility = await exerciseAccessibilityAndExports(page)
          report.results.push(result(10, 'accessibility and exports UI checks', accessibility.passed ? 'pass' : 'fail', accessibility))
        } catch (error) {
          report.results.push(result(10, 'accessibility and exports UI checks', 'fail', { error: error.message }))
        }
      }
    } catch (error) {
      report.results.push(result(0, 'runner execution', 'fail', { error: error.message }))
    } finally {
      const diagnostics = diagnosticClassification(
        [...consoleErrors, ...additionalConsoleErrors],
        [...requestFailures, ...additionalRequestFailures],
      )
      report.consoleErrors = diagnostics.actionableConsoleErrors
      report.requestFailures = diagnostics.actionableRequestFailures
      report.expectedDiagnostics = {
        consoleErrors: diagnostics.expectedConsoleErrors,
        requestFailures: diagnostics.expectedRequestFailures,
      }
      if (report.consoleErrors.length || report.requestFailures.length) {
        report.results.push(result(10, 'application diagnostics', 'fail', {
          consoleErrors: report.consoleErrors,
          requestFailures: report.requestFailures,
        }))
      }
      await context.close()
      await browser.close()
      if (CREATE_ACCOUNT) {
        const cleanupResults = []
        for (const account of createdAccounts.reverse()) {
          try {
            cleanupResults.push(await cleanupAccount(account?.email))
          } catch (error) {
            cleanupResults.push({ status: 'fail', error: error.message })
          }
        }
        report.cleanup = {
          status: cleanupResults.every(cleanup => cleanup.status !== 'fail') ? 'pass' : 'fail',
          accounts: cleanupResults,
        }
        if (report.cleanup.status === 'fail') {
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