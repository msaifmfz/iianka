import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { login } from '@/routes';

export default function Register() {
    return (
        <>
            <Head title="登録不可" />

            <div className="flex flex-col gap-6">
                <div className="space-y-2 text-center">
                    <p className="text-sm text-muted-foreground">
                        このアプリでは新規アカウント登録を公開していません。
                    </p>
                    <p className="text-sm text-muted-foreground">
                        アカウント作成が必要な場合は管理者へ依頼してください。
                    </p>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    ログイン画面に戻るには{' '}
                    <TextLink href={login()} tabIndex={1}>
                        こちら
                    </TextLink>
                </div>
            </div>
        </>
    );
}

Register.layout = {
    title: '新規登録は無効です',
    description: 'アカウント作成は管理者のみ行えます',
};
