import {
    FileText,
    FolderUp,
    History,
    LayoutDashboard,
    LayoutGrid,
    Settings,
    Users,
    CalendarRange,
} from 'lucide-vue-next';
import type { NavItem } from '@/types';

/** Sidebar order (PRD-00 §5). "Kelas" is rendered separately with its sub menu. */
export const dashboardItem: NavItem = {
    title: 'Dashboard',
    href: '/',
    icon: LayoutDashboard,
};

export const classesItem: NavItem = {
    title: 'Kelas',
    href: '/kelas',
    icon: LayoutGrid,
    prefix: '/kelas',
};

export const secondaryItems: NavItem[] = [
    { title: 'Siswa', href: '/siswa', icon: Users, prefix: '/siswa' },
    {
        title: 'Periode & Kelas',
        href: '/periode',
        icon: CalendarRange,
        prefix: '/periode',
    },
    { title: 'Impor/Ekspor', href: '/impor', icon: FolderUp, prefix: '/impor' },
    { title: 'Dokumen', href: '/dokumen', icon: FileText, prefix: '/dokumen' },
    {
        title: 'Pengaturan',
        href: '/pengaturan/sekolah',
        icon: Settings,
        prefix: '/pengaturan',
    },
    { title: 'Log Aktivitas', href: '/log', icon: History, prefix: '/log' },
];
