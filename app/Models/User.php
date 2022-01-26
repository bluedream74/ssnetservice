<?php

namespace App\Models;

use App\Enums\PointsStatus;
use App\Enums\UserRole;
use App\Mail\User\ForgotPassword;
use App\Services\Helpers\EmailService;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use App\Traits\ImageUpload;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

class User extends Authenticatable
{
    use Notifiable, CanResetPassword, Billable, ImageUpload, SoftDeletes, Sortable;

    const DIRECTORY = "users";
    const IMAGE_FIELD = "avatar";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     * bio: biography, introduce yourself, profile
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = [
        'avatar_url',
        'company'
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function photo()
    {
        return $this->belongsTo(Photo::class);
    }

    public function isAdmin()
    {
        if (empty($this->role)) return false;
        if ($this->role->name == "administrator" && $this->is_active == 1) {
            return true;
        }
        return false;
    }

    public function getIsTutorAttribute()
    {
        return isset($this->role) && $this->role->id === \App\Enums\UserRole::TUTOR;
    }

    public function getIsUserAttribute()
    {
        return isset($this->role) && $this->role->id === \App\Enums\UserRole::SUBSCRIBER;
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function getGravatarAttribute()
    {

        $hash = md5(strtolower(trim($this->attributes['email'])));

        return "http://www.gravatar.com/avatar/$hash";
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\User\ForgotPassword($token));
    }

    public function scopeOnlyTutor($query)
    {
        return $query->where('role_id', UserRole::TUTOR);
    }

    public function scopeOnlyStudent($query)
    {
        return $query->where('role_id', UserRole::SUBSCRIBER);
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->role_id === UserRole::ADMINISTRATOR) return "/img/logo.svg";

        if (is_null($this->avatar)) {
            return '/img/no-image.jpg';
        }

        // avatar from social
        $parseUrl = parse_url($this->avatar);
        if (isset($parseUrl['host'])) {
            return $this->avatar;
        }

        return $this->getImageFilePath($this->avatar);
    }

    public function getReviewScoreAttribute()
    {
        $reviews = Review::where('to_user_id', $this->id)->get();
        if (sizeof($reviews) === 0) {
            return 0;
        }

        $score = 0.0;
        foreach ($reviews as $review) {
            $score = floatval($score) + floatval($review->marks);
        }

        return number_format("" . floatval($score / floatval(sizeof($reviews))), 2);
    }

    public function getReviewCountAttribute()
    {
        return Review::where('to_user_id', $this->id)->count();
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function tutor()
    {
        return $this->hasOne(Tutor::class);
    }

    public function blocks()
    {
        return $this->hasMany(UserBlock::class)->select(['block_user_id', 'user_id']);
    }

    public function blockedBys()
    {
        return $this->hasMany(UserBlock::class, 'block_user_id', 'id')->select(['user_id', 'block_user_id']);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'user_categories', 'user_id', 'category_id');
    }

    public function favourites()
    {
        return $this->hasMany(Favorite::class, 'favorite_user_id', 'id');
    }

    public function studentFavorites()
    {
        return $this->hasMany(Favorite::class, 'user_id');
    }

    public function meeting()
    {
        return $this->hasOne(Meeting::class, 'user_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'to_user_id', 'id');
    }

    /**
     * History buy point
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function pointsHistory()
    {
        return $this->hasMany(Point::class);
    }

    public function pointsSuccess()
    {
        return $this->pointsHistory()->where('status', PointsStatus::SUCCESS);
    }

    public function getTotalPointBuyAttribute()
    {
        return $this->pointsSuccess()->sum('point');
    }

    public function pointUsage()
    {
        return $this->hasMany(PointUsage::class);
    }

    public function getTotalPointUsageAttribute()
    {
        return $this->pointUsage()->sum('points');
    }

    public function getIsCustomerStripeAttribute()
    {
        return !empty($this->stripe_id);
    }

    public function getHasPlanAttribute()
    {
        if (empty($subscription = $this->subscription(config('cashier.subscription_name')))
            || empty($subscription->ends_at)
        ) {
            return false;
        }

        return $subscription->ends_at->gt(now());
    }

    public function adminlte_image()
    {
        return $this->avatar_url; // 'https://picsum.photos/300/300';
    }

    public function adminlte_desc()
    {
        return $this->name;
    }

    public function getNameAttribute($value)
    {
        if ($this->is_tutor && isset($this->tutor)) return $this->tutor->name;
        if ($this->is_user) return $value;

        return $value;
    }

    public function getCategoryLabelAttribute()
    {
        if (isset($this->categories)) return implode("、", $this->categories()->pluck('name')->toArray());

        return "";
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->where('status', Question::PUBLISH)->with('answers');
    }

    public function allQuestions()
    {
        return $this->hasMany(Question::class, 'user_id')->with('answers');
    }

    public function answers()
    {
        return $this->hasMany(UserAnswer::class, 'user_id', 'id')->orderByDesc('created_at')->with('question')->with('options');
    }

    public function open_reservations()
    {
        return $this->hasMany(Reservation::class)
                ->whereIn('status', [Reservation::CREATED, Reservation::RESERVED])
                ->whereHas('schedule');
    }

    public function user_reservations()
    {
        return $this->hasMany(Reservation::class, 'user_id')
                ->whereIn('status', [Reservation::CREATED, Reservation::RESERVED, Reservation::FINISHED])
                ->whereHas('schedule')
                ->with('user_answers')
                ->orderByDesc('reserved_at');
    }

    public function tutor_reservations()
    {
        return $this->hasMany(Reservation::class, 'tutor_id')
                ->whereIn('status', [Reservation::CREATED, Reservation::RESERVED, Reservation::FINISHED])
                ->whereHas('schedule')
                ->orderByDesc('reserved_at');
    }

    public function tutor_finished_reservations()
    {
        return $this->hasMany(Reservation::class, 'tutor_id')
                ->whereIn('status', [Reservation::FINISHED])
                ->whereHas('schedule')
                ->orderByDesc('reserved_at');
    }

    public function user_finished_reservations()
    {
        return $this->hasMany(Reservation::class, 'user_id')
                ->whereIn('status', [Reservation::FINISHED])
                ->whereHas('schedule')
                ->orderByDesc('reserved_at');
    }

    public function user_livestreams()
    {
        return $this->hasMany(LivestreamUser::class, 'user_id');
    }

    public function getCompanyAttribute()
    {
        if ($this->role_id === UserRole::TUTOR) return $this->tutor->company;
        return "";
    }

    public function studentMailboxes()
    {
        return $this->hasMany(Mailbox::class, 'student_id', 'id');
    }

    public function getLatestReservationAttribute()
    {
        $meetingPrepareTime = intval(Config::where('meta', 'meeting_prepare_time')->first()->value);
        $now = \Carbon\Carbon::now();

        $reservations = $this->user_reservations()
                    ->where('status', Reservation::RESERVED)
                    ->whereHas('schedule', function ($query) use ($now) {
                        $query->where('start_date', $now->format("Y-m-d"));
                    })
                    ->get();
        foreach ($reservations as $reservation) {
            $startTime = \Carbon\Carbon::parse("{$reservation->schedule->start_date} {$reservation->schedule->start_time}:00")->addMinutes(0 - $meetingPrepareTime);
            $endTime = \Carbon\Carbon::parse("{$reservation->schedule->start_date} {$reservation->schedule->start_time}:00")->addMinutes($reservation->schedule->duration);
            if ($startTime->lt($now) && $endTime->gt($now)) {
                return $reservation;
            }
        }

        return null;
    }

    public function getImagesAttribute()
    {
        $images = array();
        if ($this->avatar) {
            $images[] = str_replace('thumbs/', '', $this->avatar_url);
        }
        if ($this->tutor && $this->tutor->sub_image1) {
            $images[] = str_replace('thumbs/', '', $this->tutor->sub_image1_url);
        }
        if ($this->tutor && $this->tutor->sub_image2) {
            $images[] = str_replace('thumbs/', '', $this->tutor->sub_image2_url);
        }

        return $images;
    }

    public function mail_notifications()
    {
        return $this->hasMany(UserNotification::class, 'user_id');
    }
}
