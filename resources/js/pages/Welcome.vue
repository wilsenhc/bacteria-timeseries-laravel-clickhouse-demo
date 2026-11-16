<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarRange, Database } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import BacteriaIcon from '@/components/BacteriaIcon.vue';
import CulturePlate from '@/components/CulturePlate.vue';
import { buttonVariants } from '@/components/ui/button';
import { home, login, register } from '@/routes';
import { index as labs } from '@/routes/labs';
import {
    index as bacteriaIndex,
    show as bacteriaShow,
} from '@/routes/bacteria';
import type { Bacteria } from '@/types/bacteria';

defineProps<{
    bacteriaCount: number;
    featuredBacteria: Bacteria[];
}>();
</script>

<template>
    <Head title="Foodborne pathogen atlas" />

    <div class="flex min-h-[100dvh] flex-col bg-background">
        <header
            class="sticky top-0 z-[100] border-b border-border/70 bg-background/85 backdrop-blur"
        >
            <div
                class="section-shell flex h-16 items-center justify-between gap-4"
            >
                <Link :href="home()" class="flex items-center gap-3">
                    <span
                        class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span class="grid">
                        <span class="text-sm leading-tight font-semibold">
                            {{ $page.props.name }}
                        </span>
                        <span class="tech hidden text-[10px] sm:block">
                            Weekly surveillance
                        </span>
                    </span>
                </Link>

                <nav class="hidden items-center gap-6 text-sm md:flex">
                    <a
                        href="#atlas"
                        class="text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Atlas
                    </a>
                    <a
                        href="#method"
                        class="text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Method
                    </a>
                </nav>

                <nav class="flex items-center gap-2">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="labs()"
                            :class="buttonVariants({ size: 'sm' })"
                        >
                            Open labs
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            :class="
                                buttonVariants({ variant: 'ghost', size: 'sm' })
                            "
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            :class="buttonVariants({ size: 'sm' })"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <section
                class="section-shell grid items-center gap-14 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:gap-20 lg:py-24"
            >
                <div class="animate-rise">
                    <p class="eyebrow">Foodborne pathogen atlas</p>
                    <h1
                        class="mt-5 max-w-[14ch] text-[clamp(2.5rem,5vw,4rem)] leading-[1.02] font-bold tracking-[-0.03em]"
                    >
                        Track foodborne
                        <span class="text-primary">bacteria</span>, week by
                        week.
                    </h1>
                    <p
                        class="mt-6 max-w-[46ch] text-base leading-relaxed text-muted-foreground"
                    >
                        A weekly record of the organisms behind food poisoning:
                        their morphology, habitat and the observations collected
                        around them.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <template v-if="$page.props.auth.user">
                            <Link
                                :href="labs()"
                                :class="buttonVariants({ size: 'lg' })"
                            >
                                Open labs
                                <ArrowRight class="size-4" />
                            </Link>
                            <Link
                                :href="bacteriaIndex()"
                                :class="
                                    buttonVariants({
                                        variant: 'outline',
                                        size: 'lg',
                                    })
                                "
                            >
                                Browse the atlas
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="login()"
                                :class="buttonVariants({ size: 'lg' })"
                            >
                                Log in to explore
                                <ArrowRight class="size-4" />
                            </Link>
                            <Link
                                :href="register()"
                                :class="
                                    buttonVariants({
                                        variant: 'outline',
                                        size: 'lg',
                                    })
                                "
                            >
                                Create an account
                            </Link>
                        </template>
                    </div>

                    <dl
                        class="mt-10 flex flex-wrap items-center gap-x-8 gap-y-3 border-t border-border pt-5"
                    >
                        <div class="flex items-center gap-2">
                            <Database class="size-4 text-primary" />
                            <dt class="tech uppercase">
                                Postgres + ClickHouse
                            </dt>
                        </div>
                        <div class="flex items-center gap-2">
                            <CalendarRange class="size-4 text-primary" />
                            <dt class="tech uppercase">ISO week windows</dt>
                        </div>
                    </dl>
                </div>

                <div
                    class="animate-rise relative mx-auto w-full max-w-md [animation-delay:160ms]"
                >
                    <CulturePlate
                        :items="featuredBacteria"
                        label="Sample plate"
                        code="FOOD / 07"
                    />
                    <div
                        class="mt-8 flex items-center justify-between gap-4 border-t border-border pt-3"
                    >
                        <span class="tech">SAMPLE / FOOD</span>
                        <span class="tech">TEMP / 37 °C</span>
                        <span class="tech">WINDOW / 7 DAYS</span>
                    </div>
                </div>
            </section>

            <section class="border-y border-border bg-background">
                <div
                    class="section-shell grid grid-cols-2 gap-px bg-border md:grid-cols-4"
                >
                    <div class="min-w-0 bg-card px-5 py-8 sm:px-6">
                        <p class="font-mono text-3xl font-medium">
                            {{ bacteriaCount }}
                        </p>
                        <p class="tech mt-2 uppercase">Catalogued bacteria</p>
                    </div>
                    <div class="min-w-0 bg-card px-5 py-8 sm:px-6">
                        <p class="font-mono text-3xl font-medium">52</p>
                        <p class="tech mt-2 uppercase">Weeks per year</p>
                    </div>
                    <div class="min-w-0 bg-card px-5 py-8 sm:px-6">
                        <p
                            class="font-mono text-lg font-medium break-words sm:text-2xl"
                        >
                            Postgres
                        </p>
                        <p class="tech mt-2 uppercase">Relational records</p>
                    </div>
                    <div class="min-w-0 bg-card px-5 py-8 sm:px-6">
                        <p
                            class="font-mono text-lg font-medium break-words sm:text-2xl"
                        >
                            ClickHouse
                        </p>
                        <p class="tech mt-2 uppercase">Time series events</p>
                    </div>
                </div>
            </section>

            <section id="atlas" class="section-shell py-20 lg:py-28">
                <div class="max-w-[48ch]">
                    <p class="eyebrow">The usual suspects</p>
                    <h2
                        class="mt-4 text-[clamp(1.75rem,3vw,2.25rem)] leading-tight font-bold"
                    >
                        A short list of organisms worth knowing.
                    </h2>
                    <p
                        class="mt-4 text-sm leading-relaxed text-muted-foreground"
                    >
                        Each bacterium keeps a fixed color and a general
                        morphology icon, so the same organism reads the same
                        across every chart and page.
                    </p>
                </div>

                <div
                    class="mt-10 grid items-stretch gap-4 sm:grid-cols-2 lg:mt-14"
                >
                    <Link
                        v-for="(bacterium, index) in featuredBacteria"
                        :key="bacterium.id"
                        :href="bacteriaShow(bacterium.id)"
                        class="surface-card hover-lift animate-rise flex items-start gap-4 p-5"
                        :style="{
                            animationDelay: `${index * 120}ms`,
                            '--bacteria-color': bacterium.color,
                        }"
                    >
                        <span
                            class="bacteria-surface flex size-11 shrink-0 items-center justify-center rounded-lg"
                        >
                            <BacteriaIcon
                                :icon="bacterium.icon"
                                class="bacteria-color size-6"
                            />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">
                                {{ bacterium.name }}
                            </span>
                            <span class="tech mt-1 block">
                                {{ bacterium.short_name }} /
                                {{ bacterium.icon }}
                            </span>
                            <span
                                class="mt-3 line-clamp-2 block text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ bacterium.description }}
                            </span>
                        </span>
                    </Link>
                </div>
            </section>

            <section
                id="method"
                class="border-t border-border bg-card py-20 lg:py-28"
            >
                <div
                    class="section-shell grid items-center gap-12 lg:grid-cols-2 lg:gap-20"
                >
                    <div class="surface-card animate-rise p-6 lg:order-2">
                        <div class="flex items-center justify-between gap-4">
                            <span class="tech uppercase">
                                Observations / schematic
                            </span>
                            <span class="tech">ISO 8601</span>
                        </div>

                        <svg
                            viewBox="0 0 480 220"
                            fill="none"
                            class="mt-6 w-full"
                            aria-hidden="true"
                        >
                            <g class="text-border" stroke="currentColor">
                                <line x1="8" y1="40" x2="472" y2="40" />
                                <line x1="8" y1="90" x2="472" y2="90" />
                                <line x1="8" y1="140" x2="472" y2="140" />
                                <line x1="8" y1="190" x2="472" y2="190" />
                            </g>
                            <path
                                d="M8 176 L72 168 L136 150 L200 128 L264 96 L328 78 L392 44 L456 30"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="text-primary"
                            />
                            <g class="text-brand-blue" fill="currentColor">
                                <circle cx="72" cy="168" r="4" />
                                <circle cx="200" cy="128" r="4" />
                                <circle cx="328" cy="78" r="4" />
                                <circle
                                    cx="456"
                                    cy="30"
                                    r="5"
                                    class="culture-pulse"
                                />
                            </g>
                        </svg>

                        <div class="tech mt-4 flex justify-between">
                            <span>W01</span>
                            <span>W13</span>
                            <span>W26</span>
                            <span>W39</span>
                            <span>W52</span>
                        </div>
                    </div>

                    <div class="animate-rise lg:order-1">
                        <p class="eyebrow">Cadence</p>
                        <h2
                            class="mt-4 text-[clamp(1.75rem,3vw,2.25rem)] leading-tight font-bold"
                        >
                            A weekly window, not a firehose.
                        </h2>
                        <p
                            class="mt-4 max-w-[48ch] text-sm leading-relaxed text-muted-foreground"
                        >
                            Observations belong to an ISO week. Scope the lab
                            map to a single window and the atlas stays a record
                            you can actually read.
                        </p>

                        <ul class="mt-8 grid gap-4">
                            <li class="flex items-start gap-3">
                                <span
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-background text-primary"
                                >
                                    <CalendarRange class="size-4" />
                                </span>
                                <span>
                                    <span class="block text-sm font-medium">
                                        ISO weeks
                                    </span>
                                    <span
                                        class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                                    >
                                        Every observation is anchored to a year
                                        and week, never a loose date range.
                                    </span>
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-background text-primary"
                                >
                                    <Database class="size-4" />
                                </span>
                                <span>
                                    <span class="block text-sm font-medium">
                                        Two stores
                                    </span>
                                    <span
                                        class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                                    >
                                        Postgres holds the catalogue and
                                        ClickHouse takes the time series.
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-border">
            <div
                class="section-shell flex flex-col gap-4 py-8 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-4" />
                    </span>
                    <span class="text-sm font-medium">
                        {{ $page.props.name }}
                    </span>
                </div>
                <p class="tech">Laravel · Inertia · Postgres · ClickHouse</p>
            </div>
        </footer>
    </div>
</template>
