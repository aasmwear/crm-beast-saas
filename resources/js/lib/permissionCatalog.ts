/**
 * Permission catalog types and helpers.
 * Canonical catalog is built server-side; this provides TypeScript types and utilities.
 */

export interface CatalogModule {
  key: string
  label: string
  icon: string
  sortOrder: number
}

export interface CatalogAction {
  key: string
  label: string
}

export interface CatalogSpecial {
  module: string
  permission: string
  label: string
}

export interface PermissionCatalog {
  modules: CatalogModule[]
  actions: CatalogAction[]
  matrix: Record<string, Record<string, string>>
  specials: CatalogSpecial[]
  aliasMap: Record<string, string>
}

export interface Permission {
  id: number
  name: string
  module: string
}

/**
 * Build a map from permission name -> id for quick lookup.
 */
export function buildPermissionNameToId(permissions: Permission[]): Record<string, number> {
  const map: Record<string, number> = {}
  for (const p of permissions) {
    map[p.name] = p.id
  }
  return map
}

/**
 * Resolve a permission name to its canonical form (using aliasMap).
 */
export function resolveCanonicalPermission(
  name: string,
  aliasMap: Record<string, string>
): string {
  return aliasMap[name] ?? name
}

/**
 * Get all permission names that are "recognized" by the catalog (in matrix or specials).
 */
export function getRecognizedPermissionNames(catalog: PermissionCatalog): Set<string> {
  const names = new Set<string>()
  for (const modKey of Object.keys(catalog.matrix)) {
    for (const permName of Object.values(catalog.matrix[modKey])) {
      names.add(permName)
    }
  }
  for (const s of catalog.specials) {
    names.add(s.permission)
  }
  return names
}
