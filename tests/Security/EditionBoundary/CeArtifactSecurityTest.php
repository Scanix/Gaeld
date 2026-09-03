<?php

namespace Tests\Security\EditionBoundary;

use Tests\TestCase;

class CeArtifactSecurityTest extends TestCase
{
    public function test_public_ce_preserves_the_agpl_license_and_contribution_path(): void
    {
        $license = $this->readFile(base_path('LICENSE'));
        $readme = $this->readFile(base_path('README.md'));
        $contributing = $this->readFile(base_path('CONTRIBUTING.md'));

        $this->assertStringContainsString('GNU AFFERO GENERAL PUBLIC LICENSE', $license);
        $this->assertStringContainsString('AGPL-3.0-or-later', $readme);
        $this->assertStringContainsString('Contribut', $contributing);
    }

    public function test_public_ce_policy_excludes_private_paths_and_exposes_a_clean_artifact_audit(): void
    {
        $gitignore = $this->readFile(base_path('.gitignore'));
        $manifest = json_decode(
            $this->readFile(base_path('contract/edition-boundary.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringContainsString('/plugins/gaeld-ee/', $gitignore);
        $this->assertStringContainsString('deploy.php', $gitignore);
        $this->assertStringContainsString('.gitlab-ci.yml', $gitignore);
        $this->assertFileExists(base_path('scripts/qa/check-ce-artifact.sh'));
        $this->assertFalse($manifest['artifact_policy']['public_ce']['requires_private_registry']);
        $this->assertFalse($manifest['artifact_policy']['public_ce']['requires_commercial_credentials']);
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        return $contents;
    }
}
