<div
    x-data="resourceUsageAlertsPush({
        configurationUrl: @js(route('resourceusagealerts.push.configuration')),
        subscribeUrl: @js(route('resourceusagealerts.push.subscribe')),
        unsubscribeUrl: @js(route('resourceusagealerts.push.unsubscribe')),
        testUrl: @js(route('resourceusagealerts.push.test')),
        workerUrl: @js(route('resourceusagealerts.push.worker')),
        labels: {
            unsupported: @js(trans('resourceusagealerts::strings.push.unsupported')),
            unavailable: @js(trans('resourceusagealerts::strings.push.unavailable')),
            blocked: @js(trans('resourceusagealerts::strings.push.blocked')),
            enabled: @js(trans('resourceusagealerts::strings.push.enabled')),
            disabled: @js(trans('resourceusagealerts::strings.push.disabled')),
            error: @js(trans('resourceusagealerts::strings.push.error')),
            testSent: @js(trans('resourceusagealerts::strings.push.test_sent')),
            rateLimited: @js(trans('resourceusagealerts::strings.push.rate_limited')),
        },
    })"
    x-init="init()"
>
    <x-filament::section
        :heading="trans('resourceusagealerts::strings.push.heading')"
        :description="trans('resourceusagealerts::strings.push.description')"
        icon="tabler-bell-ringing"
    >
        <div class="space-y-4">
            <div class="flex items-center gap-2 text-sm">
                <span class="font-medium">{{ trans('resourceusagealerts::strings.push.status') }}:</span>
                <span x-text="status"></span>
            </div>

            <div class="flex flex-wrap gap-3">
                <x-filament::button
                    type="button"
                    icon="tabler-bell-plus"
                    x-on:click="enable()"
                    x-bind:disabled="busy || subscribed || !available"
                >
                    {{ trans('resourceusagealerts::strings.push.enable') }}
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="gray"
                    icon="tabler-bell-off"
                    x-on:click="disable()"
                    x-bind:disabled="busy || !subscribed"
                >
                    {{ trans('resourceusagealerts::strings.push.disable') }}
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="gray"
                    icon="tabler-send"
                    x-on:click="sendTest()"
                    x-bind:disabled="busy || !subscribed"
                >
                    {{ trans('resourceusagealerts::strings.push.test') }}
                </x-filament::button>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ trans('resourceusagealerts::strings.push.secure_context_help') }}
            </p>
        </div>
    </x-filament::section>
</div>

@once
    <script>
        window.resourceUsageAlertsPush = (options) => ({
            available: false,
            busy: false,
            subscribed: false,
            publicKey: null,
            status: options.labels.disabled,

            async init() {
                if (!window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                    this.status = options.labels.unsupported;
                    return;
                }

                try {
                    const response = await fetch(options.configurationUrl, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();
                    this.available = response.ok && data.enabled;
                    this.publicKey = data.publicKey;

                    if (!this.available) {
                        this.status = options.labels.unavailable;
                        return;
                    }

                    const registration = await navigator.serviceWorker.getRegistration('/');
                    const subscription = registration ? await registration.pushManager.getSubscription() : null;
                    this.subscribed = Boolean(subscription);
                    this.status = this.subscribed ? options.labels.enabled : options.labels.disabled;

                    if (Notification.permission === 'denied') {
                        this.status = options.labels.blocked;
                    }
                } catch (error) {
                    this.status = options.labels.error;
                }
            },

            async enable() {
                this.busy = true;

                try {
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        this.status = permission === 'denied' ? options.labels.blocked : options.labels.disabled;
                        return;
                    }

                    const registration = await navigator.serviceWorker.register(options.workerUrl, { scope: '/' });
                    let subscription = await registration.pushManager.getSubscription();

                    if (!subscription) {
                        subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.decodeKey(this.publicKey),
                        });
                    }

                    const payload = subscription.toJSON();
                    payload.contentEncoding = PushManager.supportedContentEncodings?.[0] || 'aes128gcm';

                    const response = await this.request(options.subscribeUrl, 'POST', payload);
                    if (!response.ok) {
                        throw new Error('Subscription could not be stored.');
                    }

                    this.subscribed = true;
                    this.status = options.labels.enabled;
                } catch (error) {
                    this.status = options.labels.error;
                } finally {
                    this.busy = false;
                }
            },

            async disable() {
                this.busy = true;

                try {
                    const registration = await navigator.serviceWorker.getRegistration('/');
                    const subscription = registration ? await registration.pushManager.getSubscription() : null;

                    if (subscription) {
                        await this.request(options.unsubscribeUrl, 'DELETE', { endpoint: subscription.endpoint });
                        await subscription.unsubscribe();
                    }

                    this.subscribed = false;
                    this.status = options.labels.disabled;
                } catch (error) {
                    this.status = options.labels.error;
                } finally {
                    this.busy = false;
                }
            },

            async sendTest() {
                this.busy = true;

                try {
                    const response = await this.request(options.testUrl, 'POST', {});
                    const data = await response.json().catch(() => ({}));

                    if (response.status === 429) {
                        const seconds = response.headers.get('Retry-After') || '60';
                        this.status = options.labels.rateLimited.replace(':seconds', seconds);
                        return;
                    }

                    this.status = data.message || (response.ok ? options.labels.testSent : options.labels.error);
                    if (data.reason === 'subscription_expired' || data.reason === 'subscription_missing') {
                        this.subscribed = false;
                    }
                } catch (error) {
                    this.status = options.labels.error;
                } finally {
                    this.busy = false;
                }
            },

            request(url, method, body) {
                return fetch(url, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });
            },

            decodeKey(value) {
                const padding = '='.repeat((4 - value.length % 4) % 4);
                const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
                const raw = window.atob(base64);
                return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
            },
        });
    </script>
@endonce
