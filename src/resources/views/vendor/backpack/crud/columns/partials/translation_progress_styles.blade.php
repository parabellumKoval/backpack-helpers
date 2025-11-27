@once
    @push('crud_list_styles')
        <style>
            .translation-progress-meta {
                font-size: 14px;
                letter-spacing: 0.03em;
            }

            .translation-progress-summary {
                text-transform: none;
            }

            .translation-locale-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 0.25rem;
            }

            .translation-locale-tags--compact {
                justify-content: flex-end;
            }

            .translation-locale-tags--spaced {
                justify-content: space-between;
                width: 100%;
            }

            .translation-locale-tag {
                font-weight: 700;
                font-size: 8px;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #adb5bd;
            }

            .translation-locale-tag.is-filled {
                color: #28a745;
            }

            .translation-progress-bar {
                height: 3px;
            }
        </style>
    @endpush
@endonce
