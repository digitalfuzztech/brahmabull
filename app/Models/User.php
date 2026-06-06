<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\CustomResetPassword;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasRoles, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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

        $this->notify(new \App\Notifications\CustomResetPasswordNotification($url));
    }
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\CustomVerifyEmailNotification);
    }
    public function deposits()
    {
        return $this->hasMany(\App\Models\Deposit::class);
    }
    public function playerProfile()
    {
        return $this->hasOne(\App\Models\PlayerProfile::class);
    }

    public function cashouts()
    {
        return $this->hasMany(\App\Models\Cashout::class);
    }

    public function gameAccounts()
    {
        return $this->hasMany(\App\Models\GameAccount::class);
    }
    public function notifications()
    {
        return $this->hasMany(
            \App\Models\Notification::class
        );
    }

    public function referrer()
    {
        return $this->belongsTo(User::class,'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(
            Referral::class,
            'referrer_id'
        );
    }
}
