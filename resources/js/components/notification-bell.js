/**
 * Notification Bell - Alpine.js Component
 *
 * Displays unread count badge on the bell icon in the topbar.
 * Opens a dropdown panel with recent notifications.
 * Polls for new notifications every 60 seconds.
 */

import Alpine from 'alpinejs';

Alpine.data('notificationBell', (config = {}) => ({
    isOpen: false,
    unreadCount: config.initialUnreadCount || 0,
    notifications: [],
    loading: false,
    pollInterval: null,

    // Route URLs passed from Blade
    unreadCountUrl: config.unreadCountUrl || '',
    recentUrl: config.recentUrl || '',
    markReadUrl: config.markReadUrl || '',
    markAllReadUrl: config.markAllReadUrl || '',
    viewAllUrl: config.viewAllUrl || '#',

    init() {
        this.fetchUnreadCount();

        // Poll every 60 seconds
        this.pollInterval = setInterval(() => {
            if (!this.isOpen) {
                this.fetchUnreadCount();
            }
        }, 60000);
    },

    destroy() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
    },

    async fetchUnreadCount() {
        if (!this.unreadCountUrl) return;

        try {
            const response = await fetch(this.unreadCountUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) return;

            const data = await response.json();
            this.unreadCount = data.count || 0;
        } catch (error) {
            // Silently fail — polling will retry
        }
    },

    async togglePanel() {
        this.isOpen = !this.isOpen;

        if (this.isOpen) {
            await this.fetchRecent();
        }
    },

    async fetchRecent() {
        if (!this.recentUrl) return;

        this.loading = true;

        try {
            const response = await fetch(this.recentUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) return;

            const data = await response.json();
            this.notifications = data.notifications || [];
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        } finally {
            this.loading = false;
        }
    },

    async markRead(id) {
        if (!this.markReadUrl) return;

        const url = this.markReadUrl.replace('__ID__', id);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            if (!response.ok) return;

            // Update local state
            const notification = this.notifications.find(n => n.id === id);
            if (notification) {
                notification.read_at = new Date().toISOString();
            }

            if (this.unreadCount > 0) {
                this.unreadCount--;
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    },

    async markAllRead() {
        if (!this.markAllReadUrl) return;

        try {
            const response = await fetch(this.markAllReadUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            if (!response.ok) return;

            // Update local state
            this.notifications.forEach(n => {
                n.read_at = new Date().toISOString();
            });
            this.unreadCount = 0;
        } catch (error) {
            console.error('Failed to mark all notifications as read:', error);
        }
    },

    handleNotificationClick(notification, event) {
        if (!notification.read_at) {
            this.markRead(notification.id);
        }

        if (notification.url) {
            this.isOpen = false;
            // Let the <a> tag navigate naturally
        } else {
            // No URL — prevent navigation, just mark as read
            event.preventDefault();
        }
    },
}));
