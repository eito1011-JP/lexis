<?php

namespace App\Notifications;

use App\Models\PullRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewRequestNotification extends Notification
{
    use Queueable;

    private PullRequest $pullRequest;

    private User $requester;

    /**
     * Create a new notification instance.
     */
    public function __construct(PullRequest $pullRequest, User $requester)
    {
        $this->pullRequest = $pullRequest;
        $this->requester = $requester;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $pullRequestUrl = config('app.url').'/change-suggestions/'.$this->pullRequest->id;

        return (new MailMessage)
            ->subject('【ハンドブック】レビュー依頼のお知らせ')
            ->greeting('レビュー依頼が届いています')
            ->line($this->requester->email.'さんから、プルリクエストのレビュー依頼が送信されました。')
            ->line('')
            ->line('**プルリクエストタイトル:** '.$this->pullRequest->title)
            ->line('**説明:** '.($this->pullRequest->description ?: 'なし'))
            ->line('')
            ->action('レビューを開始する', $pullRequestUrl)
            ->line('上記のボタンをクリックして、変更内容の確認とレビューを行ってください。')
            ->salutation('ハンドブック管理システム');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pull_request_id' => $this->pullRequest->id,
            'pull_request_title' => $this->pullRequest->title,
            'requester_email' => $this->requester->email,
        ];
    }
}
