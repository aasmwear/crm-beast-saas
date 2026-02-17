/**
 * useRealtime Composable
 *
 * Provides a clean wrapper around Laravel Echo for subscribing to realtime channels.
 * Handles automatic cleanup on component unmount.
 *
 * @example
 * ```typescript
 * const { subscribeToProject, unsubscribeAll } = useRealtime();
 *
 * subscribeToProject(orgId, projectId, {
 *   onTaskMoved: (data) => console.log('Task moved:', data),
 *   onTaskUpdated: (data) => console.log('Task updated:', data),
 * });
 *
 * // Automatically unsubscribes on component unmount
 * onUnmounted(() => unsubscribeAll());
 * ```
 */
import { onUnmounted, ref } from 'vue';
export function useRealtime() {
    // Store active channels for cleanup
    const channels = ref([]);
    /**
     * Subscribe to a project channel for realtime task updates.
     *
     * @param organizationId Organization ID
     * @param projectId Project ID
     * @param callbacks Event callbacks
     */
    const subscribeToProject = (organizationId, projectId, callbacks) => {
        if (!window.Echo) {
            console.warn('Laravel Echo is not initialized');
            return null;
        }
        const channelName = `org.${organizationId}.projects.${projectId}`;
        try {
            const channel = window.Echo.private(channelName);
            // Listen for task.moved event
            if (callbacks.onTaskMoved) {
                channel.listen('.task.moved', callbacks.onTaskMoved);
            }
            // Listen for task.updated event
            if (callbacks.onTaskUpdated) {
                channel.listen('.task.updated', callbacks.onTaskUpdated);
            }
            // Store for cleanup
            channels.value.push(channel);
            console.log(`[Realtime] Subscribed to ${channelName}`);
            return channel;
        }
        catch (error) {
            console.error(`[Realtime] Failed to subscribe to ${channelName}:`, error);
            return null;
        }
    };
    /**
     * Subscribe to platform announcements (Super Admin only).
     *
     * @param callbacks Event callbacks
     */
    const subscribeToPlatform = (callbacks) => {
        if (!window.Echo) {
            console.warn('Laravel Echo is not initialized');
            return null;
        }
        const channelName = 'platform-announcements';
        try {
            const channel = window.Echo.channel(channelName);
            // Listen for platform.alert event
            if (callbacks.onAlert) {
                channel.listen('.platform.alert', callbacks.onAlert);
            }
            // Store for cleanup
            channels.value.push(channel);
            console.log(`[Realtime] Subscribed to ${channelName}`);
            return channel;
        }
        catch (error) {
            console.error(`[Realtime] Failed to subscribe to ${channelName}:`, error);
            return null;
        }
    };
    /**
     * Unsubscribe from a specific channel.
     *
     * @param channelName Channel name (e.g., 'org.1.projects.5')
     */
    const unsubscribeFrom = (channelName) => {
        if (!window.Echo) {
            return;
        }
        try {
            window.Echo.leave(channelName);
            // Remove from tracked channels
            channels.value = channels.value.filter((ch) => ch.name !== channelName && ch.name !== `private-${channelName}`);
            console.log(`[Realtime] Unsubscribed from ${channelName}`);
        }
        catch (error) {
            console.error(`[Realtime] Failed to unsubscribe from ${channelName}:`, error);
        }
    };
    /**
     * Unsubscribe from all active channels.
     */
    const unsubscribeAll = () => {
        if (!window.Echo) {
            return;
        }
        try {
            channels.value.forEach((channel) => {
                window.Echo.leave(channel.name);
            });
            channels.value = [];
            console.log('[Realtime] Unsubscribed from all channels');
        }
        catch (error) {
            console.error('[Realtime] Failed to unsubscribe from channels:', error);
        }
    };
    /**
     * Get the current user's presence channel.
     *
     * @param userId User ID
     */
    const subscribeToUserPresence = (userId) => {
        if (!window.Echo) {
            console.warn('Laravel Echo is not initialized');
            return null;
        }
        const channelName = `App.Models.User.${userId}`;
        try {
            const channel = window.Echo.private(channelName);
            // Store for cleanup
            channels.value.push(channel);
            console.log(`[Realtime] Subscribed to presence channel for user ${userId}`);
            return channel;
        }
        catch (error) {
            console.error(`[Realtime] Failed to subscribe to user presence:`, error);
            return null;
        }
    };
    /**
     * Check if Echo is initialized and ready.
     */
    const isReady = () => {
        return !!window.Echo;
    };
    // Automatic cleanup on component unmount
    onUnmounted(() => {
        unsubscribeAll();
    });
    return {
        subscribeToProject,
        subscribeToPlatform,
        subscribeToUserPresence,
        unsubscribeFrom,
        unsubscribeAll,
        isReady,
        activeChannels: channels,
    };
}
