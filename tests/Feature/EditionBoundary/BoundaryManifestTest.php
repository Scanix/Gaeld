<?php

namespace Tests\Feature\EditionBoundary;

use Tests\TestCase;

class BoundaryManifestTest extends TestCase
{
    public function test_boundary_manifest_defines_all_edition_owners_and_required_surfaces(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('edition-boundary', $manifest['_meta']['contract']);
        $this->assertSame('1.0.0', $manifest['_meta']['version']);
        $this->assertSame(['ce', 'ee', 'shared'], array_keys($manifest['editions']));

        $surfaceOwners = [];
        foreach ($manifest['boundary_matrix'] as $surface) {
            $this->assertArrayHasKey('id', $surface);
            $this->assertArrayHasKey('owner', $surface);
            $this->assertContains($surface['owner'], ['ce', 'ee', 'shared']);

            foreach ([
                'source_paths',
                'runtime_ownership',
                'license',
                'packaging',
                'dependencies',
                'data_ownership',
                'feature_flags',
                'tests',
                'documentation',
                'deployment',
            ] as $field) {
                $this->assertArrayHasKey($field, $surface, "Boundary surface is missing {$field}");
            }

            $surfaceOwners[$surface['owner']] = true;
        }

        $ownerKeys = array_keys($surfaceOwners);
        sort($ownerKeys);

        $this->assertSame(['ce', 'ee', 'shared'], $ownerKeys);
    }

    public function test_public_ce_artifact_does_not_require_private_registry_or_commercial_credentials(): void
    {
        $publicArtifact = $this->manifest()['artifact_policy']['public_ce'];

        $this->assertFalse($publicArtifact['requires_private_registry']);
        $this->assertFalse($publicArtifact['requires_commercial_credentials']);
        $this->assertContains('plugins/gaeld-ee', $publicArtifact['must_exclude']);
        $this->assertContains('private registry credentials', $publicArtifact['must_exclude']);
    }

    public function test_release_contract_requires_immutable_compatibility_metadata(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('1.0.0', $manifest['compatibility']['contract_version']);
        $this->assertSame('immutable-versioned-artifact', $manifest['compatibility']['ee_version_policy']);
        $this->assertSame(
            'reject-incompatible-pair-before-traffic-switch',
            $manifest['compatibility']['failure_mode']
        );
        $this->assertContains('artifact_digest', $manifest['release']['identifiers']);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $manifestPath = base_path('contract/edition-boundary.json');

        $this->assertFileExists($manifestPath);

        $manifest = json_decode(
            $this->readFile($manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return $manifest;
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        return $contents;
    }
}
