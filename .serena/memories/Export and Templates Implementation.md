# Export & Template System

## Completed

### Templates (resources/templates/phasen/)
- `p3-datenbankmatrix.md` — Matrix with DB details, filters
- `p5-screening.md` — L1/L2 criteria, tool config, PRISMA schema
- `p7-synthese.md` — Synthesis method selection (Meta-Analysis, Thematic, etc.)
- `p8-suchprotokoll.md` — Auto-generated from P1-P7 data (NEW)

### Export Services
1. **ProjectExportService** (`app/Services/ProjectExportService.php`)
   - `generateMarkdown(Projekt)` — Full MD export with all phases
   - `generateLaTeX(Projekt, markdown?)` — LaTeX conversion
   - Supports P1-P8 sections with auto-fetched data

2. **ExportProjectAction** (`app/Actions/ExportProjectAction.php`)
   - `asMarkdown(Projekt)` → HTTP download response
   - `asLaTeX(Projekt)` → HTTP download response
   - `getMarkdown()`, `getLaTeX()` → String return (for display)

## Usage

### In Controller/Route
```php
route('projekt.export.markdown', $projekt->id)
route('projekt.export.latex', $projekt->id)
```

### In Livewire Component
```php
dispatch(new ExportProjectAction)->asMarkdown($projekt);
```

## Integration Points
- Add export buttons to project detail view
- Create routes in web.php:
  - `GET /projects/{id}/export/md`
  - `GET /projects/{id}/export/tex`
- Route handler calls ExportProjectAction

## File Structure
- P1: Component counts
- P2: Cluster + Mapping table
- P3: Database matrix table
- P4: Suchstrings with descriptions
- P5: Hit summary (not full list)
- P6/P7: Counts + summary
- P8: Phase summary table

## Next: Mayring Snippet-Highlighting
- P7MayringSnippet Model
- Text selection component
- Auto source reference extraction
