<?php

namespace App\Models;

use App\Notifications\CustomResetPassword;
use App\Notifications\CustomResetPasswordNotification;
use App\Notifications\CustomVerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'photo',
        'phone',
        'role',
        'referral_code',
        'referred_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'brahma_balance' => 'decimal:2',
        ];
    }

    protected static function booted()
    {
        // VERIFY EMAIL
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Verify Your BrahmaBull Account')
                ->greeting('Welcome to BrahmaBull Gaming Club!')
                ->line('Please verify your email before accessing your account.')
                ->action('Verify Email', $url)
                ->line('If you did not create this account, you can ignore this email.');
        });

        // RESET PASSWORD
        ResetPassword::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Reset Your BrahmaBull Password')
                ->greeting('Password Reset Request')
                ->line('We received a request to reset your password.')
                ->action('Reset Password', $url)
                ->line('If this was not you, ignore this email.');
        });

    }

    // public function sendPasswordResetNotification($token)
    // {
    //   $this->notify(new CustomResetPassword($token));
    // }
    public function sendPasswordResetNotification($token): void
    {
        $url = url(route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ], false));

        $this->notify(new CustomResetPasswordNotification($url));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomVerifyEmailNotification);
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }

    public function playerProfile()
    {
        return $this->hasOne(PlayerProfile::class);
    }

    public function cashouts()
    {
        return $this->hasMany(Cashout::class);
    }

    public function gameAccounts()
    {
        return $this->hasMany(GameAccount::class);
    }

    public function notifications()
    {
        return $this->hasMany(
            Notification::class
        );
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(
            Referral::class,
            'referrer_id'
        );
    }

    public function brahmaDeposits()
    {
        return $this->hasMany(BrahmaDeposit::class);
    }

    public function brahmaPlayRequests()
    {
        return $this->hasMany(BrahmaPlayRequest::class);
    }

    public function brahmaBalanceTransactions()
    {
        return $this->hasMany(BrahmaBalanceTransaction::class);
    }

    public function chatConversationsAsPlayer()
    {
        return $this->hasMany(ChatConversation::class, 'player_id');
    }

    public function assignedChatConversations()
    {
        return $this->hasMany(ChatConversation::class, 'assigned_to');
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function chatbotRulesCreated()
    {
        return $this->hasMany(ChatbotRule::class, 'created_by');
    }

    public function chatSupportEventsHandled()
    {
        return $this->hasMany(ChatSupportEvent::class, 'handled_by');
    }

    public function chatConversationParticipants()
    {
        return $this->hasMany(ChatConversationParticipant::class);
    }

    public function chatMessageReactions()
    {
        return $this->hasMany(ChatMessageReaction::class);
    }

    public function chatE2eeDevices()
    {
        return $this->hasMany(ChatE2eeDevice::class);
    }

    public function spinAttemptGrants()
    {
        return $this->hasMany(SpinAttemptGrant::class);
    }

    public function spinWheelSpins()
    {
        return $this->hasMany(SpinWheelSpin::class);
    }

    public function spinRewardEntitlements()
    {
        return $this->hasMany(SpinRewardEntitlement::class);
    }

    public function spinPromotionalPointLedgers()
    {
        return $this->hasMany(SpinPromotionalPointLedger::class);
    }
}
