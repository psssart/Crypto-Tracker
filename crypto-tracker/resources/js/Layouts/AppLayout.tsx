import { PropsWithChildren } from 'react';
import { FlashMessages } from '@/Components/FlashMessages';

export default function AppLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen bg-slate-950 text-slate-100">
            <FlashMessages />
            {children}
        </div>
    );
}
