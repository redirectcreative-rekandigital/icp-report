<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Inbox, Users } from 'lucide-vue-next';
import ClassCard from '@/components/app/ClassCard.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import ErrorState from '@/components/app/ErrorState.vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
import PinPad from '@/components/app/PinPad.vue';
import SaveIndicator from '@/components/app/SaveIndicator.vue';
import ScoreCell from '@/components/app/ScoreCell.vue';
import SkillRadar from '@/components/app/SkillRadar.vue';
import StatCard from '@/components/app/StatCard.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/AppLayout.vue';
import { LEVELS } from '@/lib/cefr';

/** /_ui — every app component and state on one page (local environment only, PRD-07 §5.2). */
const now = Date.now();
</script>

<template>
    <Head title="Galeri UI" />
    <AppLayout :breadcrumbs="[{ title: 'Galeri UI', href: '/_ui' }]">
        <h1 class="mb-6 text-2xl font-bold">Galeri komponen</h1>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card>
                <CardHeader
                    ><CardTitle class="text-base">Tombol</CardTitle></CardHeader
                >
                <CardContent class="flex flex-wrap gap-2">
                    <Button>Primer</Button>
                    <Button variant="secondary">Sekunder</Button>
                    <Button variant="outline">Outline</Button>
                    <Button variant="ghost">Ghost</Button>
                    <Button variant="destructive">Hapus kelas 5A</Button>
                    <Button disabled>Nonaktif</Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >LevelBadge & StatusBadge</CardTitle
                    ></CardHeader
                >
                <CardContent class="grid gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <LevelBadge v-for="l in LEVELS" :key="l" :level="l" />
                        <LevelBadge :level="null" />
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <LevelBadge level="A2" size="sm" :percent="75" />
                        <LevelBadge level="A2" size="md" />
                        <LevelBadge level="A2" size="lg" />
                        <LevelBadge level="A2" size="xl" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge
                            v-for="s in [
                                'below',
                                'meeting',
                                'above',
                                'incomplete',
                                'draft',
                                'final',
                            ] as const"
                            :key="s"
                            :status="s"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >ScoreCell</CardTitle
                    ></CardHeader
                >
                <CardContent
                    class="grid grid-cols-3 gap-3 text-xs text-muted-foreground sm:grid-cols-6"
                >
                    <div>
                        idle<ScoreCell
                            :model-value="15"
                            :max="20"
                            label="idle"
                        />
                    </div>
                    <div>
                        kosong<ScoreCell
                            :model-value="null"
                            :max="20"
                            label="kosong"
                        />
                    </div>
                    <div>
                        menyimpan<ScoreCell
                            :model-value="12.5"
                            :max="20"
                            saving
                            label="menyimpan"
                        />
                    </div>
                    <div>
                        gagal<ScoreCell
                            :model-value="18"
                            :max="20"
                            server-error="Gagal menyimpan."
                            label="gagal"
                        />
                    </div>
                    <div>
                        terkunci<ScoreCell
                            :model-value="17"
                            :max="20"
                            locked
                            label="terkunci"
                        />
                    </div>
                    <div>
                        besar<ScoreCell
                            :model-value="9"
                            :max="20"
                            large
                            label="besar"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >SaveIndicator</CardTitle
                    ></CardHeader
                >
                <CardContent class="grid gap-2">
                    <SaveIndicator
                        :saving="false"
                        :pending="0"
                        :failed="0"
                        :online="true"
                        :saved-at="now"
                    />
                    <SaveIndicator
                        :saving="true"
                        :pending="1"
                        :failed="0"
                        :online="true"
                        :saved-at="null"
                    />
                    <SaveIndicator
                        :saving="false"
                        :pending="3"
                        :failed="0"
                        :online="false"
                        :saved-at="null"
                    />
                    <SaveIndicator
                        :saving="false"
                        :pending="1"
                        :failed="1"
                        :online="true"
                        :saved-at="null"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base">PinPad</CardTitle></CardHeader
                >
                <CardContent class="grid gap-6 sm:grid-cols-2">
                    <PinPad compact :capture-keyboard="false" />
                    <PinPad
                        compact
                        :capture-keyboard="false"
                        :lockout-seconds="899"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >StatCard, ClassCard, SkillRadar, Skeleton</CardTitle
                    ></CardHeader
                >
                <CardContent class="grid gap-3 sm:grid-cols-2">
                    <StatCard
                        :icon="Users"
                        label="Siswa dinilai"
                        :value="128"
                        :of="140"
                        :ring="91"
                        >nilai lengkap</StatCard
                    >
                    <ClassCard
                        :id="0"
                        name="5A"
                        english-teacher="Ms. Sarah"
                        status="draft"
                        :complete="18"
                        :total="25"
                    />
                    <SkillRadar
                        :percent="{
                            listening: 75,
                            reading: 85,
                            writing: 55,
                            speaking: 65,
                        }"
                        :size="160"
                    />
                    <div class="grid gap-2">
                        <Skeleton class="h-6 w-2/3" /><Skeleton
                            class="h-24 w-full"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >EmptyState</CardTitle
                    ></CardHeader
                >
                <CardContent>
                    <EmptyState
                        :icon="Inbox"
                        title="Belum ada dokumen"
                        description="Contoh empty state dengan tombol aksi."
                        ><Button>Unggah dokumen</Button></EmptyState
                    >
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >ErrorState</CardTitle
                    ></CardHeader
                >
                <CardContent><ErrorState error-ref="ERR-7F3K2" /></CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
