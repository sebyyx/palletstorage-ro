# Imagini necesare pentru PalletStorage.ro

Adaugă fotografiile în acest folder (`assets/images/`) și actualizează fișierul `index.html`.

## Lista fotografiilor recomandate

| Fișier               | Descriere                                      | Unde se folosește                    |
|----------------------|------------------------------------------------|--------------------------------------|
| `hero.jpg`           | Fotografie impact – depozit, paleți stivuiți   | Fundalul secțiunii principale (hero) |
| `depozit.jpg`        | Interior depozit cu paleți organizați          | Secțiunea "Despre noi"               |
| `exterior.jpg`       | Exterior locație, acces camioane               | Secțiunea "Locație" sau galerie      |
| `rampa.jpg`          | Rampă de descărcare, TIR accesat               | Secțiunea "Servicii"                 |
| `favicon.ico`        | Iconiță site (32x32 px)                        | Tab browser                          |
| `og-image.jpg`       | Imagine pentru share social media (1200x630px) | Meta tags Open Graph                 |

## Cum înlocuiești placeholder-urile

### Hero (fundal principal)
În `styles.css`, găsește:
```css
.hero__bg-placeholder {
  background: linear-gradient(...);
```
Înlocuiește cu:
```css
.hero__bg-placeholder {
  background: url('assets/images/hero.jpg') center/cover no-repeat;
```

### Fotografie depozit (secțiunea Despre noi)
În `index.html`, găsește:
```html
<div class="image-placeholder">FOTO DEPOZIT</div>
```
Înlocuiește cu:
```html
<img src="assets/images/depozit.jpg" alt="Depozit paleți PalletStorage București" />
```

### Harta Google Maps
1. Mergi pe [maps.google.com](https://maps.google.com)
2. Caută adresa exactă
3. Click Share → Embed a map → copiază iframe-ul
4. În `index.html`, înlocuiește `<div class="map-placeholder">...</div>` cu iframe-ul copiat

## Specificații tehnice recomandate
- Format: JPG (pentru fotografii), PNG (pentru logo/iconiță)
- Hero: minim 1920×1080px, comprimat sub 500KB
- Restul: minim 800×600px
- Compresie: folosește [squoosh.app](https://squoosh.app) sau [tinypng.com](https://tinypng.com)
