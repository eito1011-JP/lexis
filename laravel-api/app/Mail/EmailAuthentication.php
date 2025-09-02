<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Eメール認証用の通知メール
 */
class EmailAuthentication extends Mailable
{
    use Queueable, SerializesModels;

    /** @var string */
    public string $email;

    /** @var string */
    public string $token;

    /**
     * コンストラクタ
     *
     * @param string $email 宛先メールアドレス
     * @param string $token 認証トークン
     */
    public function __construct(string $email, string $token)
    {
        $this->email = $email;
        $this->token = $token;
    }

    /**
     * メッセージの構築
     */
    public function build(): self
    {
        $frontendUrl = config('app.frontend_url');
        $verifyUrl = rtrim($frontendUrl, '/').'/organization/register?token='.$this->token;

        return $this->subject('【Lexis】メール認証のご案内')
            ->view('emails.email_authentication')
            ->with([
                'email' => $this->email,
                'verifyUrl' => $verifyUrl,
            ]);
    }
}


