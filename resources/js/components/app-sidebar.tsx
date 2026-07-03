import { usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarCheck2,
    ClipboardList,
    ClipboardCheck,
    Archive,
    FileSearch,
    FileStack,
    FileText,
    Inbox,
    ListChecks,
    ClipboardPenLine,
    Search,
    UsersRound,
} from 'lucide-react';
import { index as auditLogIndex } from '@/actions/App/Http/Controllers/Admin/AuditLogController';
import { index as adminUserIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import { index as attendanceRecordIndex } from '@/actions/App/Http/Controllers/AttendanceRecordController';
import { index as cleaningDutyRuleIndex } from '@/actions/App/Http/Controllers/CleaningDutyRuleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { index as voucherIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleVoucherController';
import { index as siteIndex } from '@/actions/App/Http/Controllers/ConstructionSiteController';
import { index as receptionArchiveIndex } from '@/actions/App/Http/Controllers/ReceptionArchiveController';
import {
    create as receptionCreate,
    index as receptionCasesIndex,
} from '@/actions/App/Http/Controllers/ReceptionCaseController';
import { index as receptionDocumentTypeIndex } from '@/actions/App/Http/Controllers/ReceptionDocumentTypeController';
import { index as receptionHomeIndex } from '@/actions/App/Http/Controllers/ReceptionHomeController';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
} from '@/components/ui/sidebar';
import { businessDateString } from '@/lib/dates';
import { index as overviewIndex } from '@/routes/schedule-overview';
import { index as scheduleSearchIndex } from '@/routes/schedule-search';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: '予定検索',
        href: scheduleSearchIndex(),
        icon: Search,
    },
    {
        title: '予定カレンダー',
        href: overviewIndex(),
        icon: CalendarDays,
    },
    {
        title: '予定一覧',
        href: scheduleIndex({ query: { range: 'today' } }),
        icon: ListChecks,
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
const emptyAttention = {
    schedule_count: 0,
    pending_voucher_count: 0,
    internal_notice_count: 0,
};

export function AppSidebar() {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { auth, attention = emptyAttention } = usePage().props;
    const navigationItems = [
        {
            ...mainNavItems[0],
        },
        {
            ...mainNavItems[1],
            href: overviewIndex({
                query: {
                    date: businessDateString(),
                },
            }),
        },
        {
            ...mainNavItems[2],
            href: scheduleIndex({
                query: {
                    range: 'today',
                    date: businessDateString(),
                },
            }),
            // badge: attention.schedule_count || null,
        },
        {
            ...mainNavItems[3],
            // badge: attention.pending_voucher_count || null,
        },
        mainNavItems[4],
        {
            title: '出勤管理',
            href: attendanceRecordIndex(),
            icon: CalendarCheck2,
        },
        {
            title: '掃除当番設定',
            href: cleaningDutyRuleIndex(),
            icon: ClipboardList,
        },
        ...(auth.permissions.manage_users
            ? [
                  {
                      title: 'ユーザー管理',
                      href: adminUserIndex(),
                      icon: UsersRound,
                  },
              ]
            : []),
        ...(auth.permissions.view_audit_logs
            ? [
                  {
                      title: '監査ログ',
                      href: auditLogIndex(),
                      icon: FileSearch,
                  },
              ]
            : []),
    ];
    const receptionItems = [
        {
            title: '受付ホーム',
            href: receptionHomeIndex(),
            icon: Inbox,
        },
        {
            title: '受付画面',
            href: receptionCreate(),
            icon: ClipboardPenLine,
        },
        {
            title: 'やること一覧',
            href: receptionCasesIndex(),
            icon: ListChecks,
        },
        {
            title: 'アーカイブ画面',
            href: receptionArchiveIndex(),
            icon: Archive,
        },
        ...(auth.permissions.manage_content
            ? [
                  {
                      title: '案件書類マスター',
                      href: receptionDocumentTypeIndex(),
                      icon: FileStack,
                  },
              ]
            : []),
    ];

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
                <NavMain items={receptionItems} label="受付" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
