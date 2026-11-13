<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import BacteriaIcon from '@/components/BacteriaIcon.vue';
import CulturePlate from '@/components/CulturePlate.vue';
import { index } from '@/routes/bacteria';
import type { Bacteria } from '@/types/bacteria';

defineProps<{
    bacteria: Bacteria;
}>();

defineOptions({
    layout: (pageProps: { bacteria: Bacteria }) => ({
        breadcrumbs: [
            {
                title: 'Bacteria',
                href: index(),
            },
            {
                title: pageProps.bacteria.short_name,
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head :title="bacteria.name" />

    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-6 p-4 md:p-6">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Atlas
        </Link>

        <div
            class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-10"
            :style="{ '--bacteria-color': bacteria.color }"
        >
            <section
                class="surface-card animate-rise flex flex-col justify-center p-6 md:p-8"
            >
                <p class="eyebrow">Isolate / {{ bacteria.short_name }}</p>
                <h1
                    class="mt-4 text-[clamp(2.25rem,4vw,3rem)] leading-[1.05] font-bold tracking-[-0.03em]"
                >
                    {{ bacteria.name }}
                </h1>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <span
                        class="bacteria-color inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 text-xs font-medium"
                    >
                        <span
                            class="size-2 rounded-full"
                            :style="{ backgroundColor: bacteria.color }"
                        />
                        {{ bacteria.short_name }}
                    </span>
                    <span class="tech uppercase">{{ bacteria.icon }}</span>
                </div>

                <p
                    class="mt-6 max-w-[58ch] text-base leading-relaxed text-muted-foreground"
                >
                    {{ bacteria.description }}
                </p>

                <dl
                    class="mt-8 grid grid-cols-1 gap-px border border-border bg-border sm:grid-cols-3"
                >
                    <div class="bg-card p-4">
                        <dt class="tech uppercase">Morphology</dt>
                        <dd class="mt-2 flex items-center gap-2 text-sm">
                            <BacteriaIcon
                                :icon="bacteria.icon"
                                class="bacteria-color size-4"
                            />
                            {{ bacteria.icon }}
                        </dd>
                    </div>
                    <div class="bg-card p-4">
                        <dt class="tech uppercase">Swatch</dt>
                        <dd class="mt-2 flex items-center gap-2">
                            <span
                                class="size-3 rounded-full"
                                :style="{ backgroundColor: bacteria.color }"
                            />
                            <span class="font-mono text-xs">
                                {{ bacteria.color }}
                            </span>
                        </dd>
                    </div>
                    <div class="bg-card p-4">
                        <dt class="tech uppercase">Domain</dt>
                        <dd class="mt-2 text-sm">Bacteria</dd>
                    </div>
                </dl>
            </section>

            <section
                class="animate-rise flex items-center justify-center [animation-delay:160ms]"
            >
                <div class="w-full max-w-md">
                    <CulturePlate
                        :items="[
                            {
                                icon: bacteria.icon,
                                color: bacteria.color,
                            },
                        ]"
                        :label="bacteria.short_name"
                        :code="`#${String(bacteria.id).padStart(3, '0')}`"
                    />
                </div>
            </section>
        </div>
    </div>
</template>
