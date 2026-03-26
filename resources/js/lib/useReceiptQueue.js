import { reactive, readonly } from 'vue'

/**
 * Global reactive store for background receipt scanning.
 * Survives Vue component unmounts (module-level state).
 */
const scans = reactive(new Map()) // scanId → { status, receiptPath, extracted, error }

let csrfToken = null

function getCsrfToken() {
  if (csrfToken) return csrfToken
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  csrfToken = match ? decodeURIComponent(match[1]) : ''
  return csrfToken
}

function resetCsrfToken() {
  csrfToken = null
}

async function uploadAndScan(file) {
  const formData = new FormData()
  formData.append('receipt', file)

  const response = await fetch('/expenses/scan-receipt', {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': getCsrfToken(),
      'Accept': 'application/json',
    },
    body: formData,
    credentials: 'same-origin',
  })

  if (response.status === 419) {
    // CSRF token might be stale — refresh and retry once
    resetCsrfToken()
    const retry = await fetch('/expenses/scan-receipt', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: formData,
      credentials: 'same-origin',
    })
    if (!retry.ok) {
      const data = await retry.json().catch(() => ({}))
      throw new Error(data.message || `HTTP ${retry.status}`)
    }
    return await retry.json()
  }

  if (!response.ok) {
    const data = await response.json().catch(() => ({}))
    throw new Error(data.message || `HTTP ${response.status}`)
  }

  return await response.json()
}

async function pollScan(scanId) {
  const maxAttempts = 90
  const intervalMs = 2000

  for (let i = 0; i < maxAttempts; i++) {
    await new Promise(resolve => setTimeout(resolve, intervalMs))

    // Stop polling if scan was dismissed
    if (!scans.has(scanId)) return

    try {
      const response = await fetch(`/expenses/scan-receipt/${encodeURIComponent(scanId)}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-XSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
      })

      if (!response.ok) continue

      const data = await response.json()

      if (data.status === 'completed') {
        scans.set(scanId, {
          status: 'completed',
          receiptPath: data.receipt_path,
          extracted: data.extracted || {},
          error: null,
        })
        return
      }

      if (data.status === 'failed') {
        scans.set(scanId, {
          ...scans.get(scanId),
          status: 'failed',
          error: data.error || null,
        })
        return
      }
    } catch {
      // Network error — keep polling
    }
  }

  // Timeout
  if (scans.has(scanId)) {
    scans.set(scanId, {
      ...scans.get(scanId),
      status: 'timeout',
      error: 'Processing took too long',
    })
  }
}

export function useReceiptQueue() {
  async function enqueue(file) {
    const data = await uploadAndScan(file)
    const { scan_id: scanId, receipt_path: receiptPath } = data

    scans.set(scanId, {
      status: 'processing',
      receiptPath,
      extracted: null,
      error: null,
    })

    // Start background polling (fire-and-forget)
    pollScan(scanId)

    return scanId
  }

  function dismiss(scanId) {
    scans.delete(scanId)
  }

  function dismissAll() {
    scans.clear()
  }

  return {
    scans: readonly(scans),
    enqueue,
    dismiss,
    dismissAll,
  }
}
