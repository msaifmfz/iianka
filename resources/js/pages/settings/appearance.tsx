import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="外観設定" />

            <h1 className="sr-only">外観設定</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="外観設定"
                    description="アカウントの外観設定を更新します"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: '外観設定',
            href: editAppearance(),
        },
    ],
};
