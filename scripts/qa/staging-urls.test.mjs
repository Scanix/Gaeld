import assert from 'node:assert/strict'
import { test } from 'node:test'
import { isAmazonPaymentsUrl, stagingMailLink } from './staging-urls.mjs'

const base = 'https://staging.example.test'

test('signed verification and invitation links keep their original origin and query', () => {
  const text = 'Your link is https://staging.example.test/email/verify/1?expires=2&amp;signature=abc'
  assert.equal(stagingMailLink(text, base, /^\/email\/verify\/.+/),
    base + '/email/verify/1?expires=2&signature=abc')
  assert.equal(stagingMailLink('<a href="https://staging.example.test/invitations/42/accept">Accept</a>',
    base, /^\/invitations\/[A-Za-z0-9]+\/accept$/), base + '/invitations/42/accept')
})

test('other origins and unrelated paths are ignored', () => {
  assert.equal(stagingMailLink('https://other.example.test/email/verify/1', base, /^\/email\/verify\//), null)
  assert.equal(stagingMailLink(base + '/dashboard', base, /^\/email\/verify\//), null)
})

test('payment diagnostics use the URL hostname', () => {
  assert.equal(isAmazonPaymentsUrl('https://payments-eu.amazon.com/service'), true)
  assert.equal(isAmazonPaymentsUrl('https://example.test/service'), false)
  assert.equal(isAmazonPaymentsUrl('ordinary console text'), false)
})
