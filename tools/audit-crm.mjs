#!/usr/bin/env node
import fg from 'fast-glob'
import fs from 'node:fs/promises'
import path from 'node:path'

const root = process.cwd()
const read = async (p) => { try { return await fs.readFile(path.join(root, p), 'utf8') } catch { return '' } }
const exists = async (p) => { try { await fs.access(path.join(root, p)); return true } catch { return false } }
const problems = []

// 1) Blade: ensure @routes before @vite
{
  const blade = await read('resources/views/app.blade.php')
  const iRoutes = blade.indexOf('@routes')
  const iVite   = blade.indexOf("@vite(['resources/js/app.ts'])")
  if (iRoutes === -1) problems.push(['app.blade.php', 'Missing @routes'])
  else if (iVite !== -1 && iRoutes > iVite) problems.push(['app.blade.php', '@routes must be before @vite'])
}

// 2) Routes: mixed param names
{
  const web = await read('routes/web.php')
  if (web.includes('{org}') && web.includes('{organization}')) {
    problems.push(['routes/web.php', 'Uses both {org} and {organization} – pick one for consistency'])
  }
}

// 3) Factories must begin with `<?php`
{
  const list = await fg('database/factories/**/*.php', { cwd: root })
  for (const f of list) {
    const txt = await read(f)
    if (txt && !txt.trimStart().startsWith('<?php')) {
      problems.push([f, 'Must start with `<?php` (no BOM/blank lines above)'])
    }
  }
}

// 4) Ziggy import shape: disallow default import from @ziggy
{
  const vueTs = await fg(['resources/js/**/*.vue', 'resources/js/**/*.ts'], {
    cwd: root,
    ignore: ['resources/js/types/**']   // <-- ignore declaration shims
  })
  for (const f of vueTs) {
    const txt = await read(f)
    if (txt.includes("import route from '@ziggy'")) {
      problems.push([f, "Use `import { route } from '@ziggy'` (named import)"])
    }
    if (/\broute\(/.test(txt) && !/from '@ziggy'/.test(txt)) {
      problems.push([f, 'Calls route() but does not import from @ziggy'])
    }
  }
}

// 5) Type shim present
{
  const hasShim = await exists('resources/js/types/ziggy.d.ts')
  if (!hasShim) problems.push(['types', 'Missing resources/js/types/ziggy.d.ts for Ziggy typings'])
}

if (!problems.length) {
  console.log('✅ Audit passed: no issues found.')
  process.exit(0)
} else {
  console.error('❌ Audit found issues:')
  for (const [file, msg] of problems) console.error('-', file, '→', msg)
  process.exit(1)
}
