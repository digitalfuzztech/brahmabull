<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminModalConsistencyTest extends TestCase
{
    public function test_admin_modal_inventory_uses_the_shared_scoped_shell(): void
    {
        $views = [
            'agents.blade.php',
            'agent-show.blade.php',
            'games.blade.php',
            'wallet-agents.blade.php',
            'wallet-agent-show.blade.php',
            'wallet-type-show.blade.php',
            'players-all.blade.php',
            'deposits.blade.php',
            'cashouts.blade.php',
            'brahma-deposits.blade.php',
            'brahma-plays.blade.php',
            'spinning-wheel.blade.php',
            'team-messenger.blade.php',
        ];

        foreach ($views as $view) {
            $markup = file_get_contents(resource_path('views/livewire/admin/'.$view));

            $this->assertStringContainsString('bb-admin-modal-overlay', $markup, $view);
            $this->assertStringContainsString('bb-admin-modal-shell', $markup, $view);
        }

        $privateHeader = file_get_contents(resource_path('views/components/private-header.blade.php'));
        $this->assertStringContainsString('bb-admin-modal-overlay', $privateHeader);
        $this->assertStringContainsString('bb-admin-modal-shell', $privateHeader);
    }

    public function test_long_financial_modals_keep_header_scrollable_body_footer_and_actions(): void
    {
        $expected = [
            'deposits.blade.php' => ['closeModal', 'processDeposit'],
            'cashouts.blade.php' => ['closeModal', 'processCashout'],
            'brahma-deposits.blade.php' => ['closeModal', '$depositSaveMethod'],
            'brahma-plays.blade.php' => ['closeModal', '$playSaveMethod'],
        ];

        foreach ($expected as $view => $actions) {
            $markup = file_get_contents(resource_path('views/livewire/admin/'.$view));

            $this->assertStringContainsString('bb-admin-modal-header', $markup, $view);
            $this->assertStringContainsString('bb-admin-modal-body', $markup, $view);
            $this->assertStringContainsString('bb-admin-modal-footer', $markup, $view);

            foreach ($actions as $action) {
                $this->assertStringContainsString($action, $markup, $view);
            }
        }
    }

    public function test_proof_and_confirmation_modals_keep_their_existing_actions(): void
    {
        foreach (['deposits.blade.php', 'cashouts.blade.php', 'brahma-deposits.blade.php'] as $view) {
            $markup = file_get_contents(resource_path('views/livewire/admin/'.$view));

            $this->assertStringContainsString('openProof', $markup, $view);
            $this->assertStringContainsString('closeProof', $markup, $view);
        }

        $spin = file_get_contents(resource_path('views/livewire/admin/spinning-wheel.blade.php'));
        $this->assertStringContainsString('deleteConfirmedType', $spin);
        $this->assertStringContainsString('deleteConfirmedOffer', $spin);
        $this->assertStringContainsString('deleteConfirmedAssignment', $spin);

        $messenger = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));
        $this->assertStringContainsString('confirmDeleteMessage', $messenger);
        $this->assertStringContainsString('cancelDisableE2ee', $messenger);
        $this->assertStringContainsString('closeImagePreview', $messenger);
    }
}
