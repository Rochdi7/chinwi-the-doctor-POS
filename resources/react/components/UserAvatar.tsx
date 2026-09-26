import type { User } from '@/types/api';

/** Letters only: "Mehdi (Propriétaire)" gives "MP", not "M(". */
export function initialsOf(name: string | undefined): string {
    return (name ?? '').split(/\s+/).map((w) => w.replace(/[^\p{L}]/gu, '')).filter(Boolean).slice(0, 2).map((w) => w[0]!.toUpperCase()).join('');
}

/** The profile photo, or the initials on a warm disc when there is none. */
export function UserAvatar({ user, className = 'size-7 text-xs' }: { user: User | null; className?: string }) {
    if (user?.avatar_url) {
        return <img src={user.avatar_url} alt="" className={`flex-none rounded-full bg-white object-cover ${className}`} />;
    }

    return (
        <span aria-hidden className={`grid flex-none place-items-center rounded-full bg-warm font-extrabold text-white ${className}`}>
            {initialsOf(user?.name) || '·'}
        </span>
    );
}
