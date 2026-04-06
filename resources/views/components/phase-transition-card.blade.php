@props([
    'project',
    'fromPhase',
    'toPhase',
])

<?php
$validator = app(\App\Services\TransitionValidator::class);
$status = $validator->getTransitionStatus($project, $fromPhase, $toPhase);
?>

<div class="phase-transition-card">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lora:wght@400;500&display=swap');

        .phase-transition-card {
            --navy: #1a2340;
            --gold: #d4a574;
            --cream: #f5f3f0;
            --success: #2d5016;
            --warning: #8b6f47;
            --danger: #8b2e2e;
            --border-gold: rgba(212, 165, 116, 0.3);
        }

        .ptc-container {
            background: linear-gradient(135deg, rgba(26, 35, 64, 0.03) 0%, rgba(212, 165, 116, 0.02) 100%);
            border: 1px solid var(--border-gold);
            border-radius: 2px;
            padding: 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .ptc-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 165, 116, 0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .ptc-content {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .ptc-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .ptc-phase-label {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy);
            letter-spacing: 1px;
        }

        .ptc-arrow {
            color: var(--gold);
            font-size: 1.5rem;
            opacity: 0.6;
        }

        .ptc-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Lora', serif;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .ptc-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .ptc-status-ready {
            background: rgba(45, 80, 22, 0.15);
            color: var(--success);
            border: 1px solid rgba(45, 80, 22, 0.3);
        }

        .ptc-status-warning {
            background: rgba(139, 111, 71, 0.15);
            color: var(--warning);
            border: 1px solid rgba(139, 111, 71, 0.3);
        }

        .ptc-status-blocked {
            background: rgba(139, 46, 46, 0.15);
            color: var(--danger);
            border: 1px solid rgba(139, 46, 46, 0.3);
        }

        .ptc-status-icon {
            width: 1.1rem;
            height: 1.1rem;
            stroke-width: 2.5;
        }

        .ptc-warning-text {
            font-family: 'Lora', serif;
            font-size: 0.9rem;
            color: var(--navy);
            opacity: 0.75;
            margin-top: 0.75rem;
            padding-left: 1.75rem;
            border-left: 2px solid var(--gold);
        }

        .ptc-actions {
            display: flex;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .ptc-override-btn {
            font-family: 'Lora', serif;
            font-weight: 500;
            padding: 0.625rem 1.25rem;
            border: 1px solid var(--navy);
            background: white;
            color: var(--navy);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 1px;
            letter-spacing: 0.3px;
        }

        .ptc-override-btn:hover {
            background: var(--navy);
            color: white;
        }

        .ptc-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-gold);
        }

        .ptc-stat-item {
            text-align: center;
        }

        .ptc-stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--navy);
            display: block;
        }

        .ptc-stat-label {
            font-family: 'Lora', serif;
            font-size: 0.8rem;
            color: var(--navy);
            opacity: 0.6;
            margin-top: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>

    <div class="ptc-container">
        <div class="ptc-content">
            <div class="ptc-header">
                <span class="ptc-phase-label">Phase {{ $fromPhase }} → {{ $toPhase }}</span>
                <span class="ptc-arrow">→</span>
            </div>

            <div class="ptc-status">
                @if ($status->isReady)
                    <span class="ptc-status-badge ptc-status-ready">
                        <svg class="ptc-status-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Bereit
                    </span>
                @elseif ($status->isBlocking)
                    <span class="ptc-status-badge ptc-status-blocked">
                        <svg class="ptc-status-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        Blockiert
                    </span>
                @else
                    <span class="ptc-status-badge ptc-status-warning">
                        <svg class="ptc-status-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Warnung
                    </span>
                @endif
            </div>

            @if ($status->warningMessage)
                <div class="ptc-warning-text">
                    {{ $status->warningMessage }}
                </div>
            @endif

            @if (!$status->isBlocking && !$status->isReady && $status->warningMessage)
                <div class="ptc-actions">
                    <button class="ptc-override-btn" onclick="window.location.href='#'">
                        Trotzdem fortfahren →
                    </button>
                </div>
            @endif

            @if (!empty($status->counts))
                <div class="ptc-stats">
                    @foreach ($status->counts as $key => $value)
                        @if (is_int($value) && $value > 0)
                            <div class="ptc-stat-item">
                                <span class="ptc-stat-value">{{ $value }}</span>
                                <span class="ptc-stat-label">{{ str_replace('_', ' ', $key) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
