export function googleRuntimeConfig() {
  try {
    const payload = JSON.parse(document.getElementById('gaeld-google-config')?.textContent || '{}')
    const config = payload.config || payload
    return {
      ...config,
      gtmId: '',
      adsId: window.location.pathname === '/conversions/confirm' ? config.adsId : '',
      allowedPaths: ['/conversions/confirm'],
    }
  } catch { return { allowedPaths: [] } }
}
