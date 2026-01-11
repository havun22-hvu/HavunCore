# Design Inspiratie: Session.org

> Minimalistisch, rustig design met moderne uitstraling

**Bron:** https://getsession.org/

## Font

**PublicSans** - open-source sans-serif
- Download: https://fonts.google.com/specimen/Public+Sans
- Fallback: `system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif`

```css
font-family: PublicSans, system-ui, -apple-system, sans-serif;
```

## Kleurenpalet

| Naam | Code | Gebruik |
|------|------|---------|
| Primary (neon groen) | `#00f782` | Buttons, accenten, links |
| Primary dark | `#00b35f` | Hover states |
| Grijs licht | `#adadad` | Subtiele tekst |
| Grijs donker | `#333132` | Body tekst |
| Zwart | `#000000` | Headings |
| Wit | `#ffffff` | Achtergrond |

## CSS Variables

```css
:root {
  --primary: #00f782;
  --primary-dark: #00b35f;
  --gray-light: #adadad;
  --gray-dark: #333132;
  --black: #000000;
  --white: #ffffff;
}
```

## Framework

**Tailwind CSS** met custom config

## Wat maakt het rustig?

1. **Veel witruimte** - generous padding/margins
2. **Beperkt kleurenpalet** - alleen zwart/wit + 1 accent
3. **Grote typography** - makkelijk leesbaar
4. **Clean backgrounds** - geen patronen of gradients
5. **Subtiele animaties** - hover transitions, geen flashy effects
6. **Asymmetrische layouts** - tekst links, visual rechts

## Tailwind Config Voorbeeld

```javascript
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#00f782',
          dark: '#00b35f',
        },
        gray: {
          light: '#adadad',
          dark: '#333132',
        },
      },
      fontFamily: {
        sans: ['PublicSans', 'system-ui', '-apple-system', 'sans-serif'],
      },
    },
  },
}
```

## Button Styling

```css
.btn-primary {
  background-color: var(--primary);
  color: var(--black);
  padding: 12px 32px;
  border-radius: 9999px; /* pill shape */
  font-weight: 500;
  transition: background-color 0.2s ease;
}

.btn-primary:hover {
  background-color: var(--primary-dark);
}
```

## Toepasbaar voor

- Landing pages
- Marketing websites
- Portfolio sites
- Product pagina's

---

*Opgeslagen: 10 januari 2026*
