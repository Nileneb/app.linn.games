@props([
    'projekt',
    'variant' => 'default', // default | compact | floating
])

<div class="export-button-group export-{{ $variant }}">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lora:wght@400;500&display=swap');

        .export-button-group {
            --navy: #1a2340;
            --gold: #d4a574;
            --cream: #f5f3f0;
            --text-light: #e8e4df;
        }

        .export-default {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(26, 35, 64, 0.02) 0%, rgba(212, 165, 116, 0.02) 100%);
            border: 1px solid rgba(212, 165, 116, 0.2);
            border-radius: 2px;
            backdrop-filter: blur(10px);
        }

        .export-compact {
            display: flex;
            gap: 0.75rem;
        }

        .export-floating {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            display: flex;
            gap: 0.75rem;
            z-index: 40;
            filter: drop-shadow(0 10px 25px rgba(26, 35, 64, 0.2));
        }

        .export-btn {
            font-family: 'Lora', serif;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.875rem 1.75rem;
            border: 2px solid var(--navy);
            background: white;
            color: var(--navy);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-radius: 1px;
        }

        .export-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--navy);
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: -1;
        }

        .export-btn:hover {
            border-color: var(--navy);
            color: white;
        }

        .export-btn:hover::before {
            left: 0;
        }

        .export-btn svg {
            width: 1.1rem;
            height: 1.1rem;
            stroke-width: 2.5;
        }

        .export-compact .export-btn {
            padding: 0.65rem 1.2rem;
            font-size: 0.85rem;
        }

        .export-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .export-btn:disabled:hover::before {
            left: -100%;
        }
    </style>

    <!-- Markdown Export -->
    <a
        href="{{ route('recherche.export.markdown', $projekt->id) }}"
        class="export-btn"
        title="Exportiere Projekt als Markdown"
        download
    >
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.386c.956 0 1.734.564 2.15 1.397M12 7.5a3 3 0 0 0-5.68.5m9.126 8.75c.747.645 1.318 1.555 1.318 2.75 0 2.485-2.686 4.5-6 4.5s-6-2.015-6-4.5c0-1.195.571-2.105 1.318-2.75m5.364-5.141a6 6 0 0 0-3.273.991m0 0a6 6 0 0 0 4.886 8.888" />
        </svg>
        Markdown
    </a>

    <!-- LaTeX Export -->
    <a
        href="{{ route('recherche.export.latex', $projekt->id) }}"
        class="export-btn"
        title="Exportiere Projekt als LaTeX"
        download
    >
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3-8.75H18a2.25 2.25 0 0 1 2.25 2.25V15a2.25 2.25 0 0 1-2.25 2.25H9.75A2.25 2.25 0 0 1 7.5 15V9.75A2.25 2.25 0 0 1 9.75 7.5" />
        </svg>
        LaTeX
    </a>

    <!-- Mayring Export -->
    <a
        href="{{ route('recherche.export.mayring', $projekt->id) }}"
        class="export-btn"
        title="Exportiere Mayring-Snippets"
        download
    >
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 0 0 2-2V9.414a1 1 0 0 0-.293-.707l-5.414-5.414A1 1 0 0 0 13.586 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z" />
        </svg>
        Snippets
    </a>
</div>
