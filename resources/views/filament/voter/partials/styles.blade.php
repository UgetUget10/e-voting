@vite('resources/css/filament/shared/theme-shared.css')
<style>
    /* Voter panel: content runs edge to edge. The page title keeps a small inset. */
    .fi-main {
        padding-inline: 0;
    }

    .fi-main .fi-header {
        padding-inline: 1.25rem;
    }

    /* Hero: gradient banner with a ballot ornament and a faint tick pattern. */
    .voter-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.5rem;
        padding: 2rem 2.25rem;
        color: #fff;
        background:
            radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.14) 1px, transparent 0) 0 0 / 22px 22px,
            linear-gradient(135deg, #c2410c 0%, #f97316 100%);
        box-shadow: 0 24px 48px -28px rgba(194, 65, 12, 0.7);
    }

    /* Keep text above the decorative ornament so it never paints over the copy. */
    .voter-hero > :not(.voter-ornament):not(.voter-sparkles) {
        position: relative;
    }

    .voter-hero h2 {
        margin-top: 0.75rem;
        max-width: 26rem;
        color: #fff;
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.02em;
    }

    .voter-hero p {
        margin-top: 0.5rem;
        max-width: 30rem;
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.925rem;
        line-height: 1.55;
    }

    .voter-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 9999px;
        padding: 0.3rem 0.8rem;
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(4px);
    }

    .voter-pill-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 9999px;
        background: #4ade80;
        box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.25);
    }

    .voter-progress {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        margin-top: 1.25rem;
        max-width: 26rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
    }

    .voter-progress-track {
        flex: 1 1 8rem;
        height: 0.4rem;
        overflow: hidden;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.25);
    }

    .voter-progress-fill {
        height: 100%;
        border-radius: 9999px;
        background: #fff;
        transition: width 400ms cubic-bezier(0.22, 1, 0.36, 1);
    }

    /* Candidate picker: photo cards in two columns on phones, three on desktop. */
    .voter-picker {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    @media (min-width: 640px) {
        .voter-picker {
            gap: 1.25rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (min-width: 1280px) {
        .voter-picker {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .voter-candidate {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        width: 100%;
        border: 0;
        background: transparent;
        padding: 0;
        text-align: center;
        cursor: pointer;
        transition: transform 180ms ease;
    }

    .voter-candidate:hover:not(:disabled) {
        transform: translateY(-3px);
    }

    .voter-candidate:focus-visible {
        outline: 3px solid #fdba74;
        outline-offset: 4px;
        border-radius: 1.25rem;
    }

    /* Fade the photo and labels only, so the blocked badge inside the photo stays fully visible. */
    .voter-candidate:disabled {
        cursor: not-allowed;
    }

    .voter-candidate:disabled > .voter-candidate-media > :not(.voter-candidate-stamp),
    .voter-candidate:disabled > .voter-candidate-name,
    .voter-candidate:disabled > .voter-candidate-rank {
        opacity: 0.45;
    }

    .voter-candidate-media {
        position: relative;
        display: block;
        aspect-ratio: 4 / 5;
        overflow: hidden;
        border-radius: 1.25rem;
        background: linear-gradient(160deg, #fed7aa 0%, #fdba74 55%, #fb923c 100%);
        box-shadow: 0 16px 30px -22px rgba(124, 45, 18, 0.8);
        transition: box-shadow 180ms ease, transform 150ms ease;
    }

    .voter-candidate:hover:not(:disabled) .voter-candidate-media {
        box-shadow: 0 22px 36px -22px rgba(124, 45, 18, 0.9);
    }

    .voter-candidate-photo {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .voter-candidate-name {
        display: block;
        color: #111827;
        font-size: 0.975rem;
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: -0.01em;
    }

    .dark .voter-candidate-name {
        color: #f9fafb;
    }


    .dark .voter-candidate-media {
        box-shadow: 0 16px 30px -22px rgba(0, 0, 0, 0.9);
    }

    @media (prefers-reduced-motion: reduce) {
        .voter-candidate,
        .voter-candidate-media,
        .voter-progress-fill,
        .voter-bar {
            transition: none;
        }

        .voter-candidate:hover:not(:disabled) {
            transform: none;
        }
    }

    .voter-candidate-initial {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 22%;
        color: rgba(255, 255, 255, 0.35);
        font-size: 6rem;
        font-weight: 800;
    }

    .voter-candidate-check {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border: 2px solid rgba(255, 255, 255, 0.9);
        border-radius: 9999px;
        background: rgba(67, 20, 7, 0.25);
        color: #fff;
    }

    .voter-candidate-check svg {
        width: 1rem;
        height: 1rem;
    }

    .voter-candidate.is-selected .voter-candidate-media {
        box-shadow: 0 0 0 4px #fff, 0 0 0 8px #ea580c, 0 22px 36px -20px rgba(234, 88, 12, 0.9);
    }

    .voter-candidate.is-selected .voter-candidate-check {
        border-color: #fff;
        background: #ea580c;
    }


    .voter-stepper {
        border-radius: 0;
        border-inline: 0;
    }

    .voter-stepper .fi-sc-wizard-step {
        padding-inline: 1rem;
    }

    .voter-stepper .fi-sc-wizard-header-step-number {
        border-radius: 9999px;
        font-weight: 600;
    }

    .voter-stepper .fi-sc-wizard-header-step-btn[aria-current='step'] .fi-sc-wizard-header-step-number {
        box-shadow: 0 0 0 4px #fed7aa;
    }

    .voter-bar {
        transition: width 600ms cubic-bezier(0.22, 1, 0.36, 1);
    }

    .voter-winner {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.1rem 0.6rem;
        font-size: 0.7rem;
        font-weight: 700;
        color: #166534;
        background: #dcfce7;
    }
    /* Submit confirmation: native <dialog> styled as a card, opened from the wizard's submit button. */
    /* Tailwind's preflight zeroes margins, which cancels the browser's centring of <dialog>. */
    .voter-dialog {
        position: fixed;
        inset: 0;
        margin: auto;
        height: fit-content;
        width: min(24rem, calc(100% - 2rem));
        max-width: none;
        border: 0;
        border-radius: 1.5rem;
        padding: 0;
        background: #fff;
        color: #111827;
        box-shadow: 0 40px 80px -30px rgba(124, 45, 18, 0.6);
    }

    .voter-dialog[open] {
        animation: voter-dialog-in 260ms cubic-bezier(0.22, 1, 0.36, 1);
    }

    .voter-dialog::backdrop {
        background: rgba(67, 20, 7, 0.55);
        backdrop-filter: blur(4px);
    }

    @keyframes voter-dialog-in {
        from {
            opacity: 0;
            transform: translateY(12px) scale(0.98);
        }
    }

    .voter-dialog-body {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 2rem 1.75rem 1.5rem;
        text-align: center;
    }

    .voter-dialog-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 9999px;
        color: #fff;
        background: linear-gradient(135deg, #c2410c 0%, #f97316 100%);
        box-shadow: 0 12px 24px -12px rgba(194, 65, 12, 0.8);
    }

    .voter-dialog-icon svg {
        width: 1.6rem;
        height: 1.6rem;
    }

    .voter-dialog-title {
        margin-top: 1rem;
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .voter-dialog-text {
        margin-top: 0.5rem;
        color: #6b7280;
        font-size: 0.9rem;
        line-height: 1.55;
    }

    .voter-dialog-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        width: 100%;
        margin-top: 1.5rem;
    }

    .voter-dialog-actions button {
        border-radius: 9999px;
        padding: 0.7rem 1rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 150ms ease, transform 150ms ease;
    }

    .voter-dialog-actions button:active {
        transform: scale(0.98);
    }

    .voter-dialog-cancel {
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #374151;
    }

    .voter-dialog-cancel:hover {
        background: #f9fafb;
    }

    .voter-dialog-confirm {
        border: 0;
        color: #fff;
        background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
        box-shadow: 0 12px 24px -14px rgba(234, 88, 12, 0.9);
    }

    .voter-dialog-confirm:hover {
        filter: brightness(1.06);
    }

    .voter-dialog-actions button:focus-visible {
        outline: 3px solid #fdba74;
        outline-offset: 3px;
    }

    .dark .voter-dialog {
        background: #1f2937;
        color: #f9fafb;
    }

    .dark .voter-dialog-text {
        color: #9ca3af;
    }

    .dark .voter-dialog-cancel {
        border-color: #374151;
        background: #111827;
        color: #e5e7eb;
    }

    .dark .voter-dialog-cancel:hover {
        background: #1f2937;
    }

    @media (prefers-reduced-motion: reduce) {
        .voter-dialog[open],
        .voter-dialog-actions button {
            animation: none;
            transition: none;
        }
    }
    /* Results page: live indicator, per-field rankings, and the locked state before results open. */
    .voter-results {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .voter-hero-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem 1rem;
    }

    .voter-live {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: rgba(255, 255, 255, 0.85);
    }

    .voter-live.is-final svg {
        width: 0.9rem;
        height: 0.9rem;
        color: #4ade80;
    }

    .voter-live-dot {
        position: relative;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 9999px;
        background: #4ade80;
    }

    .voter-live-dot::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 9999px;
        background: #4ade80;
        animation: voter-live-ping 1.6s cubic-bezier(0, 0, 0.2, 1) infinite;
    }

    @keyframes voter-live-ping {
        75%,
        100% {
            transform: scale(2.4);
            opacity: 0;
        }
    }

    .voter-results-grid {
        display: grid;
        gap: 1.25rem;
        grid-template-columns: minmax(0, 1fr);
    }

    @media (min-width: 900px) {
        .voter-results-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .voter-field {
        border: 1px solid #e5e7eb;
        border-radius: 1.25rem;
        padding: 1.25rem;
        background: #fff;
        box-shadow: 0 18px 36px -30px rgba(124, 45, 18, 0.5);
    }

    .voter-field-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.25rem 1rem;
        padding-bottom: 0.875rem;
        margin-bottom: 0.25rem;
        border-bottom: 2px dashed #ffedd5;
    }

    .voter-field-head h3 {
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: #111827;
    }

    .voter-field-meta {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
    }

    .voter-ranking {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .voter-rank {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 0.5rem;
        border-radius: 0.875rem;
        transition: background-color 200ms ease;
    }

    .voter-rank.is-advancing {
        background: #fff7ed;
    }

    .voter-rank-number {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #6b7280;
        background: #f3f4f6;
    }

    .voter-rank.is-advancing .voter-rank-number {
        color: #fff;
        background: #ea580c;
    }

    .voter-rank-avatar {
        width: 2.5rem;
        height: 2.5rem;
        flex-shrink: 0;
        border-radius: 9999px;
        object-fit: cover;
    }

    .voter-rank-avatar.is-initial {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        font-weight: 700;
        color: #c2410c;
        background: #ffedd5;
    }

    .voter-rank-body {
        flex: 1;
        min-width: 0;
    }

    .voter-rank-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.25rem 0.75rem;
        font-size: 0.875rem;
    }

    .voter-rank-name {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem;
        font-weight: 600;
        color: #111827;
    }

    .voter-rank-votes {
        font-size: 0.8rem;
        font-variant-numeric: tabular-nums;
        color: #6b7280;
    }

    .voter-rank-votes strong {
        color: #111827;
        font-size: 0.9rem;
    }

    .voter-rank-track {
        margin-top: 0.5rem;
        height: 0.5rem;
        overflow: hidden;
        border-radius: 9999px;
        background: #f3f4f6;
    }

    .voter-rank-fill {
        height: 100%;
        border-radius: 9999px;
        background: #d1d5db;
    }

    .voter-rank.is-advancing .voter-rank-fill {
        background: linear-gradient(90deg, #ea580c 0%, #f97316 100%);
    }

    .voter-locked {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 2.5rem 1.5rem;
        border: 2px dashed #fed7aa;
        border-radius: 1.25rem;
        text-align: center;
        background: #fffaf5;
    }

    .voter-locked-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.25rem;
        height: 3.25rem;
        border-radius: 9999px;
        color: #ea580c;
        background: #ffedd5;
    }

    .voter-locked-icon svg {
        width: 1.4rem;
        height: 1.4rem;
    }

    .voter-locked-title {
        margin-top: 1rem;
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
    }

    .voter-locked-text {
        margin-top: 0.4rem;
        font-size: 0.875rem;
        color: #6b7280;
    }

    .dark .voter-field {
        border-color: #374151;
        background: #1f2937;
    }

    .dark .voter-field-head {
        border-bottom-color: #c2410c;
    }

    .dark .voter-field-head h3,
    .dark .voter-rank-name,
    .dark .voter-rank-votes strong,
    .dark .voter-locked-title {
        color: #f9fafb;
    }

    .dark .voter-field-meta,
    .dark .voter-rank-votes,
    .dark .voter-locked-text {
        color: #9ca3af;
    }

    .dark .voter-rank.is-advancing {
        background: rgba(234, 88, 12, 0.18);
    }

    .dark .voter-rank-number {
        color: #d1d5db;
        background: #374151;
    }

    .dark .voter-rank-avatar.is-initial {
        color: #fed7aa;
        background: rgba(249, 115, 22, 0.25);
    }

    .dark .voter-rank-track {
        background: #374151;
    }

    .dark .voter-rank-fill {
        background: #4b5563;
    }

    .dark .voter-locked {
        border-color: #c2410c;
        background: rgba(194, 65, 12, 0.08);
    }

    .dark .voter-locked-icon {
        color: #fed7aa;
        background: rgba(249, 115, 22, 0.25);
    }

    @media (prefers-reduced-motion: reduce) {
        .voter-live-dot::after {
            animation: none;
        }

        .voter-rank {
            transition: none;
        }
    }
    /* Decorative ornaments: sparkles in the hero, ink-ring and orbit accents, ballot stamp and confetti. */
    .voter-sparkles {
        display: none;
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
    }

    @media (min-width: 1024px) {
        .voter-sparkles {
            display: block;
        }
    }

    .voter-sparkle {
        position: absolute;
        color: rgba(255, 255, 255, 0.9);
        animation: voter-twinkle 3.2s ease-in-out infinite;
    }

    /* Clustered above-left of the ballot box ornament, clear of the live indicator in the top-right. */
    .voter-sparkle.is-large {
        top: 3.5rem;
        right: 15.5rem;
        width: 1.25rem;
        height: 1.25rem;
    }

    .voter-sparkle.is-medium {
        top: 6rem;
        right: 17.5rem;
        width: 0.8rem;
        height: 0.8rem;
        animation-delay: 1.1s;
    }

    .voter-sparkle.is-small {
        top: 2.75rem;
        right: 18.5rem;
        width: 0.6rem;
        height: 0.6rem;
        animation-delay: 2s;
    }

    @keyframes voter-twinkle {
        0%,
        100% {
            opacity: 0.35;
            transform: scale(0.8) rotate(0deg);
        }

        50% {
            opacity: 1;
            transform: scale(1.1) rotate(25deg);
        }
    }

    /* Ballot stamp on the selected candidate, like a mark pressed onto the paper. */
    .voter-candidate-stamp {
        position: absolute;
        top: 50%;
        left: 50%;
        padding: 0.55rem 1.1rem;
        border: 3px solid #fff;
        border-radius: 0.6rem;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        white-space: nowrap;
        color: #fff;
        background: rgba(234, 88, 12, 0.92);
        box-shadow: 0 12px 26px -10px rgba(124, 45, 18, 0.9);
        transform: translate(-50%, -50%) rotate(-6deg);
        animation: voter-stamp-in 280ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    /* Blocked candidate: the same badge as the pick, in slate, sized to wrap inside the card. */
    .voter-candidate-stamp.is-blocked {
        width: max-content;
        max-width: 85%;
        padding: 0.45rem 0.8rem;
        border-radius: 0.6rem;
        font-size: 0.78rem;
        letter-spacing: 0.08em;
        line-height: 1.35;
        white-space: normal;
        text-align: center;
        background: rgba(68, 64, 60, 0.92);
        box-shadow: 0 12px 26px -10px rgba(28, 25, 23, 0.9);
    }

    @keyframes voter-stamp-in {
        from {
            opacity: 0;
            transform: translate(-50%, -50%) rotate(-6deg) scale(1.6);
        }

        to {
            opacity: 1;
            transform: translate(-50%, -50%) rotate(-6deg) scale(1);
        }
    }

    /* Dotted ring around the confirmation icon, like a fingertip ink mark. */
    .voter-dialog-icon {
        position: relative;
    }

    .voter-dialog-ring {
        position: absolute;
        inset: -0.6rem;
        border: 2px dotted #fdba74;
        border-radius: 9999px;
        animation: voter-orbit 14s linear infinite;
    }

    .voter-locked-icon {
        position: relative;
    }

    .voter-locked-orbit {
        position: absolute;
        inset: -0.5rem;
        border: 2px dashed #fdba74;
        border-radius: 9999px;
        animation: voter-orbit 18s linear infinite;
    }

    @keyframes voter-orbit {
        to {
            transform: rotate(360deg);
        }
    }

    .voter-rank {
        position: relative;
    }

    /* Sits on the top-left corner of the rank badge, clear of the vote figures. */
    .voter-confetti {
        position: absolute;
        top: -0.35rem;
        left: 0.35rem;
        width: 2.25rem;
        height: 1.5rem;
        pointer-events: none;
    }

    .voter-confetti i {
        position: absolute;
        width: 0.4rem;
        height: 0.4rem;
        border-radius: 2px;
    }

    .voter-confetti i:nth-child(1) {
        left: 0;
        top: 0.6rem;
        background: #f59e0b;
    }

    .voter-confetti i:nth-child(2) {
        left: 1rem;
        top: 0;
        width: 0.3rem;
        height: 0.7rem;
        background: #ec4899;
        transform: rotate(30deg);
    }

    .voter-confetti i:nth-child(3) {
        left: 2rem;
        top: 0.9rem;
        border-radius: 9999px;
        background: #22c55e;
    }

    .voter-confetti i:nth-child(4) {
        left: 1.5rem;
        top: 1.4rem;
        background: #38bdf8;
        transform: rotate(-20deg);
    }

    @media (prefers-reduced-motion: reduce) {
        .voter-sparkle,
        .voter-dialog-ring,
        .voter-locked-orbit,
        .voter-candidate-stamp {
            animation: none;
        }
    }

    /* Accent strip along the top of each field card. */
    .voter-field {
        position: relative;
        overflow: hidden;
    }

    .voter-field::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, #ea580c 0%, #fb923c 50%, #f59e0b 100%);
    }
    /* Voter summary strip: registered, voted, and still-to-vote counts. */
    .voter-stats {
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .voter-stat {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        border: 1px solid #e5e7eb;
        border-radius: 1.1rem;
        padding: 0.9rem 1rem;
        background: #fff;
        box-shadow: 0 14px 28px -26px rgba(124, 45, 18, 0.6);
    }

    .voter-stat-label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .voter-stat-value {
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
        color: #111827;
        line-height: 1.1;
    }

    .voter-stat-meta {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
    }

    .voter-stat.is-voted .voter-stat-value {
        color: #ea580c;
    }

    .voter-stat.is-pending .voter-stat-value {
        color: #b45309;
    }

    /* Official recap: KPU-style tables, one per field. */
    .voter-recap {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 1.25rem;
        padding: 1.25rem;
        background: #fff;
        box-shadow: 0 18px 36px -30px rgba(124, 45, 18, 0.5);
    }

    .voter-recap-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1rem;
    }

    .voter-recap-head h3 {
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: #111827;
    }

    .voter-recap-head p {
        margin-top: 0.2rem;
        font-size: 0.8rem;
        color: #6b7280;
    }

    .voter-recap-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        font-variant-numeric: tabular-nums;
    }

    .voter-recap-table caption {
        padding: 0.5rem 0 0.6rem;
        text-align: left;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #c2410c;
    }

    .voter-recap-table th,
    .voter-recap-table td {
        padding: 0.55rem 0.6rem;
        border-bottom: 1px solid #f3f4f6;
        text-align: left;
        color: #374151;
    }

    .voter-recap-table thead th {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
        background: #f9fafb;
    }

    .voter-recap-table .is-num {
        text-align: right;
    }

    .voter-recap-table tbody tr.is-advancing td {
        color: #111827;
        font-weight: 600;
        background: #fff7ed;
    }

    .voter-recap-table tfoot th,
    .voter-recap-table tfoot td {
        font-weight: 700;
        color: #111827;
        border-bottom: 0;
    }

    .dark .voter-stat,
    .dark .voter-recap {
        border-color: #374151;
        background: #1f2937;
    }

    .dark .voter-stat-value,
    .dark .voter-recap-head h3,
    .dark .voter-recap-table tfoot th,
    .dark .voter-recap-table tfoot td {
        color: #f9fafb;
    }

    .dark .voter-stat-label,
    .dark .voter-stat-meta,
    .dark .voter-recap-head p,
    .dark .voter-recap-table thead th {
        color: #9ca3af;
    }

    .dark .voter-stat.is-voted .voter-stat-value {
        color: #fdba74;
    }

    .dark .voter-stat.is-pending .voter-stat-value {
        color: #fbbf24;
    }

    .dark .voter-recap-table caption {
        color: #fdba74;
    }

    .dark .voter-recap-table th,
    .dark .voter-recap-table td {
        border-bottom-color: #374151;
        color: #d1d5db;
    }

    .dark .voter-recap-table thead th {
        background: #111827;
    }

    .dark .voter-recap-table tbody tr.is-advancing td {
        color: #f9fafb;
        background: rgba(234, 88, 12, 0.18);
    }

    @media (max-width: 639px) {
        .voter-stats {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    /* Warning modal on Hasil, shown when a voter who already voted is redirected from the ballot. */
    .voter-dialog-icon.is-warning {
        color: #b45309;
        background: linear-gradient(135deg, #fde68a 0%, #fbbf24 100%);
        box-shadow: 0 12px 24px -12px rgba(217, 119, 6, 0.8);
    }

    .voter-dialog-confirm.is-warning {
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        box-shadow: 0 12px 24px -14px rgba(180, 83, 9, 0.9);
    }

    .voter-dialog-actions.is-single {
        grid-template-columns: minmax(0, 1fr);
    }

    .voter-alert-kicker {
        display: inline-block;
        margin-top: 0.75rem;
        border-radius: 9999px;
        padding: 0.15rem 0.6rem;
        font-size: 0.66rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #92400e;
        background: rgba(251, 191, 36, 0.35);
    }

    .dark .voter-alert-kicker {
        color: #fef3c7;
        background: rgba(251, 191, 36, 0.25);
    }

    .dark .voter-dialog-icon.is-warning {
        color: #fef3c7;
        background: linear-gradient(135deg, #b45309 0%, #92400e 100%);
    }
    /* Receipt: the code the voter keeps after voting, and the page that checks it. */
    .voter-receipt {
        display: grid;
        gap: 0.5rem;
        margin: 0 0 1.25rem;
        padding: 1rem 1.25rem;
        border: 1px solid #fed7aa;
        border-radius: 1rem;
        background: #fff7ed;
    }

    .voter-receipt.is-found {
        border-color: #bbf7d0;
        background: #f0fdf4;
    }

    .voter-receipt.is-missing {
        border-color: #fecaca;
        background: #fef2f2;
    }

    .voter-receipt-text {
        margin: 0;
        font-size: 0.9375rem;
        line-height: 1.5;
        color: #374151;
    }

    .voter-receipt-code {
        justify-self: start;
        padding: 0.4rem 0.75rem;
        border-radius: 0.5rem;
        background: #ffffff;
        border: 1px dashed #ea580c;
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        color: #9a3412;
    }

    .voter-receipt-link {
        justify-self: start;
        font-weight: 600;
        color: #c2410c;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .voter-receipt-button {
        padding: 0.8rem 1.6rem;
        border: 0;
        border-radius: 0.85rem;
        background: linear-gradient(135deg, #f97316, #c2410c);
        font-weight: 700;
        color: #ffffff;
        cursor: pointer;
        box-shadow: 0 10px 20px -12px rgba(194, 65, 12, 0.8);
        transition: filter 0.15s, transform 0.15s;
    }

    .voter-receipt-button:hover {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }

    .voter-receipt-button:disabled {
        opacity: 0.7;
        cursor: wait;
    }

    .voter-receipt-result {
        grid-template-columns: auto 1fr;
        align-items: start;
        gap: 1rem;
        margin: 0;
        padding: 1.25rem 1.5rem;
        animation: voter-receipt-pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .voter-receipt-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border-radius: 9999px;
        color: #ffffff;
    }

    .voter-receipt-badge.is-found {
        background: linear-gradient(135deg, #4ade80, #16a34a);
        box-shadow: 0 0 0 6px #dcfce7;
    }

    .voter-receipt-badge.is-missing {
        background: linear-gradient(135deg, #f87171, #dc2626);
        box-shadow: 0 0 0 6px #fee2e2;
    }

    .voter-receipt-heading {
        display: block;
        margin-bottom: 0.2rem;
        font-size: 1.05rem;
        color: #111827;
    }

    @keyframes voter-receipt-pop {
        from { opacity: 0; transform: translateY(8px) scale(0.98); }
    }

    .dark .voter-receipt {
        border-color: rgba(251, 146, 60, 0.35);
        background: rgba(124, 45, 18, 0.25);
    }

    .dark .voter-receipt.is-found {
        border-color: rgba(74, 222, 128, 0.35);
        background: rgba(20, 83, 45, 0.3);
    }

    .dark .voter-receipt.is-missing {
        border-color: rgba(248, 113, 113, 0.35);
        background: rgba(127, 29, 29, 0.3);
    }

    .dark .voter-receipt-text {
        color: #f3f4f6;
    }

    .dark .voter-receipt-heading {
        color: #f3f4f6;
    }

    .dark .voter-receipt-code {
        background: #1f2937;
        color: #fdba74;
    }

    /* Ballot menu: header, candidate cards, profile, countdown, field chips and the send animation. */
    .voter-picker-header {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.25rem 0.75rem;
        margin-bottom: 1rem;
    }

    .voter-picker-ordinal {
        color: #c2410c;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .voter-picker-hint {
        color: #6b7280;
        font-size: 0.8rem;
    }

    .voter-candidate-wrap {
        display: flex;
        flex-direction: column;
        justify-self: center;
        width: 100%;
        max-width: 13rem;
        gap: 0.5rem;
        animation: voter-card-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
        animation-delay: min(calc(var(--i, 0) * 60ms), 600ms);
    }

    .voter-candidate:active:not(:disabled) .voter-candidate-media {
        transform: scale(0.97);
    }

    .voter-candidate-rank {
        color: #9a3412;
        font-size: 0.72rem;
        font-weight: 600;
    }

    .voter-candidate-profile {
        align-self: center;
        border: 1px solid #fed7aa;
        border-radius: 9999px;
        background: #fff;
        color: #c2410c;
        padding: 0.3rem 0.8rem;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 150ms ease, transform 150ms ease;
    }

    .voter-candidate-profile:hover {
        background: #fff7ed;
    }

    .voter-candidate-profile:active {
        transform: scale(0.96);
    }

    .voter-candidate-profile:focus-visible {
        outline: 3px solid #fdba74;
        outline-offset: 2px;
    }

    .voter-check-path {
        stroke-dasharray: 1;
        stroke-dashoffset: 1;
        animation: voter-check-draw 360ms ease-out 120ms forwards;
    }

    .voter-stepper .fi-sc-wizard-step.fi-active {
        animation: voter-step-in 380ms cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .voter-hero {
        animation: voter-hero-drift 18s linear infinite;
    }

    .voter-countdown {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-top: 1rem;
        border-radius: 9999px;
        padding: 0.4rem 0.9rem;
        background: rgba(255, 255, 255, 0.16);
        font-size: 0.8rem;
        backdrop-filter: blur(4px);
    }

    .voter-countdown-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 9999px;
        background: #fde68a;
        animation: voter-countdown-ping 1.6s ease-out infinite;
    }

    .voter-countdown-label {
        opacity: 0.85;
        font-weight: 500;
    }

    .voter-countdown-value {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
    }

    .voter-field-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .voter-field-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        border: 1px solid rgba(255, 255, 255, 0.35);
        border-radius: 9999px;
        padding: 0.3rem 0.75rem 0.3rem 0.3rem;
        background: rgba(255, 255, 255, 0.14);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 180ms ease, color 180ms ease, transform 150ms ease;
    }

    .voter-field-chip:hover {
        background: rgba(255, 255, 255, 0.26);
    }

    .voter-field-chip:active {
        transform: scale(0.96);
    }

    .voter-field-chip:focus-visible {
        outline: 3px solid #fff;
        outline-offset: 2px;
    }

    .voter-field-chip-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.4rem;
        height: 1.4rem;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.25);
        font-size: 0.7rem;
        font-weight: 700;
    }

    .voter-field-chip.is-filled {
        border-color: #fff;
        background: #fff;
        color: #9a3412;
    }

    .voter-field-chip.is-filled .voter-field-chip-mark {
        background: #22c55e;
        color: #fff;
    }

    /* Shakes the "belum diisi" warning each time it appears. */
    .voter-shake {
        animation: voter-shake 420ms ease-in-out;
    }

    .voter-summary {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        width: 100%;
        max-height: 40vh;
        margin: 1.25rem 0 0;
        overflow-y: auto;
        padding: 0.5rem;
        border-radius: 1rem;
        background: #fff7ed;
        list-style: none;
        text-align: start;
    }

    .voter-summary-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        border-radius: 0.75rem;
        padding: 0.55rem 0.75rem;
        background: #fff;
        font-size: 0.85rem;
    }

    .voter-summary-field {
        color: #9a3412;
        font-weight: 600;
    }

    .voter-summary-candidate {
        color: #111827;
        font-weight: 700;
        text-align: end;
    }

    .voter-summary-row.is-missing .voter-summary-candidate {
        color: #b45309;
        font-style: italic;
        font-weight: 600;
    }

    .voter-sent {
        position: relative;
    }

    .voter-sent-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 9999px;
        color: #fff;
        background: linear-gradient(135deg, #22c55e 0%, #15803d 100%);
        box-shadow: 0 12px 24px -12px rgba(21, 128, 61, 0.8);
        animation: voter-pop 420ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    .voter-sent-icon svg {
        width: 1.6rem;
        height: 1.6rem;
    }

    /* Confetti bursts out from the centre of the success icon. */
    .voter-burst {
        position: absolute;
        top: 3.75rem;
        left: 50%;
        width: 0;
        height: 0;
        pointer-events: none;
    }

    .voter-burst i {
        position: absolute;
        left: -0.25rem;
        top: -0.25rem;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 2px;
        background: var(--c);
        animation: voter-burst 800ms cubic-bezier(0.22, 1, 0.36, 1) 180ms both;
    }

    .voter-profile-dialog {
        width: min(20rem, calc(100% - 2rem));
    }

    .voter-profile-body {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1.5rem;
        text-align: center;
    }

    .voter-profile-media {
        position: relative;
        width: 12rem;
        aspect-ratio: 4 / 5;
        overflow: hidden;
        border-radius: 1.25rem;
        background: linear-gradient(160deg, #fed7aa 0%, #fdba74 55%, #fb923c 100%);
        box-shadow: 0 16px 30px -22px rgba(124, 45, 18, 0.8);
    }

    .voter-profile-media .voter-candidate-initial {
        color: rgba(255, 255, 255, 0.5);
    }

    .voter-profile-name {
        margin-top: 1rem;
        color: #111827;
        font-size: 1.2rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .voter-profile-rank {
        margin-top: 0.25rem;
        color: #9a3412;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .voter-profile-close {
        width: 100%;
        margin-top: 1.25rem;
        border: 0;
        border-radius: 9999px;
        padding: 0.7rem 1rem;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
    }

    .dark .voter-picker-hint {
        color: #9ca3af;
    }

    .dark .voter-summary-row.is-missing .voter-summary-candidate {
        color: #fbbf24;
    }

    .dark .voter-picker-ordinal,
    .dark .voter-candidate-rank,
    .dark .voter-summary-field,
    .dark .voter-profile-rank {
        color: #fdba74;
    }

    .dark .voter-candidate-profile {
        border-color: rgba(251, 146, 60, 0.35);
        background: #1f2937;
        color: #fdba74;
    }

    .dark .voter-candidate-profile:hover {
        background: #374151;
    }

    .dark .voter-summary {
        background: rgba(251, 146, 60, 0.08);
    }

    .dark .voter-summary-row {
        background: #111827;
    }

    .dark .voter-summary-candidate,
    .dark .voter-profile-name {
        color: #f9fafb;
    }

    @keyframes voter-card-in {
        from {
            opacity: 0;
            transform: translateY(14px) scale(0.98);
        }
    }

    @keyframes voter-step-in {
        from {
            opacity: 0;
            transform: translateX(18px);
        }
    }

    @keyframes voter-check-draw {
        to {
            stroke-dashoffset: 0;
        }
    }

    @keyframes voter-hero-drift {
        from {
            background-position: 0 0, 0 0;
        }

        to {
            background-position: 22px 22px, 0 0;
        }
    }

    @keyframes voter-countdown-ping {
        from {
            box-shadow: 0 0 0 0 rgba(253, 230, 138, 0.6);
        }

        to {
            box-shadow: 0 0 0 7px rgba(253, 230, 138, 0);
        }
    }

    @keyframes voter-shake {
        0%,
        100% {
            transform: translateX(0);
        }

        20% {
            transform: translateX(-8px);
        }

        40% {
            transform: translateX(8px);
        }

        60% {
            transform: translateX(-5px);
        }

        80% {
            transform: translateX(3px);
        }
    }

    @keyframes voter-pop {
        from {
            opacity: 0;
            transform: scale(0.4);
        }
    }

    @keyframes voter-burst {
        from {
            opacity: 1;
            transform: translate(0, 0) scale(0.3);
        }

        to {
            opacity: 0;
            transform: translate(var(--x), var(--y)) rotate(200deg) scale(1);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .voter-candidate-wrap,
        .voter-stepper .fi-sc-wizard-step.fi-active,
        .voter-hero,
        .voter-shake,
        .voter-sent-icon,
        .voter-countdown-dot {
            animation: none;
        }

        .voter-check-path {
            animation: none;
            stroke-dashoffset: 0;
        }

        .voter-burst i {
            animation: none;
            opacity: 0;
        }

        .voter-candidate-profile,
        .voter-field-chip,
        .voter-candidate:active:not(:disabled) .voter-candidate-media {
            transition: none;
            transform: none;
        }
    }

    /* Status page: one centred card with an orb, a short status line, and the vote button when needed. */
    .voter-status {
        display: flex;
        justify-content: center;
        padding: 1.5rem 0 2.5rem;
    }

    .voter-status-card {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        width: min(30rem, 100%);
        overflow: hidden;
        padding: 2.75rem 1.75rem 2.5rem;
        border: 1px solid #fed7aa;
        border-radius: 1.75rem;
        background: #ffffff;
        text-align: center;
        box-shadow: 0 30px 60px -36px rgba(124, 45, 18, 0.45);
        animation: voter-status-in 560ms cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .voter-status-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 6px;
        background: linear-gradient(90deg, #f97316, #fb923c, #fbbf24);
    }

    .voter-status.is-voted .voter-status-card {
        border-color: #bbf7d0;
    }

    .voter-status.is-voted .voter-status-card::before {
        background: linear-gradient(90deg, #22c55e, #4ade80, #86efac);
    }

    .voter-status-pill {
        border-radius: 9999px;
        padding: 0.3rem 0.85rem;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .voter-status.is-pending .voter-status-pill {
        background: #fef3c7;
        color: #92400e;
    }

    .voter-status.is-voted .voter-status-pill {
        background: #dcfce7;
        color: #166534;
    }

    .voter-status-orb {
        position: relative;
        display: grid;
        place-items: center;
        width: 6rem;
        height: 6rem;
        margin-top: 0.25rem;
        border-radius: 9999px;
        color: #ffffff;
        animation: voter-pop 480ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    .voter-status-orb svg {
        position: relative;
        width: 2.75rem;
        height: 2.75rem;
    }

    .voter-status.is-pending .voter-status-orb {
        background: linear-gradient(135deg, #fbbf24 0%, #ea580c 100%);
        box-shadow: 0 18px 36px -18px rgba(234, 88, 12, 0.85);
    }

    .voter-status.is-voted .voter-status-orb {
        background: linear-gradient(135deg, #4ade80 0%, #16a34a 100%);
        box-shadow: 0 18px 36px -18px rgba(22, 163, 74, 0.85);
    }

    /* A soft ring that breathes out from the orb to draw the eye. */
    .voter-status-halo {
        position: absolute;
        inset: -0.6rem;
        border-radius: 9999px;
        border: 2px solid;
        animation: voter-status-halo 2.4s ease-out infinite;
    }

    .voter-status.is-pending .voter-status-halo {
        border-color: rgba(251, 146, 60, 0.55);
    }

    .voter-status.is-voted .voter-status-halo {
        border-color: rgba(74, 222, 128, 0.55);
    }

    .voter-status-draw {
        stroke-dasharray: 1;
        stroke-dashoffset: 1;
        animation: voter-check-draw 460ms ease-out 260ms forwards;
    }

    .voter-status-nudge {
        animation: voter-status-nudge 1.8s ease-in-out infinite;
    }

    .voter-status-title {
        max-width: 20rem;
        margin: 0.25rem 0 0;
        color: #111827;
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: -0.02em;
        animation: voter-status-in 560ms cubic-bezier(0.22, 1, 0.36, 1) 120ms both;
    }

    .voter-status-cta {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        margin-top: 0.5rem;
        border-radius: 9999px;
        padding: 0.9rem 1.75rem;
        background: linear-gradient(135deg, #f97316 0%, #c2410c 100%);
        color: #ffffff;
        font-size: 0.95rem;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 16px 30px -16px rgba(194, 65, 12, 0.9);
        transition: transform 160ms ease, filter 160ms ease;
    }

    .voter-status-cta:hover {
        transform: translateY(-2px);
        filter: brightness(1.05);
    }

    .voter-status-cta:active {
        transform: scale(0.98);
    }

    .voter-status-cta:focus-visible {
        outline: 3px solid #fdba74;
        outline-offset: 3px;
    }

    .voter-status-cta svg {
        width: 1.1rem;
        height: 1.1rem;
        transition: transform 200ms ease;
    }

    .voter-status-cta:hover svg {
        transform: translateX(3px);
    }

    @keyframes voter-status-in {
        from {
            opacity: 0;
            transform: translateY(16px) scale(0.98);
        }
    }

    @keyframes voter-status-halo {
        from {
            opacity: 0.9;
            transform: scale(0.9);
        }

        to {
            opacity: 0;
            transform: scale(1.35);
        }
    }

    @keyframes voter-status-nudge {
        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-4px);
        }
    }

    .dark .voter-status-card {
        border-color: rgba(251, 146, 60, 0.35);
        background: #1f2937;
        box-shadow: 0 30px 60px -36px rgba(0, 0, 0, 0.8);
    }

    .dark .voter-status.is-voted .voter-status-card {
        border-color: rgba(74, 222, 128, 0.35);
    }

    .dark .voter-status-title {
        color: #f9fafb;
    }

    .dark .voter-status.is-pending .voter-status-pill {
        background: rgba(251, 191, 36, 0.15);
        color: #fcd34d;
    }

    .dark .voter-status.is-voted .voter-status-pill {
        background: rgba(74, 222, 128, 0.15);
        color: #86efac;
    }

    @media (prefers-reduced-motion: reduce) {
        .voter-status-card,
        .voter-status-orb,
        .voter-status-title,
        .voter-status-halo,
        .voter-status-nudge {
            animation: none;
        }

        .voter-status-draw {
            animation: none;
            stroke-dashoffset: 0;
        }

        .voter-status-cta,
        .voter-status-cta svg {
            transition: none;
        }
    }

    .voter-status-cta.is-secondary {
        margin-top: 0.25rem;
        border: 1px solid #fdba74;
        background: #ffffff;
        color: #c2410c;
        box-shadow: none;
    }

    .voter-status-cta.is-secondary:hover {
        background: #fff7ed;
    }

    /* Account in the top bar: name and logout sit outside any menu. */
    .voter-account {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .voter-account-avatar {
        display: grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #fb923c 0%, #c2410c 100%);
        color: #ffffff;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .voter-account-name {
        max-width: 12rem;
        overflow: hidden;
        color: #111827;
        font-size: 0.875rem;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .voter-account-logout {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border: 1px solid #fed7aa;
        border-radius: 9999px;
        background: #ffffff;
        padding: 0.4rem 0.85rem;
        color: #c2410c;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 150ms ease, transform 150ms ease;
    }

    .voter-account-logout:hover {
        background: #fff7ed;
    }

    .voter-account-logout:active {
        transform: scale(0.96);
    }

    .voter-account-logout:focus-visible {
        outline: 3px solid #fdba74;
        outline-offset: 2px;
    }

    .voter-account-logout svg {
        width: 1rem;
        height: 1rem;
    }

    /* The form wraps the confirm button, so it must not break the two-column dialog actions. */
    .voter-account-form {
        display: contents;
    }

    @media (max-width: 640px) {
        .voter-account-name {
            display: none;
        }
    }

    /* Ballot before voting opens: the status card with a countdown to the opening time. */
    .voter-waiting-text {
        max-width: 22rem;
        margin: 0;
        color: #4b5563;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .voter-waiting-countdown {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 0.5rem 0.75rem;
        border: 1px solid #fed7aa;
        border-radius: 1rem;
        padding: 0.75rem 1.25rem;
        background: #fff7ed;
    }

    .voter-waiting-label {
        color: #9a3412;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .voter-waiting-value {
        color: #c2410c;
        font-size: 1.35rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
    }

    .voter-waiting-note {
        margin: 0;
        color: #6b7280;
        font-size: 0.8rem;
    }
</style>


