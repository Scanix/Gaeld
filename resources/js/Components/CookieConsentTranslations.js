const COOKIE_NAME = 'gaeld_cookie_consent'
const COOKIE_DOMAIN = import.meta.env.VITE_COOKIE_DOMAIN || window.location.hostname

export default function getTranslations() {
  return {
    en: {
      consentModal: {
        title: 'Cookies & Privacy',
        description:
          'Our website uses essential cookies to ensure its proper functioning. <a href="https://gaeld.ch/privacy" class="cc-link">Privacy Policy</a>',
        acceptAllBtn: 'Accept',
        showPreferencesBtn: 'More info',
      },
      preferencesModal: {
        title: 'Cookie preferences',
        acceptAllBtn: 'Accept',
        savePreferencesBtn: 'Save preferences',
        closeIconLabel: 'Close',
        sections: [
          {
            title: 'Cookie usage',
            description:
              'We use cookies to ensure the basic functionalities of the website. For more details about cookies and other sensitive data, please read the full <a href="https://gaeld.ch/privacy" class="cc-link">privacy policy</a>',
          },
          {
            title: 'Strictly necessary cookies',
            description:
              'These cookies are essential for the proper functioning of the website. Without these cookies, the website would not work properly.',
            linkedCategory: 'necessary',
            cookieTable: {
              headers: {
                name: 'Name',
                domain: 'Domain',
                description: 'Description',
                expiration: 'Expiration',
              },
              body: [
                {
                  name: COOKIE_NAME,
                  domain: COOKIE_DOMAIN,
                  description: 'Stores your cookie consent preferences.',
                  expiration: '1 year',
                },
                {
                  name: 'XSRF-TOKEN',
                  domain: COOKIE_DOMAIN,
                  description: 'CSRF protection token.',
                  expiration: 'Session',
                },
                {
                  name: 'gaeld_session',
                  domain: COOKIE_DOMAIN,
                  description: 'Session identifier.',
                  expiration: 'Session',
                },
              ],
            },
          },
          {
            title: 'More information',
            description:
              'For any questions regarding our cookie policy and your choices, please <a class="cc-link" href="https://gaeld.ch/privacy">contact us</a>',
          },
        ],
      },
    },

    fr: {
      consentModal: {
        title: 'Cookies & Confidentialit\u00e9',
        description:
          'Notre site web utilise des cookies essentiels pour assurer son bon fonctionnement. <a href="https://gaeld.ch/fr/privacy" class="cc-link">Politique de confidentialit\u00e9</a>',
        acceptAllBtn: 'Accepter',
        showPreferencesBtn: "Plus d\'infos",
      },
      preferencesModal: {
        title: 'Pr\u00e9f\u00e9rences de cookies',
        acceptAllBtn: 'Accepter',
        savePreferencesBtn: 'Sauvegarder les pr\u00e9f\u00e9rences',
        closeIconLabel: 'Fermer',
        sections: [
          {
            title: 'Utilisation des cookies',
            description:
              'Nous utilisons des cookies pour assurer les fonctionnalit\u00e9s de base du site. Pour plus de d\u00e9tails sur les cookies et autres donn\u00e9es sensibles, veuillez lire la <a href="https://gaeld.ch/fr/privacy" class="cc-link">politique de confidentialit\u00e9</a>',
          },
          {
            title: 'Cookies strictement n\u00e9cessaires',
            description:
              'Ces cookies sont essentiels au bon fonctionnement du site. Sans ces cookies, le site ne fonctionnerait pas correctement.',
            linkedCategory: 'necessary',
            cookieTable: {
              headers: {
                name: 'Nom',
                domain: 'Domaine',
                description: 'Description',
                expiration: 'Expiration',
              },
              body: [
                {
                  name: COOKIE_NAME,
                  domain: COOKIE_DOMAIN,
                  description: 'Stocke vos pr\u00e9f\u00e9rences de consentement aux cookies.',
                  expiration: '1 an',
                },
                {
                  name: 'XSRF-TOKEN',
                  domain: COOKIE_DOMAIN,
                  description: 'Jeton de protection CSRF.',
                  expiration: 'Session',
                },
                {
                  name: 'gaeld_session',
                  domain: COOKIE_DOMAIN,
                  description: 'Identifiant de session.',
                  expiration: 'Session',
                },
              ],
            },
          },
          {
            title: "Plus d\'informations",
            description:
              'Pour toute question concernant notre politique de cookies et vos choix, veuillez <a class="cc-link" href="https://gaeld.ch/fr/privacy">nous contacter</a>',
          },
        ],
      },
    },

    de: {
      consentModal: {
        title: 'Cookies & Datenschutz',
        description:
          'Unsere Website verwendet essentielle Cookies, um deren ordnungsgem\u00e4sses Funktionieren sicherzustellen. <a href="https://gaeld.ch/de/privacy" class="cc-link">Datenschutzerkl\u00e4rung</a>',
        acceptAllBtn: 'Akzeptieren',
        showPreferencesBtn: 'Mehr Infos',
      },
      preferencesModal: {
        title: 'Cookie-Einstellungen',
        acceptAllBtn: 'Akzeptieren',
        savePreferencesBtn: 'Einstellungen speichern',
        closeIconLabel: 'Schliessen',
        sections: [
          {
            title: 'Cookie-Nutzung',
            description:
              'Wir verwenden Cookies, um die grundlegenden Funktionen der Website sicherzustellen. Weitere Details zu Cookies und anderen sensiblen Daten finden Sie in unserer <a href="https://gaeld.ch/de/privacy" class="cc-link">Datenschutzerkl\u00e4rung</a>',
          },
          {
            title: 'Unbedingt erforderliche Cookies',
            description:
              'Diese Cookies sind f\u00fcr das ordnungsgem\u00e4sse Funktionieren der Website unerl\u00e4sslich. Ohne diese Cookies w\u00fcrde die Website nicht richtig funktionieren.',
            linkedCategory: 'necessary',
            cookieTable: {
              headers: {
                name: 'Name',
                domain: 'Domain',
                description: 'Beschreibung',
                expiration: 'Ablauf',
              },
              body: [
                {
                  name: COOKIE_NAME,
                  domain: COOKIE_DOMAIN,
                  description: 'Speichert Ihre Cookie-Einstellungen.',
                  expiration: '1 Jahr',
                },
                {
                  name: 'XSRF-TOKEN',
                  domain: COOKIE_DOMAIN,
                  description: 'CSRF-Schutztoken.',
                  expiration: 'Sitzung',
                },
                {
                  name: 'gaeld_session',
                  domain: COOKIE_DOMAIN,
                  description: 'Sitzungskennung.',
                  expiration: 'Sitzung',
                },
              ],
            },
          },
          {
            title: 'Weitere Informationen',
            description:
              'Bei Fragen zu unserer Cookie-Richtlinie und Ihren Wahlm\u00f6glichkeiten <a class="cc-link" href="https://gaeld.ch/de/privacy">kontaktieren Sie uns</a>',
          },
        ],
      },
    },

    it: {
      consentModal: {
        title: 'Cookie e Privacy',
        description:
          'Il nostro sito web utilizza cookie essenziali per garantirne il corretto funzionamento. <a href="https://gaeld.ch/it/privacy" class="cc-link">Informativa sulla privacy</a>',
        acceptAllBtn: 'Accetta',
        showPreferencesBtn: "Pi\u00f9 info",
      },
      preferencesModal: {
        title: 'Preferenze cookie',
        acceptAllBtn: 'Accetta',
        savePreferencesBtn: 'Salva preferenze',
        closeIconLabel: 'Chiudi',
        sections: [
          {
            title: 'Utilizzo dei cookie',
            description:
              "Utilizziamo i cookie per garantire le funzionalit\u00e0 di base del sito. Per maggiori dettagli sui cookie e altri dati sensibili, leggete l\'<a href=\"https://gaeld.ch/it/privacy\" class=\"cc-link\">informativa sulla privacy</a>.",
          },
          {
            title: 'Cookie strettamente necessari',
            description:
              'Questi cookie sono essenziali per il corretto funzionamento del sito. Senza questi cookie, il sito non funzionerebbe correttamente.',
            linkedCategory: 'necessary',
            cookieTable: {
              headers: {
                name: 'Nome',
                domain: 'Dominio',
                description: 'Descrizione',
                expiration: 'Scadenza',
              },
              body: [
                {
                  name: COOKIE_NAME,
                  domain: COOKIE_DOMAIN,
                  description: 'Memorizza le vostre preferenze sui cookie.',
                  expiration: '1 anno',
                },
                {
                  name: 'XSRF-TOKEN',
                  domain: COOKIE_DOMAIN,
                  description: 'Token di protezione CSRF.',
                  expiration: 'Sessione',
                },
                {
                  name: 'gaeld_session',
                  domain: COOKIE_DOMAIN,
                  description: 'Identificatore di sessione.',
                  expiration: 'Sessione',
                },
              ],
            },
          },
          {
            title: 'Maggiori informazioni',
            description:
              'Per qualsiasi domanda sulla nostra politica dei cookie e le vostre scelte, <a class="cc-link" href="https://gaeld.ch/it/privacy">contattateci</a>.',
          },
        ],
      },
    },
  }
}
