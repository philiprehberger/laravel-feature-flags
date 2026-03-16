<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Tests\Feature;

use Illuminate\View\Compilers\BladeCompiler;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;
use PhilipRehberger\FeatureFlags\FeatureManager;
use PhilipRehberger\FeatureFlags\Tests\TestCase;

class BladeDirectiveTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('feature-flags.driver', 'config');
    }

    /**
     * Compile a Blade template string and evaluate it, returning the output.
     *
     * Blade directives (including @endfeature) must be on their own line — just
     * as they would appear in a real .blade.php file. This matches Blade's regex
     * which requires \B (non-word boundary) before the @ sign.
     */
    private function evaluate(string $template): string
    {
        /** @var BladeCompiler $compiler */
        $compiler = $this->app['blade.compiler'];
        $compiled = $compiler->compileString($template);

        ob_start();

        try {
            eval('?>'.$compiled);
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    public function test_feature_directive_renders_content_when_active(): void
    {
        $this->app['config']->set('feature-flags.features', ['my-feature' => true]);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        $output = $this->evaluate("@feature('my-feature')\nYES\n@endfeature");

        $this->assertStringContainsString('YES', $output);
    }

    public function test_feature_directive_hides_content_when_inactive(): void
    {
        $this->app['config']->set('feature-flags.features', ['my-feature' => false]);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        $output = $this->evaluate("@feature('my-feature')\nYES\n@endfeature");

        $this->assertStringNotContainsString('YES', $output);
    }

    public function test_feature_directive_hides_content_for_undefined_feature(): void
    {
        $this->app['config']->set('feature-flags.features', []);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        $output = $this->evaluate("@feature('undefined-feature')\nYES\n@endfeature");

        $this->assertStringNotContainsString('YES', $output);
    }

    public function test_feature_directive_renders_surrounding_content(): void
    {
        $this->app['config']->set('feature-flags.features', ['active-flag' => true]);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        $output = $this->evaluate("BEFORE\n@feature('active-flag')\nINSIDE\n@endfeature\nAFTER");

        $this->assertStringContainsString('BEFORE', $output);
        $this->assertStringContainsString('INSIDE', $output);
        $this->assertStringContainsString('AFTER', $output);
    }

    public function test_feature_directive_compiles_to_if_block(): void
    {
        /** @var BladeCompiler $compiler */
        $compiler = $this->app['blade.compiler'];

        $compiled = $compiler->compileString("@feature('my-feature')\nYES\n@endfeature");

        // Blade::if() compiles to Blade::check() calls
        $this->assertStringContainsString('Blade::check', $compiled);
        $this->assertStringContainsString("'feature'", $compiled);
        $this->assertStringContainsString('if (', $compiled);
        $this->assertStringContainsString('endif', $compiled);
    }

    public function test_elsefeature_directive_renders_when_second_feature_is_active(): void
    {
        $this->app['config']->set('feature-flags.features', [
            'my-feature' => false,
            'other-feature' => true,
        ]);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        // @elsefeature is the "elseif" variant — rendered when the named feature is active.
        $output = $this->evaluate(
            "@feature('my-feature')\nFIRST\n@elsefeature('other-feature')\nSECOND\n@endfeature"
        );

        $this->assertStringNotContainsString('FIRST', $output);
        $this->assertStringContainsString('SECOND', $output);
    }
}
