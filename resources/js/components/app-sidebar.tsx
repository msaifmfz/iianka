import { usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardCheck,
    FileText,
    UsersRound,
} from 'lucide-react';
import { index as adminUserIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { index as voucherIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleVoucherController';
import { index as siteIndex } from '@/actions/App/Http/Controllers/ConstructionSiteController';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: '予定表',
        href: scheduleIndex(),
        icon: CalendarDays,
    },
    {
        title: '伝票確認',
        href: voucherIndex(),
        icon: ClipboardCheck,
    },
    {
        title: '現場案内図',
        href: siteIndex(),
        icon: FileText,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = usePage().props;
    const navigationItems = auth.user.is_admin
        ? [
              ...mainNavItems,
              {
                  title: 'ユーザー管理',
                  href: adminUserIndex(),
                  icon: UsersRound,
              },
          ]
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            {/* <SidebarHeader> */}
            {/*     <SidebarMenu> */}
            {/*         <SidebarMenuItem> */}
            {/*             <SidebarMenuButton size="lg" asChild> */}
            {/*                 <Link href={dashboard()} prefetch> */}
            {/*                     <AppLogo /> */}
            {/*                 </Link> */}
            {/*             </SidebarMenuButton> */}
            {/*         </SidebarMenuItem> */}
            {/*     </SidebarMenu> */}
            {/* </SidebarHeader> */}

            <SidebarContent>
                <NavMain items={navigationItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
