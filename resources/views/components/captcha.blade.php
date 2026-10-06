@props(['challenge'])
@if ($challenge)
    <div>
        @if (($challenge['driver'] ?? '') === 'turnstile')
            <div class="cf-turnstile" data-sitekey="{{ $challenge['site_key'] }}" data-response-field-name="captcha_answer"></div>
            @push('head')
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
            @endpush
        @else
            <label for="captcha_answer" class="field-label">{{ __('Security check') }}</label>
            <div class="flex items-center gap-3">
                <img src="{{ $challenge['image'] }}" alt="{{ __('Arithmetic question') }}" width="190" height="60" class="rounded-[6px] border border-line">
                <input type="hidden" name="captcha_id" value="{{ $challenge['id'] }}">
                <input id="captcha_answer" name="captcha_answer" type="text" inputmode="numeric" autocomplete="off" required class="field max-w-[120px]" placeholder="{{ __('Answer') }}" @error('captcha_answer') aria-invalid="true" aria-describedby="captcha_answer-error" @enderror>
            </div>
        @endif
        @error('captcha_answer')<p class="field-error" id="captcha_answer-error">{{ $message }}</p>@enderror
    </div>
@endif
