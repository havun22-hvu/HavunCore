# Pattern: Website Builder (Pagina Builder)

> Drag-and-drop website builder - een eigen WordPress-alternatief.

## Wanneer gebruiken

- Gebruikers zelf pagina's laten bouwen zonder code
- Event/toernooi websites
- Landing pages
- Simpele content websites

## Features

| Feature | Beschrijving |
|---------|--------------|
| **Drag & Drop** | Secties en blokken slepen |
| **Rich Text** | Trix editor voor tekst |
| **Responsive Preview** | Desktop/tablet/mobile preview |
| **Theme Kleuren** | Kleur picker voor branding |
| **Auto-save** | Automatisch opslaan tijdens bewerken |
| **Templates** | Voorgebouwde sectie templates |
| **Afbeelding Upload** | Direct uploaden in builder |

## Architectuur

```
┌─────────────────────────────────────────────────────────┐
│                    Pagina Builder                        │
├──────────────┬──────────────────────┬──────────────────┤
│   Sidebar    │       Canvas         │   Settings       │
│  - Blokken   │   - Secties          │  - Kleuren       │
│  - Secties   │   - Drag & Drop      │  - Padding       │
│  - Templates │   - Live preview     │  - Achtergrond   │
└──────────────┴──────────────────────┴──────────────────┘
```

## Data Structuur

```json
{
  "sections": [
    {
      "id": "section-abc123",
      "layout": "full",
      "columns": [
        {
          "blocks": [
            {
              "id": "block-xyz789",
              "type": "hero",
              "data": {
                "title": "Welkom",
                "subtitle": "Bij ons toernooi",
                "bgImage": "path/to/image.jpg",
                "buttons": [
                  {"text": "Inschrijven", "url": "/register", "style": "primary"}
                ]
              }
            }
          ]
        }
      ],
      "settings": {
        "bgColor": "#ffffff",
        "padding": "py-12 px-6",
        "textColor": "#1f2937"
      }
    }
  ]
}
```

## Blok Types

### Basis
| Type | Beschrijving |
|------|--------------|
| `heading` | Koppen (H1-H6) |
| `text` | Rich text met Trix editor |
| `image` | Enkele afbeelding met caption |
| `button` | Call-to-action knoppen |
| `spacer` | Verticale ruimte |
| `divider` | Horizontale lijn |

### Media
| Type | Beschrijving |
|------|--------------|
| `hero` | Hero sectie met achtergrond |
| `gallery` | Afbeelding galerij |
| `video` | YouTube/Vimeo embed |
| `carousel` | Afbeelding slider |

### Layout
| Type | Beschrijving |
|------|--------------|
| `columns` | 2/3/4 kolommen |
| `grid` | Grid layout |
| `accordion` | Uitklapbare items |
| `tabs` | Tab navigatie |

### Specials
| Type | Beschrijving |
|------|--------------|
| `sponsors` | Sponsor logo's grid |
| `info_card` | Info kaart met icoon |
| `countdown` | Aftellen naar datum |
| `map` | Google Maps embed |
| `form` | Contact formulier |

## Implementatie

### 1. Database (Migration)

```php
Schema::table('toernooien', function (Blueprint $table) {
    $table->json('pagina_content')->nullable();
    $table->string('thema_kleur', 7)->default('#2563eb');
});
```

### 2. Model

```php
class Toernooi extends Model
{
    protected $casts = [
        'pagina_content' => 'array',
    ];
}
```

### 3. Controller

```php
class PaginaBuilderController extends Controller
{
    public function index(Toernooi $toernooi): View
    {
        $sections = $toernooi->pagina_content['sections'] ?? [];

        return view('pagina-builder', [
            'toernooi' => $toernooi,
            'sections' => $sections,
        ]);
    }

    public function opslaan(Request $request, Toernooi $toernooi): JsonResponse
    {
        $toernooi->update([
            'pagina_content' => [
                'sections' => $request->input('sections'),
            ],
            'thema_kleur' => $request->input('themeColor'),
        ]);

        return response()->json(['success' => true]);
    }

    public function upload(Request $request, Toernooi $toernooi): JsonResponse
    {
        $request->validate(['afbeelding' => 'required|image|max:5120']);

        $path = $request->file('afbeelding')
            ->store("pagina-afbeeldingen/{$toernooi->id}", 'public');

        return response()->json([
            'success' => true,
            'url' => asset('storage/' . $path),
        ]);
    }
}
```

### 4. Frontend (Alpine.js + SortableJS)

```javascript
function paginaBuilder() {
    return {
        sections: @json($sections),
        saving: false,
        saved: false,
        themeColor: '{{ $toernooi->thema_kleur }}',
        previewMode: 'desktop',
        selectedSection: null,

        init() {
            // Initialize SortableJS for drag & drop
            new Sortable(document.getElementById('sections-container'), {
                animation: 150,
                handle: '.drag-handle',
                onEnd: () => this.saveSections(),
            });
        },

        addSection(layout = 'full') {
            this.sections.push({
                id: 'section-' + Date.now(),
                layout: layout,
                columns: [{ blocks: [] }],
                settings: {
                    bgColor: '#ffffff',
                    padding: 'py-12 px-6',
                    textColor: '#1f2937',
                },
            });
            this.saveSections();
        },

        addBlock(type, sectionId, columnIndex = 0) {
            const section = this.sections.find(s => s.id === sectionId);
            if (!section) return;

            section.columns[columnIndex].blocks.push({
                id: 'block-' + Date.now(),
                type: type,
                data: this.getDefaultData(type),
            });
            this.saveSections();
        },

        getDefaultData(type) {
            const defaults = {
                heading: { text: 'Nieuwe kop', level: 'h2' },
                text: { html: '<p>Voer tekst in...</p>' },
                image: { src: null, alt: '', caption: '' },
                hero: { title: '', subtitle: '', bgImage: null, buttons: [] },
                button: { text: 'Klik hier', url: '#', style: 'primary' },
            };
            return defaults[type] || {};
        },

        async saveSections() {
            this.saving = true;
            this.saved = false;

            await fetch('{{ route("pagina-builder.opslaan", $toernooi) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    sections: this.sections,
                    themeColor: this.themeColor,
                }),
            });

            this.saving = false;
            this.saved = true;
            setTimeout(() => this.saved = false, 2000);
        },

        async uploadImage(event, block, field) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('afbeelding', file);

            const response = await fetch('{{ route("pagina-builder.upload", $toernooi) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: formData,
            });

            const data = await response.json();
            block.data[field] = data.path;
            this.saveSections();
        },
    };
}
```

### 5. Routes

```php
Route::prefix('toernooi/{toernooi}/pagina-builder')->group(function () {
    Route::get('/', [PaginaBuilderController::class, 'index'])
        ->name('pagina-builder.index');
    Route::post('/opslaan', [PaginaBuilderController::class, 'opslaan'])
        ->name('pagina-builder.opslaan');
    Route::post('/upload', [PaginaBuilderController::class, 'upload'])
        ->name('pagina-builder.upload');
});
```

## Dependencies

```html
<!-- Trix Editor (rich text) -->
<link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>

<!-- SortableJS (drag & drop) -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<!-- Alpine.js (reactivity) -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

## Responsive Preview

```css
.preview-desktop { max-width: 100%; }
.preview-tablet { max-width: 768px; margin: 0 auto; }
.preview-mobile { max-width: 375px; margin: 0 auto; }
```

## Best Practices

1. **Auto-save met debounce** - Niet bij elke keystroke
2. **Optimistic UI** - Direct feedback, save op achtergrond
3. **Undo/Redo** - History stack bijhouden
4. **Keyboard shortcuts** - Ctrl+S, Delete, etc.
5. **Mobile-first** - Blokken responsive maken
6. **Image optimization** - Resize bij upload

## Roadmap / Verbeteringen

- [ ] Undo/Redo functionaliteit
- [ ] Copy/paste blokken
- [ ] Global styles (fonts, kleuren)
- [ ] SEO instellingen per pagina
- [ ] Multi-page support
- [ ] Custom CSS per blok
- [ ] Export als HTML
- [ ] A/B testing

## Projecten die dit gebruiken

- Judotoernooi (event pagina's)

---

*Pattern toegevoegd: 2025-12-30*
