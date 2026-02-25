/**
 * Permission catalog types and helpers.
 * Canonical catalog is built server-side; this provides TypeScript types and utilities.
 */
/**
 * Build a map from permission name -> id for quick lookup.
 */
export function buildPermissionNameToId(permissions) {
    const map = {};
    for (const p of permissions) {
        map[p.name] = p.id;
    }
    return map;
}
/**
 * Resolve a permission name to its canonical form (using aliasMap).
 */
export function resolveCanonicalPermission(name, aliasMap) {
    return aliasMap[name] ?? name;
}
/**
 * Get all permission names that are "recognized" by the catalog (in matrix or specials).
 */
export function getRecognizedPermissionNames(catalog) {
    const names = new Set();
    for (const modKey of Object.keys(catalog.matrix)) {
        for (const permName of Object.values(catalog.matrix[modKey])) {
            names.add(permName);
        }
    }
    for (const s of catalog.specials) {
        names.add(s.permission);
    }
    return names;
}
