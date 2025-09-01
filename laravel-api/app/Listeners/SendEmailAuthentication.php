<?php

namespace App\Listeners;

use App\Events\PreUserCreated;
use App\Mail\EmailAuthentication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * PreUserCreated イベントでメールを送信
 */
class SendEmailAuthentication implements ShouldQueue
{
    /**
     * ハンドラ
     */
    public function handle(PreUserCreated $event): void
    {
        Mail::to($event->email)->send(new EmailAuthentication($event->email, $event->token));
    }
}


