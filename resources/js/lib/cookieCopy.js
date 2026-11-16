const copy = {
  en: {
    description:
      'Essential cookies keep Gäld working. Optional analytics measure visits; optional advertising measurement links sign-ups and first payments to Google Ads. Choose each separately.',
    accept: 'Accept all',
    reject: 'Reject optional',
    preferences: 'Choose cookies',
    title: 'Advertising measurement',
    marketing:
      'Optional Google Ads conversion measurement. Google processes click identifiers and technical information. We do not send names, emails or accounting content. No enhanced conversions or remarketing.',
    duration: 'Set by Google; see details',
  },
  fr: {
    description:
      'Les cookies essentiels font fonctionner Gäld. L’analyse facultative mesure les visites ; la mesure publicitaire facultative relie les inscriptions et premiers paiements aux annonces Google. Choisissez séparément.',
    accept: 'Tout accepter',
    reject: 'Refuser les facultatifs',
    preferences: 'Choisir les cookies',
    title: 'Mesure publicitaire',
    marketing:
      'Mesure facultative des conversions Google Ads. Google traite des identifiants de clic et des informations techniques. Nous ne transmettons ni noms, ni e-mails, ni contenu comptable. Aucun remarketing ni conversion améliorée.',
    duration: 'Définie par Google ; voir les détails',
  },
  de: {
    description:
      'Notwendige Cookies ermöglichen Gäld. Optionale Analyse misst Besuche; optionale Werbemessung ordnet Registrierungen und erste Zahlungen Google Ads zu. Entscheiden Sie getrennt.',
    accept: 'Alle akzeptieren',
    reject: 'Optionale ablehnen',
    preferences: 'Cookies auswählen',
    title: 'Werbemessung',
    marketing:
      'Optionale Google-Ads-Conversion-Messung. Google verarbeitet Klickkennungen und technische Informationen. Wir senden keine Namen, E-Mail-Adressen oder Buchhaltungsinhalte. Keine erweiterten Conversions und kein Remarketing.',
    duration: 'Von Google festgelegt; siehe Details',
  },
  it: {
    description:
      'I cookie necessari fanno funzionare Gäld. L’analisi facoltativa misura le visite; la misurazione pubblicitaria facoltativa collega iscrizioni e primi pagamenti a Google Ads. Scegli separatamente.',
    accept: 'Accetta tutti',
    reject: 'Rifiuta i facoltativi',
    preferences: 'Scegli i cookie',
    title: 'Misurazione pubblicitaria',
    marketing:
      'Misurazione facoltativa delle conversioni Google Ads. Google tratta identificatori dei clic e informazioni tecniche. Non inviamo nomi, email o contenuti contabili. Nessuna conversione avanzata o remarketing.',
    duration: 'Stabilita da Google; vedi i dettagli',
  },
}

export function withMarketingTranslations(translations, domain) {
  for (const [locale, text] of Object.entries(copy)) {
    const entry = translations[locale]
    const privacy = entry.consentModal.description.match(/<a[^>]*>.*?<\/a>/)?.[0] || ''
    entry.consentModal.description = `${text.description} ${privacy}`
    entry.consentModal.acceptAllBtn = text.accept
    entry.consentModal.acceptNecessaryBtn = text.reject
    entry.consentModal.showPreferencesBtn = text.preferences
    entry.preferencesModal.acceptAllBtn = text.accept
    entry.preferencesModal.acceptNecessaryBtn = text.reject
    const sections = entry.preferencesModal.sections
    sections.splice(sections.length - 1, 0, {
      title: text.title,
      description: text.marketing,
      linkedCategory: 'marketing',
      cookieTable: {
        headers: sections.find((section) => section.cookieTable).cookieTable.headers,
        body: [
          {
            name: '_gcl_* / _gcl_ls',
            domain,
            description: text.marketing,
            expiration: text.duration,
          },
        ],
      },
    })
  }
  return translations
}
