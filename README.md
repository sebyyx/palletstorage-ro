# PalletStorage.ro – Site de prezentare

Site simplu de prezentare pentru servicii de depozitare paleți în București, zona Faur.

## Structura proiectului

```
palletstorage-ro/
├── index.html              ← Pagina principală
├── styles.css              ← Toate stilurile
├── assets/
│   └── images/
│       └── CITESTE-MA.md  ← Ghid fotografii (ce imagini să adaugi și unde)
└── README.md               ← Acest fișier
```

## Cum deschizi site-ul local

Dublu-click pe `index.html` — se deschide direct în browser.

Sau, dacă ai VS Code cu extensia **Live Server**:
1. Click dreapta pe `index.html` → **Open with Live Server**
2. Site-ul se reîncarcă automat când salvezi modificări

## Ce trebuie completat / personalizat

### 1. Date de contact (index.html)
Caută și înlocuiește:
- `+40 700 000 000` → numărul tău real de telefon
- `contact@palletstorage.ro` → emailul tău real
- `Zona Faur, București` → adresa exactă

### 2. Statistici (secțiunea "Cifre")
Actualizează numerele reale:
- 5 000+ paleți depozitați
- 2 000 m² spațiu
- etc.

### 3. Fotografii
Urmărește ghidul din `assets/images/CITESTE-MA.md`

### 4. Harta Google Maps
Înlocuiește placeholder-ul din secțiunea "Locație" cu iframe-ul real de la Google Maps.

### 5. Formularul de contact
Formularul momentan nu trimite emailuri (e doar HTML). Opțiuni pentru a-l activa:
- **Formspree** (gratuit, simplu): mergi pe [formspree.io](https://formspree.io), creezi un endpoint și îl pui în `action=""` al formularului
- **EmailJS**: funcționează direct din browser, fără server
- **Netlify Forms**: dacă hostezi pe Netlify, funcționează automat

## Hosting recomandat (gratuit/ieftin)

| Opțiune       | Cost  | Detalii                                  |
|---------------|-------|------------------------------------------|
| **Netlify**   | Gratis | Drag & drop folder → site live în 1 min |
| **Vercel**    | Gratis | Similar cu Netlify                       |
| **GitHub Pages** | Gratis | Necesită cont GitHub                  |

> Pentru domeniu `.ro` → înregistrează pe [rotld.ro](https://rotld.ro) sau orice registrar (ex: GoDaddy, Namecheap)

## Culori brand

| Culoare   | Hex       | Folosită pentru         |
|-----------|-----------|-------------------------|
| Amber     | `#F5A623` | Accente, butoane, logo  |
| Dark      | `#111827` | Fundal hero, texte      |
| Light Gray| `#F9FAFB` | Fundal secțiuni alt     |

## Font

**Inter** – încărcat de pe Google Fonts (necesită internet). Pentru versiunea offline, descarcă de pe [rsms.me/inter](https://rsms.me/inter/).
