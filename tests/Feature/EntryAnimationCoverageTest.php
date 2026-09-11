<?php

namespace Tests\Feature;

use Tests\TestCase;

class EntryAnimationCoverageTest extends TestCase
{
    public function test_homepage_sections_keep_the_existing_reveal_system_and_top_games_mascots(): void
    {
        $home = file_get_contents(resource_path('views/livewire/public/home-page.blade.php'));
        $topGames = file_get_contents(resource_path('views/livewire/public/sections/top-games-section.blade.php'));
        $topWinners = file_get_contents(resource_path('views/livewire/public/top-winners.blade.php'));

        $this->assertStringContainsString('data-bb-home-reveals', $home);
        $this->assertStringContainsString('data-bb-reveal', $topGames);
        $this->assertStringContainsString('brahma-mascot-left.png', $topGames);
        $this->assertStringContainsString('brahma-mascot-right.png', $topGames);
        $this->assertStringContainsString('bb-games-slider-footer', $topGames);
        $this->assertStringContainsString('brahma_mascot_half.png', $topGames);
        $this->assertStringContainsString('bb-brahma-mascot-half', $topGames);
        $this->assertTrue(
            strpos($topGames, 'bb-games-slider-footer') < strpos($topGames, 'bb-brahma-mascot-half'),
        );
        $this->assertStringContainsString('data-bb-reveal', $topWinners);
    }

    public function test_requested_player_and_information_pages_opt_into_reveals(): void
    {
        $views = [
            'livewire/pages/games.blade.php',
            'livewire/player/profile-page.blade.php',
            'public/brahmabull-rules.blade.php',
            'public/guide-to-play.blade.php',
            'public/terms-and-conditions.blade.php',
            'public/privacy-policy.blade.php',
        ];

        foreach ($views as $view) {
            $source = file_get_contents(resource_path("views/{$view}"));

            $this->assertStringContainsString('data-bb-reveal-page', $source, $view);
            $this->assertStringContainsString('data-bb-reveal', $source, $view);
        }
    }

    public function test_reveal_initializer_remains_single_and_livewire_navigation_aware(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertSame(1, substr_count($source, 'new IntersectionObserver'));
        $this->assertStringContainsString('[data-bb-home-reveals], [data-bb-reveal-page]', $source);
        $this->assertStringContainsString("document.addEventListener('livewire:navigated', initializeHomepageReveals)", $source);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $source);
    }

    public function test_top_games_mascot_breakpoints_match_the_required_ranges(): void
    {
        $source = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.bb-brahma-mascot-left,\s*\.bb-brahma-mascot-right\s*\{\s*display:\s*none;/s',
            $source,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width:\s*768px\)\s*\{\s*\.bb-brahma-mascot-half\s*\{\s*display:\s*none;/s',
            $source,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width:\s*1365px\)\s*\{\s*\.bb-brahma-mascot-left,\s*\.bb-brahma-mascot-right\s*\{\s*display:\s*block;/s',
            $source,
        );
        $this->assertStringNotContainsString('min-width: 1921px', $source);
    }
}
