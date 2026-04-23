---
title: Externe testpages — handmatige audit-tools per project-URL
type: reference
scope: alle-projecten
last_check: 2026-04-23
---

# Externe testpages voor productie-domeinen

> **Regel (feedback-memory):** deze testpages blijven **handmatig**.
> De API's geven alleen eindscore; de UI toont de volledige breakdown
> die voor beoordeling nodig is.
>
> Frequentie richtlijn: **na elke deploy** + **maandelijks cross-check**
> van alle categorieën per productie-domein.

## Security & TLS (hoogste prioriteit)

| Testpage | URL | Meet | Best gebruik |
|----------|-----|------|--------------|
| **Mozilla Observatory** | https://observatory.mozilla.org | CSP, SRI, HSTS, cookie flags, subresource integrity. Score 0-100. | Primaire security-baseline. Al geautomatiseerd in `qv:scan --only=observatory` voor drift-detectie. |
| **SecurityHeaders.com** | https://securityheaders.com | Alle HTTP security headers (CSP, HSTS, X-Frame-Options, Permissions-Policy). Letter grade A-F. | Headers-focus; toont per header wat mist + waarom. |
| **SSL Labs** | https://www.ssllabs.com/ssltest | SSL certificaat, cipher suites, protocol support, session resumption, DNS CAA, HSTS. Letter grade A-F. | Diepe TLS-analyse. Klik "Clear cache" na server-config wijziging. |
| **Hardenize** | https://hardenize.com | Overall security: TLS + DNS + email (SPF/DKIM/DMARC) + headers + CAA in één rapport. | Brede sweep; goed voor "waar sta ik portfolio-breed". |
| **Internet.nl** | https://internet.nl | NL-autoriteit check: web + mail + connection. IPv6, DNSSEC, HTTPS, modern TLS, DMARC. | Nederlandse compliance-focus; overheidsstandaarden. |
| **CSP Evaluator** | https://csp-evaluator.withgoogle.com | Plak je CSP-string in, krijg Google's analyse (unsafe-inline detectie, missende directives, bypass-risico's). | Specifiek voor CSP-header debugging. |
| **CryptCheck** | https://cryptcheck.fr | Diepere TLS-analyse dan SSL Labs: specifieke cipher-weaknesses, heartbleed-style exploits. | Supplement bij TLS-debugging als SSL Labs iets niet duidelijk zegt. |

## Performance

| Testpage | URL | Meet | Best gebruik |
|----------|-----|------|--------------|
| **PageSpeed Insights** | https://pagespeed.web.dev | Google's Core Web Vitals (LCP, INP, CLS) + Lighthouse. Score 0-100 mobile + desktop. | Primair performance. Mobile + desktop score ≥ 90 als norm. |
| **WebPageTest** | https://www.webpagetest.org | Uitgebreide waterfall, filmstrip, connection-view. Geo-location testing. | Diepe performance-debugging; waterfall laat bottlenecks zien. |
| **GTmetrix** | https://gtmetrix.com | Lighthouse-gebaseerd, met historie en alerts. | Tracking over tijd; goed voor "is het langzamer geworden?". |

## Accessibility

| Testpage | URL | Meet | Best gebruik |
|----------|-----|------|--------------|
| **WAVE (WebAIM)** | https://wave.webaim.org | WCAG 2.1 overtredingen, contrast, ARIA-issues. | Primary accessibility scan. |
| **axe DevTools** | https://www.deque.com/axe | Open source accessibility engine, ook als browser extension. | Tijdens development als browser-plugin. |

## SEO & Crawlability

| Testpage | URL | Meet | Best gebruik |
|----------|-----|------|--------------|
| **Google Search Console** | https://search.google.com/search-console | Indexing, crawl errors, core web vitals vanaf Google's perspectief. | Eigenaar-tool; per domein 1x eenmalig verifiëren + daarna monitoren. |
| **Google Mobile-Friendly Test** | https://search.google.com/test/mobile-friendly | Mobile rendering + usability issues. | Publieke spot-check. |
| **Rich Results Test** | https://search.google.com/test/rich-results | Schema.org markup validatie. | Als je structured data (JSON-LD) gebruikt. |

## DNS & Mail

| Testpage | URL | Meet | Best gebruik |
|----------|-----|------|--------------|
| **MXToolbox** | https://mxtoolbox.com/SuperTool.aspx | DNS records, blacklist check, mail server test. | Bij email-delivery problemen. |
| **DNSViz** | https://dnsviz.net | DNSSEC-validatie, chain-of-trust visualisatie. | Na DNSSEC setup of DS-record wijziging. |
| **mail-tester.com** | https://www.mail-tester.com | Stuur een mail naar hen, krijg score 0-10 over spam-kans + SPF/DKIM/DMARC validatie. | Na mail-server wijziging of nieuwe sender-domain. |

## Mobile app testing (als we native apps hebben)

| Testpage | URL | Meet | Best gebruik |
|----------|-----|------|--------------|
| **BrowserStack** | https://www.browserstack.com | Real device + browser grid (Chrome/Safari + iOS/Android versies). Paid. | Cross-device compatibility check. |
| **LambdaTest** | https://www.lambdatest.com | Concurrent: real browsers + devices. Paid. | Alternatief voor BrowserStack. |

## Monitoring (doorlopend, geen handmatige test)

- **qv:scan** (onze eigen) — draait wekelijks, dekt composer/npm/SSL/Observatory/server-health/forms/ratelimit/secrets/cookies/test-erosion/debug-mode
- **UptimeRobot / Pingdom** — uptime + response time alerts (indien actief)

## Checklist per productie-deploy

**Minimum na elke deploy:**
1. SSL Labs — moet A+ blijven (geen regressie in cipher/cert)
2. SecurityHeaders — grade moet hetzelfde/beter blijven
3. Mozilla Observatory — score moet hetzelfde/beter blijven

**Maandelijks (bovenop wekelijkse qv:scan):**
4. Hardenize — cross-check DNS/mail/TLS/headers
5. Internet.nl — NL-compliance check
6. PageSpeed Insights — core web vitals
7. WAVE — accessibility (bij UI-wijzigingen)

## Zie ook

- `runbooks/kwaliteit-veiligheid-systeem.md` — V&K architectuur + automatische qv:scan layer
- `runbooks/security-headers-check.md` — CSP en security headers detail
- `C:/Users/henkv/.claude/projects/D--GitHub-HavunCore/memory/feedback_3_testpages_manual.md`
  — waarom deze tests handmatig blijven (volledige breakdown vereist UI-ervaring)
