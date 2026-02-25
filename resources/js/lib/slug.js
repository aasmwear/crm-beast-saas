/**
 * Convert a human-friendly string to a URL/role slug (lowercase, hyphens).
 * e.g. "WordPress Developer" → "wordpress-developer"
 */
export function toSlug(str) {
    if (!str || typeof str !== 'string')
        return '';
    return str
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}
/**
 * Sanitize user input to a valid slug (lowercase, numbers, hyphens only).
 * Use when user manually edits the slug.
 */
export function sanitizeSlug(str) {
    if (!str || typeof str !== 'string')
        return '';
    return str
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}
