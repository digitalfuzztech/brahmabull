<?php

namespace App\Livewire\Public;

use Livewire\Component;

class HeroCarousel extends Component
{
    public int $active = 0;

    public array $slides = [
        [
            'title' => 'Play. Win. Dominate.',
            'subtitle' => 'Premium gaming experience with instant rewards and bonuses.',
            'image' => '/images/slides/slide-1.jpg',
            'button1' => 'Explore Games',
            'button2' => 'Sign Up',
        ],
        [
            'title' => 'Earn Real Rewards',
            'subtitle' => 'Deposit, play, and get rewarded instantly with secure payouts.',
            'image' => '/images/slides/slide-2.jpg',
            'button1' => 'My Details',
            'button2' => 'My Details',
        ],
        [
            'title' => 'Referral Rewards System',
            'subtitle' => 'Invite friends and earn bonus commissions instantly.',
            'image' => '/images/slides/slide-3.jpg',
            'button1' => 'Invite Now',
            'button2' => 'Play Now',
        ],
    ];

    public function mount()
    {
        // Auto start loop using Livewire polling
        $this->dispatch('start-carousel');
    }

    public function next()
    {
        $this->active = ($this->active + 1) % count($this->slides);
    }

    public function prev()
    {
        $this->active = ($this->active - 1 + count($this->slides)) % count($this->slides);
    }

    public function render()
    {
        return view('livewire.public.hero-carousel');
    }
}
