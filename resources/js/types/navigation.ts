import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: string;
};

export type NavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** Also active for sub-paths (e.g. /pengaturan/*). */
    prefix?: string;
};
