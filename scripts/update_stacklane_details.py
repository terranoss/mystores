import os
import sys
import paramiko

HOST = "103.10.78.60"
USER = "melurcom"
PORT = 55000
KEY_PATH = r"D:\GitHub\PinScout\docs\melurcom"
REMOTE_PATH = "/home/melurcom/stacklane.melur.com/web"

def run_cmd(ssh, cmd):
    full_cmd = f"cd {REMOTE_PATH} && {cmd}"
    print(f">> Executing: {full_cmd}")
    stdin, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode('utf-8', errors='replace').strip()
    err = stderr.read().decode('utf-8', errors='replace').strip()
    status = stdout.channel.recv_exit_status()
    if out:
        print(f"OUT: {out}")
    if err:
        print(f"ERR: {err}")
    return status, out, err

def main():
    print("[1] Connecting to stacklane.melur.com via SSH...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    key = paramiko.RSAKey.from_private_key_file(KEY_PATH, password="Wanderer*1974")
    ssh.connect(HOST, port=PORT, username=USER, pkey=key, timeout=20)
    print("  [OK] Connected successfully.")

    print("\n[2] Uploading clean, professional product.blade.php (NO EMOJIS, MANDATORY PREREQUISITES)...")
    view_path = "resources/views/storefront/product.blade.php"
    
    new_blade = """@extends('layouts.storefront')

@section('title', $product->name)
@section('meta_description', $product->tagline)

@section('content')
<section class="mx-auto max-w-6xl px-6 py-16">
    <p class="text-xs uppercase tracking-widest text-[var(--sl-copper)]">Stacklane shelf</p>
    <h1 class="mt-3 font-display text-5xl text-[var(--sl-paper)]">{{ $product->name }}</h1>
    <p class="mt-4 max-w-3xl text-lg text-[var(--sl-muted)]">{{ $product->tagline }}</p>

    <div class="mt-10 grid gap-10 lg:grid-cols-5">
        <div class="lg:col-span-3 space-y-8">
            <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-4 sm:p-5">
                <p class="font-display text-base font-semibold text-emerald-400">Lifetime License Guarantee</p>
                <div class="mt-3 grid gap-2.5 sm:grid-cols-2 text-sm text-[var(--sl-paper)]/90">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span><strong>No subscription:</strong> Pay once, own it forever</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span><strong>No expiration:</strong> Your key never expires</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @if ($product->code === 'mystores')
                            <span><strong>Self-Hosted:</strong> Complete server and data ownership</span>
                        @else
                            <span><strong>Full desktop app:</strong> Runs locally on your PC</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @if ($product->code === 'mystores')
                            <span><strong>Install Activation:</strong> No recurring phone-home checks</span>
                        @else
                            <span><strong>Offline grace:</strong> Works even without internet</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Main Description --}}
            <div class="text-[var(--sl-paper)]/90 leading-relaxed text-base space-y-4">
                <p>{{ $product->description }}</p>
            </div>

            @if ($product->code === 'mystores')
                {{-- Mandatory Prerequisites Block --}}
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-5 sm:p-6 space-y-4">
                    <div class="border-b border-amber-500/20 pb-3">
                        <h3 class="font-display text-lg text-amber-300 font-bold uppercase tracking-wide">
                            Mandatory Prerequisites Before Purchasing
                        </h3>
                        <p class="text-xs text-[var(--sl-muted)] mt-1">
                            Please ensure you meet the following requirements before placing your order.
                        </p>
                    </div>

                    <ul class="space-y-3 text-sm text-[var(--sl-paper)]/90">
                        <li class="flex items-start gap-3">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-400 mt-2 shrink-0"></span>
                            <div>
                                <strong class="text-white">Shopee and/or Lazada Open Platform Developer Account (Required):</strong>
                                <p class="text-xs text-[var(--sl-muted)] mt-0.5 leading-relaxed">
                                    You must have an approved developer or partner app account on Shopee Open Platform and/or Lazada Open Platform. This provides the Partner ID, Partner Key, App Key, and App Secret required to authorize and sync your marketplace stores.
                                </p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-400 mt-2 shrink-0"></span>
                            <div>
                                <strong class="text-white">Web Hosting with Domain &amp; HTTPS (Required):</strong>
                                <p class="text-xs text-[var(--sl-muted)] mt-0.5 leading-relaxed">
                                    Standard cPanel, VPS, or cloud hosting running PHP 8.2+ with MySQL/MariaDB. A valid SSL certificate (HTTPS) is required for marketplace webhooks and staff QR code connection.
                                </p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-400 mt-2 shrink-0"></span>
                            <div>
                                <strong class="text-white">Android Mobile Devices for Staff:</strong>
                                <p class="text-xs text-[var(--sl-muted)] mt-0.5 leading-relaxed">
                                    Staff members and warehouse pickers require Android smartphones, tablets, or smart POS terminals with Google Play Store access to install the companion app.
                                </p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mt-2 shrink-0"></span>
                            <div>
                                <strong class="text-white">No Programming or Build Tools Required:</strong>
                                <p class="text-xs text-[var(--sl-muted)] mt-0.5 leading-relaxed">
                                    The software comes pre-compiled and sanitized. No Node.js, npm, or Composer dependencies are required on your hosting. Setup is completed via a web wizard.
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>

                {{-- Official Mobile App Banner with prominent Google Play button --}}
                <div class="rounded-xl border border-sky-500/30 bg-gradient-to-r from-sky-950/40 to-slate-900/60 p-5 sm:p-6 shadow-lg">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-500/30">
                                Mobile Companion Included
                            </span>
                            <h3 class="font-display text-xl text-white font-bold">Official MyStores Mobile App</h3>
                            <p class="text-sm text-slate-300 max-w-md leading-relaxed">
                                Warehouse staff, cashiers, and store managers can download the companion app free from Google Play and connect directly to your server by scanning the Store QR code.
                            </p>
                        </div>
                        <div class="shrink-0">
                            <a
                                href="https://play.google.com/store/apps/details?id=com.melur.mystores"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-3 bg-white text-slate-950 hover:bg-slate-100 px-5 py-3 rounded-lg font-semibold text-sm shadow-md transition-all hover:scale-[1.02]"
                            >
                                <svg class="w-6 h-6 shrink-0" viewBox="0 0 512 512" fill="none">
                                    <path d="M47.7 5.7c-5.8 4.2-9.7 11.2-9.7 19.3v462c0 8.1 3.9 15.1 9.7 19.3l242.3-250.3L47.7 5.7z" fill="#00E5FF"/>
                                    <path d="M380.2 153.5L290 256l90.2 102.5 73.1-42.2c16.2-9.3 16.2-24.7 0-34.1l-73.1-68.7z" fill="#FFD600"/>
                                    <path d="M290 256L47.7 5.7c2.5-1.8 5.6-2.9 8.9-2.9 5.8 0 11.7 2.8 17.6 6.2l249 144.5L290 256z" fill="#00E676"/>
                                    <path d="M290 256l33.2-34.5 57 32.8-57 32.8L290 256zm-213.4 253.2c-5.9 3.4-11.8 6.2-17.6 6.2-3.3 0-6.4-1.1-8.9-2.9L290 256l33.2 34.5-246.6 218.7z" fill="#FF1744"/>
                                </svg>
                                <div class="text-left leading-tight">
                                    <div class="text-[10px] uppercase font-bold text-slate-600">Get it on</div>
                                    <div class="text-sm font-bold text-slate-900">Google Play</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Deep Dive: Open Platform & Why it Matters --}}
                <div class="space-y-4 pt-2">
                    <h2 class="font-display text-2xl text-[var(--sl-paper)]">What is Open Platform &amp; Why Does It Matter?</h2>
                    <p class="text-sm text-[var(--sl-paper)]/85 leading-relaxed">
                        An <strong>Open Platform</strong> is the official developer and enterprise API infrastructure provided by marketplaces like <strong>Shopee Open Platform</strong> and <strong>Lazada Open Platform</strong>. Instead of relying on fragile third-party scrapers that break during platform updates, MyStore connects through direct, authorized API channels using your official developer credentials.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2 pt-2">
                        <div class="border border-white/10 bg-[var(--sl-shelf)] p-4 rounded-lg space-y-1.5">
                            <h4 class="font-medium text-amber-400 text-sm">Automated Real-Time Sync</h4>
                            <p class="text-xs text-[var(--sl-muted)] leading-relaxed">
                                Orders, inventory levels, and customer tracking numbers synchronize automatically without manual double-entry.
                            </p>
                        </div>
                        <div class="border border-white/10 bg-[var(--sl-shelf)] p-4 rounded-lg space-y-1.5">
                            <h4 class="font-medium text-amber-400 text-sm">Instant AWB Airway Bills</h4>
                            <p class="text-xs text-[var(--sl-muted)] leading-relaxed">
                                Generate and batch-print thermal shipping labels (SPX, J&amp;T, PosLaju, NinjaVan) directly from your fulfillment screen.
                            </p>
                        </div>
                        <div class="border border-white/10 bg-[var(--sl-shelf)] p-4 rounded-lg space-y-1.5">
                            <h4 class="font-medium text-amber-400 text-sm">Vacation Mode &amp; Stock Guard</h4>
                            <p class="text-xs text-[var(--sl-muted)] leading-relaxed">
                                Toggle vacation mode directly via Shopee Open Platform API, and synchronize inventory buffers across Shopee and Lazada to prevent overselling while away.
                            </p>
                        </div>
                        <div class="border border-white/10 bg-[var(--sl-shelf)] p-4 rounded-lg space-y-1.5">
                            <h4 class="font-medium text-amber-400 text-sm">100% Data Privacy</h4>
                            <p class="text-xs text-[var(--sl-muted)] leading-relaxed">
                                Self-hosted on your own server. Your customer lists, sales figures, and supplier costs remain completely private.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Who Should Use It --}}
                <div class="border border-white/10 bg-[var(--sl-shelf)] p-6 rounded-lg space-y-3">
                    <h3 class="font-display text-lg text-[var(--sl-paper)] font-semibold">Who Should Use MyStore?</h3>
                    <ul class="space-y-2.5 text-sm text-[var(--sl-paper)]/85">
                        <li class="flex items-start gap-2.5">
                            <span class="text-emerald-400 mt-0.5 font-bold">&#10003;</span>
                            <span><strong>Multi-Store E-Commerce Merchants:</strong> Businesses operating multiple Shopee and Lazada storefronts requiring unified fulfillment in one central portal.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="text-emerald-400 mt-0.5 font-bold">&#10003;</span>
                            <span><strong>Retail Outlets &amp; Hybrid Stores:</strong> Brick-and-mortar stores requiring counter point-of-sale operations synced with online inventory.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="text-emerald-400 mt-0.5 font-bold">&#10003;</span>
                            <span><strong>Warehouse &amp; Fulfillment Teams:</strong> Staff using mobile barcode scanners or handheld devices to pick orders, verify items, and pack cartons.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="text-emerald-400 mt-0.5 font-bold">&#10003;</span>
                            <span><strong>Merchants Avoiding Recurring Software Fees:</strong> Businesses wanting perpetual ownership of their platform without recurring monthly SaaS subscriptions.</span>
                        </li>
                    </ul>
                </div>

                {{-- Interactive Demo Preview --}}
                <div class="rounded-xl border border-white/10 bg-[var(--sl-shelf)] p-5 sm:p-6 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                        <div>
                            <h3 class="font-display text-lg text-[var(--sl-paper)] font-semibold">
                                See it in action
                            </h3>
                            <p class="text-sm text-[var(--sl-muted)] mt-1 leading-relaxed max-w-2xl">
                                Explore a fully interactive sample store. Every screen below is the real MyStore interface,
                                loaded with fabricated sample data. Nothing here is connected to a live store, and no
                                action you take in the demo affects anything.
                            </p>
                        </div>
                        <a
                            href="{{ url('/demo') }}/"
                            target="_blank"
                            rel="noopener"
                            class="shrink-0 inline-flex items-center gap-2 rounded-md border border-white/20 px-4 py-2 text-sm font-semibold text-[var(--sl-paper)] hover:border-[var(--sl-copper)] hover:text-[var(--sl-copper-hot)] transition-colors"
                        >
                            Open full screen
                        </a>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-white/10 bg-[#f7f6f2]">
                        <iframe
                            src="{{ url('/demo') }}/"
                            title="MyStore interactive demo — sample dashboard"
                            loading="lazy"
                            class="block w-full h-[720px] border-0"
                        ></iframe>
                    </div>

                    <p class="text-xs text-[var(--sl-muted)] leading-relaxed">
                        Sample dashboard shown. If the preview does not load,
                        <a href="{{ url('/demo') }}/" target="_blank" rel="noopener" class="underline hover:text-[var(--sl-copper-hot)]">open the demo in a new tab</a>.
                    </p>
                </div>
            @endif

            @if ($product->isPinscoutFamily())
                <p class="text-sm text-[var(--sl-muted)] leading-relaxed">
                    You are responsible for complying with Google, LinkedIn, and Facebook terms of service, applicable laws, and website robots/terms when using this tool. PinScout is intended for personal and research use. Sites may block or limit access; use at your own risk.
                </p>
            @endif

            @if ($product->latest_version)
                <p class="text-sm text-[var(--sl-muted)]">Current version {{ $product->latest_version }}</p>
            @endif
        </div>

        {{-- Aside Buy Card --}}
        <aside class="lg:col-span-2 border border-white/10 bg-[var(--sl-shelf)] p-6 h-fit rounded-lg">
            <div class="flex items-baseline justify-between">
                <p class="font-display text-3xl text-[var(--sl-paper)]">{{ $product->display_price }}</p>
                <span class="rounded bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-400">One-time</span>
            </div>
            <p class="mt-2 text-sm text-[var(--sl-muted)]">{{ $product->price_label }}</p>
            
            <ul class="mt-4 space-y-2 border-t border-white/10 pt-4 text-xs text-[var(--sl-paper)]/80">
                <li class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span><strong>100% Lifetime Access</strong> (No recurring charges)</span>
                </li>
                <li class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @if ($product->code === 'mystores')
                        <span><strong>1 Live Domain</strong> license activation</span>
                    @else
                        <span><strong>{{ $product->max_activations }} PC {{ $product->max_activations === 1 ? 'seat' : 'seats' }}</strong> activation</span>
                    @endif
                </li>
                <li class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Instant installer &amp; license key delivery</span>
                </li>
                @if ($product->code === 'mystores')
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Shopee &amp; Lazada Open Platform ready</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Official Google Play mobile app included</span>
                    </li>
                @endif
            </ul>

            <div class="mt-6 flex flex-col gap-3">
                @auth
                    <a href="{{ route('checkout.buy', ['product' => $product->slug]) }}" class="rounded-md bg-[var(--sl-copper)] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[var(--sl-copper-hot)] transition-colors">
                        Buy lifetime license
                    </a>
                    <a href="{{ route('portal.licenses') }}" class="text-center text-sm text-[var(--sl-muted)] hover:text-[var(--sl-copper-hot)]">
                        Open license portal
                    </a>
                @else
                    <a href="{{ route('register') }}" class="rounded-md bg-[var(--sl-copper)] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[var(--sl-copper-hot)] transition-colors">
                        Create account to buy
                    </a>
                    <a href="{{ route('login') }}" class="text-center text-sm text-[var(--sl-muted)] hover:text-[var(--sl-copper-hot)]">
                        Already have an account? Sign in
                    </a>
                @endauth

@if ($product->code === 'mystores')
                    <a
                        href="{{ url('/demo') }}/"
                        target="_blank"
                        rel="noopener"
                        class="rounded-md border border-white/20 bg-transparent px-4 py-2.5 text-center text-sm font-semibold text-[var(--sl-paper)] hover:border-[var(--sl-copper)] hover:text-[var(--sl-copper-hot)] transition-colors inline-flex items-center justify-center gap-2"
                    >
                        View Live Demo
                    </a>
                    <a
                        href="https://play.google.com/store/apps/details?id=com.melur.mystores"
                        target="_blank"
                        rel="noopener"
                        class="rounded-md border border-white/20 bg-transparent px-4 py-2.5 text-center text-sm font-semibold text-[var(--sl-paper)] hover:border-sky-400 hover:text-sky-300 transition-colors inline-flex items-center justify-center gap-2"
                    >
                        Download on Google Play
                    </a>
                @endif

                @if ($product->code === 'pinscout' && filled(config('license.lite_download_url')))
                    <a
                        href="{{ config('license.lite_download_url') }}"
                        class="rounded-md border border-white/20 bg-transparent px-4 py-2.5 text-center text-sm font-semibold text-[var(--sl-paper)] hover:border-[var(--sl-copper)] hover:text-[var(--sl-copper-hot)] transition-colors"
                    >
                        Try Now — free Lite download
                    </a>
                    <p class="text-center text-xs text-[var(--sl-muted)] leading-relaxed">
                        Free limited build — unlimited Maps; 50 GB / 5 LinkedIn & FB leads per day, masked contacts. Upgrade anytime with a lifetime key.
                    </p>
                @endif
            </div>

            @if ($product->code === 'pinscout')
                <a href="{{ route('storefront.product', 'pinscout-agency') }}" class="mt-4 block text-center text-xs text-[var(--sl-muted)] underline hover:text-[var(--sl-copper-hot)]">
                    Need 5 seats? See PinScout Agency
                </a>
            @elseif ($product->code === 'pinscout-agency')
                <a href="{{ route('storefront.product', 'pinscout') }}" class="mt-4 block text-center text-xs text-[var(--sl-muted)] underline hover:text-[var(--sl-copper-hot)]">
                    Just one PC? See PinScout Solo
                </a>
            @endif

            <p class="mt-6 text-center text-xs text-[var(--sl-muted)]">
                Instant delivery: Download installer and activate with your key immediately.
            </p>
        </aside>
    </div>
</section>

@if ($product->isPinscoutFamily())
{{-- Screenshots from README --}}
<section class="border-y border-white/10 bg-[var(--sl-ink-soft)]">
    <div class="mx-auto max-w-6xl px-6 py-16 space-y-14">
        <div>
            <h2 class="font-display text-3xl text-[var(--sl-paper)]">Screenshots</h2>
            <p class="mt-2 text-[var(--sl-muted)]">From a real PinScout session — dashboard beside the Chrome window you search in.</p>
        </div>

        <figure class="space-y-3">
            <div class="overflow-hidden border border-white/10 bg-black/40">
                <img
                    src="{{ asset('images/pinscout/dashboard.jpg') }}"
                    alt="PinScout control panel after a completed crawl"
                    class="w-full h-auto object-cover object-top"
                    loading="lazy"
                >
            </div>
            <figcaption class="text-sm text-[var(--sl-muted)]">
                PinScout window after a completed crawl — rail actions, filter toolbar, results table, and activity log.
            </figcaption>
        </figure>

        <figure class="space-y-3">
            <div class="overflow-hidden border border-white/10 bg-black/40">
                <img
                    src="{{ asset('images/pinscout/maps.jpg') }}"
                    alt="Google Maps browser session used for manual search"
                    class="w-full h-auto object-cover object-top"
                    loading="lazy"
                >
            </div>
            <figcaption class="text-sm text-[var(--sl-muted)]">
                Google Maps browser session used for manual search — you search in Chrome; PinScout extracts the feed.
            </figcaption>
        </figure>
    </div>
</section>

{{-- Features from README --}}
<section class="mx-auto max-w-6xl px-6 py-16">
    <h2 class="font-display text-3xl text-[var(--sl-paper)]">What it does</h2>
    <p class="mt-2 max-w-2xl text-[var(--sl-muted)]">The same capabilities you get after activating your license — four bots in one desktop app.</p>

    <ul class="mt-10 grid gap-6 md:grid-cols-2">
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">Four bots, one Chrome window</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Choose Maps, Google Business, LinkedIn, or Facebook Pages at launch, or switch later from the Mode list. Chrome opens on the matching site.</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">You search, PinScout extracts</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Opens your installed Google Chrome (not a hidden browser). You run the search yourself; PinScout reads the results feed and fills the dashboard.</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">Google Maps — 55 columns</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Name, address parts, phone, website, email, socials, lat/lng, category, rating, reviews, star histogram, hours (Mon–Sun), amenities, Maps URL, and more.</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">Google Business listings</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Clicks each card in the left list and scrapes the detail box — hours by day, services, rating, reviews. Set a record limit before Start.</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">LinkedIn people search</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Pulls headline, company, experience, education, certifications, skills, languages, about, connections, and contact info from people-search profiles.</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">Facebook Pages</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Company and Pages search: about, website, phone, email, location, hours, likes, followers, rating, reviews, and Instagram.</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">Customize extract fields</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Tick which columns to scrape and export for the current bot. Optionally remember the set for future runs.</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">Website contact enrichment</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">For Maps and Google Business, visits each site to pull emails and socials (Facebook, X, Instagram, YouTube, LinkedIn, TikTok, Pinterest).</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">Filter &amp; filtered Excel export</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Has Email / Phone / Website, Unclaimed Only, min rating, min reviews, live search. Export writes only the filtered subset to .xlsx.</p>
        </li>
        <li class="border border-white/10 bg-[var(--sl-shelf)] p-5">
            <h3 class="font-medium text-[var(--sl-paper)]">Cover photos on export</h3>
            <p class="mt-2 text-sm text-[var(--sl-muted)] leading-relaxed">Downloads main cover photos to output/photos/ only when you click Export — keeps scraping fast. Already-scraped URLs are skipped if you Start again.</p>
        </li>
    </ul>
</section>

{{-- How to use from README --}}
<section class="border-t border-white/10 bg-[var(--sl-ink-soft)]">
    <div class="mx-auto max-w-6xl px-6 py-16">
        <h2 class="font-display text-3xl text-[var(--sl-paper)]">How to run</h2>
        <ol class="mt-10 grid gap-8 md:grid-cols-2 lg:grid-cols-4">
            <li>
                <span class="font-display text-3xl text-[var(--sl-copper)]">01</span>
                <h3 class="mt-3 font-medium text-[var(--sl-paper)]">Launch</h3>
                <p class="mt-2 text-sm text-[var(--sl-muted)]">Start PinScout and pick a bot. Chrome opens on Maps, Google Business, LinkedIn, or Facebook.</p>
            </li>
            <li>
                <span class="font-display text-3xl text-[var(--sl-copper)]">02</span>
                <h3 class="mt-3 font-medium text-[var(--sl-paper)]">Search in Chrome</h3>
                <p class="mt-2 text-sm text-[var(--sl-muted)]">Type your query on that site (e.g. “Coffee Shops in Austin” or a people search).</p>
            </li>
            <li>
                <span class="font-display text-3xl text-[var(--sl-copper)]">03</span>
                <h3 class="mt-3 font-medium text-[var(--sl-paper)]">Start in PinScout</h3>
                <p class="mt-2 text-sm text-[var(--sl-muted)]">Click Start. Google Business asks how many records. The live table fills as listings extract.</p>
            </li>
            <li>
                <span class="font-display text-3xl text-[var(--sl-copper)]">04</span>
                <h3 class="mt-3 font-medium text-[var(--sl-paper)]">Filter &amp; export</h3>
                <p class="mt-2 text-sm text-[var(--sl-muted)]">Filter the grid, then Export for Excel + cover photos.</p>
            </li>
        </ol>
        <p class="mt-10 text-sm text-[var(--sl-muted)]">After purchase: download the installer, then activate with your Stacklane license key. Dev builds need Python 3.10+ and Google Chrome.</p>
    </div>
</section>
@endif
@endsection
"""

    sftp = ssh.open_sftp()
    with sftp.file(f"{REMOTE_PATH}/{view_path}", "w") as f:
        f.write(new_blade)
    sftp.close()
    print("  [OK] Uploaded updated product.blade.php.")

    # 3. Update Database product tagline and description
    print("\n[3] Updating database product record with clean professional marketing copy...")
    tagline = "Self-Hosted Multi-Outlet Retail Platform with Shopee & Lazada Open Platform sync. Lifetime license - no subscriptions."
    desc = (
        "MyStore is a self-hosted retail management and point-of-sale platform built on Laravel 12 and React 19. "
        "It provides official Shopee Open Platform and Lazada Open Platform integrations for unified order processing, "
        "automated multi-store inventory synchronization, and bulk thermal AWB airway bill printing. "
        "Includes multi-outlet management, employee role access, cash register reconciliation, and a 5-minute self-hosted web installer."
    )

    update_product_php = f'''php -r "
require 'vendor/autoload.php';
\\$app = require_once 'bootstrap/app.php';
\\$kernel = \\$app->make(Illuminate\\Contracts\\Console\\Kernel::class);
\\$kernel->bootstrap();

use App\\Models\\Product;
\\$prod = Product::where('code', 'mystores')->first();
if (\\$prod) {{
    \\$prod->update([
        'tagline' => '{tagline}',
        'description' => '{desc}',
        'price_label' => 'Perpetual License (1 Domain)',
        'amount_display' => 'RM 220',
        'amount_display_usd' => '$50',
    ]);
    echo 'PRODUCT_UPDATED';
}} else {{
    echo 'NOT_FOUND';
}}
"'''
    run_cmd(ssh, update_product_php)

    # 4. Clear compiled view & application cache
    print("\n[4] Clearing cache on server...")
    run_cmd(ssh, "php artisan view:clear && php artisan cache:clear")

    ssh.close()
    print("\n[5] Deployment complete!")

if __name__ == "__main__":
    main()
