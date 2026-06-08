// Persistent storage for shown notifications
const shownNotificationsStore = {
    getShown: () => {
        try {
            return new Set(JSON.parse(localStorage.getItem('shownNotifications') || '[]'));
        } catch (e) {
            console.warn('Failed to load shown notifications:', e);
            return new Set();
        }
    },
    markShown: (id) => {
        try {
            const shown = shownNotificationsStore.getShown();
            shown.add(id);
            localStorage.setItem('shownNotifications', JSON.stringify([...shown]));
        } catch (e) {
            console.warn('Failed to save shown notification:', e);
        }
    },
    clearShown: () => {
        try {
            localStorage.removeItem('shownNotifications');
        } catch (e) {
            console.warn('Failed to clear shown notifications:', e);
        }
    }
};

// Initialize from storage
let shownNotifications = shownNotificationsStore.getShown();

// Current session user id (set by layouts when available)
const CURRENT_USER_ID = (typeof window !== 'undefined' && window.CURRENT_USER_ID !== undefined) ? window.CURRENT_USER_ID : null;

// Get icon based on notification type
function getNotificationIcon(type) {
    switch(type) {
        case 'success': return 'fa-check-circle';
        case 'error': return 'fa-times-circle';
        case 'warning': return 'fa-exclamation-triangle';
        case 'low_stock': return 'fa-box';
        case 'out_of_stock': return 'fa-box-open';
        case 'transaction': return 'fa-receipt';
        default: return 'fa-info-circle';
    }
}

// Escape HTML to prevent XSS
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Function to show notification toast
function showNotificationToast(notification) {
    if (!notification?.id || shownNotifications.has(notification.id.toString())) {
        console.debug('Skipping shown notification:', notification?.id);
        return;
    }
    
    const container = document.getElementById('notificationToasts');
    if (!container) {
        console.error('Toast container not found');
        return;
    }

    const toast = document.createElement('div');
    
    // Add to shown notifications set and persist
    const notifId = notification.id.toString();
    shownNotifications.add(notifId);
    shownNotificationsStore.markShown(notifId);
    
    // Create toast element
    toast.className = `toast notification-toast notification-${notification.type || 'info'} mb-2`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    // Get appropriate icon
    const icon = getNotificationIcon(notification.type);
    
    // Set toast content
    toast.innerHTML = `
        <div class="notification-content">
            <i class="fas ${icon} me-2"></i>
            <div class="toast-body">${escapeHtml(notification.message)}</div>
        </div>
        <button type="button" class="btn-close me-2" data-bs-dismiss="toast" aria-label="Close"></button>
    `;
    
    // Add toast to container
    container.appendChild(toast);
    
    // Initialize and show Bootstrap toast
    const bsToast = new bootstrap.Toast(toast, { 
        autohide: true,
        delay: 5000
    });
    bsToast.show();
    
    // Mark as read in database only if notification targets this user
    markNotificationAsRead(notification.id, notification.user_id ?? null);
}

// Mark notification as read in database
async function markNotificationAsRead(id, notifUserId = null) {
    if (!id) return;
    try {
        // Only auto-mark read when the notification targets a specific user and it matches current session
        if (notifUserId !== null && notifUserId !== undefined) {
            if (String(notifUserId) !== String(CURRENT_USER_ID)) return;
        } else {
            // system-wide notification (user_id null) - do not auto-mark as read on client
            return;
        }

        await fetch(notifEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: id })
        });
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark all notifications as read
async function markAllNotificationsRead() {
    try {
        const response = await fetch(notifEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            // include_system: true so staff clients request system notifications to be included
            body: JSON.stringify({ action: 'mark_all_read', include_system: true })
        });
        
            if (response.ok) {
                // Clear local storage since targeted notifications have been read for this user
                shownNotificationsStore.clearShown();
                // Update UI - remove only notification count badges
                document.querySelectorAll('.notif-count').forEach(badge => badge.remove());
                // Do not clear the notifications dropdown here; system notifications should persist
                // until explicitly cleared by admin. If you want to refresh the dropdown, call
                // fetchAndShowNotifications() separately.
            }
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
    }
}

// Fetch and show notifications
async function fetchAndShowNotifications() {
    try {
        const response = await fetch(notifEndpoint);
        const data = await response.json();
        console.debug('Notifications fetch result:', data);

        if (data.success && Array.isArray(data.notifications)) {
            data.notifications.forEach(notification => {
                if (!notification?.id) {
                    console.debug('Skipping invalid notification:', notification);
                    return;
                }
                if ((notification.status || '').toLowerCase() === 'unread') {
                    showNotificationToast(notification);
                }
            });
        }
    } catch (error) {
        console.error('Error fetching notifications:', error);
    }
}