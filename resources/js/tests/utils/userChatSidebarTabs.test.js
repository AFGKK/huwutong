import { describe, it, expect } from 'vitest';
import { USER_CHAT_SIDEBAR_TABS } from '@/utils/userChatSidebarTabs';

describe('userChatSidebarTabs', () => {
    it('places favorites and pending after friends as primary tabs', () => {
        expect([...USER_CHAT_SIDEBAR_TABS]).toEqual([
            'messages',
            'friends',
            'favorites',
            'pending',
        ]);
    });

    it('does not include redundant notifications or more dropdown tab', () => {
        expect(USER_CHAT_SIDEBAR_TABS).not.toContain('notifications');
        expect(USER_CHAT_SIDEBAR_TABS).not.toContain('more');
    });
});
