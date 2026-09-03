#!/bin/sh

set -eu

SCRIPT_DIR="$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)"
API_ROOT="$(CDPATH='' cd -- "$SCRIPT_DIR/../.." && pwd)"
WEB_ROOT="${BOUNDARY_WEB_ROOT:-$API_ROOT/../web}"
DOCS_ROOT="${BOUNDARY_DOCS_ROOT:-$API_ROOT/../docs}"

node --input-type=module - \
    "$API_ROOT/contract/edition-boundary.json" \
    "$WEB_ROOT/contract/edition-boundary.json" \
        "$DOCS_ROOT/contract/edition-boundary.json" <<'NODE'
import { readFileSync } from 'node:fs'

const [apiPath, webPath, docsPath] = process.argv.slice(2)
const api = JSON.parse(readFileSync(apiPath, 'utf8'))
const web = JSON.parse(readFileSync(webPath, 'utf8'))
const docs = JSON.parse(readFileSync(docsPath, 'utf8'))

const apiVersion = api._meta?.version
const apiContract = api._meta?.contract
const apiSurfaces = (api.boundary_matrix ?? []).map((surface) => surface.id).sort()

for (const [name, projection] of [['web', web], ['docs', docs]]) {
    if (projection._meta?.contract !== apiContract || projection._meta?.version !== apiVersion) {
        console.error(`${name} boundary projection has a different contract or version.`)
        process.exit(1)
    }

    const projectionSurfaces = [...(projection.required_surfaces ?? [])].sort()
    if (JSON.stringify(projectionSurfaces) !== JSON.stringify(apiSurfaces)) {
        console.error(`${name} boundary projection does not match the API-owned surface list.`)
        process.exit(1)
    }

    if (projection.public_ce?.complete_accounting_foundation !== true
        || projection.public_ce?.private_registry_required !== false
        || projection.public_ce?.commercial_credentials_required !== false) {
        console.error(`${name} boundary projection weakens the public CE contract.`)
        process.exit(1)
    }
}
NODE

printf 'Boundary projections match the API-owned contract.\n'
